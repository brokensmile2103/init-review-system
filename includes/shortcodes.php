<?php
defined( 'ABSPATH' ) || exit;

// Enqueue CSS/JS helper
function init_plugin_suite_review_system_enqueue_assets() {
    if ( wp_script_is( 'init-review-system-script', 'enqueued' ) ) {
        return;
    }

    wp_enqueue_script(
        'init-review-system-script',
        INIT_PLUGIN_SUITE_RS_ASSETS_URL . 'js/script.js',
        [],
        INIT_PLUGIN_SUITE_RS_VERSION,
        true
    );

    $options        = get_option( INIT_PLUGIN_SUITE_RS_OPTION );
    $require_login  = ! empty( $options['require_login'] );
    $is_logged_in   = is_user_logged_in();
    $rest_url       = rest_url( INIT_PLUGIN_SUITE_RS_NAMESPACE );
    $rest_nonce     = wp_create_nonce( 'wp_rest' );

    $current_user_name = $is_logged_in ? wp_get_current_user()->display_name : '';
    $current_user_avatar = $is_logged_in
        ? get_avatar_url( get_current_user_id(), [ 'size' => 80 ] )
        : INIT_PLUGIN_SUITE_RS_ASSETS_URL . '/img/default-avatar.svg';

    wp_localize_script( 'init-review-system-script', 'InitReviewSystemData', [
        'require_login'        => $require_login,
        'is_logged_in'         => $is_logged_in,
        'rest_url'             => $rest_url,
        'nonce'                => $rest_nonce,
        'current_user_name'    => $current_user_name,
        'current_user_avatar'  => $current_user_avatar,
        'assets_url'           => INIT_PLUGIN_SUITE_RS_ASSETS_URL,
        'i18n'                 => [
            'validation_error' => __( 'Please select scores and write a review.', 'init-review-system' ),
            'success'          => __( 'Your review has been submitted!', 'init-review-system' ),
            'error'            => __( 'Submission failed. Please try again later.', 'init-review-system' ),
            'review_label'     => __( 'reviews', 'init-review-system' ),
        ],
    ] );
}

// [init_review_score]
add_shortcode( 'init_review_score', function( $atts ) {
    $atts = shortcode_atts([
        'id'            => get_the_ID(),
        'icon'          => 'false',
        'sub'           => 'true',
        'class'         => '',
        'hide_if_empty' => 'false',
    ], $atts, 'init_review_score' );

    $post_id       = intval( $atts['id'] );
    $icon          = filter_var( $atts['icon'], FILTER_VALIDATE_BOOLEAN );
    $sub           = filter_var( $atts['sub'], FILTER_VALIDATE_BOOLEAN );
    $hide_if_empty = filter_var( $atts['hide_if_empty'], FILTER_VALIDATE_BOOLEAN );
    $class         = sanitize_html_class( $atts['class'] );

    $total = intval( get_post_meta( $post_id, '_init_review_count', true ) ) ?: 0;

    if ( $total === 0 && $hide_if_empty ) {
        return '';
    }

    init_plugin_suite_review_system_enqueue_assets();

    $score = floatval( get_post_meta( $post_id, '_init_review_avg', true ) );
    $score = min( 5, $score ); // Ngăn ghi sai điểm > 5

    $output  = '<span class="init-review-score ' . esc_attr( $class ) . '">';
    if ( $icon ) {
        // SVG mặc định
        $default_svg = '<svg width="20" height="20" viewBox="0 0 20 20" aria-hidden="true">
            <polygon fill="none" stroke="currentColor" stroke-width="1.01" 
                points="10 2 12.63 7.27 18.5 8.12 14.25 12.22 15.25 18 
                        10 15.27 4.75 18 5.75 12.22 1.5 8.12 7.37 7.27"></polygon>
        </svg> ';

        // Cho phép thay thế SVG qua filter
        $icon_svg = apply_filters( 'init_plugin_suite_review_score_star_icon', $default_svg, $post_id, $score );

        $output .= $icon_svg;
    }
    $output .= esc_html( number_format( $score, 1 ) );
    if ( $sub ) {
        $output .= '<sub>/5</sub>';
    }
    $output .= '</span>';

    return $output;
});

