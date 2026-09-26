<?php
/**
 * Truy vấn & lưu trữ review nhiều tiêu chí.
 *
 * @package InitReviewSystem
 */

defined( 'ABSPATH' ) || exit;

/**
 * Thêm review mới vào hệ thống.
 *
 * @param int    $post_id         ID bài viết được review.
 * @param int    $user_id         ID người dùng tạo review.
 * @param array  $criteria_scores Mảng điểm tiêu chí (key => value).
 * @param string $review_content  Nội dung review.
 * @param string $status          Trạng thái review ('approved', 'pending', ...).
 * @return int|false ID review nếu thành công, false nếu lỗi.
 */
function init_plugin_suite_review_system_add_criteria_review( $post_id, $user_id, $criteria_scores = array(), $review_content = '', $status = 'approved' ) {
	global $wpdb;
	$table_name = $wpdb->prefix . 'init_criteria_reviews';

	// Tính điểm trung bình.
	$avg_score = ! empty( $criteria_scores )
		? array_sum( $criteria_scores ) / count( $criteria_scores )
		: 0;

	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
	$inserted = $wpdb->insert(
		$table_name,
		array(
			'post_id'         => absint( $post_id ),
			'user_id'         => absint( $user_id ),
			'criteria_scores' => maybe_serialize( $criteria_scores ),
			'avg_score'       => floatval( $avg_score ),
			'review_content'  => wp_kses_post( $review_content ),
			'status'          => sanitize_text_field( $status ),
			'created_at'      => current_time( 'mysql' ),
		),
		array( '%d', '%d', '%s', '%f', '%s', '%s', '%s' )
	);
	// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

	if ( false === $inserted ) {
		return false;
	}

	$review_id = (int) $wpdb->insert_id;

	/**
	 * Fires after a review has been successfully added.
	 *
	 * @since 1.9
	 *
	 * @param int    $review_id       ID của review mới.
	 * @param int    $post_id         ID bài viết được review.
	 * @param int    $user_id         ID người tạo review.
	 * @param array  $criteria_scores Mảng điểm tiêu chí.
	 * @param float  $avg_score       Điểm trung bình.
	 * @param string $status          Trạng thái review ('approved', ...).
	 * @param string $review_content  Nội dung review.
	 */
	do_action(
		'init_plugin_suite_review_system_after_insert',
		$review_id,
		$post_id,
		$user_id,
		$criteria_scores,
		$avg_score,
		$status,
		$review_content
	);

	return $review_id;
}

/**
 * Version cache reviews theo từng post_id.
 *
 * Cache của get_reviews_by_post_id()/get_total_reviews_by_post_id() có nhiều
 * biến thể key (theo $paged, $per_page, $status...) nên không thể xoá từng key.
 * Thay vào đó dùng một "version" theo từng post_id: khi cần invalidate chỉ
 * cần đổi version — mọi cache key cũ coi như miss.
 *
 * Từ 2.0.1 version là một mốc thời gian (microtime) thay vì số đếm bắt đầu từ
 * 1: nếu persistent object cache (Redis/Memcached) evict mất key version, giá
 * trị mới sinh ra sẽ không bao giờ trùng với version cũ — tránh trường hợp
 * version quay về 1 rồi "hồi sinh" nhầm dữ liệu cache cũ còn sống.
 *
 * @param int $post_id ID bài viết (0 = phạm vi toàn site, dùng chung 1 version).
 * @return string Version hiện tại.
 */
function init_plugin_suite_review_system_get_reviews_cache_version( $post_id ) {
	$key     = 'reviews_cache_version_' . absint( $post_id );
	$version = wp_cache_get( $key, 'init_review_system' );

	if ( false === $version ) {
		$version = init_plugin_suite_review_system_new_cache_version();
		wp_cache_set( $key, $version, 'init_review_system', 0 ); // 0 = không tự hết hạn.
	}

	return (string) $version;
}

/**
 * Sinh giá trị version cache mới (duy nhất theo thời gian).
 *
 * @return string
 */
function init_plugin_suite_review_system_new_cache_version() {
	return str_replace( '.', '', sprintf( '%.6F', microtime( true ) ) );
}

