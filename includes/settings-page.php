<?php
/**
 * Trang cài đặt của plugin.
 *
 * @package InitReviewSystem
 */

defined( 'ABSPATH' ) || exit;

add_action( 'admin_menu', 'init_plugin_suite_review_system_add_settings_page' );

/**
 * Add settings menu.
 *
 * @return void
 */
function init_plugin_suite_review_system_add_settings_page() {
	// Main menu page.
	add_menu_page(
		__( 'Init Review System', 'init-review-system' ),
		__( 'Review System', 'init-review-system' ),
		'manage_options',
		INIT_PLUGIN_SUITE_RS_SLUG,
		'init_plugin_suite_review_system_render_settings_page',
		'dashicons-star-filled',
		102
	);

	// Settings submenu (same as main page).
	add_submenu_page(
		INIT_PLUGIN_SUITE_RS_SLUG,
		__( 'Settings', 'init-review-system' ),
		__( 'Settings', 'init-review-system' ),
		'manage_options',
		INIT_PLUGIN_SUITE_RS_SLUG,
		'init_plugin_suite_review_system_render_settings_page'
	);
}

add_action( 'admin_init', 'init_plugin_suite_review_system_register_settings' );

/**
 * Register settings, sections and fields.
 *
 * @return void
 */
function init_plugin_suite_review_system_register_settings() {
	register_setting(
		INIT_PLUGIN_SUITE_RS_OPTION,
		INIT_PLUGIN_SUITE_RS_OPTION,
		array(
			'sanitize_callback' => 'init_plugin_suite_review_system_sanitize_options',
			'default'           => array(
				'require_login'                 => false,
				'double_click_to_rate'          => false,
				'strict_ip_check'               => false,
				'score_position'                => 'none',
				'vote_position'                 => 'none',
				'auto_reactions_before_comment' => false,
				'criteria_1'                    => '',
				'criteria_2'                    => '',
				'criteria_3'                    => '',
				'criteria_4'                    => '',
				'criteria_5'                    => '',
				// ===== NEW: Moderation defaults =====
				'js_precheck_enabled'           => false,
				'banned_words'                  => '',
				'banned_phrases'                => '',
			),
		)
	);

	add_settings_section(
		'init_plugin_suite_review_system_general',
		__( 'General Settings', 'init-review-system' ),
		'__return_false',
		INIT_PLUGIN_SUITE_RS_SLUG
	);

	add_settings_section(
		'init_plugin_suite_review_system_criteria',
		__( 'Multi-Criteria Review', 'init-review-system' ),
		'__return_false',
		INIT_PLUGIN_SUITE_RS_SLUG
	);

	// ===== NEW: Moderation section =====
	add_settings_section(
		'init_plugin_suite_review_system_moderation',
		__( 'Content Quality Guard', 'init-review-system' ),
		function () {
			echo '<p class="description">' . esc_html__( 'Pre-submit JS checks and banned words configuration (settings only).', 'init-review-system' ) . '</p>';
		},
		INIT_PLUGIN_SUITE_RS_SLUG
	);

	add_settings_field(
		'require_login',
		__( 'Require login to vote', 'init-review-system' ),
		'init_plugin_suite_review_system_field_require_login',
		INIT_PLUGIN_SUITE_RS_SLUG,
		'init_plugin_suite_review_system_general'
	);

	add_settings_field(
		'double_click_to_rate',
		__( 'Require double-click to rate', 'init-review-system' ),
		'init_plugin_suite_review_system_field_double_click_to_rate',
		INIT_PLUGIN_SUITE_RS_SLUG,
		'init_plugin_suite_review_system_general'
	);

	add_settings_field(
		'strict_ip_check',
		__( 'Enable strict IP check', 'init-review-system' ),
		'init_plugin_suite_review_system_field_strict_ip_check',
		INIT_PLUGIN_SUITE_RS_SLUG,
		'init_plugin_suite_review_system_general'
	);

	add_settings_field(
		'auto_reactions_before_comment',
		__( 'Auto-insert Reactions Bar (before comment form)', 'init-review-system' ),
		'init_plugin_suite_review_system_field_auto_reactions_before_comment',
		INIT_PLUGIN_SUITE_RS_SLUG,
		'init_plugin_suite_review_system_general'
	);

	add_settings_field(
		'score_position',
		__( 'Display score position', 'init-review-system' ),
		'init_plugin_suite_review_system_field_score_position',
		INIT_PLUGIN_SUITE_RS_SLUG,
		'init_plugin_suite_review_system_general'
	);

	add_settings_field(
		'vote_position',
		__( 'Display review system', 'init-review-system' ),
		'init_plugin_suite_review_system_field_vote_position',
		INIT_PLUGIN_SUITE_RS_SLUG,
		'init_plugin_suite_review_system_general'
	);

	for ( $i = 1; $i <= 5; $i++ ) {
		add_settings_field(
			"criteria_$i",
			// translators: %d is the index number of the review criteria.
			sprintf( __( 'Criteria #%d', 'init-review-system' ), $i ),
			'init_plugin_suite_review_system_field_criteria',
			INIT_PLUGIN_SUITE_RS_SLUG,
			'init_plugin_suite_review_system_criteria',
			array( 'index' => $i )
		);
	}

	// ===== NEW: Fields in Moderation section =====
	add_settings_field(
		'js_precheck_enabled',
		__( 'Enable JS pre-submit checks', 'init-review-system' ),
		'init_plugin_suite_review_system_field_js_precheck_enabled',
		INIT_PLUGIN_SUITE_RS_SLUG,
		'init_plugin_suite_review_system_moderation'
	);

	add_settings_field(
		'banned_words',
		__( 'Banned words (exact match, one per line)', 'init-review-system' ),
		'init_plugin_suite_review_system_field_banned_words',
		INIT_PLUGIN_SUITE_RS_SLUG,
		'init_plugin_suite_review_system_moderation'
	);

	add_settings_field(
		'banned_phrases',
		__( 'Banned phrases (substring, one per line)', 'init-review-system' ),
		'init_plugin_suite_review_system_field_banned_phrases',
		INIT_PLUGIN_SUITE_RS_SLUG,
		'init_plugin_suite_review_system_moderation'
	);
}