// [init_review_system]
add_shortcode( 'init_review_system', function( $atts ) {
    init_plugin_suite_review_system_enqueue_assets();

    $atts = shortcode_atts([
        'id'     => get_the_ID(),
        'class'  => '',
        'schema' => 'false',
    ], $atts, 'init_review_system' );

    $post_id = intval( $atts['id'] );
    $class   = sanitize_html_class( $atts['class'] );
    $schema  = filter_var( $atts['schema'], FILTER_VALIDATE_BOOLEAN );

    $total = intval( get_post_meta( $post_id, '_init_review_count', true ) ) ?: 0;
    $score = floatval( get_post_meta( $post_id, '_init_review_avg', true ) );
    $score = min( 5, $score ); // Giới hạn nếu dữ liệu cũ sai

    $output = '<div class="init-review-system ' . esc_attr( $class ) . '" data-post-id="' . esc_attr( $post_id ) . '" data-max-score="5">';

    $output .= '<div class="init-review-box">';

    // Stars
    $output .= '<div class="init-review-stars">';

    // SVG mặc định (giữ nguyên như trước)
    $default_star_svg = '<svg class="i-star" width="20" height="20" viewBox="0 0 64 64" aria-hidden="true"><path fill="currentColor" d="M63.9 24.28a2 2 0 0 0-1.6-1.35l-19.68-3-8.81-18.78a2 2 0 0 0-3.62 0l-8.82 18.78-19.67 3a2 2 0 0 0-1.13 3.38l14.3 14.66-3.39 20.7a2 2 0 0 0 2.94 2.07L32 54.02l17.57 9.72a2 2 0 0 0 2.12-.11 2 2 0 0 0 .82-1.96l-3.38-20.7 14.3-14.66a2 2 0 0 0 .46-2.03"></path></svg>';

    for ( $i = 1; $i <= 5; $i++ ) {
        // Cho phép thay thế icon ngôi sao qua filter:
        // Hook: init_plugin_suite_review_system_star_icon
        // Args: ($default_svg, $post_id, $score, $i)
        $star_svg = apply_filters( 'init_plugin_suite_review_system_star_icon', $default_star_svg, $post_id, $score, $i );

        $output .= '<span class="star" data-value="' . $i . '">' . $star_svg . '</span>';
    }
    $output .= '</div>';

    // Info
    $output .= '<div class="init-review-info">';
    $output .= '<strong>' . esc_html( number_format( $score, 1 ) ) . '</strong><sub>/5</sub>';
    $output .= ' (' . esc_html( number_format_i18n( $total ) ) . ')';
    $output .= '</div>';

    $output .= '</div>'; // .init-review-box

    // Schema JSON-LD nếu bật
    if ( $schema && $score > 0 && $total > 0 ) {
        $post_type = get_post_type( $post_id );

        $type_map = [
            'product' => 'Product',
            'book'    => 'Book',
            'course'  => 'Course',
            'movie'   => 'Movie',
            'post'    => 'Article',
            'page'    => 'Article',
        ];

        $schema_type = apply_filters( 'init_plugin_suite_review_system_schema_type', $type_map[ $post_type ] ?? 'CreativeWork', $post_type );

        $schema_data = [
            "@context" => "https://schema.org",
            "@type"    => $schema_type,
            "name"     => get_the_title( $post_id ),
            "aggregateRating" => [
                "@type"       => "AggregateRating",
                "ratingValue" => $score,
                "reviewCount" => $total,
                "bestRating"  => 5,
            ]
        ];

        $schema_data = apply_filters( 'init_plugin_suite_review_system_schema_data', $schema_data, $post_id, $schema_type );
        
        $output .= '<script type="application/ld+json">' . wp_json_encode( $schema_data ) . '</script>';
    }

    $output .= '</div>'; // .init-review-system

    return $output;
});

// [init_review_criteria]
add_shortcode('init_review_criteria', function ($atts) {
    init_plugin_suite_review_system_enqueue_assets();

    // Nhận và xử lý các thuộc tính từ shortcode
    $atts = shortcode_atts([
        'id'       => get_the_ID(),
        'class'    => '',
        'schema'   => 'false',
        'per_page' => 0, // Không giới hạn
    ], $atts, 'init_review_criteria');

    $post_id        = intval($atts['id']);
    $class          = sanitize_html_class($atts['class']);
    $schema         = filter_var($atts['schema'], FILTER_VALIDATE_BOOLEAN);
    $per_page       = intval($atts['per_page']);
    $user_logged_in = is_user_logged_in();
    $user_id        = get_current_user_id();

    // Lấy dữ liệu thống kê điểm
    $summary       = init_plugin_suite_review_system_get_score_summary_by_post_id($post_id);
    $total_reviews = init_plugin_suite_review_system_get_total_reviews_by_post_id($post_id);

    $settings       = get_option(INIT_PLUGIN_SUITE_RS_OPTION);
    $require_login  = apply_filters('init_plugin_suite_review_system_require_login', !empty($settings['require_login']));
    $has_reviewed   = $user_logged_in ? init_plugin_suite_review_system_has_user_reviewed($post_id, $user_id) : false;

    $can_review = ($user_logged_in || ! $require_login) && ! $has_reviewed;

    // Chuẩn bị dữ liệu truyền vào template
    $template_data = [
        'post_id'       => $post_id,
        'user_id'       => $user_id,
        'can_review'    => $can_review,
        'class'         => $class,
        'schema'        => $schema,
        'per_page'      => $per_page,
        'summary'       => $summary,
        'total_reviews' => $total_reviews,
        'criteria'      => init_plugin_suite_review_system_get_criteria_labels(),
        'reviews'       => init_plugin_suite_review_system_get_reviews_by_post_id($post_id, 1, $per_page),
    ];

    // Render template
    ob_start();
    init_plugin_suite_review_system_render_template('review-criteria-display.php', $template_data);
    return ob_get_clean();
});

