<?php
defined( 'ABSPATH' ) || exit;

// Hàm thêm review
function init_plugin_suite_review_system_add_criteria_review($post_id, $user_id, $criteria_scores = [], $review_content = '', $status = 'approved') {
    global $wpdb;
    $table_name = $wpdb->prefix . 'init_criteria_reviews';

    // Tính điểm trung bình
    if (!empty($criteria_scores)) {
        $avg_score = array_sum($criteria_scores) / count($criteria_scores);
    } else {
        $avg_score = 0;
    }

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
    $result = $wpdb->insert(
        $table_name,
        [
            'post_id'         => $post_id,
            'user_id'         => $user_id,
            'criteria_scores' => maybe_serialize($criteria_scores),
            'avg_score'       => $avg_score,
            'review_content'  => $review_content,
            'status'          => $status,
            'created_at'      => current_time('mysql'),
        ],
        [
            '%d', '%d', '%s', '%f', '%s', '%s', '%s',
        ]
    );
    // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

    return $result !== false ? $wpdb->insert_id : false;
}

// Lấy review của bài viết
function init_plugin_suite_review_system_get_reviews_by_post_id( $post_id, $paged = 1, $per_page = 0, $status = 'approved' ) {
    global $wpdb;
    $table_reviews = $wpdb->prefix . 'init_criteria_reviews';
    $table_posts   = $wpdb->posts;

    // Base SELECT (chọn r.* để tránh đụng cột trùng tên nếu join)
    $sql    = "SELECT r.* FROM {$table_reviews} r";
    $params = array();

    // Nếu lấy toàn bộ (post_id = 0) thì join với wp_posts để loại review mồ côi
    if ( (int) $post_id === 0 ) {
        $sql .= " INNER JOIN {$table_posts} p ON p.ID = r.post_id";
    }

    // WHERE
    $sql .= " WHERE r.status = %s";
    $params[] = $status;

    // Lọc theo post_id nếu có
    if ( (int) $post_id > 0 ) {
        $sql .= " AND r.post_id = %d";
        $params[] = (int) $post_id;
    }

    // Sắp xếp
    $sql .= " ORDER BY r.created_at DESC";

    // Phân trang
    if ( $per_page > 0 && $paged > 0 ) {
        $offset   = ( $paged - 1 ) * $per_page;
        $sql     .= " LIMIT %d OFFSET %d";
        $params[] = (int) $per_page;
        $params[] = (int) $offset;
    }

    // Chuẩn bị & thực thi
    // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $prepared_sql = $wpdb->prepare( $sql, ...$params );
    $results      = $wpdb->get_results( $prepared_sql, ARRAY_A );
    // phpcs:enable WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared

    // Giải mã tiêu chí
    foreach ( $results as &$review ) {
        $review['criteria_scores'] = maybe_unserialize( $review['criteria_scores'] );
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

    // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
    $sql = $wpdb->prepare(
        "SELECT * FROM {$table_name} 
         WHERE status = %s AND post_id IN ({$placeholders})
         ORDER BY created_at DESC
         LIMIT %d OFFSET %d",
        array_merge( [ $status ], $post_ids, [ $per_page, $offset ] )
    );
    $results = $wpdb->get_results( $sql, ARRAY_A );
    // phpcs:enable WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber

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

    // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $sql = $wpdb->prepare(
        "SELECT COUNT(*) FROM {$table_name} 
         WHERE status = %s AND post_id IN ({$placeholders})",
        array_merge( [ $status ], $post_ids )
    );
    $result = (int) $wpdb->get_var( $sql );
    // phpcs:enable WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    
    return $result;
}

// Kiểm tra user đã review chưa
function init_plugin_suite_review_system_has_user_reviewed($post_id, $user_id) {
    if (! $post_id || ! $user_id) {
        return false;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'init_criteria_reviews';

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $review_id = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT id FROM {$table_name} WHERE post_id = %d AND user_id = %d AND status = %s LIMIT 1",
            $post_id,
            $user_id,
            'approved'
        )
    );
    // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared

    return ! empty($review_id);
}

// Lấy tổng review của bài viết
function init_plugin_suite_review_system_get_total_reviews_by_post_id( $post_id, $status = 'approved' ) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'init_criteria_reviews';

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $count = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} WHERE post_id = %d AND status = %s",
            $post_id,
            $status
        )
    );
    // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared

    return intval( $count );
}

// Lấy điểm trung bình tổng và từng tiêu chí
function init_plugin_suite_review_system_get_score_summary_by_post_id( $post_id, $status = 'approved' ) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'init_criteria_reviews';

    // Lấy toàn bộ điểm theo post_id
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $results = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT avg_score, criteria_scores FROM {$table_name} WHERE post_id = %d AND status = %s",
            $post_id,
            $status
        ),
        ARRAY_A
    );
    // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared

    if ( empty( $results ) ) {
        return [
            'overall_avg' => 0,
            'breakdown'   => [],
        ];
    }

    $criteria_aggregate = [];
    $overall_sum = 0;
    $overall_count = 0;

    foreach ( $results as $row ) {
        $scores = maybe_unserialize( $row['criteria_scores'] );
        if ( ! is_array( $scores ) ) continue;

        foreach ( $scores as $label => $score ) {
            $label = sanitize_text_field( $label );
            if ( ! isset( $criteria_aggregate[ $label ] ) ) {
                $criteria_aggregate[ $label ] = [];
            }
            $criteria_aggregate[ $label ][] = floatval( $score );
        }

        $overall_sum += floatval( $row['avg_score'] );
        $overall_count++;
    }

    $breakdown = [];
    foreach ( $criteria_aggregate as $label => $values ) {
        $breakdown[ $label ] = round( array_sum( $values ) / count( $values ), 2 );
    }

    return [
        'overall_avg' => $overall_count ? round( $overall_sum / $overall_count, 2 ) : 0,
        'breakdown'   => $breakdown,
    ];
}

// Tính tổng số trang review dựa trên số review mỗi trang
function init_plugin_suite_review_system_get_total_pages( $per, $status = 'approved' ) {
    if ( $per <= 0 ) {
        return 1;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'init_criteria_reviews';

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $total_reviews = (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} WHERE status = %s",
            $status
        )
    );
    // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared

    return (int) ceil( $total_reviews / $per );
}