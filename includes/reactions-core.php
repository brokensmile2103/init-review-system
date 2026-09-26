<?php
/**
 * Core reactions helpers (no hooks, no endpoints).
 *
 * - Nguồn sự thật (source of truth): bảng {$wpdb->prefix}init_reactions
 *   (1 dòng / user / post, có UNIQUE KEY post_id+user_id).
 * - Số đếm luôn được COUNT trực tiếp từ bảng (có cache, TTL 1h, tự invalidate
 *   khi có thay đổi) — không cộng/trừ tay nên không thể bị lệch dữ liệu.
 * - Post meta `_irs_rx_{type}` chỉ là bản sao đồng bộ để tương thích ngược
 *   (ví dụ site nào đang orderby/meta_query theo các key này), KHÔNG phải
 *   nguồn sự thật, không nên đọc trực tiếp để hiển thị số đếm.
 * - Chỉ chứa HÀM, không tự gắn hook/shortcode.
 *
 * @package InitReviewSystem
 */

defined( 'ABSPATH' ) || exit;

/**
 * Lấy danh sách reaction types (label + emoji).
 *
 * @return array [
 *     'slug' => [ 'Label', 'Emoji' ],
 * ]
 */
function init_plugin_suite_review_system_get_reaction_types() {
	$types = array(
		'upvote'    => array( __( 'Upvote', 'init-review-system' ), '👍' ),
		'funny'     => array( __( 'Funny', 'init-review-system' ), '😄' ),
		'love'      => array( __( 'Love', 'init-review-system' ), '😍' ),
		'surprised' => array( __( 'Surprised', 'init-review-system' ), '😯' ),
		'angry'     => array( __( 'Angry', 'init-review-system' ), '😠' ),
		'sad'       => array( __( 'Sad', 'init-review-system' ), '😢' ),
	);

	/**
	 * Filter: init_plugin_suite_review_system_get_reaction_types
	 *
	 * Cho phép thêm, xoá, hoặc sửa các loại reaction.
	 *
	 * @param array $types Mảng reaction mặc định.
	 */
	return apply_filters( 'init_plugin_suite_review_system_get_reaction_types', $types );
}

/**
 * Lấy tên bảng reactions.
 *
 * @return string
 */
function init_plugin_suite_review_system_get_reaction_table() {
	global $wpdb;
	return $wpdb->prefix . 'init_reactions';
}

/**
 * Kiểm tra post_id hợp lệ.
 *
 * @param int $post_id ID bài viết.
 * @return int ID hợp lệ hoặc 0.
 */
function init_plugin_suite_review_system_assert_post( $post_id ) {
	$post_id = absint( $post_id );
	if ( ! $post_id || ! get_post( $post_id ) ) {
		return 0;
	}
	return $post_id;
}

/**
 * Chuẩn hóa & kiểm tra reaction hợp lệ.
 *
 * @param string $reaction Reaction slug.
 * @return string Key hợp lệ hoặc ''.
 */
function init_plugin_suite_review_system_validate_reaction( $reaction ) {
	$rx    = sanitize_key( $reaction );
	$types = init_plugin_suite_review_system_get_reaction_types();
	return isset( $types[ $rx ] ) ? $rx : '';
}

/**
 * Key meta đếm reaction.
 *
 * @param string $rx_key Reaction slug.
 * @return string
 */
function init_plugin_suite_review_system_reaction_meta_key( $rx_key ) {
	return apply_filters( 'init_plugin_suite_review_system_reaction_meta_key', '_irs_rx_' . sanitize_key( $rx_key ), $rx_key );
}

/**
 * Đếm reaction trực tiếp từ bảng {$wpdb->prefix}init_reactions (GROUP BY).
 *
 * Đây là truy vấn "sự thật" — không cache, dùng nội bộ bởi
 * init_plugin_suite_review_system_get_reaction_counts() (có cache) và khi
 * cần đồng bộ lại post meta.
 *
 * @param int $post_id ID bài viết (đã được assert hợp lệ bởi hàm gọi).
 * @return array Map slug => số đếm.
 */
