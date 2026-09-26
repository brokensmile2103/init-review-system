<?php
/**
 * Shortcodes & assets front-end.
 *
 * @package InitReviewSystem
 */

defined( 'ABSPATH' ) || exit;

/**
 * Enqueue JS chính + dữ liệu localize (chỉ chạy 1 lần mỗi request).
 *
 * @return void
 */
function init_plugin_suite_review_system_enqueue_assets() {
	if ( wp_script_is( 'init-review-system-script', 'enqueued' ) ) {
		return;
	}

	wp_enqueue_script(
		'init-review-system-script',
		INIT_PLUGIN_SUITE_RS_ASSETS_URL . 'js/script.js',
		array(),
		INIT_PLUGIN_SUITE_RS_VERSION,
		true
	);

	$options              = get_option( INIT_PLUGIN_SUITE_RS_OPTION );
	$require_login        = ! empty( $options['require_login'] );
	$double_click_to_rate = ! empty( $options['double_click_to_rate'] );
	$is_logged_in         = is_user_logged_in();

	$current_user_name   = $is_logged_in ? wp_get_current_user()->display_name : '';
	$current_user_avatar = $is_logged_in
		? get_avatar_url( get_current_user_id(), array( 'size' => 80 ) )
		: init_plugin_suite_review_system_get_default_avatar_url();

	// Pass enable flag + thresholds to JS (no banned lists exposed).
	$js_precheck_enabled  = ! empty( $options['js_precheck_enabled'] );
	$ws_min_len_threshold = (int) apply_filters( 'init_plugin_suite_review_system_min_len_for_ws_check', 20 );
	$repeat_threshold     = (int) apply_filters( 'init_plugin_suite_review_system_repetition_threshold', 8 );

	wp_localize_script(
		'init-review-system-script',
		'InitReviewSystemData',
		array(
			'require_login'        => $require_login,
			'double_click_to_rate' => $double_click_to_rate,
			'is_logged_in'         => $is_logged_in,
			'rest_url'             => rest_url( INIT_PLUGIN_SUITE_RS_NAMESPACE ),
			'nonce'                => wp_create_nonce( 'wp_rest' ),
			'current_user_name'    => $current_user_name,
			'current_user_avatar'  => $current_user_avatar,
			'assets_url'           => INIT_PLUGIN_SUITE_RS_ASSETS_URL,

			// Precheck config for front-end.
			'precheck'             => array(
				'enabled'               => $js_precheck_enabled,
				'minLenWhitespaceCheck' => $ws_min_len_threshold,
				'repeatThreshold'       => $repeat_threshold,
			),

			'i18n'                 => array(
				'validation_error'       => __( 'Please select scores and write a review.', 'init-review-system' ),
				'success'                => __( 'Your review has been submitted!', 'init-review-system' ),
				'error'                  => __( 'Submission failed. Please try again later.', 'init-review-system' ),
				'review_label'           => __( 'reviews', 'init-review-system' ),

				// Các chuỗi bổ sung cho moderation/backend.
				'login_required'         => __( 'Login required to submit review.', 'init-review-system' ),
				'invalid_nonce'          => __( 'Invalid nonce.', 'init-review-system' ),
				'invalid_data'           => __( 'Invalid request data.', 'init-review-system' ),
				'no_valid_scores'        => __( 'No valid scores provided.', 'init-review-system' ),
				'rate_all_criteria'      => __( 'Please rate all required criteria.', 'init-review-system' ),
				'duplicate_review'       => __( 'You have already submitted a review.', 'init-review-system' ),
				'duplicate_ip'           => __( 'You have already submitted a review from this IP.', 'init-review-system' ),
				'banned_word_detected'   => __( 'Your review contains banned words.', 'init-review-system' ),
				'banned_phrase_detected' => __( 'Your review contains banned phrases.', 'init-review-system' ),
				'no_whitespace'          => __( 'Your review appears to contain no whitespace. Please rewrite it more naturally.', 'init-review-system' ),
				'excessive_repetition'   => __( 'Your review repeats the same word too many times.', 'init-review-system' ),
				'db_error'               => __( 'Could not insert review.', 'init-review-system' ),
			),
		)
	);
}

