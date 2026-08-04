<?php
defined( 'ABSPATH' ) || exit;

// Abilities API (WordPress 6.9+). Bail out silently on older versions so this
// file is always safe to require regardless of the host site's WP version.
if ( ! function_exists( 'wp_register_ability' ) ) {
    return;
}

// Register the ability category used by this plugin.
add_action( 'wp_abilities_api_categories_init', 'init_plugin_suite_review_system_register_ability_categories' );
function init_plugin_suite_review_system_register_ability_categories() {
    if ( function_exists( 'wp_has_ability_category' ) && wp_has_ability_category( 'init-review-system' ) ) {
        return;
    }

    wp_register_ability_category(
        'init-review-system',
        [
            'label'       => __( 'Init Review System', 'init-review-system' ),
            'description' => __( 'Abilities exposed by the Init Review System plugin.', 'init-review-system' ),
        ]
    );
}

// Register the abilities themselves. All 3 are read-only: they surface the
// same data as [init_review_score], [init_review_criteria], and
// [init_reactions] (or their REST equivalents). Actions that write data
// (vote, submit-criteria-review, reactions/toggle) are intentionally NOT
// exposed as abilities, since an ability may be discovered and invoked
// directly by an AI agent or automation tool — voting/reviewing/reacting on
// a visitor's behalf is not something that should happen without the
// visitor's own, in-context action.
add_action( 'wp_abilities_api_init', 'init_plugin_suite_review_system_register_abilities' );
function init_plugin_suite_review_system_register_abilities() {
    wp_register_ability(
        'init-review-system/get-review-score',
        [
            'label'               => __( 'Get Review Score', 'init-review-system' ),
            'description'         => __( 'Returns the average 5-star score and vote count for a post, the same data shown by the [init_review_score] shortcode.', 'init-review-system' ),
            'category'            => 'init-review-system',
            'input_schema'        => [
                'type'       => 'object',
                'properties' => [
                    'post_id' => [
                        'type'        => 'integer',
                        'description' => __( 'The post ID to get the review score for.', 'init-review-system' ),
                        'minimum'     => 1,
                    ],
                ],
                'required'   => [ 'post_id' ],
            ],
            'output_schema'       => [
                'type'       => 'object',
                'properties' => [
                    'post_id'   => [ 'type' => 'integer' ],
                    'average'   => [ 'type' => 'number' ],
                    'total'     => [ 'type' => 'integer' ],
                    'total_raw' => [ 'type' => 'number' ],
                    'max'       => [ 'type' => 'integer' ],
                ],
            ],
            'execute_callback'    => 'init_plugin_suite_review_system_ability_get_review_score',
            'permission_callback' => '__return_true',
            'meta'                => [
                'show_in_rest' => true,
                'annotations'  => [
                    'readonly' => true,
                ],
            ],
        ]
    );

    wp_register_ability(
        'init-review-system/get-criteria-reviews',
        [
            'label'               => __( 'Get Criteria Reviews', 'init-review-system' ),
            'description'         => __( 'Returns the multi-criteria score breakdown and a page of written reviews for a post, the same data used by the [init_review_criteria] shortcode.', 'init-review-system' ),
            'category'            => 'init-review-system',
            'input_schema'        => [
                'type'       => 'object',
                'properties' => [
                    'post_id'  => [
                        'type'        => 'integer',
                        'description' => __( 'The post ID to get reviews for.', 'init-review-system' ),
                        'minimum'     => 1,
                    ],
                    'page'     => [
                        'type'        => 'integer',
                        'description' => __( 'Page number of reviews to return.', 'init-review-system' ),
                        'default'     => 1,
                        'minimum'     => 1,
                    ],
                    'per_page' => [
                        'type'        => 'integer',
                        'description' => __( 'Number of reviews per page (max 100).', 'init-review-system' ),
                        'default'     => 10,
                        'minimum'     => 1,
                        'maximum'     => 100,
                    ],
                ],
                'required'   => [ 'post_id' ],
            ],
            'output_schema'       => [
                'type'       => 'object',
                'properties' => [
                    'post_id'  => [ 'type' => 'integer' ],
                    'page'     => [ 'type' => 'integer' ],
                    'per_page' => [ 'type' => 'integer' ],
                    'total'    => [ 'type' => 'integer' ],
                    'max_page' => [ 'type' => 'integer' ],
                    'criteria' => [
                        'type'  => 'array',
                        'items' => [ 'type' => 'string' ],
                    ],
                    'reviews'  => [
                        'type'  => 'array',
                        'items' => [
                            'type'       => 'object',
                            'properties' => [
                                'id'              => [ 'type' => 'integer' ],
                                'post_id'         => [ 'type' => 'integer' ],
                                'user_id'         => [ 'type' => 'integer' ],
                                'display_name'    => [ 'type' => 'string' ],
                                'avatar_url'      => [ 'type' => 'string' ],
                                'criteria_scores' => [ 'type' => 'object' ],
                                'avg_score'       => [ 'type' => 'number' ],
                                'review_content'  => [ 'type' => 'string' ],
                                'created_at'      => [ 'type' => 'string' ],
                            ],
                        ],
                    ],
                ],
            ],
            'execute_callback'    => 'init_plugin_suite_review_system_ability_get_criteria_reviews',
            'permission_callback' => '__return_true',
            'meta'                => [
                'show_in_rest' => true,
                'annotations'  => [
                    'readonly' => true,
                ],
            ],
        ]
    );

    wp_register_ability(
        'init-review-system/get-reactions-summary',
        [
            'label'               => __( 'Get Reactions Summary', 'init-review-system' ),
            'description'         => __( 'Returns emoji reaction counts for a post, the same data used by the [init_reactions] shortcode.', 'init-review-system' ),
            'category'            => 'init-review-system',
            'input_schema'        => [
                'type'       => 'object',
                'properties' => [
                    'post_id' => [
                        'type'        => 'integer',
                        'description' => __( 'The post ID to get reactions for.', 'init-review-system' ),
                        'minimum'     => 1,
                    ],
                ],
                'required'   => [ 'post_id' ],
            ],
            'output_schema'       => [
                'type'       => 'object',
                'properties' => [
                    'post_id'       => [ 'type' => 'integer' ],
                    'counts'        => [ 'type' => 'object' ],
                    'user_reaction' => [ 'type' => 'string' ],
                    'types'         => [ 'type' => 'object' ],
                ],
            ],
            'execute_callback'    => 'init_plugin_suite_review_system_ability_get_reactions_summary',
            'permission_callback' => '__return_true',
            'meta'                => [
                'show_in_rest' => true,
                'annotations'  => [
                    'readonly' => true,
                ],
            ],
        ]
    );
}