/**
 * Sanitize logic.
 *
 * @param mixed $input Dữ liệu gửi lên từ form.
 * @return array
 */
function init_plugin_suite_review_system_sanitize_options( $input ) {
	$input  = is_array( $input ) ? $input : array();
	$output = array();

	$output['require_login']                 = ! empty( $input['require_login'] );
	$output['double_click_to_rate']          = ! empty( $input['double_click_to_rate'] );
	$output['strict_ip_check']               = ! empty( $input['strict_ip_check'] );
	$output['auto_reactions_before_comment'] = ! empty( $input['auto_reactions_before_comment'] );

	foreach ( array( 'score_position', 'vote_position' ) as $key ) {
		$val = $input[ $key ] ?? 'none';

		$valid = ( 'vote_position' === $key )
			? array( 'none', 'before', 'after', 'before_comment', 'after_comment' )
			: array( 'none', 'before', 'after' );

		$output[ $key ] = in_array( $val, $valid, true ) ? $val : 'none';
	}

	for ( $i = 1; $i <= 5; $i++ ) {
		$val                     = sanitize_text_field( $input[ "criteria_$i" ] ?? '' );
		$output[ "criteria_$i" ] = $val;
	}

	// Moderation fields (lưu dạng text, được parse khi kiểm duyệt).
	$output['js_precheck_enabled'] = ! empty( $input['js_precheck_enabled'] );

	// Normalize line endings and strip tags; keep as plain text list.
	$banned_words   = isset( $input['banned_words'] ) ? sanitize_textarea_field( $input['banned_words'] ) : '';
	$banned_phrases = isset( $input['banned_phrases'] ) ? sanitize_textarea_field( $input['banned_phrases'] ) : '';

	$output['banned_words']   = str_replace( array( "\r\n", "\r" ), "\n", $banned_words );
	$output['banned_phrases'] = str_replace( array( "\r\n", "\r" ), "\n", $banned_phrases );

	return $output;
}

/**
 * Field: auto_reactions_before_comment (checkbox).
 *
 * @return void
 */
