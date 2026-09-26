<?php
/**
 * Abilities API integration (WordPress 6.9+).
 *
 * @package InitReviewSystem
 */

defined( 'ABSPATH' ) || exit;

// Abilities API (WordPress 6.9+). Bail out silently on older versions so this
// file is always safe to require regardless of the host site's WP version.
if ( ! function_exists( 'wp_register_ability' ) ) {
	return;
}

add_action( 'wp_abilities_api_categories_init', 'init_plugin_suite_review_system_register_ability_categories' );

/**
 * Register the ability category used by this plugin.
 *
 * @return void
 */
function init_plugin_suite_review_system_register_ability_categories() {
	if ( function_exists( 'wp_has_ability_category' ) && wp_has_ability_category( 'init-review-system' ) ) {
		return;
	}

	wp_register_ability_category(
		'init-review-system',
		array(
			'label'       => __( 'Init Review System', 'init-review-system' ),
			'description' => __( 'Abilities exposed by the Init Review System plugin.', 'init-review-system' ),
		)
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

/**
 * Register the three read-only abilities.
 *
 * @return void
 */
function init_plugin_suite_review_system_register_abilities() {
	wp_register_ability(
		'init-review-system/get-review-score',
		array(
			'label'               => __( 'Get Review Score', 'init-review-system' ),
			'description'         => __( 'Returns the average 5-star score and vote count for a post, the same data shown by the [init_review_score] shortcode.', 'init-review-system' ),
			'category'            => 'init-review-system',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'post_id' => array(
						'type'        => 'integer',
						'description' => __( 'The post ID to get the review score for.', 'init-review-system' ),
						'minimum'     => 1,
					),
				),
				'required'   => array( 'post_id' ),
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'post_id'   => array( 'type' => 'integer' ),
					'average'   => array( 'type' => 'number' ),
					'total'     => array( 'type' => 'integer' ),
					'total_raw' => array( 'type' => 'number' ),
					'max'       => array( 'type' => 'integer' ),
				),
			),
			'execute_callback'    => 'init_plugin_suite_review_system_ability_get_review_score',
			'permission_callback' => '__return_true',
			'meta'                => array(
				'show_in_rest' => true,
				'annotations'  => array(
					'readonly' => true,
				),
			),
		)
	);

	wp_register_ability(
		'init-review-system/get-criteria-reviews',
		array(
			'label'               => __( 'Get Criteria Reviews', 'init-review-system' ),
			'description'         => __( 'Returns the multi-criteria score breakdown and a page of written reviews for a post, the same data used by the [init_review_criteria] shortcode.', 'init-review-system' ),
			'category'            => 'init-review-system',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'post_id'  => array(
						'type'        => 'integer',
						'description' => __( 'The post ID to get reviews for.', 'init-review-system' ),
						'minimum'     => 1,
					),
					'page'     => array(
						'type'        => 'integer',
						'description' => __( 'Page number of reviews to return.', 'init-review-system' ),
						'default'     => 1,
						'minimum'     => 1,
					),
					'per_page' => array(
						'type'        => 'integer',
						'description' => __( 'Number of reviews per page (max 100).', 'init-review-system' ),
						'default'     => 10,
						'minimum'     => 1,
						'maximum'     => 100,
					),
				),
				'required'   => array( 'post_id' ),
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'post_id'  => array( 'type' => 'integer' ),
					'page'     => array( 'type' => 'integer' ),
					'per_page' => array( 'type' => 'integer' ),
					'total'    => array( 'type' => 'integer' ),
					'max_page' => array( 'type' => 'integer' ),
					'criteria' => array(
						'type'  => 'array',
						'items' => array( 'type' => 'string' ),
					),
					'reviews'  => array(
						'type'  => 'array',
						'items' => array(
							'type'       => 'object',
							'properties' => array(
								'id'              => array( 'type' => 'integer' ),
								'post_id'         => array( 'type' => 'integer' ),
								'user_id'         => array( 'type' => 'integer' ),
								'display_name'    => array( 'type' => 'string' ),
								'avatar_url'      => array( 'type' => 'string' ),
								'criteria_scores' => array( 'type' => 'object' ),
								'avg_score'       => array( 'type' => 'number' ),
								'review_content'  => array( 'type' => 'string' ),
								'created_at'      => array( 'type' => 'string' ),
							),
						),
					),
				),
			),
			'execute_callback'    => 'init_plugin_suite_review_system_ability_get_criteria_reviews',
			'permission_callback' => '__return_true',
			'meta'                => array(
				'show_in_rest' => true,
				'annotations'  => array(
					'readonly' => true,
				),
			),
		)
	);

	wp_register_ability(
		'init-review-system/get-reactions-summary',
		array(
			'label'               => __( 'Get Reactions Summary', 'init-review-system' ),
			'description'         => __( 'Returns emoji reaction counts for a post, the same data used by the [init_reactions] shortcode.', 'init-review-system' ),
			'category'            => 'init-review-system',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'post_id' => array(
						'type'        => 'integer',
						'description' => __( 'The post ID to get reactions for.', 'init-review-system' ),
						'minimum'     => 1,
					),
				),
				'required'   => array( 'post_id' ),
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'post_id'       => array( 'type' => 'integer' ),
					'counts'        => array( 'type' => 'object' ),
					'user_reaction' => array( 'type' => 'string' ),
					'types'         => array( 'type' => 'object' ),
				),
			),
			'execute_callback'    => 'init_plugin_suite_review_system_ability_get_reactions_summary',
			'permission_callback' => '__return_true',
			'meta'                => array(
				'show_in_rest' => true,
				'annotations'  => array(
					'readonly' => true,
				),
			),
		)
	);
}

