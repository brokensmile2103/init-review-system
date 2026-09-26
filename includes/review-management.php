<?php
/**
 * Trang quản lý review trong admin.
 *
 * @package InitReviewSystem
 */

defined( 'ABSPATH' ) || exit;

/**
 * Slug trang quản lý review.
 */
const INIT_PLUGIN_SUITE_RS_MANAGEMENT_PAGE = 'init-review-management';

add_action( 'admin_menu', 'init_plugin_suite_review_system_add_management_page', 11 );

/**
 * Add review management submenu.
 *
 * @return void
 */
function init_plugin_suite_review_system_add_management_page() {
	add_submenu_page(
		INIT_PLUGIN_SUITE_RS_SLUG,
		__( 'Manage Reviews', 'init-review-system' ),
		__( 'Manage Reviews', 'init-review-system' ),
		'manage_options',
		INIT_PLUGIN_SUITE_RS_MANAGEMENT_PAGE,
		'init_plugin_suite_review_system_render_management_page'
	);
}

/**
 * Request hiện tại có phải là trang quản lý review hay không.
 *
 * Các handler bên dưới chạy trên `admin_init` (tức MỌI request admin, kể cả
 * admin-ajax). Trước 2.0.1 chúng chỉ dựa vào tham số `action` + `review_id` /
 * `reviews[]`, nên một plugin khác dùng trùng tên tham số sẽ bị dính
 * "Security check failed". Giờ chỉ xử lý khi đúng trang của plugin.
 *
 * @return bool
 */
function init_plugin_suite_review_system_is_management_request() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Chỉ đọc để định tuyến, nonce được kiểm tra ở handler.
	$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
	return INIT_PLUGIN_SUITE_RS_MANAGEMENT_PAGE === $page;
}

/**
 * URL trang quản lý review.
 *
 * @param array $args Query args bổ sung.
 * @return string
 */
function init_plugin_suite_review_system_management_url( $args = array() ) {
	return add_query_arg( $args, admin_url( 'admin.php?page=' . INIT_PLUGIN_SUITE_RS_MANAGEMENT_PAGE ) );
}

/**
 * URL (có nonce) cho thao tác đơn lẻ trên một review.
 *
 * @param string $action    approve | reject | delete.
 * @param int    $review_id ID review.
 * @return string
 */
function init_plugin_suite_review_system_review_action_url( $action, $review_id ) {
	$review_id = absint( $review_id );

	return wp_nonce_url(
		init_plugin_suite_review_system_management_url(
			array(
				'action'    => $action,
				'review_id' => $review_id,
			)
		),
		"review_{$action}_{$review_id}"
	);
}

/**
 * Cập nhật trạng thái / xoá nhiều review cùng lúc và invalidate cache liên quan.
 *
 * @since 2.0.1
 *
 * @param string $action     approve | reject | delete.
 * @param int[]  $review_ids Danh sách ID review.
 * @return int|false Số dòng bị ảnh hưởng, false nếu lỗi hoặc action không hợp lệ.
 */
