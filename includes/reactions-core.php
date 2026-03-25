<?php
defined('ABSPATH') || exit;

/**
 * === Core reactions helpers (no hooks, no endpoints) ===
 * - Lưu đếm theo post meta: _irs_rx_{type}
 * - Nhật ký user↔post trong table: {$wpdb->prefix}init_reactions
 * - Chỉ chứa HÀM, không tự gắn hook/shortcode.
 */

/**
 * Lấy danh sách reaction types (label + emoji).
 *
 * @return array [
 *     'slug' => [ 'Label', 'Emoji' ],
 * ]
 */
function init_plugin_suite_review_system_get_reaction_types() {
    $types = [
        'upvote'    => [ __('Upvote', 'init-review-system'),    '👍' ],
        'funny'     => [ __('Funny', 'init-review-system'),     '😄' ],
        'love'      => [ __('Love', 'init-review-system'),      '😍' ],
        'surprised' => [ __('Surprised', 'init-review-system'), '😯' ],
        'angry'     => [ __('Angry', 'init-review-system'),     '😠' ],
        'sad'       => [ __('Sad', 'init-review-system'),       '😢' ],
    ];

    /**
     * Filter: init_plugin_suite_review_system_get_reaction_types
     *
     * Cho phép thêm, xoá, hoặc sửa các loại reaction.
     *
     * @param array $types Mảng reaction mặc định.
     */
    return apply_filters('init_plugin_suite_review_system_get_reaction_types', $types);
}

/** Lấy tên bảng reactions an toàn */
function init_plugin_suite_review_system_get_reaction_table() {
    global $wpdb;
    return $wpdb->prefix . 'init_reactions';
}

/** Kiểm tra post_id hợp lệ */
function init_plugin_suite_review_system_assert_post($post_id) {
    $post_id = absint($post_id);
    if (!$post_id || !get_post($post_id)) {
        return 0;
    }
    return $post_id;
}

/** Chuẩn hóa & kiểm tra reaction hợp lệ; trả về key hợp lệ hoặc '' */
function init_plugin_suite_review_system_validate_reaction($reaction) {
    $rx = sanitize_key($reaction);
    $types = init_plugin_suite_review_system_get_reaction_types();
    return isset($types[$rx]) ? $rx : '';
}

/** Key meta đếm reaction */
function init_plugin_suite_review_system_reaction_meta_key($rx_key) {
    return apply_filters('init_plugin_suite_review_system_reaction_meta_key', '_irs_rx_' . sanitize_key($rx_key), $rx_key);
}

/** Lấy map đếm reactions từ post meta (thiếu loại nào thì trả 0) */
function init_plugin_suite_review_system_get_reaction_counts($post_id) {
    $post_id = init_plugin_suite_review_system_assert_post($post_id);
    if (!$post_id) return [];

    $counts = [];
    foreach (init_plugin_suite_review_system_get_reaction_types() as $key => $_) {
        $counts[$key] = (int) get_post_meta($post_id, init_plugin_suite_review_system_reaction_meta_key($key), true);
    }
    return $counts;
}

/** Ghi lại toàn bộ counts (âm → 0) */
function init_plugin_suite_review_system_set_reaction_counts($post_id, array $counts) {
    $post_id = init_plugin_suite_review_system_assert_post($post_id);
    if (!$post_id) return false;

    foreach (init_plugin_suite_review_system_get_reaction_types() as $key => $_) {
        $val = isset($counts[$key]) ? max(0, (int)$counts[$key]) : 0;
        update_post_meta($post_id, init_plugin_suite_review_system_reaction_meta_key($key), $val);
    }
    return true;
}

/** Lấy reaction hiện tại của 1 user trên 1 post ('' nếu chưa có) */
function init_plugin_suite_review_system_get_user_reaction( $post_id, $user_id ) {
    $post_id = init_plugin_suite_review_system_assert_post( $post_id );
    $user_id = absint( $user_id );
    if ( ! $post_id || ! $user_id ) return '';

    // --- Cache ---
    $ttl       = HOUR_IN_SECONDS;
    $cache_key = "user_reaction_{$post_id}_{$user_id}";
    $cached    = wp_cache_get( $cache_key, 'init_review_system' );
    if ( false !== $cached ) {
        // Lưu dạng string: reaction slug hoặc '__none__' nếu chưa có
        return $cached === '__none__' ? '' : (string) $cached;
    }

    global $wpdb;
    $table = init_plugin_suite_review_system_get_reaction_table();
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
    $rx = $wpdb->get_var( $wpdb->prepare(
        "SELECT reaction FROM {$table} WHERE post_id = %d AND user_id = %d LIMIT 1",
        $post_id, $user_id
    ) );
    // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter

    $result = $rx ? sanitize_key( $rx ) : '';

    // Lưu '__none__' thay vì '' để phân biệt với false (cache miss)
    wp_cache_set( $cache_key, $result !== '' ? $result : '__none__', 'init_review_system', $ttl );

    return $result;
}

