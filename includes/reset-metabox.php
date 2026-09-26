<?php
/**
 * Admin Metabox: Custom Score (Avg + Count only).
 *
 * Prefix: init_plugin_suite_review_system_*
 *
 * @package InitReviewSystem
 */

defined( 'ABSPATH' ) || exit;

add_action( 'add_meta_boxes', 'init_plugin_suite_review_system_register_metabox', 10, 2 );

/**
 * Đăng ký metabox cho từng bài (per post), chỉ khi bài đã có điểm.
 *
 * Dùng thẳng 2 tham số ($post_type, $post) của hook `add_meta_boxes`, thay vì
 * đăng ký thêm một closure `add_meta_boxes_{$pt}` cho MỌI post type public
 * mỗi lần mở trang soạn thảo như trước.
 *
 * @param string  $post_type Post type hiện tại.
 * @param WP_Post $post      Bài viết hiện tại.
 * @return void
 */
function init_plugin_suite_review_system_register_metabox( $post_type, $post ) {
	if ( ! $post instanceof WP_Post || 'attachment' === $post_type ) {
		return;
	}

	if ( ! current_user_can( 'edit_others_posts' ) ) {
		return;
	}

	$post_type_object = get_post_type_object( $post_type );
	if ( ! $post_type_object || ! $post_type_object->public ) {
		return;
	}

	$count = intval( get_post_meta( $post->ID, '_init_review_count', true ) );
	$total = floatval( get_post_meta( $post->ID, '_init_review_total', true ) );

	// Không có điểm → KHÔNG add metabox.
	if ( $count <= 0 && $total <= 0 ) {
		return;
	}

	add_meta_box(
		'init_plugin_suite_review_system_metabox',
		__( 'Review Score', 'init-review-system' ),
		'init_plugin_suite_review_system_render_metabox',
		$post_type,
		'side',
		'default'
	);
}

/**
 * Render metabox (chỉ hiện khi đã có điểm).
 *
 * @param WP_Post $post Bài viết hiện tại.
 * @return void
 */
function init_plugin_suite_review_system_render_metabox( $post ) {
	if ( ! current_user_can( 'edit_others_posts' ) ) {
		return;
	}

	$total = floatval( get_post_meta( $post->ID, '_init_review_total', true ) );
	$count = intval( get_post_meta( $post->ID, '_init_review_count', true ) );
	$avg   = get_post_meta( $post->ID, '_init_review_avg', true );
	$avg   = ( '' === $avg ? '' : floatval( $avg ) );

	if ( $total <= 0 && $count <= 0 ) {
		echo '<p style="margin:0;">' . esc_html__( 'No votes yet. This box appears after the first vote.', 'init-review-system' ) . '</p>';
		return;
	}

	wp_nonce_field( 'init_plugin_suite_review_system_metabox_action', 'init_plugin_suite_review_system_metabox_nonce' );

	// Mini stat chips / badge style.
	echo '<div style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:12px;">';

	$stats = array(
		__( 'Avg', 'init-review-system' )   => $avg,
		__( 'Votes', 'init-review-system' ) => $count,
		__( 'Total', 'init-review-system' ) => $total,
	);

	foreach ( $stats as $label => $value ) {
		echo '<div style="display:flex; flex-direction:column; align-items:center; padding:6px 10px; border:1px solid #d0d7de; border-radius:6px; background:#fff; box-shadow:0 1px 2px rgba(0,0,0,.05); min-width:52px;" aria-label="' . esc_attr( $label ) . '">';
		echo '<span style="font-size:10px; font-weight:600; color:#555; letter-spacing:.3px; text-transform:uppercase;">' . esc_html( $label ) . '</span>';
		// Value luôn là số => cast rồi escape cho chắc.
		$value_num = is_numeric( $value ) ? $value + 0 : 0;
		echo '<span style="font-size:14px; font-weight:700; margin-top:2px;">' . esc_html( (string) $value_num ) . '</span>';
		echo '</div>';
	}

	echo '</div>';

	// Input zone (Avg + Count).
	?>
	<p>
		<label for="init_plugin_suite_review_system_avg" style="display:block;font-weight:600;">
			<?php esc_html_e( 'Set Average (0–5)', 'init-review-system' ); ?>
		</label>
		<input type="number" step="0.01" min="0" max="5"
			id="init_plugin_suite_review_system_avg"
			name="init_plugin_suite_review_system_avg"
			value="<?php echo esc_attr( $avg ); ?>"
			style="width:100%;" />
	</p>

	<p>
		<label for="init_plugin_suite_review_system_count" style="display:block;font-weight:600;">
			<?php esc_html_e( 'Set Count (>= 0)', 'init-review-system' ); ?>
		</label>
		<input type="number" step="1" min="0"
			id="init_plugin_suite_review_system_count"
			name="init_plugin_suite_review_system_count"
			value="<?php echo esc_attr( $count ); ?>"
			style="width:100%;" />
		<small><?php esc_html_e( 'Total auto = Avg × Count (rounded)', 'init-review-system' ); ?></small>
	</p>

	<p style="margin-top:10px;">
		<label style="display:flex;align-items:center;gap:6px;">
			<input type="checkbox" name="init_plugin_suite_review_system_reset_all" value="1" />
			<span><?php esc_html_e( 'Reset all review data', 'init-review-system' ); ?></span>
		</label>
	</p>

	<p><em><?php esc_html_e( 'Save / Update the post to apply changes.', 'init-review-system' ); ?></em></p>
	<?php
}