/**
 * Execute callback for init-review-system/get-review-score.
 *
 * @param array $input Ability input.
 * @return array|WP_Error
 */
function init_plugin_suite_review_system_ability_get_review_score( $input ) {
	$post_id = isset( $input['post_id'] ) ? absint( $input['post_id'] ) : 0;

	if ( ! $post_id || ! init_plugin_suite_review_system_can_view_post( $post_id ) ) {
		return new WP_Error(
			'init_review_system_invalid_post_id',
			__( 'A valid post ID is required.', 'init-review-system' )
		);
	}

	$data = init_plugin_suite_review_system_get_rating_data( $post_id );

	return array(
		'post_id'   => $post_id,
		'average'   => $data['average'],
		'total'     => $data['total'],
		'total_raw' => $data['total_raw'],
		'max'       => $data['max'],
	);
}

/**
 * Execute callback for init-review-system/get-criteria-reviews.
 *
 * @param array $input Ability input.
 * @return array|WP_Error
 */
function init_plugin_suite_review_system_ability_get_criteria_reviews( $input ) {
	$post_id  = isset( $input['post_id'] ) ? absint( $input['post_id'] ) : 0;
	$page     = isset( $input['page'] ) ? absint( $input['page'] ) : 1;
	$per_page = isset( $input['per_page'] ) ? absint( $input['per_page'] ) : 10;

	// Trả về mảng dữ liệu hoặc WP_Error (post không hợp lệ / không được xem).
	return init_plugin_suite_review_system_get_criteria_reviews_data( $post_id, $page, $per_page );
}

/**
 * Execute callback for init-review-system/get-reactions-summary.
 *
 * @param array $input Ability input.
 * @return array|WP_Error
 */
function init_plugin_suite_review_system_ability_get_reactions_summary( $input ) {
	$post_id = isset( $input['post_id'] ) ? absint( $input['post_id'] ) : 0;

	// Trả về mảng dữ liệu hoặc WP_Error (post không hợp lệ / không được xem).
	return init_plugin_suite_review_system_get_reactions_summary_data( $post_id );
}