function init_plugin_suite_review_system_apply_review_action( $action, $review_ids ) {
	global $wpdb;

	$review_ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $review_ids ) ) ) );
	if ( empty( $review_ids ) || ! in_array( $action, array( 'approve', 'reject', 'delete' ), true ) ) {
		return false;
	}

	$table_name   = $wpdb->prefix . 'init_criteria_reviews';
	$placeholders = implode( ',', array_fill( 0, count( $review_ids ), '%d' ) );

	// Lấy trước post_id/user_id bị ảnh hưởng để invalidate cache đúng chỗ sau
	// khi UPDATE/DELETE — các thao tác này sửa DB trực tiếp bằng $wpdb nên
	// không tự chạy qua hook after_insert như luồng submit thường.
	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber,PluginCheck.Security.DirectDB.UnescapedDBParameter
	$affected_reviews = $wpdb->get_results(
		$wpdb->prepare( "SELECT post_id, user_id FROM {$table_name} WHERE id IN ($placeholders)", ...$review_ids ),
		ARRAY_A
	);

	if ( 'delete' === $action ) {
		$result = $wpdb->query(
			$wpdb->prepare( "DELETE FROM {$table_name} WHERE id IN ($placeholders)", ...$review_ids )
		);
	} else {
		$status = ( 'approve' === $action ) ? 'approved' : 'rejected';
		$result = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table_name} SET status = %s WHERE id IN ($placeholders)",
				array_merge( array( $status ), $review_ids )
			)
		);
	}
	// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber,PluginCheck.Security.DirectDB.UnescapedDBParameter

	if ( false === $result ) {
		return false;
	}

	// Invalidate mỗi cặp post/user đúng 1 lần.
	$seen = array();
	foreach ( (array) $affected_reviews as $row ) {
		$key = $row['post_id'] . ':' . $row['user_id'];
		if ( isset( $seen[ $key ] ) ) {
			continue;
		}
		$seen[ $key ] = true;
		init_plugin_suite_review_system_invalidate_review_cache( $row['post_id'], $row['user_id'] );
	}

	/**
	 * Fires sau khi admin duyệt / từ chối / xoá review.
	 *
	 * @since 2.0.1
	 *
	 * @param string $action     approve | reject | delete.
	 * @param int[]  $review_ids Danh sách ID review.
	 * @param int    $result     Số dòng bị ảnh hưởng.
	 */
	do_action( 'init_plugin_suite_review_system_after_admin_review_action', $action, $review_ids, (int) $result );

	return (int) $result;
}

add_action( 'admin_init', 'init_plugin_suite_review_system_handle_management_actions' );

/**
 * Handle review management actions (single review, qua link GET có nonce).
 *
 * @return void
 */
function init_plugin_suite_review_system_handle_management_actions() {
	if ( ! init_plugin_suite_review_system_is_management_request() || ! current_user_can( 'manage_options' ) ) {
		return;
	}

	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Nonce được kiểm tra ngay bên dưới.
	$action    = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';
	$review_id = isset( $_GET['review_id'] ) ? absint( $_GET['review_id'] ) : 0;
	// phpcs:enable WordPress.Security.NonceVerification.Recommended

	if ( ! $action || ! $review_id || ! in_array( $action, array( 'approve', 'reject', 'delete' ), true ) ) {
		return;
	}

	check_admin_referer( "review_{$action}_{$review_id}" );

	$result = init_plugin_suite_review_system_apply_review_action( $action, array( $review_id ) );

	// Redirect (PRG) kèm mã thông báo — trước đây notice được add_action() rồi
	// redirect ngay nên không bao giờ hiển thị.
	wp_safe_redirect(
		init_plugin_suite_review_system_management_url(
			array( 'irs_notice' => init_plugin_suite_review_system_notice_code( $action, $result, true ) )
		)
	);
	exit;
}

add_action( 'admin_init', 'init_plugin_suite_review_system_handle_bulk_actions' );

/**
 * Handle bulk actions.
 *
 * @return void
 */