add_action( 'save_post', 'init_plugin_suite_review_system_save_metabox', 10, 2 );

/**
 * Save handler: chỉ nhận Avg + Count, tự tính Total.
 *
 * @param int     $post_id ID bài viết.
 * @param WP_Post $post    Bài viết (không dùng).
 * @return void
 */
function init_plugin_suite_review_system_save_metabox( $post_id, $post ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( wp_is_post_revision( $post_id ) ) {
		return;
	}
	if ( ! isset( $_POST['init_plugin_suite_review_system_metabox_nonce'] ) ) {
		return;
	}
	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['init_plugin_suite_review_system_metabox_nonce'] ) ), 'init_plugin_suite_review_system_metabox_action' ) ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) || ! current_user_can( 'edit_others_posts' ) ) {
		return;
	}

	// Không có input gì thì thôi.
	$has_any = isset( $_POST['init_plugin_suite_review_system_avg'] ) ||
		isset( $_POST['init_plugin_suite_review_system_count'] ) ||
		isset( $_POST['init_plugin_suite_review_system_reset_all'] );
	if ( ! $has_any ) {
		return;
	}

	// Reset sạch.
	if ( ! empty( $_POST['init_plugin_suite_review_system_reset_all'] ) ) {
		delete_post_meta( $post_id, '_init_review_total' );
		delete_post_meta( $post_id, '_init_review_count' );
		delete_post_meta( $post_id, '_init_review_avg' );
		// Weighted là giá trị dẫn xuất từ 3 meta trên — xoá luôn để không còn
		// giữ thứ hạng cũ sau khi reset (trước đây bị bỏ sót).
		delete_post_meta( $post_id, '_init_review_weighted' );
		do_action( 'init_plugin_suite_review_system_after_admin_reset', $post_id );
		return;
	}

	$avg_raw = isset( $_POST['init_plugin_suite_review_system_avg'] )
		? sanitize_text_field( wp_unslash( $_POST['init_plugin_suite_review_system_avg'] ) )
		: '';

	$count_raw = isset( $_POST['init_plugin_suite_review_system_count'] )
		? sanitize_text_field( wp_unslash( $_POST['init_plugin_suite_review_system_count'] ) )
		: '';

	// Current (fallback).
	$cur_count = (int) get_post_meta( $post_id, '_init_review_count', true );
	$cur_avg   = get_post_meta( $post_id, '_init_review_avg', true );
	$cur_avg   = ( '' === $cur_avg ? null : (float) $cur_avg );

	// Cast sau khi sanitize.
	$avg   = ( '' === $avg_raw ? ( $cur_avg ?? 0.0 ) : (float) $avg_raw );
	$count = ( '' === $count_raw ? $cur_count : (int) $count_raw );

	// Validate + chuẩn hoá.
	$avg   = round( max( 0.0, min( 5.0, $avg ) ), 2 );
	$count = max( 0, intval( $count ) );

	// Admin không đổi gì (chỉ bấm Cập nhật bài viết): bỏ qua. Trước đây mỗi lần
	// lưu bài đều tính lại total = round(avg × count) từ avg đã làm tròn, khiến
	// total bị trôi dần (ví dụ 13 → 12.99) dù không ai chỉnh điểm.
	if ( $count === $cur_count && null !== $cur_avg && abs( $avg - round( $cur_avg, 2 ) ) < 0.00001 ) {
		return;
	}

	// Tính total từ Avg × Count.
	if ( 0 === $count ) {
		$total = 0.0;
		$avg   = 0.0;
	} else {
		$total = round( $avg * $count, 2 );
		// Đảm bảo avg hiển thị = total/count (khớp front).
		$avg = round( $total / $count, 2 );
	}

	update_post_meta( $post_id, '_init_review_total', $total );
	update_post_meta( $post_id, '_init_review_count', $count );
	update_post_meta( $post_id, '_init_review_avg', $avg );

	// Đồng bộ weighted score theo số liệu mới (trước đây bị giữ nguyên giá trị cũ).
	init_plugin_suite_review_system_refresh_weighted_score( $post_id, $avg, $count );

	do_action( 'init_plugin_suite_review_system_after_admin_adjust', $post_id, $avg, $count, $total );
}