/**
 * Đổi version cache reviews của 1 post_id — invalidate toàn bộ biến thể cache cũ.
 *
 * @param int $post_id ID bài viết.
 * @return void
 */
function init_plugin_suite_review_system_bump_reviews_cache_version( $post_id ) {
	$key = 'reviews_cache_version_' . absint( $post_id );
	wp_cache_set( $key, init_plugin_suite_review_system_new_cache_version(), 'init_review_system', 0 );
}

/**
 * Xoá/làm mới toàn bộ cache liên quan tới review của một bài viết.
 *
 * Dùng ở bất cứ đâu sửa DB review NGOÀI luồng insert bình thường (ví dụ admin
 * duyệt/từ chối/xoá review) — những chỗ đó sửa DB trực tiếp bằng $wpdb nên
 * phải tự gọi hàm này.
 *
 * @param int $post_id ID bài viết.
 * @param int $user_id (tuỳ chọn) ID người review, để xoá đúng cache has_user_reviewed của người đó.
 * @return void
 */
function init_plugin_suite_review_system_invalidate_review_cache( $post_id, $user_id = 0 ) {
	$post_id = absint( $post_id );
	$user_id = absint( $user_id );
	if ( ! $post_id ) {
		return;
	}

	foreach ( array( 'approved', 'pending', 'rejected' ) as $status ) {
		wp_cache_delete( "score_summary_{$post_id}_{$status}", 'init_review_system' );
	}

	if ( $user_id > 0 ) {
		wp_cache_delete( "has_reviewed_{$post_id}_{$user_id}", 'init_review_system' );
	}

	init_plugin_suite_review_system_bump_reviews_cache_version( $post_id );

	// Danh sách review toàn site (post_id = 0) cũng chứa review của bài này.
	init_plugin_suite_review_system_bump_reviews_cache_version( 0 );
}

/**
 * TTL (giây) cho cache danh sách/tổng số review.
 *
 * @return int
 */
function init_plugin_suite_review_system_get_reviews_cache_ttl() {
	// Mặc định 5 phút — site có thể override qua filter, trả về 0 để tắt hẳn.
	return (int) apply_filters( 'init_plugin_suite_review_system_ttl', 5 * MINUTE_IN_SECONDS );
}

/**
 * Giải mã cột criteria_scores cho danh sách review.
 *
 * @param array $results Danh sách review (ARRAY_A).
 * @return array
 */
function init_plugin_suite_review_system_unserialize_reviews( $results ) {
	if ( empty( $results ) || ! is_array( $results ) ) {
		return array();
	}

	foreach ( $results as &$review ) {
		$review['criteria_scores'] = maybe_unserialize( $review['criteria_scores'] );
	}
	unset( $review );

	return $results;
}

/**
 * Lấy review của bài viết (post_id = 0: toàn site).
 *
 * @param int    $post_id  ID bài viết.
 * @param int    $paged    Trang hiện tại.
 * @param int    $per_page Số review mỗi trang (0 = tất cả).
 * @param string $status   Trạng thái review.
 * @return array
 */
function init_plugin_suite_review_system_get_reviews_by_post_id( $post_id, $paged = 1, $per_page = 0, $status = 'approved' ) {
	global $wpdb;

	$post_id  = absint( $post_id );
	$paged    = (int) $paged;
	$per_page = (int) $per_page;
	$ttl      = init_plugin_suite_review_system_get_reviews_cache_ttl();

	$cache_key = null;
	if ( $ttl > 0 ) {
		$version   = init_plugin_suite_review_system_get_reviews_cache_version( $post_id );
		$cache_key = "reviews_{$post_id}_v{$version}_{$paged}_{$per_page}_{$status}";
		$cached    = wp_cache_get( $cache_key, 'init_review_system' );
		if ( false !== $cached ) {
			return $cached;
		}
	}

	$table_reviews = $wpdb->prefix . 'init_criteria_reviews';
	$table_posts   = $wpdb->posts;

	$sql    = "SELECT r.* FROM {$table_reviews} r";
	$params = array();

	if ( 0 === $post_id ) {
		$sql .= " INNER JOIN {$table_posts} p ON p.ID = r.post_id";
	}

	$sql     .= ' WHERE r.status = %s';
	$params[] = $status;

	if ( $post_id > 0 ) {
		$sql     .= ' AND r.post_id = %d';
		$params[] = $post_id;
	}

	$sql .= ' ORDER BY r.created_at DESC';

	if ( $per_page > 0 && $paged > 0 ) {
		$sql     .= ' LIMIT %d OFFSET %d';
		$params[] = $per_page;
		$params[] = ( $paged - 1 ) * $per_page;
	}

	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
	$results = $wpdb->get_results( $wpdb->prepare( $sql, ...$params ), ARRAY_A );
	// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter

	$results = init_plugin_suite_review_system_unserialize_reviews( $results );

	if ( $ttl > 0 ) {
		wp_cache_set( $cache_key, $results, 'init_review_system', $ttl );
	}

	return $results;
}

