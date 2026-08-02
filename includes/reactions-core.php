<?php
defined('ABSPATH') || exit;

/**
 * === Core reactions helpers (no hooks, no endpoints) ===
 * - Nguồn sự thật (source of truth): bảng {$wpdb->prefix}init_reactions
 *   (1 dòng / user / post, có UNIQUE KEY post_id+user_id).
 * - Số đếm luôn được COUNT trực tiếp từ bảng (có cache, TTL 1h, tự invalidate
 *   khi có thay đổi) — không cộng/trừ tay nên không thể bị lệch dữ liệu.
 * - Post meta `_irs_rx_{type}` chỉ là bản sao đồng bộ để tương thích ngược
 *   (ví dụ site nào đang orderby/meta_query theo các key này), KHÔNG phải
 *   nguồn sự thật, không nên đọc trực tiếp để hiển thị số đếm.
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

/**
 * Đếm reaction trực tiếp từ bảng {$wpdb->prefix}init_reactions (GROUP BY).
 *
 * Đây là truy vấn "sự thật" — không đoán, không cộng dồn thủ công — nên luôn
 * chính xác 100% bất kể có bao nhiêu request chạy song song. Hàm này KHÔNG
 * cache, dùng nội bộ bởi init_plugin_suite_review_system_get_reaction_counts()
 * (có cache) và khi cần đồng bộ lại post meta.
 *
 * @param int $post_id ID bài viết (đã được assert hợp lệ bởi hàm gọi).
 * @return array Map slug => số đếm.
 */
function init_plugin_suite_review_system_query_reaction_counts_from_table( $post_id ) {
    global $wpdb;
    $table = init_plugin_suite_review_system_get_reaction_table();

    $counts = [];
    foreach ( init_plugin_suite_review_system_get_reaction_types() as $key => $_ ) {
        $counts[ $key ] = 0;
    }

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
 * Lấy map đếm reactions (có cache).
 *
 * Trước đây hàm này đọc từ counter lưu ở post meta, được cộng/trừ thủ công
 * trong PHP mỗi lần có người bấm reaction → dễ bị lệch khi nhiều người bấm
 * cùng lúc (race condition), sai sẽ tồn tại vĩnh viễn cho tới khi recount thủ
 * công. Giờ chuyển sang tính trực tiếp từ bảng {$wpdb->prefix}init_reactions
 * (COUNT thật) mỗi khi cache miss — không thể lệch vì không còn cộng dồn tay.
 *
 * @param int $post_id ID bài viết.
 * @return array Map slug => số đếm.
 */
function init_plugin_suite_review_system_get_reaction_counts($post_id) {
    $post_id = init_plugin_suite_review_system_assert_post($post_id);
    if (!$post_id) return [];

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
 * Không còn là nguồn sự thật (bảng init_reactions mới là) — hàm này chỉ giữ
 * lại để đồng bộ post meta `_irs_rx_*` cho tương thích ngược, phòng trường hợp
 * theme/plugin khác đang orderby/meta_query theo các meta key này.
 */
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
    $table = init_plugin_suite_review_system_get_reaction_table();
    $prev  = init_plugin_suite_review_system_get_user_reaction($post_id, $user_id);
    $current = '';

    // ===== Trường hợp 1: gỡ (remove) — bấm lại đúng reaction cũ, hoặc reaction rỗng
    if ($new_rx === '' || $new_rx === $prev) {
        if ($prev !== '') {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->delete($table, ['post_id'=>$post_id, 'user_id'=>$user_id], ['%d','%d']);
        }
    } else {
        // ===== Trường hợp 2: thêm mới hoặc chuyển reaction
        // Bảng có UNIQUE KEY (post_id, user_id) nên INSERT ... ON DUPLICATE KEY
        // UPDATE là một thao tác ATOMIC duy nhất ở tầng DB: vừa thêm mới vừa
        // chuyển đổi đều được xử lý đúng dù nhiều request chạy song song,
        // không còn phụ thuộc vào việc đọc "reaction cũ" rồi tính tay như trước.
        // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $wpdb->query(
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

    // Xoá cache liên quan — counts sẽ được đếm lại (COUNT thật) từ bảng ngay
    // bên dưới, không còn cộng/trừ tay nên không thể lệch dữ liệu.
    wp_cache_delete( "user_reaction_{$post_id}_{$user_id}", 'init_review_system' );
    wp_cache_delete( "reaction_counts_{$post_id}", 'init_review_system' );

    $counts = init_plugin_suite_review_system_query_reaction_counts_from_table( $post_id );
    wp_cache_set( "reaction_counts_{$post_id}", $counts, 'init_review_system', HOUR_IN_SECONDS );

    // Đồng bộ post meta `_irs_rx_*` cho tương thích ngược (xem ghi chú ở
    // set_reaction_counts) — luôn ghi từ counts vừa đếm thật, không phải cộng dồn.
    init_plugin_suite_review_system_set_reaction_counts($post_id, $counts);

    return [
        'success' => true,
        'prev'    => $prev,
        'current' => $current,
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

    $counts = init_plugin_suite_review_system_query_reaction_counts_from_table( $post_id );

    wp_cache_set( "reaction_counts_{$post_id}", $counts, 'init_review_system', HOUR_IN_SECONDS );

    return init_plugin_suite_review_system_set_reaction_counts($post_id, $counts);
}