/**
 * Áp dụng reaction cho user:
 * - Nếu $reaction === '' hoặc trùng với reaction cũ → gỡ (remove)
 * - Nếu khác → chuyển (switch)
 * Trả về array:
 * [
 *   'success' => bool,
 *   'prev'    => (string) reaction cũ,
 *   'current' => (string) reaction mới sau khi áp dụng ('' nếu gỡ),
 *   'counts'  => (array) map số đếm mới
 * ]
 */
function init_plugin_suite_review_system_apply_user_reaction($post_id, $user_id, $reaction) {
    $post_id  = init_plugin_suite_review_system_assert_post($post_id);
    $user_id  = absint($user_id);
    $new_rx   = init_plugin_suite_review_system_validate_reaction($reaction); // có thể ''

    if (!$post_id || !$user_id) {
        return ['success'=>false, 'prev'=>'', 'current'=>'', 'counts'=>[]];
    }

    global $wpdb;
    $table  = init_plugin_suite_review_system_get_reaction_table();
    $counts = init_plugin_suite_review_system_get_reaction_counts($post_id);
    $prev   = init_plugin_suite_review_system_get_user_reaction($post_id, $user_id);

    // ===== Trường hợp 1: gỡ (remove)
    if ($new_rx === '' || $new_rx === $prev) {
        if ($prev !== '') {
            // xóa row + giảm đếm loại cũ
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->delete($table, ['post_id'=>$post_id, 'user_id'=>$user_id], ['%d','%d']);
            if (isset($counts[$prev])) $counts[$prev] = max(0, (int)$counts[$prev] - 1);
            init_plugin_suite_review_system_set_reaction_counts($post_id, $counts);
        }

        // Xóa cache user reaction
        wp_cache_delete( "user_reaction_{$post_id}_{$user_id}", 'init_review_system' );

        return [
            'success' => true,
            'prev'    => $prev,
            'current' => '',
            'counts'  => $counts,
        ];
    }

    // ===== Trường hợp 2: chuyển (switch) hoặc thêm mới
    if ($prev === '') {
        // thêm mới
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->insert(
            $table,
            ['post_id'=>$post_id, 'user_id'=>$user_id, 'reaction'=>$new_rx, 'created_at'=>current_time('mysql')],
            ['%d','%d','%s','%s']
        );
        if (isset($counts[$new_rx])) $counts[$new_rx] = (int)$counts[$new_rx] + 1;
    } else {
        // update reaction
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->update(
            $table,
            ['reaction'=>$new_rx],
            ['post_id'=>$post_id, 'user_id'=>$user_id],
            ['%s'],
            ['%d','%d']
        );
        if (isset($counts[$prev]))   $counts[$prev]   = max(0, (int)$counts[$prev] - 1);
        if (isset($counts[$new_rx])) $counts[$new_rx] = (int)$counts[$new_rx] + 1;
    }

    init_plugin_suite_review_system_set_reaction_counts($post_id, $counts);

    // Xóa cache user reaction
    wp_cache_delete( "user_reaction_{$post_id}_{$user_id}", 'init_review_system' );

    return [
        'success' => true,
        'prev'    => $prev,
        'current' => $new_rx,
        'counts'  => $counts,
    ];
}

/**
 * Recount: dựng lại số đếm từ bảng (dùng khi cần sửa chữa dữ liệu)
 * - Đọc toàn bộ reaction của post và gom nhóm
 * - Ghi lại vào post meta
 */
function init_plugin_suite_review_system_recount_reactions($post_id) {
    $post_id = init_plugin_suite_review_system_assert_post($post_id);
    if (!$post_id) return false;

    global $wpdb;
    $table = init_plugin_suite_review_system_get_reaction_table();
    $types = init_plugin_suite_review_system_get_reaction_types();
    $counts = [];
    foreach ($types as $k => $_) $counts[$k] = 0;

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT reaction, COUNT(*) c FROM {$table} WHERE post_id = %d GROUP BY reaction",
        $post_id
    ), ARRAY_A);
    // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter

    if (is_array($rows)) {
        foreach ($rows as $row) {
            $k = sanitize_key($row['reaction']);
            if (isset($counts[$k])) {
                $counts[$k] = (int)$row['c'];
            }
        }
    }

    return init_plugin_suite_review_system_set_reaction_counts($post_id, $counts);
}
