<?php
/**
 * Hàm tiện ích dùng chung.
 *
 * @package InitReviewSystem
 */

defined( 'ABSPATH' ) || exit;

/**
 * Lấy dữ liệu đánh giá của một bài viết.
 *
 * @param int $post_id ID bài viết.
 * @return array {
 *     @type int     $total     Tổng lượt đánh giá.
 *     @type float   $average   Điểm trung bình (0 nếu chưa có).
 *     @type float   $total_raw Tổng điểm gộp lại (để debug hoặc thống kê).
 *     @type int     $max       Điểm tối đa.
 * }
 */
function init_plugin_suite_review_system_get_rating_data( $post_id ) {
	$post_id = absint( $post_id );
	if ( ! $post_id || ! get_post( $post_id ) ) {
		return array(
			'total'     => 0,
			'average'   => 0,
			'total_raw' => 0,
			'max'       => 5,
		);
	}
	$total_raw = floatval( get_post_meta( $post_id, '_init_review_total', true ) );
	$total     = intval( get_post_meta( $post_id, '_init_review_count', true ) );
	$average   = $total > 0 ? round( $total_raw / $total, 2 ) : 0;
	return array(
		'total'     => $total,
		'average'   => min( 5, $average ),
		'total_raw' => $total_raw,
		'max'       => 5,
	);
}

/**
 * Kiểm tra người xem hiện tại có được phép xem dữ liệu đánh giá của bài viết.
 *
 * Bài viết công khai (kể cả có mật khẩu) → luôn được. Bài nháp/riêng tư/đã lên
 * lịch → chỉ khi người dùng có quyền đọc bài đó. Dùng cho các REST endpoint và
 * ability công khai, tránh lộ dữ liệu (review, reaction) của bài chưa công bố.
 *
 * @since 2.0.1
 *
 * @param int $post_id ID bài viết.
 * @return bool
 */
function init_plugin_suite_review_system_can_view_post( $post_id ) {
	$post = get_post( absint( $post_id ) );
	if ( ! $post ) {
		return false;
	}

	$can_view = is_post_publicly_viewable( $post ) || current_user_can( 'read_post', $post->ID );

	/**
	 * Filter: cho phép tuỳ chỉnh quyền xem dữ liệu đánh giá của một bài viết.
	 *
	 * @since 2.0.1
	 *
	 * @param bool    $can_view Kết quả mặc định.
	 * @param WP_Post $post     Bài viết.
	 */
	return (bool) apply_filters( 'init_plugin_suite_review_system_can_view_post', $can_view, $post );
}

/**
 * Lấy IP address đã được sanitize từ request.
 *
 * @return string IP address (fallback 127.0.0.1).
 */
function init_plugin_suite_review_system_get_client_ip() {
	$ip_keys = array(
		'HTTP_CF_CONNECTING_IP',     // Cloudflare.
		'HTTP_X_FORWARDED_FOR',      // Load balancer/proxy.
		'HTTP_X_FORWARDED',          // Proxy.
		'HTTP_X_CLUSTER_CLIENT_IP',  // Cluster.
		'HTTP_CLIENT_IP',            // Proxy.
		'HTTP_X_REAL_IP',            // Nginx proxy.
		'REMOTE_ADDR',               // Standard.
	);

	$client_ip = '127.0.0.1'; // Ultimate fallback.

	foreach ( $ip_keys as $key ) {
		if ( empty( $_SERVER[ $key ] ) ) {
			continue;
		}

		$ip = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );

		// X-Forwarded-For có thể chứa nhiều IP, lấy IP đầu tiên (client gốc).
		if ( false !== strpos( $ip, ',' ) ) {
			$ip = trim( explode( ',', $ip )[0] );
		}

		// Chấp nhận cả IP private (môi trường dev/local) như trước.
		if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			$client_ip = $ip;
			break;
		}
	}

	/**
	 * Filter: cho phép site tự quyết định IP client (ví dụ chỉ tin header
	 * của reverse proxy mà site thực sự sử dụng).
	 *
	 * @since 2.0.1
	 *
	 * @param string $client_ip IP đã xác định.
	 */
	return (string) apply_filters( 'init_plugin_suite_review_system_client_ip', $client_ip );
}

