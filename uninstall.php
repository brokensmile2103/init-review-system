<?php
/**
 * Uninstall Init Review System
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// Tên option đang sử dụng
$option_name = 'init_plugin_suite_review_system_settings';

// Xoá option lưu trong wp_options
delete_option( $option_name );