function init_plugin_suite_review_system_query_reaction_counts_from_table( $post_id ) {
	global $wpdb;
	$table = init_plugin_suite_review_system_get_reaction_table();

	$counts = array_fill_keys( array_keys( init_plugin_suite_review_system_get_reaction_types() ), 0 );

	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT reaction, COUNT(*) c FROM {$table} WHERE post_id = %d GROUP BY reaction",
			$post_id
		),
		ARRAY_A
	);
	// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

	if ( is_array( $rows ) ) {
		foreach ( $rows as $row ) {
			$k = sanitize_key( $row['reaction'] );
			if ( isset( $counts[ $k ] ) ) {
				$counts[ $k ] = (int) $row['c'];
			}
		}
	}

	return $counts;
}

/**
 * Lấy map đếm reactions (có cache 1 giờ, tự invalidate khi có thay đổi).
 *
 * @param int $post_id ID bài viết.
 * @return array Map slug => số đếm.
 */
function init_plugin_suite_review_system_get_reaction_counts( $post_id ) {
	$post_id = init_plugin_suite_review_system_assert_post( $post_id );
	if ( ! $post_id ) {
		return array();
	}

	$cache_key = "reaction_counts_{$post_id}";
	$cached    = wp_cache_get( $cache_key, 'init_review_system' );
	if ( false !== $cached ) {
		return $cached;
	}

	$counts = init_plugin_suite_review_system_query_reaction_counts_from_table( $post_id );
	wp_cache_set( $cache_key, $counts, 'init_review_system', HOUR_IN_SECONDS );

	return $counts;
}

/**
 * Ghi lại toàn bộ counts vào post meta (âm → 0).
 *
 * Không còn là nguồn sự thật (bảng init_reactions mới là) — chỉ để đồng bộ
 * post meta `_irs_rx_*` cho tương thích ngược.
 *
 * @param int   $post_id ID bài viết.
 * @param array $counts  Map slug => số đếm.
 * @return bool
 */
function init_plugin_suite_review_system_set_reaction_counts( $post_id, array $counts ) {
	$post_id = init_plugin_suite_review_system_assert_post( $post_id );
	if ( ! $post_id ) {
		return false;
	}

	foreach ( init_plugin_suite_review_system_get_reaction_types() as $key => $type ) {
		$val = isset( $counts[ $key ] ) ? max( 0, (int) $counts[ $key ] ) : 0;
		update_post_meta( $post_id, init_plugin_suite_review_system_reaction_meta_key( $key ), $val );
	}
	return true;
}

/**
 * Lấy reaction hiện tại của 1 user trên 1 post.
 *
 * @param int $post_id ID bài viết.
 * @param int $user_id ID người dùng.
 * @return string Reaction slug, '' nếu chưa có.
 */
function init_plugin_suite_review_system_get_user_reaction( $post_id, $user_id ) {
	$post_id = init_plugin_suite_review_system_assert_post( $post_id );
	$user_id = absint( $user_id );
	if ( ! $post_id || ! $user_id ) {
		return '';
	}

	$cache_key = "user_reaction_{$post_id}_{$user_id}";
	$cached    = wp_cache_get( $cache_key, 'init_review_system' );
	if ( false !== $cached ) {
		// Lưu dạng string: reaction slug hoặc '__none__' nếu chưa có.
		return '__none__' === $cached ? '' : (string) $cached;
	}

	global $wpdb;
	$table = init_plugin_suite_review_system_get_reaction_table();
	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
	$rx = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT reaction FROM {$table} WHERE post_id = %d AND user_id = %d LIMIT 1",
			$post_id,
			$user_id
		)
	);
	// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter

	$result = $rx ? sanitize_key( $rx ) : '';

	// Lưu '__none__' thay vì '' để phân biệt với false (cache miss).
	wp_cache_set( $cache_key, '' !== $result ? $result : '__none__', 'init_review_system', HOUR_IN_SECONDS );

	return $result;
}

/**
 * Áp dụng reaction cho user.
 *
 * - Nếu $reaction === '' hoặc trùng với reaction cũ → gỡ (remove).
 * - Nếu khác → thêm mới / chuyển (switch).
 *
 * @param int    $post_id  ID bài viết.
 * @param int    $user_id  ID người dùng.
 * @param string $reaction Reaction slug.
 * @return array {
 *     @type bool   $success Thành công hay không.
 *     @type string $prev    Reaction cũ.
 *     @type string $current Reaction mới sau khi áp dụng ('' nếu gỡ).
 *     @type array  $counts  Map số đếm mới.
 * }
 */