add_shortcode( 'init_review_score', 'init_plugin_suite_review_system_shortcode_score' );

/**
 * Handler của [init_review_score].
 *
 * @param array|string $atts Shortcode attributes.
 * @return string
 */
function init_plugin_suite_review_system_shortcode_score( $atts ) {
	$atts = shortcode_atts(
		array(
			'id'            => get_the_ID(),
			'icon'          => 'false',
			'sub'           => 'true',
			'show_count'    => 'false',
			'class'         => '',
			'hide_if_empty' => 'false',
		),
		$atts,
		'init_review_score'
	);

	$post_id       = intval( $atts['id'] );
	$icon          = filter_var( $atts['icon'], FILTER_VALIDATE_BOOLEAN );
	$sub           = filter_var( $atts['sub'], FILTER_VALIDATE_BOOLEAN );
	$show_count    = filter_var( $atts['show_count'], FILTER_VALIDATE_BOOLEAN );
	$hide_if_empty = filter_var( $atts['hide_if_empty'], FILTER_VALIDATE_BOOLEAN );
	$class         = init_plugin_suite_review_system_sanitize_html_classes( $atts['class'] );

	$total = max( 0, intval( get_post_meta( $post_id, '_init_review_count', true ) ) );

	if ( 0 === $total && $hide_if_empty ) {
		return '';
	}

	$score = floatval( get_post_meta( $post_id, '_init_review_avg', true ) );
	$score = min( 5, $score ); // Ngăn ghi sai điểm > 5.

	$output = '<span class="init-review-score ' . esc_attr( $class ) . '">';
	if ( $icon ) {
		// SVG mặc định.
		$default_svg = '<svg width="20" height="20" viewBox="0 0 20 20" aria-hidden="true">
            <polygon fill="none" stroke="currentColor" stroke-width="1.01" 
                points="10 2 12.63 7.27 18.5 8.12 14.25 12.22 15.25 18 
                        10 15.27 4.75 18 5.75 12.22 1.5 8.12 7.37 7.27"></polygon>
        </svg> ';

		// Cho phép thay thế SVG qua filter (giữ nguyên tên hook cũ để tương thích).
		$output .= apply_filters( 'init_plugin_suite_review_score_star_icon', $default_svg, $post_id, $score ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
	}
	$output .= esc_html( number_format( (float) $score, 1, '.', '' ) );
	if ( $sub ) {
		$output .= '<sub>/5</sub>';
	}
	if ( $show_count ) {
		$output .= apply_filters(
			'init_plugin_suite_review_system_score_count_html',
			' (' . number_format_i18n( $total ) . ')',
			$total,
			$post_id
		);
	}
	$output .= '</span>';

	return $output;
}

/**
 * Loại schema.org tương ứng với post type.
 *
 * @param int $post_id ID bài viết.
 * @return string
 */
function init_plugin_suite_review_system_get_schema_type( $post_id ) {
	$post_type = get_post_type( $post_id );

	$type_map = array(
		'product' => 'Product',
		'book'    => 'Book',
		'course'  => 'Course',
		'movie'   => 'Movie',
		'post'    => 'Article',
		'page'    => 'Article',
	);

	return apply_filters( 'init_plugin_suite_review_system_schema_type', $type_map[ $post_type ] ?? 'CreativeWork', $post_type );
}

add_shortcode( 'init_review_system', 'init_plugin_suite_review_system_shortcode_system' );

/**
 * Handler của [init_review_system].
 *
 * @param array|string $atts Shortcode attributes.
 * @return string
 */
function init_plugin_suite_review_system_shortcode_system( $atts ) {
	init_plugin_suite_review_system_enqueue_assets();

	$atts = shortcode_atts(
		array(
			'id'     => get_the_ID(),
			'class'  => '',
			'schema' => 'false',
		),
		$atts,
		'init_review_system'
	);

	$post_id = intval( $atts['id'] );
	$class   = init_plugin_suite_review_system_sanitize_html_classes( $atts['class'] );
	$schema  = filter_var( $atts['schema'], FILTER_VALIDATE_BOOLEAN );

	$total = max( 0, intval( get_post_meta( $post_id, '_init_review_count', true ) ) );
	$score = floatval( get_post_meta( $post_id, '_init_review_avg', true ) );
	$score = min( 5, $score ); // Giới hạn nếu dữ liệu cũ sai.

	$output  = '<div class="init-review-system ' . esc_attr( $class ) . '" data-post-id="' . esc_attr( $post_id ) . '" data-max-score="5">';
	$output .= '<div class="init-review-box">';

	// Stars.
	$output .= '<div class="init-review-stars">';

	$default_star_svg = '<svg class="i-star" width="20" height="20" viewBox="0 0 64 64" aria-hidden="true"><path fill="currentColor" d="M63.9 24.28a2 2 0 0 0-1.6-1.35l-19.68-3-8.81-18.78a2 2 0 0 0-3.62 0l-8.82 18.78-19.67 3a2 2 0 0 0-1.13 3.38l14.3 14.66-3.39 20.7a2 2 0 0 0 2.94 2.07L32 54.02l17.57 9.72a2 2 0 0 0 2.12-.11 2 2 0 0 0 .82-1.96l-3.38-20.7 14.3-14.66a2 2 0 0 0 .46-2.03"></path></svg>';

	for ( $i = 1; $i <= 5; $i++ ) {
		// Cho phép thay thế icon ngôi sao qua filter.
		// Args: ($default_svg, $post_id, $score, $i).
		$star_svg = apply_filters( 'init_plugin_suite_review_system_star_icon', $default_star_svg, $post_id, $score, $i );

		$output .= '<span class="star" data-value="' . $i . '">' . $star_svg . '</span>';
	}
	$output .= '</div>';

	// Info.
	$output .= '<div class="init-review-info">';
	$output .= '<strong>' . esc_html( number_format( (float) $score, 1, '.', '' ) ) . '</strong><sub>/5</sub>';
	$output .= ' (' . esc_html( number_format_i18n( $total ) ) . ')';
	$output .= '</div>';

	$output .= '</div>'; // End .init-review-box.

	// Schema JSON-LD nếu bật.
	if ( $schema && $score > 0 && $total > 0 ) {
		$schema_type = init_plugin_suite_review_system_get_schema_type( $post_id );

		$schema_data = array(
			'@context'        => 'https://schema.org',
			'@type'           => $schema_type,
			'name'            => get_the_title( $post_id ),
			'aggregateRating' => array(
				'@type'       => 'AggregateRating',
				'ratingValue' => $score,
				'reviewCount' => $total,
				'bestRating'  => 5,
			),
		);

		$schema_data = apply_filters( 'init_plugin_suite_review_system_schema_data', $schema_data, $post_id, $schema_type );

		$output .= '<script type="application/ld+json">' . wp_json_encode( $schema_data ) . '</script>';
	}

	$output .= '</div>'; // End .init-review-system.

	return $output;
}

add_shortcode( 'init_review_criteria', 'init_plugin_suite_review_system_shortcode_criteria' );

/**
 * Handler của [init_review_criteria].
 *
 * @param array|string $atts Shortcode attributes.
 * @return string
 */
function init_plugin_suite_review_system_shortcode_criteria( $atts ) {
	init_plugin_suite_review_system_enqueue_assets();

	$atts = shortcode_atts(
		array(
			'id'       => get_the_ID(),
			'class'    => '',
			'schema'   => 'false',
			'per_page' => 0, // Không giới hạn.
			'paged'    => 1, // Trang hiện tại.
		),
		$atts,
		'init_review_criteria'
	);

	$post_id        = intval( $atts['id'] );
	$class          = init_plugin_suite_review_system_sanitize_html_classes( $atts['class'] );
	$schema         = filter_var( $atts['schema'], FILTER_VALIDATE_BOOLEAN );
	$per_page       = max( 0, intval( $atts['per_page'] ) );
	$paged          = max( 1, intval( $atts['paged'] ) ); // Tránh 0 hoặc âm.
	$user_logged_in = is_user_logged_in();
	$user_id        = get_current_user_id();

	// Lấy dữ liệu thống kê điểm.
	$summary       = init_plugin_suite_review_system_get_score_summary_by_post_id( $post_id );
	$total_reviews = init_plugin_suite_review_system_get_total_reviews_by_post_id( $post_id );

	$settings      = get_option( INIT_PLUGIN_SUITE_RS_OPTION );
	$require_login = apply_filters( 'init_plugin_suite_review_system_require_login', ! empty( $settings['require_login'] ) );
	$has_reviewed  = $user_logged_in ? init_plugin_suite_review_system_has_user_reviewed( $post_id, $user_id ) : false;

	$can_review = ( $user_logged_in || ! $require_login ) && ! $has_reviewed;

	// Chưa có review nào thì khỏi query danh sách.
	$reviews = $total_reviews > 0
		? init_plugin_suite_review_system_get_reviews_by_post_id( $post_id, $paged, $per_page )
		: array();

	// Chuẩn bị dữ liệu truyền vào template. Tiêu chí lấy qua
	// get_criteria_by_post_id() để filter `init_plugin_suite_review_system_criteria`
	// áp dụng nhất quán cho cả phần hiển thị (trước đây chỉ REST mới áp dụng).
	$template_data = array(
		'post_id'       => $post_id,
		'user_id'       => $user_id,
		'can_review'    => $can_review,
		'class'         => $class,
		'schema'        => $schema,
		'per_page'      => $per_page,
		'paged'         => $paged,
		'summary'       => $summary,
		'total_reviews' => $total_reviews,
		'criteria'      => init_plugin_suite_review_system_get_criteria_by_post_id( $post_id ),
		// Enrich sẵn display_name/avatar_url + prime cache user hàng loạt,
		// thay vì template phải gọi get_userdata() riêng cho từng review.
		'reviews'       => init_plugin_suite_review_system_enrich_reviews( $reviews, 48 ),
	);

	ob_start();
	init_plugin_suite_review_system_render_template( 'review-criteria-display.php', $template_data );
	return ob_get_clean();
}

add_action( 'admin_enqueue_scripts', 'init_plugin_suite_review_system_enqueue_shortcode_builder' );

/**
 * Enqueue shortcode builder trên trang settings của plugin.
 *
 * @param string $hook Hook suffix của trang admin hiện tại.
 * @return void
 */
function init_plugin_suite_review_system_enqueue_shortcode_builder( $hook ) {
	if ( 'toplevel_page_' . INIT_PLUGIN_SUITE_RS_SLUG !== $hook ) {
		return;
	}

	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	// Script builder UI dùng chung (copy, preview, tab, v.v.).
	wp_enqueue_script(
		'init-review-system-shortcode-builder',
		INIT_PLUGIN_SUITE_RS_URL . 'assets/js/init-shortcode-builder.js',
		array(),
		INIT_PLUGIN_SUITE_RS_VERSION,
		true
	);

	wp_localize_script(
		'init-review-system-shortcode-builder',
		'InitReviewSystemShortcodeBuilder',
		array(
			'i18n' => array(
				'copy'                 => __( 'Copy', 'init-review-system' ),
				'copied'               => __( 'Copied!', 'init-review-system' ),
				'close'                => __( 'Close', 'init-review-system' ),
				'shortcode_preview'    => __( 'Shortcode Preview', 'init-review-system' ),
				'shortcode_builder'    => __( 'Shortcode Builder', 'init-review-system' ),
				'init_review_score'    => __( 'Review Score', 'init-review-system' ),
				'init_review_system'   => __( 'Init Review System', 'init-review-system' ),
				'init_review_widget'   => __( 'Review Widget', 'init-review-system' ),
				'init_review_criteria' => __( 'Review Criteria', 'init-review-system' ),
				'init_reactions'       => __( 'Reactions Bar', 'init-review-system' ),
				'icon'                 => __( 'Show Icon', 'init-review-system' ),
				'sub'                  => __( 'Show "/5" Suffix', 'init-review-system' ),
				'show_count'           => __( 'Show Review Count', 'init-review-system' ),
				'hide_if_empty'        => __( 'Hide If No Reviews', 'init-review-system' ),
				'schema'               => __( 'Enable Schema.org', 'init-review-system' ),
				'class'                => __( 'Custom Class', 'init-review-system' ),
				'css'                  => __( 'Load CSS', 'init-review-system' ),
				'id'                   => __( 'Post ID', 'init-review-system' ),
				'per_page'             => __( 'Posts per page', 'init-review-system' ),
			),
		)
	);

	// Script riêng cho phần builder hiển thị trong admin UI.
	wp_enqueue_script(
		'init-review-system-admin-shortcode-panel',
		INIT_PLUGIN_SUITE_RS_URL . 'assets/js/shortcodes.js',
		array( 'init-review-system-shortcode-builder' ),
		INIT_PLUGIN_SUITE_RS_VERSION,
		true
	);
}

add_shortcode( 'init_reactions', 'init_plugin_suite_review_system_shortcode_reactions' );

/**
 * Handler của [init_reactions].
 *
 * - id: Post ID (mặc định get_the_ID()).
 * - class: thêm class ngoài.
 * - css: "true" | "false" (mặc định true) → auto enqueue assets/css/reactions.css.
 *
 * @param array|string $atts Shortcode attributes.
 * @return string
 */
function init_plugin_suite_review_system_shortcode_reactions( $atts ) {
	$atts = shortcode_atts(
		array(
			'id'    => get_the_ID(),
			'class' => '',
			'css'   => 'true',
		),
		$atts,
		'init_reactions'
	);

	$post_id = intval( $atts['id'] );
	if ( ! $post_id ) {
		return '';
	}

	// Đảm bảo object InitReviewSystemData được set (reactions.js ưu tiên dùng).
	init_plugin_suite_review_system_enqueue_assets();

	// Bật/tắt CSS.
	if ( filter_var( $atts['css'], FILTER_VALIDATE_BOOLEAN ) ) {
		wp_enqueue_style(
			'init-review-system-reactions',
			INIT_PLUGIN_SUITE_RS_ASSETS_URL . 'css/reactions.css',
			array(),
			INIT_PLUGIN_SUITE_RS_VERSION
		);
	}

	// Luôn yêu cầu đăng nhập.
	$require_login = true;
	$is_logged_in  = is_user_logged_in();

	// Enqueue + localize JS cho reactions — chỉ 1 lần mỗi request. Trước đây
	// mỗi thanh reactions trên trang lại in thêm một bản InitReviewReactionsData.
	if ( ! wp_script_is( 'init-review-system-reactions', 'enqueued' ) ) {
		wp_enqueue_script(
			'init-review-system-reactions',
			INIT_PLUGIN_SUITE_RS_ASSETS_URL . 'js/reactions.js',
			array(),
			INIT_PLUGIN_SUITE_RS_VERSION,
			true
		);

		wp_localize_script(
			'init-review-system-reactions',
			'InitReviewReactionsData',
			array(
				'require_login' => $require_login,
				'is_logged_in'  => $is_logged_in,
				'rest_url'      => rest_url( INIT_PLUGIN_SUITE_RS_NAMESPACE ),
				'nonce'         => wp_create_nonce( 'wp_rest' ),
				'assets_url'    => INIT_PLUGIN_SUITE_RS_ASSETS_URL,
			)
		);
	}

	$data = array(
		'post_id'       => $post_id,
		'class'         => init_plugin_suite_review_system_sanitize_html_classes( $atts['class'] ),
		'types'         => init_plugin_suite_review_system_get_reaction_types(),
		'counts'        => init_plugin_suite_review_system_get_reaction_counts( $post_id ),
		'require_login' => $require_login,
		'is_logged_in'  => $is_logged_in,
	);

	ob_start();
	init_plugin_suite_review_system_render_template( 'reactions-bar.php', $data );
	return ob_get_clean();
}
