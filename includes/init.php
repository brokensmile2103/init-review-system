<?php
defined( 'ABSPATH' ) || exit;

// ==========================
// Activation hook
// ==========================
register_activation_hook( __FILE__, 'init_plugin_suite_review_system_on_activation' );

function init_plugin_suite_review_system_on_activation() {
    if ( is_multisite() ) {
        $sites = get_sites( [ 'number' => 0 ] );
        foreach ( $sites as $site ) {
            switch_to_blog( $site->blog_id );
            init_plugin_suite_review_system_create_criteria_review_table();
            init_plugin_suite_review_system_create_reactions_table();
            restore_current_blog();
        }
    } else {
        init_plugin_suite_review_system_create_criteria_review_table();
        init_plugin_suite_review_system_create_reactions_table();
    }

    update_option( 'irs_plugin_db_version', INIT_PLUGIN_SUITE_RS_VERSION );
}

// ==========================
// New blog (multisite)
// ==========================
add_action( 'wpmu_new_blog', 'init_plugin_suite_review_system_on_new_blog', 10, 6 );

function init_plugin_suite_review_system_on_new_blog( $blog_id, $user_id, $domain, $path, $site_id, $meta ) {
    switch_to_blog( $blog_id );
    init_plugin_suite_review_system_create_criteria_review_table();
    init_plugin_suite_review_system_create_reactions_table();
    restore_current_blog();
}

// ==========================
// admin_init — chỉ chạy khi version thay đổi
// ==========================
add_action( 'admin_init', 'init_plugin_suite_review_system_maybe_check_tables' );

function init_plugin_suite_review_system_maybe_check_tables() {
    $db_version = get_option( 'irs_plugin_db_version', '0.0.0' );

    if ( version_compare( $db_version, INIT_PLUGIN_SUITE_RS_VERSION, '>=' ) ) {
        return; // Đã đúng version, không làm gì cả
    }

    if ( ! current_user_can( 'administrator' ) ) {
        return;
    }

    global $wpdb;

    $criteria_table = $wpdb->prefix . 'init_criteria_reviews';
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
    if ( $wpdb->get_var( "SHOW TABLES LIKE '$criteria_table'" ) !== $criteria_table ) {
        init_plugin_suite_review_system_create_criteria_review_table();
    }

    $reactions_table = $wpdb->prefix . 'init_reactions';
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
    if ( $wpdb->get_var( "SHOW TABLES LIKE '$reactions_table'" ) !== $reactions_table ) {
        init_plugin_suite_review_system_create_reactions_table();
    }

    update_option( 'irs_plugin_db_version', INIT_PLUGIN_SUITE_RS_VERSION );
}

// ==========================
// upgrader_process_complete — chạy sau khi update plugin
// ==========================
add_action( 'upgrader_process_complete', 'init_plugin_suite_review_system_on_update', 10, 2 );

function init_plugin_suite_review_system_on_update( $upgrader_object, $options ) {
    if (
        isset( $options['action'], $options['type'] ) &&
        $options['action'] === 'update' &&
        $options['type'] === 'plugin' &&
        isset( $options['plugins'] ) && is_array( $options['plugins'] )
    ) {
        foreach ( $options['plugins'] as $plugin_path ) {
            if ( strpos( $plugin_path, INIT_PLUGIN_SUITE_RS_SLUG ) !== false ) {
                // Reset version flag để admin_init chạy lại check
                delete_option( 'irs_plugin_db_version' );
                break;
            }
        }
    }
}

// ==========================
// Tạo bảng criteria reviews
// ==========================
function init_plugin_suite_review_system_create_criteria_review_table() {
    global $wpdb;
    $table_name      = $wpdb->prefix . 'init_criteria_reviews';
    $charset_collate = $wpdb->get_charset_collate();

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $sql = "CREATE TABLE $table_name (
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

    dbDelta( $sql );
}

// ==========================
// Tạo bảng reactions
// ==========================
function init_plugin_suite_review_system_create_reactions_table() {
    global $wpdb;
    $table_name      = $wpdb->prefix . 'init_reactions';
    $charset_collate = $wpdb->get_charset_collate();

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $sql = "CREATE TABLE $table_name (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        post_id BIGINT UNSIGNED NOT NULL,
        user_id BIGINT UNSIGNED NOT NULL,
        reaction VARCHAR(32) NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        UNIQUE KEY post_user (post_id, user_id),
        KEY post_reaction (post_id, reaction),
        KEY user_id (user_id)
    ) $charset_collate;";

    dbDelta( $sql );
}