/**
 * Chuẩn bị dữ liệu review đầy đủ (kèm display_name/avatar_url) cho một bài
 * viết — dùng chung bởi REST endpoint /get-criteria-reviews và ability
 * init-review-system/get-criteria-reviews.
 *
 * @param int    $post_id  ID bài viết.
 * @param int    $page     Trang hiện tại (bắt đầu từ 1).
 * @param int    $per_page Số review mỗi trang (tối đa 100).
 * @param string $status   Trạng thái review.
 * @return array|WP_Error Dữ liệu tổng hợp, hoặc WP_Error nếu post_id không hợp lệ.
 */
function init_plugin_suite_review_system_get_criteria_reviews_data( $post_id, $page = 1, $per_page = 10, $status = 'approved' ) {
	$post_id = absint( $post_id );

	if ( ! $post_id || ! init_plugin_suite_review_system_can_view_post( $post_id ) ) {
		return new WP_Error( 'invalid_post', __( 'Invalid post ID.', 'init-review-system' ), array( 'status' => 400 ) );
	}

	$page     = max( 1, absint( $page ) );
	$per_page = min( 100, max( 1, absint( $per_page ) ) );

	$total    = init_plugin_suite_review_system_get_total_reviews_by_post_id( $post_id, $status );
	$max_page = (int) ceil( $total / $per_page );

	// Trang nằm ngoài phạm vi thì khỏi query.
	$reviews = ( $page <= $max_page )
		? init_plugin_suite_review_system_get_reviews_by_post_id( $post_id, $page, $per_page, $status )
		: array();

	return array(
		'post_id'  => $post_id,
		'page'     => $page,
		'per_page' => $per_page,
		'total'    => $total,
		'max_page' => $max_page,
		'criteria' => init_plugin_suite_review_system_get_criteria_by_post_id( $post_id ),
		'reviews'  => init_plugin_suite_review_system_enrich_reviews( $reviews, 48 ),
	);
}

/**
 * Lấy danh sách review theo nhiều bài viết.
 *
 * @param int[]  $post_ids Danh sách ID bài viết.
 * @param int    $paged    Trang hiện tại.
 * @param int    $per_page Số review mỗi trang.
 * @param string $status   Trạng thái review.
 * @return array
 */
function init_plugin_suite_review_system_get_reviews_by_post_ids( $post_ids = array(), $paged = 1, $per_page = 10, $status = 'approved' ) {
	global $wpdb;
	$table_name = $wpdb->prefix . 'init_criteria_reviews';

	$post_ids = array_unique( array_filter( array_map( 'intval', (array) $post_ids ) ) );
	if ( empty( $post_ids ) ) {
		return array();
	}

	$paged    = max( 1, (int) $paged );
	$per_page = max( 1, (int) $per_page );
	$offset   = ( $paged - 1 ) * $per_page;

	$placeholders = implode( ',', array_fill( 0, count( $post_ids ), '%d' ) );

	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber,PluginCheck.Security.DirectDB.UnescapedDBParameter
	$sql     = $wpdb->prepare(
		"SELECT * FROM {$table_name}
		WHERE status = %s AND post_id IN ({$placeholders})
		ORDER BY created_at DESC
		LIMIT %d OFFSET %d",
		array_merge( array( $status ), $post_ids, array( $per_page, $offset ) )
	);
	$results = $wpdb->get_results( $sql, ARRAY_A );
	// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber,PluginCheck.Security.DirectDB.UnescapedDBParameter

	return init_plugin_suite_review_system_unserialize_reviews( $results );
}