function init_plugin_suite_review_system_handle_bulk_actions() {
	if ( ! init_plugin_suite_review_system_is_management_request() || ! current_user_can( 'manage_options' ) ) {
		return;
	}

	// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce được kiểm tra bên dưới trước khi thay đổi dữ liệu.
	$action = isset( $_POST['action'] ) ? sanitize_key( wp_unslash( $_POST['action'] ) ) : '';
	if ( '' === $action || '-1' === $action ) {
		$action = isset( $_POST['action2'] ) ? sanitize_key( wp_unslash( $_POST['action2'] ) ) : '';
	}

	if ( ! in_array( $action, array( 'approve', 'reject', 'delete' ), true ) ) {
		return;
	}

	if ( ! isset( $_POST['reviews'] ) || ! is_array( $_POST['reviews'] ) ) {
		return;
	}
	// phpcs:enable WordPress.Security.NonceVerification.Missing

	check_admin_referer( 'bulk_reviews_action' );

	$review_ids = array_filter( array_map( 'absint', wp_unslash( $_POST['reviews'] ) ) );
	if ( empty( $review_ids ) ) {
		return;
	}

	$result = init_plugin_suite_review_system_apply_review_action( $action, $review_ids );

	// PRG: tránh submit lại khi F5, giữ nguyên bộ lọc/trang hiện tại.
	$redirect = wp_get_referer();
	if ( ! $redirect ) {
		$redirect = init_plugin_suite_review_system_management_url();
	}
	$redirect = remove_query_arg( array( 'irs_notice', 'irs_count', 'action', 'review_id', '_wpnonce' ), $redirect );

	$notice = init_plugin_suite_review_system_notice_code( $action, $result, false );
	if ( $notice ) {
		$redirect = add_query_arg(
			array(
				'irs_notice' => $notice,
				'irs_count'  => (int) $result,
			),
			$redirect
		);
	}

	wp_safe_redirect( $redirect );
	exit;
}

/**
 * Mã thông báo (irs_notice) cho kết quả một thao tác quản lý review.
 *
 * Giữ đúng các thông báo như trước: thao tác đơn lẻ dùng câu thông báo đơn,
 * thao tác hàng loạt dùng câu có số lượng và chỉ hiện khi có dòng bị ảnh hưởng.
 *
 * @param string    $action approve | reject | delete.
 * @param int|false $result Kết quả trả về từ apply_review_action().
 * @param bool      $single Thao tác đơn lẻ (link) hay hàng loạt (bulk).
 * @return string Mã thông báo, '' nếu không cần thông báo.
 */
function init_plugin_suite_review_system_notice_code( $action, $result, $single ) {
	if ( $single && 'delete' === $action && ! $result ) {
		return 'delete_failed';
	}

	if ( false === $result ) {
		return '';
	}

	if ( $single ) {
		return $action . '_single';
	}

	return $result ? $action : '';
}

/**
 * In thông báo kết quả sau khi redirect về trang quản lý.
 *
 * @return void
 */
function init_plugin_suite_review_system_render_management_notice() {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Chỉ hiển thị thông báo, không thay đổi dữ liệu.
	$notice = isset( $_GET['irs_notice'] ) ? sanitize_key( wp_unslash( $_GET['irs_notice'] ) ) : '';
	$count  = isset( $_GET['irs_count'] ) ? absint( $_GET['irs_count'] ) : 0;
	// phpcs:enable WordPress.Security.NonceVerification.Recommended

	if ( ! $notice ) {
		return;
	}

	switch ( $notice ) {
		case 'approve':
			$type = 'success';
			// translators: %d is the number of reviews approved.
			$message = sprintf( _n( '%d review approved.', '%d reviews approved.', $count, 'init-review-system' ), $count );
			break;

		case 'reject':
			$type = 'warning';
			// translators: %d is the number of reviews rejected.
			$message = sprintf( _n( '%d review rejected.', '%d reviews rejected.', $count, 'init-review-system' ), $count );
			break;

		case 'delete':
			$type = 'success';
			// translators: %d is the number of reviews deleted.
			$message = sprintf( _n( '%d review deleted.', '%d reviews deleted.', $count, 'init-review-system' ), $count );
			break;

		case 'approve_single':
			$type    = 'success';
			$message = __( 'Review approved.', 'init-review-system' );
			break;

		case 'reject_single':
			$type    = 'warning';
			$message = __( 'Review rejected.', 'init-review-system' );
			break;

		case 'delete_single':
			$type    = 'success';
			$message = __( 'Review deleted successfully.', 'init-review-system' );
			break;

		case 'delete_failed':
			$type    = 'error';
			$message = __( 'Failed to delete review.', 'init-review-system' );
			break;

		default:
			return;
	}

	printf(
		'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
		esc_attr( $type ),
		esc_html( $message )
	);
}

