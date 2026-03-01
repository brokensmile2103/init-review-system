<?php
defined( 'ABSPATH' ) || exit;

/**
 * Lấy dữ liệu đánh giá của một bài viết.
 *
 * @param int $post_id
 * @return array {
 *     @type int     $total     Tổng lượt đánh giá
 *     @type float   $average   Điểm trung bình (0 nếu chưa có)
 *     @type float   $total_raw Tổng điểm gộp lại (để debug hoặc thống kê)
 *     @type int     $max       Điểm tối đa
 * }
 */
function init_plugin_suite_review_system_get_rating_data( $post_id ) {
    $post_id = absint( $post_id );
    if ( ! $post_id || ! get_post( $post_id ) ) {
        return [
            'total'     => 0,
            'average'   => 0,
            'total_raw' => 0,
            'max'       => 5,
        ];
    }
    $total_raw = floatval( get_post_meta( $post_id, '_init_review_total', true ) );
    $total     = intval( get_post_meta( $post_id, '_init_review_count', true ) );
    $average   = $total > 0 ? round( $total_raw / $total, 2 ) : 0;
    return [
        'total'     => $total,
        'average'   => min( 5, $average ),
        'total_raw' => $total_raw,
        'max'       => 5,
    ];
}

/**
 * Lấy IP address đã được sanitize từ request
 *
 * @return string|false IP address hoặc false nếu không hợp lệ
 */
function init_plugin_suite_review_system_get_client_ip() {
    $ip_keys = [
        'HTTP_CF_CONNECTING_IP',     // Cloudflare
        'HTTP_X_FORWARDED_FOR',      // Load balancer/proxy
        'HTTP_X_FORWARDED',          // Proxy
        'HTTP_X_CLUSTER_CLIENT_IP',  // Cluster
        'HTTP_CLIENT_IP',            // Proxy
        'HTTP_X_REAL_IP',           // Nginx proxy
        'REMOTE_ADDR'               // Standard
    ];

    foreach ($ip_keys as $key) {
        if (array_key_exists($key, $_SERVER)) {
            $ip = sanitize_text_field( wp_unslash( $_SERVER[$key] ) );
            
            // Handle comma-separated IPs (X-Forwarded-For có thể có nhiều IP)
            if (strpos($ip, ',') !== false) {
                $ip = trim(explode(',', $ip)[0]);
            }
            
            // Validate IP
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
            
            // Fallback: accept private IPs too (for local dev)
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    
    return '127.0.0.1'; // Ultimate fallback
}

// Check IP gần đây
function init_plugin_suite_review_system_is_ip_recent( $post_id, $type = 'default' ) {
    $ip = init_plugin_suite_review_system_get_client_ip();
    
    if ( ! $ip ) {
        return false;
    }
    
    $hash = base_convert( sprintf('%u', crc32($ip) ), 10, 36 );
    $key  = 'irs_recent_ips_' . $post_id . '_' . $type;
    $list = get_transient( $key );
    if ( ! is_array( $list ) ) {
        $list = [];
    }
    if ( in_array( $hash, $list, true ) ) {
        return true;
    }
    array_unshift( $list, $hash );
    if ( count( $list ) > 75 ) {
        array_pop( $list );
    }
    set_transient( $key, $list, WEEK_IN_SECONDS );
    return false;
}

// Render template
function init_plugin_suite_review_system_render_template( $template, $data = [] ) {
    $theme_template  = locate_template("init-review-system/{$template}");
    $plugin_template = INIT_PLUGIN_SUITE_RS_TEMPLATES_PATH . "{$template}";
    if ( $theme_template && file_exists($theme_template) ) {
        $template_path = $theme_template;
    } elseif ( file_exists($plugin_template) ) {
        $template_path = $plugin_template;
    } else {
        return;
    }
    extract($data);
    include $template_path;
}

// Lấy danh sách tiêu chí
function init_plugin_suite_review_system_get_criteria_labels() {
    $options = get_option(INIT_PLUGIN_SUITE_RS_OPTION);
    $labels = [];
    for ($i = 1; $i <= 5; $i++) {
        if (!empty($options["criteria_$i"])) {
            $labels[] = sanitize_text_field($options["criteria_$i"]);
        }
    }
    return $labels;
}

// Helper: tính weighted score
function init_plugin_suite_review_system_calculate_weighted_score( $avg, $count, $global_avg, $min_votes = 50 ) {
    if ( $count <= 0 ) {
        return 0;
    }

    $v = (int) $count;
    $R = (float) $avg;
    $m = (int) $min_votes;
    $C = (float) $global_avg;

    return ( ( $v / ( $v + $m ) ) * $R ) + ( ( $m / ( $v + $m ) ) * $C );
}

// Global average
function init_plugin_suite_review_system_get_global_avg() {
    $transient_key = 'init_plugin_suite_rs_global_avg';

    $cached = get_transient( $transient_key );
    if ( $cached !== false ) {
        return (float) $cached;
    }

    global $wpdb;

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $avg = (float) $wpdb->get_var("
        SELECT AVG(meta_value)
        FROM {$wpdb->postmeta}
        WHERE meta_key = '_init_review_avg'
          AND meta_value > 0
    ");

    set_transient( $transient_key, $avg, HOUR_IN_SECONDS );
    return $avg;
}
