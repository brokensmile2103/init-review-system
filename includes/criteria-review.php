<?php
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
function init_plugin_suite_review_system_add_criteria_review( $post_id, $user_id, $criteria_scores = [], $review_content = '', $status = 'approved' ) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'init_criteria_reviews';

    // Tính điểm trung bình
    $avg_score = ! empty( $criteria_scores )
        ? array_sum( $criteria_scores ) / count( $criteria_scores )
        : 0;

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
    $inserted = $wpdb->insert(
        $table_name,
        [
            'post_id'         => absint( $post_id ),
            'user_id'         => absint( $user_id ),
            'criteria_scores' => maybe_serialize( $criteria_scores ),
            'avg_score'       => floatval( $avg_score ),
            'review_content'  => wp_kses_post( $review_content ),
            'status'          => sanitize_text_field( $status ),
            'created_at'      => current_time( 'mysql' ),
        ],
        [ '%d', '%d', '%s', '%f', '%s', '%s', '%s' ]
    );
    // phpcs:enable

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
 * Cache của get_reviews_by_post_id()/get_total_reviews_by_post_id() có nhiều
 * biến thể key khác nhau (theo $paged, $per_page, $status...) nên không thể
 * wp_cache_delete() từng key cụ thể khi có thay đổi. Thay vào đó dùng một số
 * "version" theo từng post_id: mỗi khi cần invalidate, chỉ cần tăng version
 * lên 1 — mọi cache key cũ (có version thấp hơn) coi như miss và tự bị bỏ qua.
 *
 * @param int $post_id ID bài viết (0 = phạm vi toàn site, dùng chung 1 version).
 * @return int Version hiện tại.
 */
function init_plugin_suite_review_system_get_reviews_cache_version( $post_id ) {
    $key     = 'reviews_cache_version_' . absint( $post_id );
    $version = wp_cache_get( $key, 'init_review_system' );

    if ( false === $version ) {
        $version = 1;
        wp_cache_set( $key, $version, 'init_review_system', 0 ); // 0 = không tự hết hạn
    }

    return (int) $version;
}

/** Tăng version cache reviews của 1 post_id — coi như invalidate toàn bộ biến thể cache cũ. */
function init_plugin_suite_review_system_bump_reviews_cache_version( $post_id ) {
    $key = 'reviews_cache_version_' . absint( $post_id );
    wp_cache_set( $key, init_plugin_suite_review_system_get_reviews_cache_version( $post_id ) + 1, 'init_review_system', 0 );
}

/**
 * Xoá/làm mới toàn bộ cache liên quan tới review của một bài viết. Dùng ở bất
 * cứ đâu sửa DB review NGOÀI luồng insert bình thường (ví dụ admin duyệt/từ
 * chối/xoá review) — những chỗ đó sửa DB trực tiếp bằng $wpdb nên phải tự gọi
 * hàm này, không có action hook nào tự động lo việc đó cho chúng.
 *
 * @param int $post_id ID bài viết.
 * @param int $user_id (tuỳ chọn) ID người review, để xoá đúng cache has_user_reviewed của người đó.
 */
function init_plugin_suite_review_system_invalidate_review_cache( $post_id, $user_id = 0 ) {
    $post_id = absint( $post_id );
    if ( ! $post_id ) {
        return;
    }

    foreach ( [ 'approved', 'pending', 'rejected' ] as $status ) {
        wp_cache_delete( "score_summary_{$post_id}_{$status}", 'init_review_system' );
    }

    if ( $user_id > 0 ) {
        wp_cache_delete( "has_reviewed_{$post_id}_{$user_id}", 'init_review_system' );
    }

    init_plugin_suite_review_system_bump_reviews_cache_version( $post_id );
}

// Lấy review của bài viết
function init_plugin_suite_review_system_get_reviews_by_post_id( $post_id, $paged = 1, $per_page = 0, $status = 'approved' ) {
    global $wpdb;

    // Mặc định bật cache 5 phút — vẫn cho phép site override qua filter này,
    // ví dụ tăng lên nếu traffic cao hoặc trả về 0 để tắt hẳn.
    $ttl = (int) apply_filters( 'init_plugin_suite_review_system_ttl', 5 * MINUTE_IN_SECONDS );

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

    if ( (int) $post_id === 0 ) {
        $sql .= " INNER JOIN {$table_posts} p ON p.ID = r.post_id";
    }

    $sql .= " WHERE r.status = %s";
    $params[] = $status;

    if ( (int) $post_id > 0 ) {
        $sql .= " AND r.post_id = %d";
        $params[] = (int) $post_id;
    }

    $sql .= " ORDER BY r.created_at DESC";

    if ( $per_page > 0 && $paged > 0 ) {
        $offset   = ( $paged - 1 ) * $per_page;
        $sql     .= " LIMIT %d OFFSET %d";
        $params[] = (int) $per_page;
        $params[] = (int) $offset;
    }

    // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
    $prepared_sql = $wpdb->prepare( $sql, ...$params );
    $results      = $wpdb->get_results( $prepared_sql, ARRAY_A );
    // phpcs:enable WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter

    foreach ( $results as &$review ) {
        $review['criteria_scores'] = maybe_unserialize( $review['criteria_scores'] );
    }

    if ( $ttl > 0 ) {
        wp_cache_set( $cache_key, $results, 'init_review_system', $ttl );
    }

    return $results;
}

