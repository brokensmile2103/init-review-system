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

// Inject [init_reactions] ngay trước form bình luận nếu bật trong settings
add_action( 'comment_form_before', function () {
    if ( is_admin() || ! is_singular() ) return;

    $options = get_option( INIT_PLUGIN_SUITE_RS_OPTION );
    $enabled = ! empty( $options['auto_reactions_before_comment'] );
    $post_id = get_the_ID();

    // Cho phép override bằng filter (nếu cần)
    $enabled = apply_filters( 'init_reactions_auto_insert_enabled', $enabled, $post_id, $options );
    if ( ! $enabled || ! function_exists( 'do_shortcode' ) || ! function_exists( 'shortcode_exists' ) ) return;
    if ( ! shortcode_exists( 'init_reactions' ) ) return;

    // Mặc định: id = current post, class rỗng, css=true
    $atts = [
        'id'    => $post_id,
        'class' => '',
        'css'   => 'true',
    ];

    // Cho phép chỉnh atts trước khi render
    $atts = apply_filters( 'init_reactions_auto_insert_atts', $atts, $post_id, $options );

    // Render shortcode
    $id    = intval( $atts['id'] ?? $post_id );
    $class = isset( $atts['class'] ) && $atts['class'] !== '' ? ' class="' . esc_attr( $atts['class'] ) . '"' : '';
    $css   = isset( $atts['css'] ) && filter_var( $atts['css'], FILTER_VALIDATE_BOOLEAN ) ? 'true' : 'false';

    echo do_shortcode( sprintf( '[init_reactions id="%d"%s css="%s"]', $id, $class, $css ) );
}, 10 );

add_action('init_review_system_new_review_pending', 'init_review_system_notify_admin_on_pending_review', 10, 2);

function init_review_system_notify_admin_on_pending_review($review_id, $post_id) {
    $admin_email = get_option('admin_email');
    $post_title = get_the_title($post_id);
    $review_url = admin_url('admin.php?page=init-review-management&status=pending');

    $subject = sprintf(__('New Pending Review for %s', 'init-review-system'), $post_title);
    $message = sprintf(
        __('A new review has been submitted for "%s" and is awaiting your approval.', 'init-review-system'),
        $post_title
    );
    $message .= "\n\n";
    $message .= sprintf(
        __('You can manage this review by visiting the following link: %s', 'init-review-system'),
        $review_url
    );

    wp_mail($admin_email, $subject, $message);
}