/**
 * Nhãn (đã dịch) cho trạng thái review.
 *
 * @param string $status Trạng thái.
 * @return string
 */
function init_plugin_suite_review_system_get_status_label( $status ) {
	$labels = array(
		'approved' => __( 'Approved', 'init-review-system' ),
		'pending'  => __( 'Pending', 'init-review-system' ),
		'rejected' => __( 'Rejected', 'init-review-system' ),
	);

	return $labels[ $status ] ?? ucfirst( (string) $status );
}

/**
 * Get reviews with filters and pagination.
 *
 * @param array $filters Bộ lọc: status, post_id, user_id, search.
 * @return array
 */
function init_plugin_suite_review_system_get_reviews_for_admin( $filters = array() ) {
	global $wpdb;
	$table_name = $wpdb->prefix . 'init_criteria_reviews';

	$where_conditions = array();
	$where_values     = array();

	// Filter by status.
	if ( ! empty( $filters['status'] ) && 'all' !== $filters['status'] ) {
		$where_conditions[] = 'status = %s';
		$where_values[]     = sanitize_text_field( $filters['status'] );
	}

	// Filter by post ID.
	if ( ! empty( $filters['post_id'] ) ) {
		$where_conditions[] = 'post_id = %d';
		$where_values[]     = absint( $filters['post_id'] );
	}

	// Filter by user ID.
	if ( ! empty( $filters['user_id'] ) ) {
		$where_conditions[] = 'user_id = %d';
		$where_values[]     = absint( $filters['user_id'] );
	}

	// Search in review content.
	if ( ! empty( $filters['search'] ) ) {
		$where_conditions[] = 'review_content LIKE %s';
		$where_values[]     = '%' . $wpdb->esc_like( sanitize_text_field( $filters['search'] ) ) . '%';
	}

	$where_clause = '';
	if ( ! empty( $where_conditions ) ) {
		$where_clause = 'WHERE ' . implode( ' AND ', $where_conditions );
	}

	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
	$count_sql = "SELECT COUNT(*) FROM {$table_name} {$where_clause}";
	$total     = (int) ( $where_values
		? $wpdb->get_var( $wpdb->prepare( $count_sql, ...$where_values ) )
		: $wpdb->get_var( $count_sql ) );
	// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter

	// Get paginated results.
	$per_page    = 20;
	$total_pages = (int) ceil( $total / $per_page );
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$paged  = isset( $_GET['paged'] ) ? max( 1, absint( wp_unslash( $_GET['paged'] ) ) ) : 1;
	$offset = ( $paged - 1 ) * $per_page;

	$results = array();
	if ( $total > 0 && $offset < $total ) {
		$query_values = array_merge( $where_values, array( $per_page, $offset ) );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber,PluginCheck.Security.DirectDB.UnescapedDBParameter
		$results = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$table_name} {$where_clause} ORDER BY created_at DESC LIMIT %d OFFSET %d", ...$query_values ),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber,PluginCheck.Security.DirectDB.UnescapedDBParameter
	}

	$results = init_plugin_suite_review_system_unserialize_reviews( $results );

	// Prime cache post/user hàng loạt để bảng bên dưới không phải query riêng
	// cho từng dòng (get_post() / get_user_by()).
	if ( ! empty( $results ) ) {
		$post_ids = array_unique( array_filter( array_map( 'absint', wp_list_pluck( $results, 'post_id' ) ) ) );
		$user_ids = array_unique( array_filter( array_map( 'absint', wp_list_pluck( $results, 'user_id' ) ) ) );

		if ( $post_ids ) {
			_prime_post_caches( $post_ids, false, false );
		}
		if ( $user_ids ) {
			cache_users( $user_ids );
		}
	}

	return array(
		'reviews'     => $results,
		'total'       => $total,
		'per_page'    => $per_page,
		'paged'       => $paged,
		'total_pages' => $total_pages,
	);
}

/**
 * Thống kê tổng quan (cho khối Summary).
 *
 * @return array{total:int,by_status:array,avg_score:float}
 */