function init_plugin_suite_review_system_apply_user_reaction( $post_id, $user_id, $reaction ) {
	$post_id = init_plugin_suite_review_system_assert_post( $post_id );
	$user_id = absint( $user_id );
	$new_rx  = init_plugin_suite_review_system_validate_reaction( $reaction ); // Có thể ''.

	if ( ! $post_id || ! $user_id ) {
		return array(
			'success' => false,
			'prev'    => '',
			'current' => '',
			'counts'  => array(),
		);
	}

	global $wpdb;
	$table   = init_plugin_suite_review_system_get_reaction_table();
	$prev    = init_plugin_suite_review_system_get_user_reaction( $post_id, $user_id );
	$current = '';
	$result  = null; // Null = không có thao tác ghi nào.

	if ( '' === $new_rx || $new_rx === $prev ) {
		// Trường hợp 1: gỡ (remove) — bấm lại đúng reaction cũ, hoặc reaction rỗng.
		if ( '' !== $prev ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$result = $wpdb->delete(
				$table,
				array(
					'post_id' => $post_id,
					'user_id' => $user_id,
				),
				array( '%d', '%d' )
			);
		}
	} else {
		// Trường hợp 2: thêm mới hoặc chuyển reaction. Bảng có UNIQUE KEY
		// (post_id, user_id) nên INSERT ... ON DUPLICATE KEY UPDATE là một thao
		// tác ATOMIC duy nhất ở tầng DB, đúng cả khi nhiều request song song.
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$result = $wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$table} (post_id, user_id, reaction, created_at)
				VALUES (%d, %d, %s, %s)
				ON DUPLICATE KEY UPDATE reaction = VALUES(reaction), created_at = VALUES(created_at)",
				$post_id,
				$user_id,
				$new_rx,
				current_time( 'mysql' )
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$current = $new_rx;
	}

	// Không có gì thay đổi (gỡ khi vốn chưa có reaction): trả số đếm đang cache,
	// không cần ghi DB, đếm lại hay đồng bộ post meta.
	if ( null === $result ) {
		return array(
			'success' => true,
			'prev'    => $prev,
			'current' => '',
			'counts'  => init_plugin_suite_review_system_get_reaction_counts( $post_id ),
		);
	}

	// Lỗi DB: báo thất bại thay vì trả về trạng thái không có thật.
	if ( false === $result ) {
		return array(
			'success' => false,
			'prev'    => $prev,
			'current' => $prev,
			'counts'  => init_plugin_suite_review_system_get_reaction_counts( $post_id ),
		);
	}

	// Xoá cache liên quan — counts được đếm lại (COUNT thật) từ bảng ngay dưới.
	wp_cache_delete( "user_reaction_{$post_id}_{$user_id}", 'init_review_system' );

	$counts = init_plugin_suite_review_system_query_reaction_counts_from_table( $post_id );
	wp_cache_set( "reaction_counts_{$post_id}", $counts, 'init_review_system', HOUR_IN_SECONDS );

	// Đồng bộ post meta `_irs_rx_*` cho tương thích ngược.
	init_plugin_suite_review_system_set_reaction_counts( $post_id, $counts );

	return array(
		'success' => true,
		'prev'    => $prev,
		'current' => $current,
		'counts'  => $counts,
	);
}

/**
 * Recount: dựng lại số đếm từ bảng (dùng khi cần sửa chữa dữ liệu) và ghi lại
 * vào post meta.
 *
 * @param int $post_id ID bài viết.
 * @return bool
 */
function init_plugin_suite_review_system_recount_reactions( $post_id ) {
	$post_id = init_plugin_suite_review_system_assert_post( $post_id );
	if ( ! $post_id ) {
		return false;
	}

	$counts = init_plugin_suite_review_system_query_reaction_counts_from_table( $post_id );

	wp_cache_set( "reaction_counts_{$post_id}", $counts, 'init_review_system', HOUR_IN_SECONDS );

	return init_plugin_suite_review_system_set_reaction_counts( $post_id, $counts );
}
