<?php
/**
 * Dynamic render cho block init-review-system/reactions.
 *
 * $attributes, $content, $block được WordPress tự inject khi dùng "render"
 * trong block.json (file được include bên trong scope của một hàm).
 *
 * @package InitReviewSystem
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$init_plugin_suite_rs_atts = array(
	'class' => isset( $attributes['htmlClass'] ) ? (string) $attributes['htmlClass'] : '',
	'css'   => ( isset( $attributes['loadCss'] ) && ! $attributes['loadCss'] ) ? 'false' : 'true',
);

if ( ! empty( $attributes['postId'] ) ) {
	$init_plugin_suite_rs_atts['id'] = (string) absint( $attributes['postId'] );
}

// init_plugin_suite_review_system_shortcode_reactions() renders the exact
// same markup as [init_reactions] and already escapes everything internally
// (it includes reactions-bar.php, same as the shortcode does) — no extra
// wrapper here.
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
echo init_plugin_suite_review_system_shortcode_reactions( $init_plugin_suite_rs_atts );