function init_plugin_suite_review_system_get_admin_stats() {
	global $wpdb;
	$table_name = $wpdb->prefix . 'init_criteria_reviews';

	// Một query GROUP BY cho cả số lượng theo trạng thái lẫn tổng điểm của
	// review approved — thay cho 3 query riêng (COUNT theo status, COUNT tổng,
	// AVG approved) như trước.
	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter
	$rows = $wpdb->get_results(
		"SELECT status, COUNT(*) AS count, AVG(avg_score) AS avg_score FROM {$table_name} GROUP BY status",
		ARRAY_A
	);
	// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter

	$stats = array(
		'total'     => 0,
		'by_status' => array(),
		'avg_score' => 0.0,
	);

	foreach ( (array) $rows as $row ) {
		$count                                = (int) $row['count'];
		$stats['total']                      += $count;
		$stats['by_status'][ $row['status'] ] = $count;

		if ( 'approved' === $row['status'] ) {
			$stats['avg_score'] = (float) $row['avg_score'];
		}
	}

	return $stats;
}

/**
 * Render management page.
 *
 * @return void
 */
function init_plugin_suite_review_system_render_management_page() {
	// Get current filters with proper sanitization and nonce-less GET handling.
	// phpcs:disable WordPress.Security.NonceVerification.Recommended
	$filters = array(
		'status'  => isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : 'all',
		'post_id' => isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : '',
		'user_id' => isset( $_GET['user_id'] ) ? absint( $_GET['user_id'] ) : '',
		'search'  => isset( $_GET['search'] ) ? sanitize_text_field( wp_unslash( $_GET['search'] ) ) : '',
	);
	// phpcs:enable WordPress.Security.NonceVerification.Recommended

	$has_filters = ( 'all' !== $filters['status'] ) || $filters['post_id'] || $filters['user_id'] || '' !== $filters['search'];

	$data = init_plugin_suite_review_system_get_reviews_for_admin( $filters );

	$delete_confirm = __( 'Are you sure you want to delete this review?', 'init-review-system' );

	$status_colors = array(
		'approved' => '#46b450',
		'pending'  => '#ffba00',
		'rejected' => '#dc3232',
	);

	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Manage Reviews', 'init-review-system' ); ?></h1>

		<?php init_plugin_suite_review_system_render_management_notice(); ?>

		<!-- Filters -->
		<div class="tablenav top">
			<form method="get" class="alignleft">
				<input type="hidden" name="page" value="<?php echo esc_attr( INIT_PLUGIN_SUITE_RS_MANAGEMENT_PAGE ); ?>">

				<select name="status">
					<option value="all" <?php selected( $filters['status'], 'all' ); ?>><?php esc_html_e( 'All Statuses', 'init-review-system' ); ?></option>
					<option value="approved" <?php selected( $filters['status'], 'approved' ); ?>><?php esc_html_e( 'Approved', 'init-review-system' ); ?></option>
					<option value="pending" <?php selected( $filters['status'], 'pending' ); ?>><?php esc_html_e( 'Pending', 'init-review-system' ); ?></option>
					<option value="rejected" <?php selected( $filters['status'], 'rejected' ); ?>><?php esc_html_e( 'Rejected', 'init-review-system' ); ?></option>
				</select>

				<input type="number" name="post_id" value="<?php echo esc_attr( $filters['post_id'] ); ?>" placeholder="<?php esc_attr_e( 'Post ID', 'init-review-system' ); ?>" style="width: 80px;">

				<input type="number" name="user_id" value="<?php echo esc_attr( $filters['user_id'] ); ?>" placeholder="<?php esc_attr_e( 'User ID', 'init-review-system' ); ?>" style="width: 80px;">

				<input type="text" name="search" value="<?php echo esc_attr( $filters['search'] ); ?>" placeholder="<?php esc_attr_e( 'Search content...', 'init-review-system' ); ?>">

				<input type="submit" class="button" value="<?php esc_attr_e( 'Filter', 'init-review-system' ); ?>">

				<?php if ( $has_filters ) : ?>
					<a href="<?php echo esc_url( init_plugin_suite_review_system_management_url() ); ?>" class="button"><?php esc_html_e( 'Clear', 'init-review-system' ); ?></a>
				<?php endif; ?>
			</form>
		</div>

		<!-- Bulk Actions Form -->
		<form method="post">
			<?php wp_nonce_field( 'bulk_reviews_action' ); ?>

			<div class="tablenav top">
				<div class="alignleft actions bulkactions">
					<select name="action">
						<option value="-1"><?php esc_html_e( 'Bulk Actions', 'init-review-system' ); ?></option>
						<option value="approve"><?php esc_html_e( 'Approve', 'init-review-system' ); ?></option>
						<option value="reject"><?php esc_html_e( 'Reject', 'init-review-system' ); ?></option>
						<option value="delete"><?php esc_html_e( 'Delete', 'init-review-system' ); ?></option>
					</select>
					<input type="submit" class="button action" value="<?php esc_attr_e( 'Apply', 'init-review-system' ); ?>">
				</div>
			</div>

			<!-- Reviews Table -->
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<td class="manage-column column-cb check-column">
							<input type="checkbox" id="cb-select-all-1">
						</td>
						<th><?php esc_html_e( 'Review', 'init-review-system' ); ?></th>
						<th><?php esc_html_e( 'Post', 'init-review-system' ); ?></th>
						<th><?php esc_html_e( 'User', 'init-review-system' ); ?></th>
						<th><?php esc_html_e( 'Score', 'init-review-system' ); ?></th>
						<th><?php esc_html_e( 'Status', 'init-review-system' ); ?></th>
						<th><?php esc_html_e( 'Date', 'init-review-system' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'init-review-system' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $data['reviews'] ) ) : ?>
						<tr>
							<td colspan="8" style="text-align: center; padding: 20px;">
								<?php esc_html_e( 'No reviews found.', 'init-review-system' ); ?>
							</td>
						</tr>
					<?php else : ?>
						<?php foreach ( $data['reviews'] as $review ) : ?>
							<?php
							$review_id      = (int) $review['id'];
							$review_post_id = (int) $review['post_id'];
							$review_user_id = (int) $review['user_id'];
							$review_post    = get_post( $review_post_id );
							$review_user    = $review_user_id > 0 ? get_user_by( 'id', $review_user_id ) : false;
							$status_color   = $status_colors[ $review['status'] ] ?? '#666';
							?>
							<tr>
								<th class="check-column">
									<input type="checkbox" name="reviews[]" value="<?php echo esc_attr( $review_id ); ?>">
								</th>
								<td>
									<div style="max-width: 300px;">
										<?php if ( ! empty( $review['criteria_scores'] ) && is_array( $review['criteria_scores'] ) ) : ?>
											<div class="criteria-scores" style="margin-bottom: 8px;">
												<?php foreach ( $review['criteria_scores'] as $label => $score ) : ?>
													<span style="display: inline-block; margin-right: 10px; font-size: 12px; background: #f1f1f1; padding: 2px 6px; border-radius: 3px;">
														<?php echo esc_html( $label ); ?>: <?php echo esc_html( number_format_i18n( (float) $score, 1 ) ); ?>/5
													</span>
												<?php endforeach; ?>
											</div>
										<?php endif; ?>
										<div style="color: #666; line-height: 1.4;">
											<?php echo esc_html( wp_trim_words( (string) $review['review_content'], 15 ) ); ?>
										</div>
									</div>
								</td>
								<td>
									<?php if ( $review_post ) : ?>
										<a href="<?php echo esc_url( (string) get_edit_post_link( $review_post_id ) ); ?>" target="_blank">
											<?php echo esc_html( wp_trim_words( $review_post->post_title, 5 ) ); ?>
										</a>
									<?php else : ?>
										<span style="color: #dc3232;"><?php esc_html_e( 'Post not found', 'init-review-system' ); ?></span>
									<?php endif; ?>
									<br>
									<small style="color: #666;">ID: <?php echo esc_html( $review_post_id ); ?></small>
								</td>
								<td>
									<?php if ( $review_user_id > 0 ) : ?>
										<?php if ( $review_user ) : ?>
											<a href="<?php echo esc_url( get_edit_user_link( $review_user_id ) ); ?>" target="_blank">
												<?php echo esc_html( $review_user->display_name ); ?>
											</a>
											<br>
											<small style="color: #666;"><?php echo esc_html( $review_user->user_email ); ?></small>
										<?php else : ?>
											<span style="color: #dc3232;"><?php esc_html_e( 'User not found', 'init-review-system' ); ?></span>
										<?php endif; ?>
									<?php else : ?>
										<span style="color: #666;"><?php esc_html_e( 'Guest', 'init-review-system' ); ?></span>
									<?php endif; ?>
								</td>
								<td>
									<strong><?php echo esc_html( number_format_i18n( (float) $review['avg_score'], 2 ) ); ?>/5</strong>
								</td>
								<td>
									<span style="color: <?php echo esc_attr( $status_color ); ?>; font-weight: 500;">
										<?php echo esc_html( init_plugin_suite_review_system_get_status_label( $review['status'] ) ); ?>
									</span>
								</td>
								<td>
									<?php echo esc_html( mysql2date( 'Y/m/d g:i A', $review['created_at'] ) ); ?>
								</td>
								<td>
									<?php if ( 'approved' !== $review['status'] ) : ?>
										<a href="<?php echo esc_url( init_plugin_suite_review_system_review_action_url( 'approve', $review_id ) ); ?>" class="button-primary" style="margin-right: 5px;">
											<?php esc_html_e( 'Approve', 'init-review-system' ); ?>
										</a>
									<?php endif; ?>

									<?php if ( 'rejected' !== $review['status'] ) : ?>
										<a href="<?php echo esc_url( init_plugin_suite_review_system_review_action_url( 'reject', $review_id ) ); ?>" class="button" style="margin-right: 5px;">
											<?php esc_html_e( 'Reject', 'init-review-system' ); ?>
										</a>
									<?php endif; ?>

									<a href="<?php echo esc_url( init_plugin_suite_review_system_review_action_url( 'delete', $review_id ) ); ?>" class="button button-link-delete" onclick="return confirm('<?php echo esc_attr( esc_js( $delete_confirm ) ); ?>')">
										<?php esc_html_e( 'Delete', 'init-review-system' ); ?>
									</a>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
				<tfoot>
					<tr>
						<td class="manage-column column-cb check-column">
							<input type="checkbox" id="cb-select-all-2">
						</td>
						<th><?php esc_html_e( 'Review', 'init-review-system' ); ?></th>
						<th><?php esc_html_e( 'Post', 'init-review-system' ); ?></th>
						<th><?php esc_html_e( 'User', 'init-review-system' ); ?></th>
						<th><?php esc_html_e( 'Score', 'init-review-system' ); ?></th>
						<th><?php esc_html_e( 'Status', 'init-review-system' ); ?></th>
						<th><?php esc_html_e( 'Date', 'init-review-system' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'init-review-system' ); ?></th>
					</tr>
				</tfoot>
			</table>

			<div class="tablenav bottom">
				<div class="alignleft actions bulkactions">
					<select name="action2">
						<option value="-1"><?php esc_html_e( 'Bulk Actions', 'init-review-system' ); ?></option>
						<option value="approve"><?php esc_html_e( 'Approve', 'init-review-system' ); ?></option>
						<option value="reject"><?php esc_html_e( 'Reject', 'init-review-system' ); ?></option>
						<option value="delete"><?php esc_html_e( 'Delete', 'init-review-system' ); ?></option>
					</select>
					<input type="submit" class="button action" value="<?php esc_attr_e( 'Apply', 'init-review-system' ); ?>">
				</div>

				<!-- Pagination -->
				<?php if ( $data['total_pages'] > 1 ) : ?>
					<div class="tablenav-pages">
						<span class="displaying-num">
							<?php
							// translators: %s is the number of items in the list.
							printf( esc_html( _n( '%s item', '%s items', $data['total'], 'init-review-system' ) ), esc_html( number_format_i18n( $data['total'] ) ) );
							?>
						</span>

						<?php
						$page_links = paginate_links(
							array(
								'base'      => remove_query_arg( array( 'irs_notice', 'irs_count' ), add_query_arg( 'paged', '%#%' ) ),
								'format'    => '',
								'prev_text' => '&laquo;',
								'next_text' => '&raquo;',
								'total'     => $data['total_pages'],
								'current'   => $data['paged'],
								'type'      => 'plain',
							)
						);

						if ( $page_links ) {
							echo '<span class="pagination-links">' . wp_kses_post( $page_links ) . '</span>';
						}
						?>
					</div>
				<?php endif; ?>
			</div>
		</form>

		<!-- Summary Stats -->
		<?php $stats = init_plugin_suite_review_system_get_admin_stats(); ?>
		<div style="margin-top: 20px; padding: 15px; background: #f9f9f9; border-radius: 5px;">
			<h3><?php esc_html_e( 'Summary', 'init-review-system' ); ?></h3>
			<div style="display: flex; gap: 20px; flex-wrap: wrap;">
				<div>
					<strong><?php esc_html_e( 'Total Reviews:', 'init-review-system' ); ?></strong>
					<?php echo esc_html( number_format_i18n( $stats['total'] ) ); ?>
				</div>
				<div>
					<strong><?php esc_html_e( 'Approved:', 'init-review-system' ); ?></strong>
					<span style="color: #46b450;">
						<?php echo esc_html( number_format_i18n( $stats['by_status']['approved'] ?? 0 ) ); ?>
					</span>
				</div>
				<div>
					<strong><?php esc_html_e( 'Pending:', 'init-review-system' ); ?></strong>
					<span style="color: #ffba00;">
						<?php echo esc_html( number_format_i18n( $stats['by_status']['pending'] ?? 0 ) ); ?>
					</span>
				</div>
				<div>
					<strong><?php esc_html_e( 'Rejected:', 'init-review-system' ); ?></strong>
					<span style="color: #dc3232;">
						<?php echo esc_html( number_format_i18n( $stats['by_status']['rejected'] ?? 0 ) ); ?>
					</span>
				</div>
				<div>
					<strong><?php esc_html_e( 'Average Score:', 'init-review-system' ); ?></strong>
					<?php echo esc_html( number_format_i18n( $stats['avg_score'], 2 ) ); ?>/5
				</div>
			</div>
		</div>
	</div>

	<?php
}

add_action( 'admin_enqueue_scripts', 'init_plugin_suite_review_system_enqueue_management_script' );

/**
 * Enqueue admin scripts for review management.
 *
 * Hook suffix của submenu phụ thuộc vào tiêu đề menu cha ĐÃ DỊCH (ví dụ bản
 * tiếng Việt không còn là "review-system_page_..."), nên so khớp theo phần
 * đuôi `_page_{slug}` thay vì chuỗi cố định như trước.
 *
 * @param string $hook Hook suffix của trang admin hiện tại.
 * @return void
 */
function init_plugin_suite_review_system_enqueue_management_script( $hook ) {
	$suffix = '_page_' . INIT_PLUGIN_SUITE_RS_MANAGEMENT_PAGE;
	if ( substr( (string) $hook, -strlen( $suffix ) ) !== $suffix ) {
		return;
	}

	wp_enqueue_script(
		'init-review-management-script',
		INIT_PLUGIN_SUITE_RS_ASSETS_URL . 'js/review-management.js',
		array(),
		INIT_PLUGIN_SUITE_RS_VERSION,
		true
	);
}
