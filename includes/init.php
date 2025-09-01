<?php
defined( 'ABSPATH' ) || exit;

// Tạo table review nhiều tiêu chí
function init_plugin_suite_review_system_maybe_create_criteria_review_table() {
    global $wpdb;

    $table_name = $wpdb->prefix . 'init_criteria_reviews';

    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id INT NOT NULL AUTO_INCREMENT,
        post_id BIGINT UNSIGNED NOT NULL,
        user_id BIGINT UNSIGNED NOT NULL,
        criteria_scores LONGTEXT NOT NULL,
        avg_score FLOAT NOT NULL,
        review_content LONGTEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        status VARCHAR(20) DEFAULT 'approved',
        PRIMARY KEY  (id),
        KEY post_id (post_id),
        KEY user_id (user_id)
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}

// Hook vào admin page load
add_action('admin_init', 'init_plugin_suite_review_system_maybe_create_criteria_review_table');

/**
 * Tạo table reactions (gọn nhẹ, silent)
 */
add_action('admin_init', function () {
    global $wpdb;

    $table   = $wpdb->prefix . 'init_reactions';
    $charset = $wpdb->get_charset_collate();

    // Nếu table đã tồn tại thì thôi
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $exists = $wpdb->get_var( $wpdb->prepare(
        "SHOW TABLES LIKE %s", $wpdb->esc_like($table)
    ) );
    if ( $exists === $table ) {
        return;
    }

    // Schema chuẩn
    $schema = "CREATE TABLE {$table} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        post_id BIGINT UNSIGNED NOT NULL,
        user_id BIGINT UNSIGNED NOT NULL,
        reaction VARCHAR(32) NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        UNIQUE KEY post_user (post_id, user_id),
        KEY post_reaction (post_id, reaction),
        KEY user_id (user_id)
    ) {$charset};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($schema);
});
