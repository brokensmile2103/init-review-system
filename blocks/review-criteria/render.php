<?php
// Dynamic render cho block init-review-system/review-criteria.
// $attributes, $content, $block được WordPress tự inject khi dùng "render" trong block.json.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$atts = [
    'class'    => isset( $attributes['htmlClass'] ) ? (string) $attributes['htmlClass'] : '',
    'schema'   => ( ! empty( $attributes['showSchema'] ) ) ? 'true' : 'false',
    'per_page' => isset( $attributes['perPage'] ) ? (string) absint( $attributes['perPage'] ) : '0',
    'paged'    => isset( $attributes['paged'] ) ? (string) max( 1, absint( $attributes['paged'] ) ) : '1',
];

if ( ! empty( $attributes['postId'] ) ) {
    $atts['id'] = (string) absint( $attributes['postId'] );
}

// init_plugin_suite_review_system_shortcode_criteria() renders the exact
// same markup as [init_review_criteria] and already escapes everything
// internally (it includes review-criteria-display.php, same as the
// shortcode does) — no extra wrapper here.
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
echo init_plugin_suite_review_system_shortcode_criteria( $atts );