// Lấy danh sách review theo nhiều bài viết
function init_plugin_suite_review_system_get_reviews_by_post_ids( $post_ids = [], $paged = 1, $per_page = 10, $status = 'approved' ) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'init_criteria_reviews';

    $post_ids = array_filter( array_map( 'intval', (array) $post_ids ) );
    if ( empty( $post_ids ) ) {
        return [];
    }

    $paged    = max( 1, (int) $paged );
    $per_page = max( 1, (int) $per_page );
    $offset   = ( $paged - 1 ) * $per_page;

    $placeholders = implode( ',', array_fill( 0, count( $post_ids ), '%d' ) );

    // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber,PluginCheck.Security.DirectDB.UnescapedDBParameter
    $sql = $wpdb->prepare(
        "SELECT * FROM {$table_name} 
         WHERE status = %s AND post_id IN ({$placeholders})
         ORDER BY created_at DESC
         LIMIT %d OFFSET %d",
        array_merge( [ $status ], $post_ids, [ $per_page, $offset ] )
    );
    $results = $wpdb->get_results( $sql, ARRAY_A );
    // phpcs:enable WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber,PluginCheck.Security.DirectDB.UnescapedDBParameter

    foreach ( $results as &$review ) {
        $review['criteria_scores'] = maybe_unserialize( $review['criteria_scores'] );
    }

    return $results;
}

// Đếm tổng số review theo nhiều bài viết
function init_plugin_suite_review_system_count_reviews_by_post_id( $post_ids = [], $status = 'approved' ) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'init_criteria_reviews';

    $post_ids = array_filter( array_map( 'intval', (array) $post_ids ) );
    if ( empty( $post_ids ) ) {
        return 0;
    }

    $placeholders = implode( ',', array_fill( 0, count( $post_ids ), '%d' ) );

    // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
    $sql = $wpdb->prepare(
        "SELECT COUNT(*) FROM {$table_name} 
         WHERE status = %s AND post_id IN ({$placeholders})",
        array_merge( [ $status ], $post_ids )
    );
    $result = (int) $wpdb->get_var( $sql );
    // phpcs:enable WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
    
    return $result;
}

// Kiểm tra user đã review chưa
function init_plugin_suite_review_system_has_user_reviewed( $post_id, $user_id ) {
    if ( ! $post_id || ! $user_id ) {
        return false;
    }

    // --- Cache ---
    $ttl       = HOUR_IN_SECONDS;
    $cache_key = "has_reviewed_{$post_id}_{$user_id}";
    $cached    = wp_cache_get( $cache_key, 'init_review_system' );
    if ( false !== $cached ) {
        // Lưu ý: cache lưu 1 hoặc 0, không dùng false !== check trực tiếp cho bool
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

    // Lưu 1/0 thay vì true/false để tránh false === wp_cache_get() false-miss
    wp_cache_set( $cache_key, (int) $result, 'init_review_system', $ttl );

    return $result;
}

// Lấy tổng review của bài viết
function init_plugin_suite_review_system_get_total_reviews_by_post_id( $post_id, $status = 'approved' ) {
    global $wpdb;

    $ttl = (int) apply_filters( 'init_plugin_suite_review_system_ttl', 5 * MINUTE_IN_SECONDS );

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

// Lấy điểm trung bình tổng và từng tiêu chí
function init_plugin_suite_review_system_get_score_summary_by_post_id( $post_id, $status = 'approved' ) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'init_criteria_reviews';

    // --- Cache ---
    $ttl       = HOUR_IN_SECONDS;
    $cache_key = "score_summary_{$post_id}_{$status}";
    $cached    = wp_cache_get( $cache_key, 'init_review_system' );
    if ( false !== $cached ) {
        return $cached;
    }

    // overall_avg: để MySQL tính AVG() trực tiếp (dùng được index post_id+status),
    // thay vì kéo hết avg_score về PHP rồi cộng tay như trước — nhẹ hơn đáng kể
    // với bài viết có nhiều review vì không phải duyệt qua từng dòng chỉ để cộng.
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
    $overall_avg = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT AVG(avg_score) FROM {$table_name} WHERE post_id = %d AND status = %s",
            $post_id,
            $status
        )
    );
    // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter

    if ( null === $overall_avg ) {
        $summary = [
            'overall_avg' => 0,
            'breakdown'   => [],
        ];
        wp_cache_set( $cache_key, $summary, 'init_review_system', $ttl );
        return $summary;
    }

    // Breakdown theo từng tiêu chí: bắt buộc phải duyệt PHP vì criteria_scores
    // lưu dạng serialize (không tính AVG theo từng key ở tầng SQL được). Đây là
    // giới hạn của cấu trúc lưu trữ hiện tại — nếu cần tối ưu hơn nữa cho các
    // bài viết cực nhiều review, hướng lâu dài là tách criteria ra bảng riêng
    // (roadmap, không nằm trong đợt cập nhật này vì cần migrate schema).
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
    $rows = $wpdb->get_col(
        $wpdb->prepare(
            "SELECT criteria_scores FROM {$table_name} WHERE post_id = %d AND status = %s",
            $post_id,
            $status
        )
    );
    // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter

    $criteria_aggregate = [];

    foreach ( $rows as $raw ) {
        $scores = maybe_unserialize( $raw );
        if ( ! is_array( $scores ) ) continue;

        foreach ( $scores as $label => $score ) {
            $label = sanitize_text_field( $label );
            if ( ! isset( $criteria_aggregate[ $label ] ) ) {
                $criteria_aggregate[ $label ] = [];
            }
            $criteria_aggregate[ $label ][] = floatval( $score );
        }
    }

    $breakdown = [];
    foreach ( $criteria_aggregate as $label => $values ) {
        $breakdown[ $label ] = round( array_sum( $values ) / count( $values ), 2 );
    }

    $summary = [
        'overall_avg' => round( (float) $overall_avg, 2 ),
        'breakdown'   => $breakdown,
    ];

    wp_cache_set( $cache_key, $summary, 'init_review_system', $ttl );

    return $summary;
}

