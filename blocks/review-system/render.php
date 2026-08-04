<?php
// Dynamic render cho block init-review-system/review-system.
// $attributes, $content, $block được WordPress tự inject khi dùng "render" trong block.json.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$atts = [
    'class'  => isset( $attributes['htmlClass'] ) ? (string) $attributes['htmlClass'] : '',
    'schema' => ( ! empty( $attributes['showSchema'] ) ) ? 'true' : 'false',
];

if ( ! empty( $attributes['postId'] ) ) {
    $atts['id'] = (string) absint( $attributes['postId'] );
}

// init_plugin_suite_review_system_shortcode_system() renders the exact same
// markup as [init_review_system] and already escapes everything internally
// — no wrapper is added here so the block's frontend output is byte-for-byte
// identical to the shortcode's.
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
echo init_plugin_suite_review_system_shortcode_system( $atts );