/**
 * Đếm tổng số review theo nhiều bài viết.
 *
 * @param int[]  $post_ids Danh sách ID bài viết.
 * @param string $status   Trạng thái review.
 * @return int
 */
function init_plugin_suite_review_system_count_reviews_by_post_id( $post_ids = array(), $status = 'approved' ) {
	global $wpdb;
	$table_name = $wpdb->prefix . 'init_criteria_reviews';

	$post_ids = array_unique( array_filter( array_map( 'intval', (array) $post_ids ) ) );
	if ( empty( $post_ids ) ) {
		return 0;
	}

	$placeholders = implode( ',', array_fill( 0, count( $post_ids ), '%d' ) );

	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
	$sql    = $wpdb->prepare(
		"SELECT COUNT(*) FROM {$table_name}
		WHERE status = %s AND post_id IN ({$placeholders})",
		array_merge( array( $status ), $post_ids )
	);
	$result = (int) $wpdb->get_var( $sql );
	// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter

	return $result;
}

/**
 * Kiểm tra user đã có review (approved) cho bài viết chưa.
 *
 * @param int $post_id ID bài viết.
 * @param int $user_id ID người dùng.
 * @return bool
 */
function init_plugin_suite_review_system_has_user_reviewed( $post_id, $user_id ) {
	$post_id = absint( $post_id );
	$user_id = absint( $user_id );
	if ( ! $post_id || ! $user_id ) {
		return false;
	}

	$cache_key = "has_reviewed_{$post_id}_{$user_id}";
	$cached    = wp_cache_get( $cache_key, 'init_review_system' );
	if ( false !== $cached ) {
		// Cache lưu 1/0 (không phải true/false) để phân biệt với cache miss.
		return (bool) $cached;
	}

	global $wpdb;
	$table_name = $wpdb->prefix . 'init_criteria_reviews';

	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
	$review_id = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT id FROM {$table_name} WHERE post_id = %d AND user_id = %d AND status = %s LIMIT 1",
			$post_id,
			$user_id,
			'approved'
		)
	);
	// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter

	$result = ! empty( $review_id );

	wp_cache_set( $cache_key, (int) $result, 'init_review_system', HOUR_IN_SECONDS );

	return $result;
}

/**
 * Lấy tổng số review của bài viết.
 *
 * @param int    $post_id ID bài viết.
 * @param string $status  Trạng thái review.
 * @return int
 */
function init_plugin_suite_review_system_get_total_reviews_by_post_id( $post_id, $status = 'approved' ) {
	global $wpdb;

	$post_id = absint( $post_id );
	$ttl     = init_plugin_suite_review_system_get_reviews_cache_ttl();

	$cache_key = null;
	if ( $ttl > 0 ) {
		$version   = init_plugin_suite_review_system_get_reviews_cache_version( $post_id );
		$cache_key = "total_reviews_{$post_id}_v{$version}_{$status}";
		$cached    = wp_cache_get( $cache_key, 'init_review_system' );
		if ( false !== $cached ) {
			return (int) $cached;
		}
	}

	$table_name = $wpdb->prefix . 'init_criteria_reviews';

	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
	$count = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$table_name} WHERE post_id = %d AND status = %s",
			$post_id,
			$status
		)
	);
	// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter

	$total = intval( $count );

	if ( $ttl > 0 ) {
		wp_cache_set( $cache_key, $total, 'init_review_system', $ttl );
	}

	return $total;
}

/**
 * Lấy điểm trung bình tổng và từng tiêu chí.
 *
 * @param int    $post_id ID bài viết.
 * @param string $status  Trạng thái review.
 * @return array{overall_avg:float|int,breakdown:array}
 */