// Execute callback for init-review-system/get-review-score.
function init_plugin_suite_review_system_ability_get_review_score( $input ) {
    $post_id = isset( $input['post_id'] ) ? absint( $input['post_id'] ) : 0;

    if ( ! $post_id || ! get_post( $post_id ) ) {
        return new WP_Error(
            'init_review_system_invalid_post_id',
            __( 'A valid post ID is required.', 'init-review-system' )
        );
    }

    $data = init_plugin_suite_review_system_get_rating_data( $post_id );

    return [
        'post_id'   => $post_id,
        'average'   => $data['average'],
        'total'     => $data['total'],
        'total_raw' => $data['total_raw'],
        'max'       => $data['max'],
    ];
}

// Execute callback for init-review-system/get-criteria-reviews.
function init_plugin_suite_review_system_ability_get_criteria_reviews( $input ) {
    $post_id  = isset( $input['post_id'] ) ? absint( $input['post_id'] ) : 0;
    $page     = isset( $input['page'] ) ? absint( $input['page'] ) : 1;
    $per_page = isset( $input['per_page'] ) ? absint( $input['per_page'] ) : 10;

    $data = init_plugin_suite_review_system_get_criteria_reviews_data( $post_id, $page, $per_page );

    if ( is_wp_error( $data ) ) {
        return $data;
    }

    return $data;
}

// Execute callback for init-review-system/get-reactions-summary.
function init_plugin_suite_review_system_ability_get_reactions_summary( $input ) {
    $post_id = isset( $input['post_id'] ) ? absint( $input['post_id'] ) : 0;

    $data = init_plugin_suite_review_system_get_reactions_summary_data( $post_id );

    if ( is_wp_error( $data ) ) {
        return $data;
    }

    return $data;
}