add_action( 'admin_enqueue_scripts', function ( $hook ) {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    if ( $hook !== 'toplevel_page_' . INIT_PLUGIN_SUITE_RS_SLUG ) {
        return;
    }

    // Script builder UI dùng chung (copy, preview, tab, v.v.)
    wp_enqueue_script(
        'init-review-system-shortcode-builder',
        INIT_PLUGIN_SUITE_RS_URL . 'assets/js/init-shortcode-builder.js',
        [],
        INIT_PLUGIN_SUITE_RS_VERSION,
        true
    );

    wp_localize_script(
        'init-review-system-shortcode-builder',
        'InitReviewSystemShortcodeBuilder',
        [
            'i18n' => [
                'copy'              => __( 'Copy', 'init-review-system' ),
                'copied'            => __( 'Copied!', 'init-review-system' ),
                'close'             => __( 'Close', 'init-review-system' ),
                'shortcode_preview' => __( 'Shortcode Preview', 'init-review-system' ),
                'shortcode_builder' => __( 'Shortcode Builder', 'init-review-system' ),
                'init_review_score' => __( 'Review Score', 'init-review-system' ),
                'init_review_system'=> __( 'Init Review System', 'init-review-system' ),
                'icon'              => __( 'Show Icon', 'init-review-system' ),
                'schema'            => __( 'Enable Schema.org', 'init-review-system' ),
                'class'             => __( 'Custom Class', 'init-review-system' ),
                'id'                => __( 'Post ID', 'init-review-system' ),
                'per_page'          => __( 'Posts per page', 'init-review-system' ),
            ],
        ]
    );

    // Script riêng cho phần builder hiển thị trong admin UI
    wp_enqueue_script(
        'init-review-system-admin-shortcode-panel',
        INIT_PLUGIN_SUITE_RS_URL . 'assets/js/shortcodes.js',
        [ 'init-review-system-shortcode-builder' ],
        INIT_PLUGIN_SUITE_RS_VERSION,
        true
    );
} );

/**
 * [init_reactions]
 * - id: Post ID (mặc định get_the_ID())
 * - class: thêm class ngoài
 * - css: "true" | "false"  (mặc định true) → auto enqueue assets/css/reactions.css
 */
add_shortcode('init_reactions', function ($atts) {
    $atts = shortcode_atts([
        'id'    => get_the_ID(),
        'class' => '',
        'css'   => 'true',
    ], $atts, 'init_reactions');

    $post_id = intval($atts['id']);
    if ( ! $post_id ) return '';

    // Đảm bảo object InitReviewSystemData được set nếu script chính đã/đang dùng
    init_plugin_suite_review_system_enqueue_assets();

    // Bật/tắt CSS
    $use_css = filter_var($atts['css'], FILTER_VALIDATE_BOOLEAN);
    if ( $use_css ) {
        wp_enqueue_style(
            'init-review-system-reactions',
            INIT_PLUGIN_SUITE_RS_ASSETS_URL . 'css/reactions.css',
            [],
            INIT_PLUGIN_SUITE_RS_VERSION
        );
    }

    // Enqueue JS cho reactions
    wp_enqueue_script(
        'init-review-system-reactions',
        INIT_PLUGIN_SUITE_RS_ASSETS_URL . 'js/reactions.js',
        [],
        INIT_PLUGIN_SUITE_RS_VERSION,
        true
    );

    // Luôn yêu cầu đăng nhập
    $require_login = true;
    $is_logged_in  = is_user_logged_in();
    $rest_url      = rest_url(INIT_PLUGIN_SUITE_RS_NAMESPACE);
    $rest_nonce    = wp_create_nonce('wp_rest');

    // Localize cho reactions.js
    wp_localize_script('init-review-system-reactions', 'InitReviewReactionsData', [
        'require_login' => $require_login,
        'is_logged_in'  => $is_logged_in,
        'rest_url'      => $rest_url,
        'nonce'         => $rest_nonce,
        'assets_url'    => INIT_PLUGIN_SUITE_RS_ASSETS_URL,
    ]);

    // Data cho template
    $types  = init_plugin_suite_review_system_get_reaction_types();
    $counts = init_plugin_suite_review_system_get_reaction_counts($post_id);

    $data = [
        'post_id'       => $post_id,
        'class'         => sanitize_html_class($atts['class']),
        'types'         => $types,
        'counts'        => $counts,
        'require_login' => $require_login,
        'is_logged_in'  => $is_logged_in,
    ];

    ob_start();
    init_plugin_suite_review_system_render_template('reactions-bar.php', $data);
    return ob_get_clean();
});
