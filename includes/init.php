<?php
/**
 * Cài đặt, nâng cấp và tạo bảng dữ liệu.
 *
 * @package InitReviewSystem
 */

defined( 'ABSPATH' ) || exit;

// ==========================
// Translations.
// ==========================
add_action( 'init', 'init_plugin_suite_review_system_load_textdomain', 0 );

/**
 * Nạp bản dịch đi kèm plugin (thư mục /languages).
 *
 * Trước 2.0.1 plugin không gọi hàm này, nên các file .po/.mo đi kèm (ví dụ
 * tiếng Việt) không bao giờ được dùng cho chuỗi PHP — chỉ có tác dụng nếu
 * WordPress.org có language pack. WordPress vẫn ưu tiên language pack trong
 * wp-content/languages/plugins nếu có, file đi kèm chỉ là fallback.
 *
 * @return void
 */
function init_plugin_suite_review_system_load_textdomain() {
	load_plugin_textdomain( 'init-review-system', false, dirname( plugin_basename( INIT_PLUGIN_SUITE_RS_FILE ) ) . '/languages' ); // phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound -- Cần để nạp bản dịch đi kèm plugin.
}

// ==========================
// Activation hook (đăng ký trong file plugin chính).
// ==========================

/**
 * Chạy khi kích hoạt plugin: tạo bảng cho site hiện tại, hoặc cho mọi site
 * nếu kích hoạt toàn mạng (network activate).
 *
 * @param bool $network_wide Có kích hoạt toàn mạng hay không.
 * @return void
 */
function init_plugin_suite_review_system_on_activation( $network_wide = false ) {
	if ( is_multisite() && $network_wide ) {
		$site_ids = get_sites(
			array(
				'fields' => 'ids',
				'number' => 0,
			)
		);

		foreach ( $site_ids as $site_id ) {
			switch_to_blog( $site_id );
			init_plugin_suite_review_system_install_tables();
			restore_current_blog();
		}
		return;
	}

	init_plugin_suite_review_system_install_tables();
}

/**
 * Tạo/cập nhật cả 2 bảng và ghi lại DB version cho site hiện tại.
 *
 * @return void
 */
function init_plugin_suite_review_system_install_tables() {
	init_plugin_suite_review_system_create_criteria_review_table();
	init_plugin_suite_review_system_create_reactions_table();

	update_option( 'irs_plugin_db_version', INIT_PLUGIN_SUITE_RS_VERSION );
}

// ==========================
// New site (multisite).
// ==========================
// `wpmu_new_blog` đã deprecated từ WP 5.1 — dùng `wp_initialize_site`, chạy
// sau khi WP dựng xong bảng core cho site mới (priority 100), nên để 200.
add_action( 'wp_initialize_site', 'init_plugin_suite_review_system_on_initialize_site', 200 );

/**
 * Tạo bảng cho site mới trong multisite.
 *
 * @param WP_Site $new_site Site vừa được tạo.
 * @return void
 */
function init_plugin_suite_review_system_on_initialize_site( $new_site ) {
	if ( ! $new_site instanceof WP_Site ) {
		return;
	}

	// Chỉ tự tạo bảng khi plugin được kích hoạt toàn mạng. Nếu plugin chỉ bật
	// riêng từng site, activation hook / admin_init của site đó sẽ tự lo.
	if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	if ( ! is_plugin_active_for_network( plugin_basename( INIT_PLUGIN_SUITE_RS_FILE ) ) ) {
		return;
	}

	init_plugin_suite_review_system_on_new_blog( (int) $new_site->blog_id );
}

/**
 * Tạo bảng cho một site theo blog ID.
 *
 * Giữ lại tên hàm cũ để tương thích ngược với code bên ngoài có thể đang gọi
 * trực tiếp; các tham số còn lại (chữ ký của hook `wpmu_new_blog` cũ) không
 * còn được dùng.
 *
 * @param int $blog_id ID của site.
 * @return void
 */
function init_plugin_suite_review_system_on_new_blog( $blog_id ) {
	switch_to_blog( absint( $blog_id ) );
	init_plugin_suite_review_system_install_tables();
	restore_current_blog();
}

// ==========================
// admin_init — chỉ chạy khi version thay đổi.
// ==========================
add_action( 'admin_init', 'init_plugin_suite_review_system_maybe_check_tables' );

/**
 * Chạy lại dbDelta() khi DB version lưu trong option cũ hơn plugin.
 *
 * DbDelta() an toàn khi chạy lại nhiều lần: nó chỉ ADD COLUMN/ADD KEY còn
 * thiếu, không đụng tới dữ liệu sẵn có — nên các thay đổi schema ở bản mới
 * được áp dụng cho cả những site đã cài plugin từ trước.
 *
 * @return void
 */
function init_plugin_suite_review_system_maybe_check_tables() {
	$db_version = get_option( 'irs_plugin_db_version', '0.0.0' );

	if ( version_compare( $db_version, INIT_PLUGIN_SUITE_RS_VERSION, '>=' ) ) {
		return; // Đã đúng version, không làm gì cả.
	}

	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	init_plugin_suite_review_system_install_tables();
}

// ==========================
// upgrader_process_complete — chạy sau khi update plugin.
// ==========================
add_action( 'upgrader_process_complete', 'init_plugin_suite_review_system_on_update', 10, 2 );

/**
 * Reset cờ DB version sau khi plugin được cập nhật để admin_init kiểm tra lại.
 *
 * @param WP_Upgrader $upgrader_object Upgrader instance (không dùng).
 * @param array       $options         Thông tin về lần cập nhật.
 * @return void
 */
function init_plugin_suite_review_system_on_update( $upgrader_object, $options ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundBeforeLastUsed
	if (
		isset( $options['action'], $options['type'], $options['plugins'] ) &&
		'update' === $options['action'] &&
		'plugin' === $options['type'] &&
		is_array( $options['plugins'] )
	) {
		foreach ( $options['plugins'] as $plugin_path ) {
			if ( false !== strpos( $plugin_path, INIT_PLUGIN_SUITE_RS_SLUG ) ) {
				// Reset version flag để admin_init chạy lại check.
				delete_option( 'irs_plugin_db_version' );
				break;
			}
		}
	}
}

// ==========================
// Tạo bảng criteria reviews.
// ==========================

/**
 * Tạo/cập nhật bảng {$prefix}init_criteria_reviews.
 *
 * @return void
 */
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
        KEY user_id (user_id),
        KEY post_status (post_id, status),
        KEY user_status (user_id, status)
    ) $charset_collate;";

	dbDelta( $sql );
}

// ==========================
// Tạo bảng reactions.
// ==========================

/**
 * Tạo/cập nhật bảng {$prefix}init_reactions.
 *
 * @return void
 */
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