function init_plugin_suite_review_system_field_auto_reactions_before_comment() {
	$options = get_option( INIT_PLUGIN_SUITE_RS_OPTION );
	$current = ! empty( $options['auto_reactions_before_comment'] );

	echo '<label><input type="checkbox" name="' . esc_attr( INIT_PLUGIN_SUITE_RS_OPTION ) . '[auto_reactions_before_comment]" value="1" ' . checked( $current, true, false ) . '> ';
	esc_html_e( 'Automatically insert the Reactions Bar right before the comment form.', 'init-review-system' );
	echo '</label>';
	echo '<p class="description" style="margin-top:4px;">' . esc_html__( 'Use this if you want a global injection without editing templates.', 'init-review-system' ) . '</p>';
}

/**
 * Field: require_login (checkbox).
 *
 * @return void
 */
function init_plugin_suite_review_system_field_require_login() {
	$options = get_option( INIT_PLUGIN_SUITE_RS_OPTION );
	$current = ! empty( $options['require_login'] );

	echo '<label><input type="checkbox" name="' . esc_attr( INIT_PLUGIN_SUITE_RS_OPTION ) . '[require_login]" value="1" ' . checked( $current, true, false ) . '> ';
	esc_html_e( 'Only allow logged-in users to vote.', 'init-review-system' );
	echo '</label>';
}

/**
 * Field: double_click_to_rate (checkbox).
 *
 * @return void
 */
function init_plugin_suite_review_system_field_double_click_to_rate() {
	$options = get_option( INIT_PLUGIN_SUITE_RS_OPTION );
	$current = ! empty( $options['double_click_to_rate'] );

	echo '<label><input type="checkbox" name="' . esc_attr( INIT_PLUGIN_SUITE_RS_OPTION ) . '[double_click_to_rate]" value="1" ' . checked( $current, true, false ) . '> ';
	esc_html_e( 'Users must double-click a star to confirm rating.', 'init-review-system' );
	echo '</label>';
	echo '<p class="description" style="margin-top:4px;">' . esc_html__( 'Helps prevent accidental ratings/misclicks.', 'init-review-system' ) . '</p>';
}

/**
 * Field: strict_ip_check (checkbox).
 *
 * @return void
 */
function init_plugin_suite_review_system_field_strict_ip_check() {
	$options = get_option( INIT_PLUGIN_SUITE_RS_OPTION );
	$current = ! empty( $options['strict_ip_check'] );

	echo '<label><input type="checkbox" name="' . esc_attr( INIT_PLUGIN_SUITE_RS_OPTION ) . '[strict_ip_check]" value="1" ' . checked( $current, true, false ) . '> ';
	esc_html_e( 'Prevent multiple votes from the same IP (via hashed IP & transient).', 'init-review-system' );
	echo '</label>';
}

/**
 * Field: score_position (radio).
 *
 * @return void
 */
function init_plugin_suite_review_system_field_score_position() {
	$options = get_option( INIT_PLUGIN_SUITE_RS_OPTION );
	$current = $options['score_position'] ?? 'none';

	init_plugin_suite_review_system_render_radio_group(
		'score_position',
		$current,
		array(
			'none'   => __( 'No (manual shortcode only)', 'init-review-system' ),
			'before' => __( 'Before content', 'init-review-system' ),
			'after'  => __( 'After content', 'init-review-system' ),
		),
		__( 'Auto-display average score at selected position.', 'init-review-system' )
	);
}

/**
 * Field: vote_position (radio).
 *
 * @return void
 */
function init_plugin_suite_review_system_field_vote_position() {
	$options = get_option( INIT_PLUGIN_SUITE_RS_OPTION );
	$current = $options['vote_position'] ?? 'none';

	init_plugin_suite_review_system_render_radio_group(
		'vote_position',
		$current,
		array(
			'none'           => __( 'No (manual shortcode only)', 'init-review-system' ),
			'before'         => __( 'Before content', 'init-review-system' ),
			'after'          => __( 'After content', 'init-review-system' ),
			'before_comment' => __( 'Before comment form', 'init-review-system' ),
			'after_comment'  => __( 'After comment form', 'init-review-system' ),
		),
		__( 'Auto-display star rating system at selected position.', 'init-review-system' )
	);
}

/**
 * Reusable radio group.
 *
 * @param string $field_key     Key của field trong option.
 * @param string $current_value Giá trị hiện tại.
 * @param array  $choices       Map value => label.
 * @param string $description   Mô tả (tuỳ chọn).
 * @return void
 */
