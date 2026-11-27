<?php
/**
 * Review meta — dữ liệu phụ gắn theo từng review (giống post meta / comment meta).
 *
 * Dùng Metadata API của WordPress với meta type "init_review": bảng
 * {$prefix}init_review_meta (meta_id, init_review_id, meta_key, meta_value),
 * cache object theo group "init_review_meta", hook added/updated/deleted_init_review_meta.
 * Dành cho mọi theme / plugin muốn lưu thêm thông tin cho review.
 *
 * Bảng được tạo cùng các bảng khác (activation / admin_init khi DB version cũ).
 * Trước khi bảng có, hàm đọc trả về rỗng; hàm ghi tự tạo bảng.
 *
 * @since 2.0.3
 * @package InitReviewSystem
 */

defined( 'ABSPATH' ) || exit;

/**
 * Tên meta type dùng với add_metadata() / get_metadata()...
 */
const INIT_PLUGIN_SUITE_RS_META_TYPE = 'init_review';

/**
 * Khai báo bảng meta cho $wpdb (Metadata API tìm bảng qua $wpdb->init_reviewmeta).
 * Gọi lại khi switch_blog để multisite trỏ đúng prefix của site.
 *
 * @return void
 */
function init_plugin_suite_review_system_register_meta_table() {
	global $wpdb;
	$wpdb->init_reviewmeta = $wpdb->prefix . 'init_review_meta';
}
init_plugin_suite_review_system_register_meta_table();
add_action( 'switch_blog', 'init_plugin_suite_review_system_register_meta_table' );

/**
 * Tạo/cập nhật bảng {$prefix}init_review_meta (cột *_id theo quy ước Metadata API).
 *
 * @return void
 */
function init_plugin_suite_review_system_create_review_meta_table() {
	global $wpdb;
	$table_name      = $wpdb->prefix . 'init_review_meta';
	$charset_collate = $wpdb->get_charset_collate();
	$max_index       = 191; // Giới hạn index utf8mb4, giống bảng meta của core.

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$sql = "CREATE TABLE $table_name (
        meta_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        init_review_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
        meta_key VARCHAR(255) DEFAULT NULL,
        meta_value LONGTEXT,
        PRIMARY KEY  (meta_id),
        KEY init_review_id (init_review_id),
        KEY meta_key (meta_key($max_index))
    ) $charset_collate;";

	dbDelta( $sql );
}

/**
 * Bảng meta đã sẵn sàng chưa (theo DB version, không query).
 *
 * @return bool
 */
function init_plugin_suite_review_system_review_meta_ready() {
	return version_compare( (string) get_option( 'irs_plugin_db_version', '0.0.0' ), '2.0.3', '>=' );
}

/**
 * Đảm bảo bảng tồn tại trước khi ghi (site cập nhật plugin nhưng admin chưa vào wp-admin).
 *
 * @return bool
 */
function init_plugin_suite_review_system_ensure_review_meta_table() {
	if ( init_plugin_suite_review_system_review_meta_ready() ) {
		return true;
	}
	init_plugin_suite_review_system_install_tables();
	return init_plugin_suite_review_system_review_meta_ready();
}

/**
 * Đánh dấu site đã có review meta → mới cần prime cache khi hiển thị danh sách.
 *
 * @return void
 */
function init_plugin_suite_review_system_mark_review_meta_used() {
	if ( '1' !== get_option( 'irs_review_meta_used' ) ) {
		update_option( 'irs_review_meta_used', '1', true );
	}
}

// ============================================================
// API công khai
// ============================================================

/**
 * Thêm meta cho review.
 *
 * @param int    $review_id  ID review.
 * @param string $meta_key   Khoá meta.
 * @param mixed  $meta_value Giá trị (tự serialize).
 * @param bool   $unique     Không thêm nếu khoá đã có.
 * @return int|false Meta ID hoặc false.
 */
function init_plugin_suite_review_system_add_review_meta( $review_id, $meta_key, $meta_value, $unique = false ) {
	if ( absint( $review_id ) <= 0 || ! init_plugin_suite_review_system_ensure_review_meta_table() ) {
		return false;
	}
	init_plugin_suite_review_system_mark_review_meta_used();
	return add_metadata( INIT_PLUGIN_SUITE_RS_META_TYPE, absint( $review_id ), $meta_key, $meta_value, $unique );
}