// Tính tổng số trang review dựa trên số review mỗi trang
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

    // Base SELECT: join với wp_posts để loại review mồ côi.
    $sql    = "SELECT r.* FROM {$table_reviews} r INNER JOIN {$table_posts} p ON p.ID = r.post_id";
    $params = array();

    // WHERE
    $sql      .= " WHERE r.user_id = %d AND r.status = %s";
    $params[]  = $user_id;
    $params[]  = $status;

    // Sắp xếp mới nhất trước
    $sql .= " ORDER BY r.created_at DESC";

    // Phân trang (nếu có)
    if ( $per_page > 0 ) {
        $offset    = ( $paged - 1 ) * $per_page;
        $sql      .= " LIMIT %d OFFSET %d";
        $params[]  = $per_page;
        $params[]  = $offset;
    }

    // Chuẩn bị & thực thi
    // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
    $prepared_sql = $wpdb->prepare( $sql, ...$params );
    $results      = $wpdb->get_results( $prepared_sql, ARRAY_A );
    // phpcs:enable WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter

    if ( empty( $results ) ) {
        return array();
    }

    // Giải mã tiêu chí
    foreach ( $results as &$review ) {
        $review['criteria_scores'] = maybe_unserialize( $review['criteria_scores'] );
    }

    return $results;
}

/**
 * Tính tổng số trang review dựa trên user_id.
 *
 * @param int    $user_id   ID người dùng cần lấy tổng số trang.
 * @param int    $per_page  Số review mỗi trang (>=1).
 * @param string $status    Trạng thái review ('approved', 'pending', ...).
 *
 * @return int Tổng số trang (>=1).
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

    // Đếm review còn tồn tại post (không tính review mồ côi)
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
 * @return array Danh sách label tiêu chí (string[]).
 */
function init_plugin_suite_review_system_get_criteria_by_post_id( $post_id ) {
    $post_id = absint( $post_id );

    // Hook cho phép override criteria theo từng post
    $override = apply_filters( 'init_plugin_suite_review_system_criteria', null, $post_id );
    if ( is_array( $override ) && ! empty( $override ) ) {
        return array_values( array_filter( array_map( 'sanitize_text_field', $override ) ) );
    }

    // Đọc đúng cấu trúc option: criteria_1 → criteria_5
    $options  = get_option( INIT_PLUGIN_SUITE_RS_OPTION, [] );
    $criteria = [];

    for ( $i = 1; $i <= 5; $i++ ) {
        $val = trim( $options[ "criteria_$i" ] ?? '' );
        if ( $val !== '' ) {
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
// CACHE INVALIDATION — gắn vào action after_insert có sẵn
// ============================================================

/**
 * Xóa cache liên quan khi có review mới được thêm vào.
 * Hook vào: init_plugin_suite_review_system_after_insert
 */
add_action(
    'init_plugin_suite_review_system_after_insert',
    function( $review_id, $post_id, $user_id ) {
        init_plugin_suite_review_system_invalidate_review_cache( $post_id, $user_id );
    },
    10,
    3
);