/**
 * Transient key + hash IP dùng cho cơ chế chống vote trùng theo IP.
 *
 * @param int    $post_id ID bài viết.
 * @param string $type    Loại (simple, criteria...).
 * @return array{0:string,1:string} [ transient key, hash IP ].
 */
function init_plugin_suite_review_system_ip_recent_key( $post_id, $type ) {
	$ip   = init_plugin_suite_review_system_get_client_ip();
	$hash = base_convert( sprintf( '%u', crc32( $ip ) ), 10, 36 );
	$key  = 'irs_recent_ips_' . absint( $post_id ) . '_' . $type;

	return array( $key, $hash );
}

/**
 * Kiểm tra IP hiện tại đã tương tác gần đây với bài viết hay chưa.
 *
 * Mặc định (giữ nguyên hành vi cũ) hàm vừa kiểm tra vừa ghi nhận IP. Truyền
 * `$record = false` để chỉ kiểm tra — dùng khi request còn có thể bị từ chối
 * ở bước sau (ví dụ kiểm duyệt nội dung), rồi gọi
 * init_plugin_suite_review_system_mark_ip_recent() khi đã thành công.
 *
 * @param int    $post_id ID bài viết.
 * @param string $type    Loại (simple, criteria...).
 * @param bool   $record  Có ghi nhận IP nếu chưa có hay không.
 * @return bool True nếu IP đã có trong danh sách gần đây.
 */
function init_plugin_suite_review_system_is_ip_recent( $post_id, $type = 'default', $record = true ) {
	list( $key, $hash ) = init_plugin_suite_review_system_ip_recent_key( $post_id, $type );

	$list = get_transient( $key );
	if ( is_array( $list ) && in_array( $hash, $list, true ) ) {
		return true;
	}

	if ( $record ) {
		init_plugin_suite_review_system_mark_ip_recent( $post_id, $type );
	}

	return false;
}

/**
 * Ghi nhận IP hiện tại vào danh sách "gần đây" của bài viết (tối đa 75 IP).
 *
 * @since 2.0.1
 *
 * @param int    $post_id ID bài viết.
 * @param string $type    Loại (simple, criteria...).
 * @return void
 */
function init_plugin_suite_review_system_mark_ip_recent( $post_id, $type = 'default' ) {
	list( $key, $hash ) = init_plugin_suite_review_system_ip_recent_key( $post_id, $type );

	$list = get_transient( $key );
	if ( ! is_array( $list ) ) {
		$list = array();
	}
	if ( in_array( $hash, $list, true ) ) {
		return;
	}

	array_unshift( $list, $hash );
	if ( count( $list ) > 75 ) {
		array_pop( $list );
	}
	set_transient( $key, $list, WEEK_IN_SECONDS );
}

/**
 * Tìm file template: ưu tiên bản override trong theme
 * (`{theme}/init-review-system/{template}`), sau đó tới bản của plugin.
 *
 * @since 2.0.1
 *
 * @param string $template Tên file template (ví dụ review-item.php).
 * @return string Đường dẫn tuyệt đối, hoặc chuỗi rỗng nếu không tìm thấy.
 */
function init_plugin_suite_review_system_locate_template( $template ) {
	$template = ltrim( (string) $template, '/\\' );

	$theme_template = locate_template( 'init-review-system/' . $template );
	if ( $theme_template ) {
		return $theme_template;
	}

	$plugin_template = INIT_PLUGIN_SUITE_RS_TEMPLATES_PATH . $template;

	return file_exists( $plugin_template ) ? $plugin_template : '';
}

/**
 * Render template (cho phép theme override) với dữ liệu truyền vào.
 *
 * @param string $template Tên file template.
 * @param array  $data     Biến truyền vào template.
 * @return void
 */
