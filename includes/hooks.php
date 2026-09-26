<?php
/**
 * Init Review System - Hooks auto insert score & vote blocks.
 *
 * Hooked into: the_content, comment_form_before, comment_form_after (tùy cấu hình).
 *
 * @package InitReviewSystem
 */

defined( 'ABSPATH' ) || exit;

/**
 * Kiểm tra có tự chèn block tại vị trí cho trước hay không.
 *
 * @param string $type     Loại block: score | vote.
 * @param string $position Vị trí: before | after | before_comment | after_comment.
 * @return bool
 */
function init_plugin_suite_review_system_should_auto_insert( $type, $position ) {
	if ( ! is_singular() || ! in_the_loop() ) {
		return false;
	}

	$options  = get_option( INIT_PLUGIN_SUITE_RS_OPTION, array() );
	$selected = $options[ $type . '_position' ] ?? 'none';

	if ( $selected !== $position ) {
		return false;
	}

	// Filter để override ngoài.
	return apply_filters(
		"init_plugin_suite_review_system_auto_insert_enabled_{$type}",
		true,
		$position,
		get_post_type()
	);
}

/**
 * Shortcode mặc định cho từng loại block (cho phép filter override).
 *
 * @param string $type Loại block: score | vote.
 * @return string
 */
function init_plugin_suite_review_system_get_default_shortcode( $type ) {
	if ( 'score' === $type ) {
		return apply_filters(
			'init_plugin_suite_review_system_default_score_shortcode',
			'[init_review_score icon="true"]'
		);
	}

	if ( 'vote' === $type ) {
		return apply_filters(
			'init_plugin_suite_review_system_default_vote_shortcode',
			'[init_review_system]'
		);
	}

	return '';
}

add_filter( 'the_content', 'init_plugin_suite_review_system_filter_the_content', 20 );

/**
 * Chèn block điểm/vote vào nội dung bài viết.
 *
 * @param string $content Nội dung bài viết.
 * @return string
 */
function init_plugin_suite_review_system_filter_the_content( $content ) {
	// Thoát sớm (không đọc option) khi không phải trang đơn trong loop.
	if ( ! is_singular() || ! in_the_loop() ) {
		return $content;
	}

	foreach ( array( 'score', 'vote' ) as $type ) {
		if ( init_plugin_suite_review_system_should_auto_insert( $type, 'before' ) ) {
			$content = do_shortcode( init_plugin_suite_review_system_get_default_shortcode( $type ) ) . $content;
		}

		if ( init_plugin_suite_review_system_should_auto_insert( $type, 'after' ) ) {
			$content .= do_shortcode( init_plugin_suite_review_system_get_default_shortcode( $type ) );
		}
	}

	return $content;
}

add_action( 'comment_form_before', 'init_plugin_suite_review_system_vote_before_comment_form' );

/**
 * Chèn block vote trước form bình luận.
 *
 * @return void
 */
function init_plugin_suite_review_system_vote_before_comment_form() {
	if ( init_plugin_suite_review_system_should_auto_insert( 'vote', 'before_comment' ) ) {
		echo do_shortcode( init_plugin_suite_review_system_get_default_shortcode( 'vote' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Shortcode output is escaped internally.
	}
}

add_action( 'comment_form_after', 'init_plugin_suite_review_system_vote_after_comment_form' );

/**
 * Chèn block vote sau form bình luận.
 *
 * @return void
 */
function init_plugin_suite_review_system_vote_after_comment_form() {
	if ( init_plugin_suite_review_system_should_auto_insert( 'vote', 'after_comment' ) ) {
		echo do_shortcode( init_plugin_suite_review_system_get_default_shortcode( 'vote' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Shortcode output is escaped internally.
	}
}

add_action( 'comment_form_before', 'init_plugin_suite_review_system_reactions_before_comment_form', 10 );

/**
 * Inject [init_reactions] ngay trước form bình luận nếu bật trong settings.
 *
 * @return void
 */
function init_plugin_suite_review_system_reactions_before_comment_form() {
	if ( is_admin() || ! is_singular() ) {
		return;
	}

	$options = get_option( INIT_PLUGIN_SUITE_RS_OPTION );
	$enabled = ! empty( $options['auto_reactions_before_comment'] );
	$post_id = get_the_ID();

	// Cho phép override bằng filter (nếu cần).
	$enabled = apply_filters( 'init_reactions_auto_insert_enabled', $enabled, $post_id, $options );
	if ( ! $enabled || ! shortcode_exists( 'init_reactions' ) ) {
		return;
	}

	// Mặc định: id = current post, class rỗng, css=true.
	$atts = array(
		'id'    => $post_id,
		'class' => '',
		'css'   => 'true',
	);

	// Cho phép chỉnh atts trước khi render.
	$atts = apply_filters( 'init_reactions_auto_insert_atts', $atts, $post_id, $options );

	$id    = intval( $atts['id'] ?? $post_id );
	$class = isset( $atts['class'] ) && '' !== $atts['class'] ? ' class="' . esc_attr( $atts['class'] ) . '"' : '';
	$css   = isset( $atts['css'] ) && filter_var( $atts['css'], FILTER_VALIDATE_BOOLEAN ) ? 'true' : 'false';

	echo do_shortcode( sprintf( '[init_reactions id="%d"%s css="%s"]', $id, $class, $css ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Shortcode output is escaped internally.
}
