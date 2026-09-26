<?php
/**
 * Dynamic render cho block init-review-system/review-system.
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
	'class'  => isset( $attributes['htmlClass'] ) ? (string) $attributes['htmlClass'] : '',
	'schema' => ( ! empty( $attributes['showSchema'] ) ) ? 'true' : 'false',
);

if ( ! empty( $attributes['postId'] ) ) {
	$init_plugin_suite_rs_atts['id'] = (string) absint( $attributes['postId'] );
}

// init_plugin_suite_review_system_shortcode_system() renders the exact same
// markup as [init_review_system] and already escapes everything internally
// — no wrapper is added here so the block's frontend output is byte-for-byte
// identical to the shortcode's.
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
echo init_plugin_suite_review_system_shortcode_system( $init_plugin_suite_rs_atts );
