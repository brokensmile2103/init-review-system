<?php
/**
 * Plugin Name: Init Review System
 * Plugin URI: https://inithtml.com/plugin/init-review-system/
 * Description: Multi-criteria review system with admin dashboard, bulk management tools, REST API endpoints, Block Editor blocks, Abilities API support, and rich schema support for WordPress sites.
 * Version: 2.0.1
 * Author: Init HTML
 * Author URI: https://inithtml.com/
 * Text Domain: init-review-system
 * Domain Path: /languages
 * Requires at least: 6.9
 * Tested up to: 7.1
 * Requires PHP: 7.4
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package InitReviewSystem
 */

defined( 'ABSPATH' ) || exit;

define( 'INIT_PLUGIN_SUITE_RS_VERSION', '2.0.1' );
define( 'INIT_PLUGIN_SUITE_RS_SLUG', 'init-review-system' );
define( 'INIT_PLUGIN_SUITE_RS_OPTION', 'init_plugin_suite_review_system_settings' );
define( 'INIT_PLUGIN_SUITE_RS_NAMESPACE', 'initrsys/v1' );
define( 'INIT_PLUGIN_SUITE_RS_FILE', __FILE__ );
define( 'INIT_PLUGIN_SUITE_RS_URL', plugin_dir_url( __FILE__ ) );
define( 'INIT_PLUGIN_SUITE_RS_PATH', plugin_dir_path( __FILE__ ) );
define( 'INIT_PLUGIN_SUITE_RS_ASSETS_URL', INIT_PLUGIN_SUITE_RS_URL . 'assets/' );
define( 'INIT_PLUGIN_SUITE_RS_ASSETS_PATH', INIT_PLUGIN_SUITE_RS_PATH . 'assets/' );
define( 'INIT_PLUGIN_SUITE_RS_LANGUAGES_PATH', INIT_PLUGIN_SUITE_RS_PATH . 'languages/' );
define( 'INIT_PLUGIN_SUITE_RS_INCLUDES_PATH', INIT_PLUGIN_SUITE_RS_PATH . 'includes/' );
define( 'INIT_PLUGIN_SUITE_RS_TEMPLATES_PATH', INIT_PLUGIN_SUITE_RS_PATH . 'templates/' );

require_once INIT_PLUGIN_SUITE_RS_INCLUDES_PATH . 'init.php';
require_once INIT_PLUGIN_SUITE_RS_INCLUDES_PATH . 'criteria-review.php';
require_once INIT_PLUGIN_SUITE_RS_INCLUDES_PATH . 'reactions-core.php';
require_once INIT_PLUGIN_SUITE_RS_INCLUDES_PATH . 'utils.php';
require_once INIT_PLUGIN_SUITE_RS_INCLUDES_PATH . 'rest-api.php';
require_once INIT_PLUGIN_SUITE_RS_INCLUDES_PATH . 'shortcodes.php';
require_once INIT_PLUGIN_SUITE_RS_INCLUDES_PATH . 'review-management.php';
require_once INIT_PLUGIN_SUITE_RS_INCLUDES_PATH . 'reset-metabox.php';
require_once INIT_PLUGIN_SUITE_RS_INCLUDES_PATH . 'settings-page.php';
require_once INIT_PLUGIN_SUITE_RS_INCLUDES_PATH . 'hooks.php';
require_once INIT_PLUGIN_SUITE_RS_INCLUDES_PATH . 'blocks.php';
require_once INIT_PLUGIN_SUITE_RS_INCLUDES_PATH . 'abilities-api.php';

// Activation hook phải được đăng ký với đúng đường dẫn file plugin CHÍNH.
// Trước 2.0.1 hook này nằm trong includes/init.php với __FILE__ trỏ vào file
// con, nên WordPress không bao giờ gọi tới — bảng chỉ được tạo khi admin mở
// wp-admin lần đầu (qua admin_init), khiến request front-end trước đó lỗi DB.
register_activation_hook( __FILE__, 'init_plugin_suite_review_system_on_activation' );

add_action( 'wp_enqueue_scripts', 'init_plugin_suite_review_system_enqueue_front_style' );

/**
 * Enqueue CSS front-end chính.
 *
 * Mặc định vẫn nạp trên mọi trang như trước (vì [init_review_score] thường
 * được gọi từ template của theme, sau wp_head). Site nào tự kiểm soát việc nạp
 * CSS có thể tắt bằng filter `init_plugin_suite_review_system_enqueue_style`.
 *
 * @return void
 */
function init_plugin_suite_review_system_enqueue_front_style() {
	/**
	 * Filter: cho phép tắt việc tự động nạp style.css trên front-end.
	 *
	 * @since 2.0.1
	 *
	 * @param bool $enqueue Mặc định true.
	 */
	if ( ! apply_filters( 'init_plugin_suite_review_system_enqueue_style', true ) ) {
		return;
	}

	wp_enqueue_style(
		'init-review-system-style',
		INIT_PLUGIN_SUITE_RS_ASSETS_URL . 'css/style.css',
		array(),
		INIT_PLUGIN_SUITE_RS_VERSION
	);
}

// ==========================
// Settings link.
// ==========================

add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'init_plugin_suite_review_system_add_settings_link' );

/**
 * Add a "Settings" link to the plugin row in the Plugins admin screen.
 *
 * @param array $links Existing action links.
 * @return array
 */
function init_plugin_suite_review_system_add_settings_link( $links ) {
	$settings_link = '<a href="' . esc_url( admin_url( 'admin.php?page=' . INIT_PLUGIN_SUITE_RS_SLUG ) ) . '">' . esc_html__( 'Settings', 'init-review-system' ) . '</a>';
	array_unshift( $links, $settings_link );
	return $links;
}