function init_plugin_suite_review_system_render_template( $template, $data = array() ) {
	$init_plugin_suite_rs_template_path = init_plugin_suite_review_system_locate_template( $template );
	if ( ! $init_plugin_suite_rs_template_path ) {
		return;
	}

	// Template (kể cả bản override trong theme) nhận dữ liệu dưới dạng biến
	// cục bộ — giữ nguyên hợp đồng này để không làm vỡ template của theme.
	extract( (array) $data, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
	include $init_plugin_suite_rs_template_path;
}

/**
 * URL avatar mặc định.
 *
 * @since 2.0.1
 *
 * @return string
 */
function init_plugin_suite_review_system_get_default_avatar_url() {
	return INIT_PLUGIN_SUITE_RS_ASSETS_URL . 'img/default-avatar.svg';
}

/**
 * Bổ sung display_name/avatar_url cho danh sách review.
 *
 * Prime cache user hàng loạt 1 lần (thay vì get_userdata() riêng từng dòng),
 * review đã có sẵn display_name thì giữ nguyên.
 *
 * @since 2.0.1
 *
 * @param array $reviews     Danh sách review (ARRAY_A).
 * @param int   $avatar_size Kích thước avatar.
 * @return array
 */
function init_plugin_suite_review_system_enrich_reviews( $reviews, $avatar_size = 48 ) {
	if ( empty( $reviews ) || ! is_array( $reviews ) ) {
		return is_array( $reviews ) ? $reviews : array();
	}

	$user_ids = array_unique( array_filter( array_map( 'absint', wp_list_pluck( $reviews, 'user_id' ) ) ) );
	if ( $user_ids ) {
		cache_users( $user_ids );
	}

	$default_avatar = init_plugin_suite_review_system_get_default_avatar_url();
	$anonymous      = __( 'Anonymous', 'init-review-system' );

	foreach ( $reviews as &$review ) {
		if ( isset( $review['display_name'] ) ) {
			continue;
		}

		$user_id = intval( $review['user_id'] ?? 0 );
		$user    = $user_id > 0 ? get_userdata( $user_id ) : false;

		$review['display_name'] = $user ? $user->display_name : $anonymous;
		$review['avatar_url']   = $default_avatar;

		if ( $user_id > 0 ) {
			$avatar_url = get_avatar_url( $user_id, array( 'size' => $avatar_size ) );
			if ( $avatar_url ) {
				$review['avatar_url'] = $avatar_url;
			}
		}
	}
	unset( $review );

	return $reviews;
}

/**
 * Chuẩn hoá chuỗi class HTML: hỗ trợ nhiều class cách nhau bởi khoảng trắng
 * (trước đây sanitize_html_class() gộp "a b" thành "ab").
 *
 * @since 2.0.1
 *
 * @param string $classes Chuỗi class.
 * @return string
 */
function init_plugin_suite_review_system_sanitize_html_classes( $classes ) {
	$classes = preg_split( '/\s+/', (string) $classes, -1, PREG_SPLIT_NO_EMPTY );
	if ( ! $classes ) {
		return '';
	}

	$classes = array_filter( array_map( 'sanitize_html_class', $classes ) );

	return implode( ' ', array_unique( $classes ) );
}

/**
 * Lấy danh sách tiêu chí từ settings.
 *
 * @return string[]
 */
function init_plugin_suite_review_system_get_criteria_labels() {
	$options = get_option( INIT_PLUGIN_SUITE_RS_OPTION );
	$labels  = array();
	for ( $i = 1; $i <= 5; $i++ ) {
		if ( ! empty( $options[ "criteria_$i" ] ) ) {
			$labels[] = sanitize_text_field( $options[ "criteria_$i" ] );
		}
	}
	return $labels;
}

/**
 * Tính weighted score (Bayesian average).
 *
 * @param float $avg        Điểm trung bình của bài.
 * @param int   $count      Số lượt vote của bài.
 * @param float $global_avg Điểm trung bình toàn site.
 * @param int   $min_votes  Ngưỡng vote tối thiểu.
 * @return float
 */
function init_plugin_suite_review_system_calculate_weighted_score( $avg, $count, $global_avg, $min_votes = 50 ) {
	$v = (int) $count;
	$m = max( 0, (int) $min_votes );

	if ( $v <= 0 ) {
		return 0;
	}

	$r = (float) $avg;
	$c = (float) $global_avg;

	return ( ( $v / ( $v + $m ) ) * $r ) + ( ( $m / ( $v + $m ) ) * $c );
}

/**
 * Tính lại và lưu `_init_review_weighted` cho một bài viết.
 *
 * @since 2.0.1
 *
 * @param int   $post_id ID bài viết.
 * @param float $avg     Điểm trung bình hiện tại.
 * @param int   $count   Số lượt vote hiện tại.
 * @return float Weighted score vừa tính.
 */
function init_plugin_suite_review_system_refresh_weighted_score( $post_id, $avg, $count ) {
	$global_avg     = init_plugin_suite_review_system_get_global_avg();
	$min_votes      = apply_filters( 'init_plugin_suite_review_system_min_votes_threshold', 50, $post_id );
	$weighted_score = init_plugin_suite_review_system_calculate_weighted_score( $avg, $count, $global_avg, $min_votes );

	update_post_meta( $post_id, '_init_review_weighted', round( $weighted_score, 4 ) );

	return $weighted_score;
}

/**
 * Cộng dồn (increment) một giá trị số vào post meta MỘT CÁCH ATOMIC.
 *
 * Dùng UPDATE trực tiếp dạng `meta_value = meta_value + x`: MySQL khoá đúng
 * dòng đó trong lúc cộng, nên nhiều request chạy song song vẫn cộng dồn đúng,
 * không mất lượt nào (lost update) như cách get → cộng trong PHP → update.
 *
 * @param int    $post_id   ID bài viết.
 * @param string $meta_key  Meta key cần cộng dồn.
 * @param float  $increment Giá trị cần cộng (có thể âm để trừ).
 * @return float Giá trị mới nhất sau khi cộng.
 */
function init_plugin_suite_review_system_atomic_increment_meta( $post_id, $meta_key, $increment ) {
	global $wpdb;

	$post_id   = absint( $post_id );
	$meta_key  = sanitize_key( $meta_key );
	$increment = (float) $increment;

	// Đảm bảo đã có dòng meta để UPDATE atomic phía dưới có thể chạm vào.
	// Add_post_meta() với $unique = true chỉ ghi nếu chưa tồn tại.
	if ( '' === get_post_meta( $post_id, $meta_key, true ) ) {
		add_post_meta( $post_id, $meta_key, 0, true );
	}

	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
	$wpdb->query(
		$wpdb->prepare(
			"UPDATE {$wpdb->postmeta} SET meta_value = meta_value + %f WHERE post_id = %d AND meta_key = %s",
			$increment,
			$post_id,
			$meta_key
		)
	);
	// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter

	// Vừa sửa DB bằng SQL trực tiếp nên object cache post meta không tự biết.
	wp_cache_delete( $post_id, 'post_meta' );

	return (float) get_post_meta( $post_id, $meta_key, true );
}

/**
 * Điểm trung bình toàn site (cache 1 giờ bằng transient).
 *
 * @return float
 */
function init_plugin_suite_review_system_get_global_avg() {
	$transient_key = 'init_plugin_suite_rs_global_avg';

	$cached = get_transient( $transient_key );
	if ( false !== $cached ) {
		return (float) $cached;
	}

	global $wpdb;

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$avg = (float) $wpdb->get_var(
		"SELECT AVG(meta_value)
		FROM {$wpdb->postmeta}
		WHERE meta_key = '_init_review_avg'
			AND meta_value > 0"
	);

	set_transient( $transient_key, $avg, HOUR_IN_SECONDS );
	return $avg;
}
