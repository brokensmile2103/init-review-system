<?php
/**
 * Dynamic render cho block init-review-system/review-score.
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
	'icon'          => ( ! empty( $attributes['showIcon'] ) ) ? 'true' : 'false',
	'sub'           => ( isset( $attributes['showSub'] ) && ! $attributes['showSub'] ) ? 'false' : 'true',
	'show_count'    => ( ! empty( $attributes['showCount'] ) ) ? 'true' : 'false',
	'class'         => isset( $attributes['htmlClass'] ) ? (string) $attributes['htmlClass'] : '',
	'hide_if_empty' => ( ! empty( $attributes['hideIfEmpty'] ) ) ? 'true' : 'false',
);

if ( ! empty( $attributes['postId'] ) ) {
	$init_plugin_suite_rs_atts['id'] = (string) absint( $attributes['postId'] );
}

// init_plugin_suite_review_system_shortcode_score() renders the exact same
// markup as [init_review_score] and already escapes every attribute
// internally — no wrapper is added here so the block's frontend output is
// byte-for-byte identical to the shortcode's.
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
echo init_plugin_suite_review_system_shortcode_score( $init_plugin_suite_rs_atts );