function init_plugin_suite_review_system_render_radio_group( $field_key, $current_value, $choices, $description = '' ) {
	echo '<fieldset>';
	foreach ( $choices as $val => $label ) {
		printf(
			'<label style="margin-right: 15px; display: inline-block;"><input type="radio" name="%1$s[%2$s]" value="%3$s"%4$s> %5$s</label><br>',
			esc_attr( INIT_PLUGIN_SUITE_RS_OPTION ),
			esc_attr( $field_key ),
			esc_attr( $val ),
			checked( $current_value, $val, false ),
			esc_html( $label )
		);
	}
	if ( $description ) {
		echo '<p class="description" style="margin-top: 4px;">' . esc_html( $description ) . '</p>';
	}
	echo '</fieldset>';
}

/**
 * Field: criteria_N (text).
 *
 * @param array $args Tham số field (index).
 * @return void
 */
function init_plugin_suite_review_system_field_criteria( $args ) {
	$i       = (int) $args['index'];
	$options = get_option( INIT_PLUGIN_SUITE_RS_OPTION );
	$value   = $options[ "criteria_$i" ] ?? '';
	printf(
		'<input type="text" name="%1$s[criteria_%2$s]" value="%3$s" class="regular-text" placeholder="%4$s">',
		esc_attr( INIT_PLUGIN_SUITE_RS_OPTION ),
		esc_attr( $i ),
		esc_attr( $value ),
		esc_attr__( 'Leave blank to disable', 'init-review-system' )
	);
}

/**
 * Field: js_precheck_enabled (checkbox).
 *
 * @return void
 */
function init_plugin_suite_review_system_field_js_precheck_enabled() {
	$options = get_option( INIT_PLUGIN_SUITE_RS_OPTION );
	$current = ! empty( $options['js_precheck_enabled'] );

	echo '<label><input type="checkbox" name="' . esc_attr( INIT_PLUGIN_SUITE_RS_OPTION ) . '[js_precheck_enabled]" value="1" ' . checked( $current, true, false ) . '> ';
	esc_html_e( 'Run basic client-side checks before submit (e.g., excessive repeated words, missing whitespaces).', 'init-review-system' );
	echo '</label>';
}

/**
 * Field: banned_words (textarea).
 *
 * @return void
 */
function init_plugin_suite_review_system_field_banned_words() {
	$options = get_option( INIT_PLUGIN_SUITE_RS_OPTION );
	$value   = isset( $options['banned_words'] ) ? $options['banned_words'] : '';
	printf(
		'<textarea name="%1$s[banned_words]" rows="6" class="large-text code" placeholder="%2$s">%3$s</textarea>',
		esc_attr( INIT_PLUGIN_SUITE_RS_OPTION ),
		esc_attr__( 'One word per line. Exact match (case-insensitive).', 'init-review-system' ),
		esc_textarea( $value )
	);
}

/**
 * Field: banned_phrases (textarea).
 *
 * @return void
 */
function init_plugin_suite_review_system_field_banned_phrases() {
	$options = get_option( INIT_PLUGIN_SUITE_RS_OPTION );
	$value   = isset( $options['banned_phrases'] ) ? $options['banned_phrases'] : '';
	printf(
		'<textarea name="%1$s[banned_phrases]" rows="6" class="large-text code" placeholder="%2$s">%3$s</textarea>',
		esc_attr( INIT_PLUGIN_SUITE_RS_OPTION ),
		esc_attr__( 'One phrase per line. Substring contains check (case-insensitive).', 'init-review-system' ),
		esc_textarea( $value )
	);
}

/**
 * Render UI.
 *
 * @return void
 */
function init_plugin_suite_review_system_render_settings_page() {
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Init Review System Settings', 'init-review-system' ); ?></h1>
		<?php settings_errors(); ?>
		<form method="post" action="options.php">
			<?php
			settings_fields( INIT_PLUGIN_SUITE_RS_OPTION );
			do_settings_sections( INIT_PLUGIN_SUITE_RS_SLUG );
			submit_button();
			?>
		</form>
		<h2><?php esc_html_e( 'Shortcode Builder', 'init-review-system' ); ?></h2>
		<div id="shortcode-builder-target" data-plugin="init-review-system"></div>
	</div>
	<?php
}