function init_plugin_suite_review_system_get_score_summary_by_post_id( $post_id, $status = 'approved' ) {
	global $wpdb;
	$table_name = $wpdb->prefix . 'init_criteria_reviews';
	$post_id    = absint( $post_id );

	$ttl       = HOUR_IN_SECONDS;
	$cache_key = "score_summary_{$post_id}_{$status}";
	$cached    = wp_cache_get( $cache_key, 'init_review_system' );
	if ( false !== $cached ) {
		return $cached;
	}

	// Gộp 2 query cũ (AVG + lấy criteria_scores) thành 1 lần đọc: overall_avg
	// được tính từ chính các dòng vừa lấy về, cho kết quả giống hệt AVG() của
	// MySQL nhưng giảm một round-trip tới DB.
	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT avg_score, criteria_scores FROM {$table_name} WHERE post_id = %d AND status = %s",
			$post_id,
			$status
		),
		ARRAY_N
	);
	// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter

	if ( empty( $rows ) ) {
		$summary = array(
			'overall_avg' => 0,
			'breakdown'   => array(),
		);
		wp_cache_set( $cache_key, $summary, 'init_review_system', $ttl );
		return $summary;
	}

	// Cộng dồn tổng + số lượng theo từng label thô (O(1) mỗi điểm, không giữ
	// mảng giá trị), rồi mới sanitize mỗi label MỘT lần ở cuối — thay vì gọi
	// sanitize_text_field() cho từng điểm của từng review như trước.
	$overall_sum = 0.0;
	$raw_sums    = array();
	$raw_counts  = array();

	foreach ( $rows as $row ) {
		$overall_sum += (float) $row[0];

		$scores = maybe_unserialize( $row[1] );
		if ( ! is_array( $scores ) ) {
			continue;
		}

		foreach ( $scores as $label => $score ) {
			if ( ! isset( $raw_sums[ $label ] ) ) {
				$raw_sums[ $label ]   = 0.0;
				$raw_counts[ $label ] = 0;
			}
			$raw_sums[ $label ] += (float) $score;
			++$raw_counts[ $label ];
		}
	}

	$sums   = array();
	$counts = array();
	foreach ( $raw_sums as $raw_label => $sum ) {
		$label = sanitize_text_field( (string) $raw_label );
		if ( ! isset( $sums[ $label ] ) ) {
			$sums[ $label ]   = 0.0;
			$counts[ $label ] = 0;
		}
		$sums[ $label ]   += $sum;
		$counts[ $label ] += $raw_counts[ $raw_label ];
	}

	$breakdown = array();
	foreach ( $sums as $label => $sum ) {
		$breakdown[ $label ] = round( $sum / $counts[ $label ], 2 );
	}

	$summary = array(
		'overall_avg' => round( $overall_sum / count( $rows ), 2 ),
		'breakdown'   => $breakdown,
	);

	wp_cache_set( $cache_key, $summary, 'init_review_system', $ttl );

	return $summary;
}

/**
 * Tính tổng số trang review (toàn site) dựa trên số review mỗi trang.
 *
 * @param int    $per    Số review mỗi trang.
 * @param string $status Trạng thái review.
 * @return int
 */
function init_plugin_suite_review_system_get_total_pages( $per, $status = 'approved' ) {
	if ( $per <= 0 ) {
		return 1;
	}

	global $wpdb;
	$table_name = $wpdb->prefix . 'init_criteria_reviews';

	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
	$total_reviews = (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$table_name} WHERE status = %s",
			$status
		)
	);
	// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter

	return (int) ceil( $total_reviews / $per );
}

/**
 * Lấy danh sách review theo user_id.
 *
 * @param int    $user_id   ID người dùng cần lấy review.
 * @param int    $paged     Trang hiện tại (>=1). Nếu $per_page = 0 thì bỏ qua phân trang.
 * @param int    $per_page  Số review mỗi trang. 0 để lấy toàn bộ.
 * @param string $status    Trạng thái review ('approved', 'pending', ...).
 *
 * @return array Danh sách review (ARRAY_A), đã unserialize 'criteria_scores'.
 */
function init_plugin_suite_review_system_get_reviews_by_user_id( $user_id, $paged = 1, $per_page = 0, $status = 'approved' ) {
	$user_id  = absint( $user_id );
	$paged    = max( 1, (int) $paged );
	$per_page = (int) $per_page;

	if ( $user_id <= 0 ) {
		return array();
	}

	global $wpdb;
	$table_reviews = $wpdb->prefix . 'init_criteria_reviews';
	$table_posts   = $wpdb->posts;

	// Join với wp_posts để loại review mồ côi; mới nhất trước.
	$sql    = "SELECT r.* FROM {$table_reviews} r INNER JOIN {$table_posts} p ON p.ID = r.post_id WHERE r.user_id = %d AND r.status = %s ORDER BY r.created_at DESC";
	$params = array( $user_id, $status );

	if ( $per_page > 0 ) {
		$sql     .= ' LIMIT %d OFFSET %d';
		$params[] = $per_page;
		$params[] = ( $paged - 1 ) * $per_page;
	}

	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
	$results = $wpdb->get_results( $wpdb->prepare( $sql, ...$params ), ARRAY_A );
	// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter

	return init_plugin_suite_review_system_unserialize_reviews( $results );
}