/**
 * Cập nhật (hoặc thêm) meta cho review.
 *
 * @param int    $review_id  ID review.
 * @param string $meta_key   Khoá meta.
 * @param mixed  $meta_value Giá trị mới.
 * @param mixed  $prev_value Chỉ cập nhật dòng có giá trị này (tuỳ chọn).
 * @return int|bool Meta ID nếu thêm mới, true nếu cập nhật, false nếu lỗi / không đổi.
 */
function init_plugin_suite_review_system_update_review_meta( $review_id, $meta_key, $meta_value, $prev_value = '' ) {
	if ( absint( $review_id ) <= 0 || ! init_plugin_suite_review_system_ensure_review_meta_table() ) {
		return false;
	}
	init_plugin_suite_review_system_mark_review_meta_used();
	return update_metadata( INIT_PLUGIN_SUITE_RS_META_TYPE, absint( $review_id ), $meta_key, $meta_value, $prev_value );
}

/**
 * Đọc meta của review.
 *
 * @param int    $review_id ID review.
 * @param string $meta_key  Khoá meta ('' = tất cả).
 * @param bool   $single    Trả về 1 giá trị thay vì mảng.
 * @return mixed '' / array() nếu không có.
 */
function init_plugin_suite_review_system_get_review_meta( $review_id, $meta_key = '', $single = false ) {
	if ( absint( $review_id ) <= 0 || ! init_plugin_suite_review_system_review_meta_ready() ) {
		return $single ? '' : array();
	}
	return get_metadata( INIT_PLUGIN_SUITE_RS_META_TYPE, absint( $review_id ), $meta_key, $single );
}

/**
 * Xoá meta của review.
 *
 * @param int    $review_id  ID review.
 * @param string $meta_key   Khoá meta.
 * @param mixed  $meta_value Chỉ xoá dòng có giá trị này (tuỳ chọn).
 * @return bool
 */
function init_plugin_suite_review_system_delete_review_meta( $review_id, $meta_key, $meta_value = '' ) {
	if ( absint( $review_id ) <= 0 || ! init_plugin_suite_review_system_review_meta_ready() ) {
		return false;
	}
	return delete_metadata( INIT_PLUGIN_SUITE_RS_META_TYPE, absint( $review_id ), $meta_key, $meta_value );
}

/**
 * Nạp sẵn meta của nhiều review vào cache bằng 1 query (tránh N+1 khi hiển thị danh sách).
 * Không query gì nếu site chưa từng lưu review meta.
 *
 * @param array $reviews Danh sách review (mảng có 'id') hoặc danh sách ID.
 * @return void
 */
function init_plugin_suite_review_system_prime_review_meta( $reviews ) {
	if ( empty( $reviews ) || '1' !== get_option( 'irs_review_meta_used' ) || ! init_plugin_suite_review_system_review_meta_ready() ) {
		return;
	}
	$ids = array();
	foreach ( (array) $reviews as $review ) {
		$id = is_array( $review ) ? absint( $review['id'] ?? 0 ) : absint( $review );
		if ( $id ) {
			$ids[] = $id;
		}
	}
	if ( $ids ) {
		update_meta_cache( INIT_PLUGIN_SUITE_RS_META_TYPE, array_unique( $ids ) );
	}
}

/**
 * Xoá toàn bộ meta của các review bị xoá.
 *
 * @param int[] $review_ids ID review.
 * @return void
 */
function init_plugin_suite_review_system_delete_all_review_meta( $review_ids ) {
	global $wpdb;
	$review_ids = array_filter( array_map( 'absint', (array) $review_ids ) );
	if ( ! $review_ids || '1' !== get_option( 'irs_review_meta_used' ) || ! init_plugin_suite_review_system_review_meta_ready() ) {
		return;
	}
	$table        = $wpdb->prefix . 'init_review_meta';
	$placeholders = implode( ',', array_fill( 0, count( $review_ids ), '%d' ) );
	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber,PluginCheck.Security.DirectDB.UnescapedDBParameter
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE init_review_id IN ($placeholders)", ...$review_ids ) );
	// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber,PluginCheck.Security.DirectDB.UnescapedDBParameter
	foreach ( $review_ids as $id ) {
		wp_cache_delete( $id, INIT_PLUGIN_SUITE_RS_META_TYPE . '_meta' );
	}
}
