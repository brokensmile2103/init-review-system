<?php
defined( 'ABSPATH' ) || exit;

/**
 * Init Review System - Hooks auto insert score & vote blocks
 * Hooked into: the_content, comment_form_before, comment_form_after (tùy cấu hình)
 */

// Check if auto-insert is enabled at given position
function init_plugin_suite_review_system_should_auto_insert( $type, $position ) {
    if ( ! is_singular() || ! in_the_loop() ) return false;

    $options      = get_option( INIT_PLUGIN_SUITE_RS_OPTION, [] );
    $current_type = get_post_type();
    $selected     = $options[ $type . '_position' ] ?? 'none';

    if ( $selected !== $position ) {
        return false;
    }

    // Filter để override ngoài
    return apply_filters(
        "init_plugin_suite_review_system_auto_insert_enabled_{$type}",
        true,
        $position,
        $current_type
    );
}

// Get default shortcode (allow filter override)
function init_plugin_suite_review_system_get_default_shortcode( $type ) {
    if ( $type === 'score' ) {
        return apply_filters(
            'init_plugin_suite_review_system_default_score_shortcode',
            '[init_review_score icon="true"]'
        );
    }

    if ( $type === 'vote' ) {
        return apply_filters(
            'init_plugin_suite_review_system_default_vote_shortcode',
            '[init_review_system]'
        );
    }

    return '';
}

// Inject score block into content
add_filter( 'the_content', function( $content ) {
    if ( init_plugin_suite_review_system_should_auto_insert( 'score', 'before' ) ) {
        $content = do_shortcode( init_plugin_suite_review_system_get_default_shortcode( 'score' ) ) . $content;
    }

    if ( init_plugin_suite_review_system_should_auto_insert( 'score', 'after' ) ) {
        $content .= do_shortcode( init_plugin_suite_review_system_get_default_shortcode( 'score' ) );
    }

    if ( init_plugin_suite_review_system_should_auto_insert( 'vote', 'before' ) ) {
        $content = do_shortcode( init_plugin_suite_review_system_get_default_shortcode( 'vote' ) ) . $content;
    }

    if ( init_plugin_suite_review_system_should_auto_insert( 'vote', 'after' ) ) {
        $content .= do_shortcode( init_plugin_suite_review_system_get_default_shortcode( 'vote' ) );
    }

    return $content;
}, 20 );

add_action( 'comment_form_before', function() {
    if ( init_plugin_suite_review_system_should_auto_insert( 'vote', 'before_comment' ) ) {
        echo do_shortcode( init_plugin_suite_review_system_get_default_shortcode( 'vote' ) );
    }
} );

add_action( 'comment_form_after', function() {
    if ( init_plugin_suite_review_system_should_auto_insert( 'vote', 'after_comment' ) ) {
        echo do_shortcode( init_plugin_suite_review_system_get_default_shortcode( 'vote' ) );
    }
} );
