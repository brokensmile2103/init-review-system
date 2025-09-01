<?php
/**
 * Plugin Name: Init Review System
 * Plugin URI: https://inithtml.com/plugin/init-review-system/
 * Description: Multi-criteria review system with admin dashboard, bulk management tools, REST API endpoints, and rich schema support for WordPress sites.
 * Version: 1.4
 * Author: Init HTML
 * Author URI: https://inithtml.com/
 * Text Domain: init-review-system
 * Domain Path: /languages
 * Requires at least: 5.5
 * Tested up to: 6.8
 * Requires PHP: 7.4
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

defined( 'ABSPATH' ) || exit;

define( 'INIT_PLUGIN_SUITE_RS_VERSION',        '1.4' );
define( 'INIT_PLUGIN_SUITE_RS_SLUG',           'init-review-system' );
define( 'INIT_PLUGIN_SUITE_RS_OPTION',         'init_plugin_suite_review_system_settings' );
define( 'INIT_PLUGIN_SUITE_RS_NAMESPACE',      'initrsys/v1' );
define( 'INIT_PLUGIN_SUITE_RS_URL',            plugin_dir_url( __FILE__ ) );
define( 'INIT_PLUGIN_SUITE_RS_PATH',           plugin_dir_path( __FILE__ ) );
define( 'INIT_PLUGIN_SUITE_RS_ASSETS_URL',     INIT_PLUGIN_SUITE_RS_URL  . 'assets/' );
define( 'INIT_PLUGIN_SUITE_RS_ASSETS_PATH',    INIT_PLUGIN_SUITE_RS_PATH . 'assets/' );
define( 'INIT_PLUGIN_SUITE_RS_LANGUAGES_PATH', INIT_PLUGIN_SUITE_RS_PATH . 'languages/' );
define( 'INIT_PLUGIN_SUITE_RS_INCLUDES_PATH',  INIT_PLUGIN_SUITE_RS_PATH . 'includes/' );
define( 'INIT_PLUGIN_SUITE_RS_TEMPLATES_PATH', INIT_PLUGIN_SUITE_RS_PATH . 'templates/' );

require_once INIT_PLUGIN_SUITE_RS_INCLUDES_PATH . 'init.php';
require_once INIT_PLUGIN_SUITE_RS_INCLUDES_PATH . 'criteria-review.php';
require_once INIT_PLUGIN_SUITE_RS_INCLUDES_PATH . 'reactions-core.php';
require_once INIT_PLUGIN_SUITE_RS_INCLUDES_PATH . 'utils.php';
require_once INIT_PLUGIN_SUITE_RS_INCLUDES_PATH . 'rest-api.php';
require_once INIT_PLUGIN_SUITE_RS_INCLUDES_PATH . 'shortcodes.php';
require_once INIT_PLUGIN_SUITE_RS_INCLUDES_PATH . 'review-management.php';
require_once INIT_PLUGIN_SUITE_RS_INCLUDES_PATH . 'settings-page.php';
require_once INIT_PLUGIN_SUITE_RS_INCLUDES_PATH . 'hooks.php';

add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style(
        'init-review-system-style',
        INIT_PLUGIN_SUITE_RS_ASSETS_URL . 'css/style.css',
        [],
        INIT_PLUGIN_SUITE_RS_VERSION
    );
});

// ==========================
// Settings link
// ==========================

add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'init_plugin_suite_review_system_add_settings_link');
// Add a "Settings" link to the plugin row in the Plugins admin screen
function init_plugin_suite_review_system_add_settings_link($links) {
    $settings_link = '<a href="' . admin_url('admin.php?page=' . INIT_PLUGIN_SUITE_RS_SLUG) . '">' . __('Settings', 'init-review-system') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
}