/**
 * Tính tổng số trang review dựa trên user_id.
 *
 * @param int    $user_id   ID người dùng cần lấy tổng số trang.
 * @param int    $per_page  Số review mỗi trang (>=1).
 * @param string $status    Trạng thái review ('approved', 'pending', ...).
 *
 * @return int Tổng số trang.
 */
function init_plugin_suite_review_system_get_total_pages_by_user_id( $user_id, $per_page = 10, $status = 'approved' ) {
	$user_id  = absint( $user_id );
	$per_page = max( 1, (int) $per_page );

	if ( $user_id <= 0 ) {
		return 1;
	}

	global $wpdb;
	$table_reviews = $wpdb->prefix . 'init_criteria_reviews';
	$table_posts   = $wpdb->posts;

	// Đếm review còn tồn tại post (không tính review mồ côi).
	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
	$total_reviews = (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*)
			FROM {$table_reviews} r
			INNER JOIN {$table_posts} p ON p.ID = r.post_id
			WHERE r.user_id = %d
				AND r.status = %s",
			$user_id,
			$status
		)
	);
	// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter

	return (int) ceil( $total_reviews / $per_page );
}

/**
 * Lấy danh sách tiêu chí (criteria) theo post_id.
 *
 * @param int $post_id ID bài viết.
 * @return string[] Danh sách label tiêu chí.
 */
function init_plugin_suite_review_system_get_criteria_by_post_id( $post_id ) {
	$post_id = absint( $post_id );

	// Hook cho phép override criteria theo từng post.
	$override = apply_filters( 'init_plugin_suite_review_system_criteria', null, $post_id );
	if ( is_array( $override ) && ! empty( $override ) ) {
		return array_values( array_filter( array_map( 'sanitize_text_field', $override ) ) );
	}

	// Đọc đúng cấu trúc option: criteria_1 → criteria_5.
	$options  = get_option( INIT_PLUGIN_SUITE_RS_OPTION, array() );
	$criteria = array();

	for ( $i = 1; $i <= 5; $i++ ) {
		$val = trim( (string) ( $options[ "criteria_$i" ] ?? '' ) );
		if ( '' !== $val ) {
			$criteria[] = sanitize_text_field( $val );
		}
	}

	return $criteria;
}

/**
 * Lấy một review theo ID.
 *
 * @param int $review_id ID của review.
 * @return array|null Review data hoặc null nếu không tìm thấy.
 */
function init_plugin_suite_review_system_get_review_by_id( $review_id ) {
	global $wpdb;
	$table_name = $wpdb->prefix . 'init_criteria_reviews';

	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
	$review = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT * FROM {$table_name} WHERE id = %d LIMIT 1",
			absint( $review_id )
		),
		ARRAY_A
	);
	// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter

	if ( ! $review ) {
		return null;
	}

	$review['criteria_scores'] = maybe_unserialize( $review['criteria_scores'] );

	return $review;
}

// ============================================================
// CACHE INVALIDATION — gắn vào action after_insert có sẵn.
// ============================================================

add_action( 'init_plugin_suite_review_system_after_insert', 'init_plugin_suite_review_system_invalidate_cache_after_insert', 10, 3 );

/**
 * Xoá cache liên quan khi có review mới được thêm vào.
 *
 * @param int $review_id ID review (không dùng).
 * @param int $post_id   ID bài viết.
 * @param int $user_id   ID người review.
 * @return void
 */
function init_plugin_suite_review_system_invalidate_cache_after_insert( $review_id, $post_id, $user_id ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundBeforeLastUsed
	init_plugin_suite_review_system_invalidate_review_cache( $post_id, $user_id );
}
