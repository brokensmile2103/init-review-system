<?php
/**
 * Uninstall Init Review System.
 *
 * Chỉ xoá option cấu hình. Dữ liệu review/reaction (bảng riêng và post meta)
 * được GIỮ LẠI có chủ đích để không mất dữ liệu người dùng khi gỡ plugin.
 *
 * @package InitReviewSystem
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// Xoá option lưu trong wp_options.
delete_option( 'init_plugin_suite_review_system_settings' );
delete_option( 'irs_plugin_db_version' );
delete_transient( 'init_plugin_suite_rs_global_avg' );
