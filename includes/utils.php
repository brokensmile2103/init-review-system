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

/**
 * Cộng dồn (increment) một giá trị số vào post meta MỘT CÁCH ATOMIC.
 *
 * Vấn đề với cách làm cũ (get_post_meta rồi cộng trong PHP rồi update_post_meta):
 * nếu 2 request xảy ra gần như đồng thời (ví dụ 2 lượt vote cùng lúc trên 1 bài
 * viết đông traffic), cả hai đều đọc cùng giá trị cũ, cộng riêng rồi ghi đè lên
 * nhau → một lượt vote bị "biến mất" khỏi tổng (lost update). Đây là lỗi thật,
 * không phải lý thuyết, và không tự sửa được vì dữ liệu đã sai ngay từ đầu.
 *
 * Hàm này dùng UPDATE trực tiếp dạng `meta_value = meta_value + x`: MySQL khoá
 * đúng dòng đó trong lúc cộng, nên nhiều request chạy song song vẫn cộng dồn
 * đúng và tuần tự, không mất lượt nào.
 *
 * @param int    $post_id  ID bài viết.
 * @param string $meta_key Meta key cần cộng dồn.
 * @param float  $increment Giá trị cần cộng (có thể âm để trừ).
 * @return float Giá trị mới nhất sau khi cộng.
 */
function init_plugin_suite_review_system_atomic_increment_meta( $post_id, $meta_key, $increment ) {
    global $wpdb;

    $post_id   = absint( $post_id );
    $meta_key  = sanitize_key( $meta_key );
    $increment = (float) $increment;

    // Đảm bảo đã có dòng meta để UPDATE atomic phía dưới có thể chạm vào.
    // add_post_meta() với $unique = true chỉ ghi nếu chưa tồn tại, nên gọi
    // nhiều lần vẫn an toàn; chỉ thực sự tạo dòng đúng 1 lần cho mỗi post.
    if ( '' === get_post_meta( $post_id, $meta_key, true ) ) {
        add_post_meta( $post_id, $meta_key, 0, true );
    }

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
    $wpdb->query(
        $wpdb->prepare(
            "UPDATE {$wpdb->postmeta} SET meta_value = meta_value + %f WHERE post_id = %d AND meta_key = %s",
            $increment,
            $post_id,
            $meta_key
        )
    );
    // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter

    // Vừa sửa DB bằng SQL trực tiếp nên object cache của WP cho post meta
    // không tự biết — phải xoá để lần get_post_meta() kế tiếp lấy giá trị mới.
    wp_cache_delete( $post_id, 'post_meta' );

    return (float) get_post_meta( $post_id, $meta_key, true );
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
