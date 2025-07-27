<?php
defined( 'ABSPATH' ) || exit;

// Register settings
add_action( 'admin_init', 'init_plugin_suite_review_system_register_settings' );
function init_plugin_suite_review_system_register_settings() {
	register_setting(
		INIT_PLUGIN_SUITE_RS_OPTION,
		INIT_PLUGIN_SUITE_RS_OPTION,
		[
			'sanitize_callback' => 'init_plugin_suite_review_system_sanitize_options',
			'default' => [
			    'require_login'       	=> false,
			    'strict_ip_check'     	=> false,
			    'score_position'      	=> 'none',
			    'vote_position'       	=> 'none',
			    'criteria_1' 			=> '',
			    'criteria_2' 			=> '',
			    'criteria_3' 			=> '',
			    'criteria_4' 			=> '',
			    'criteria_5' 			=> '',
			]
		]
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

	add_settings_field(
	    'require_login',
	    __( 'Require login to vote', 'init-review-system' ),
	    'init_plugin_suite_review_system_field_require_login',
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
	        [ 'index' => $i ]
	    );
	}
}

// Sanitize logic
function init_plugin_suite_review_system_sanitize_options( $input ) {
	$output = [];

	$output['require_login']   = ! empty( $input['require_login'] );
	$output['strict_ip_check'] = ! empty( $input['strict_ip_check'] );

	foreach ( ['score_position', 'vote_position'] as $key ) {
		$val = $input[ $key ] ?? 'none';

		$valid = ( $key === 'vote_position' )
		    ? ['none', 'before', 'after', 'before_comment', 'after_comment']
		    : ['none', 'before', 'after'];

		$output[ $key ] = in_array( $val, $valid, true ) ? $val : 'none';
	}

	for ( $i = 1; $i <= 5; $i++ ) {
	    $val = sanitize_text_field( $input[ "criteria_$i" ] ?? '' );
	    $output[ "criteria_$i" ] = $val;
	}

	return $output;
}

// Field: require_login (checkbox)
function init_plugin_suite_review_system_field_require_login() {
    $options = get_option( INIT_PLUGIN_SUITE_RS_OPTION );
    $current = ! empty( $options['require_login'] );

    echo '<label><input type="checkbox" name="' . esc_attr( INIT_PLUGIN_SUITE_RS_OPTION ) . '[require_login]" value="1" ' . checked( $current, true, false ) . '> ';
    esc_html_e( 'Only allow logged-in users to vote.', 'init-review-system' );
    echo '</label>';
}

// Field: strict_ip_check (checkbox)
function init_plugin_suite_review_system_field_strict_ip_check() {
    $options = get_option( INIT_PLUGIN_SUITE_RS_OPTION );
    $current = ! empty( $options['strict_ip_check'] );

    echo '<label><input type="checkbox" name="' . esc_attr( INIT_PLUGIN_SUITE_RS_OPTION ) . '[strict_ip_check]" value="1" ' . checked( $current, true, false ) . '> ';
    esc_html_e( 'Prevent multiple votes from the same IP (via hashed IP & transient).', 'init-review-system' );
    echo '</label>';
}

// Field: score_position (radio)
function init_plugin_suite_review_system_field_score_position() {
	$options = get_option( INIT_PLUGIN_SUITE_RS_OPTION );
	$current = $options['score_position'] ?? 'none';

	init_plugin_suite_review_system_render_radio_group( 'score_position', $current, [
		'none'   => __( 'No (manual shortcode only)', 'init-review-system' ),
		'before' => __( 'Before content', 'init-review-system' ),
		'after'  => __( 'After content', 'init-review-system' ),
	], __( 'Auto-display average score at selected position.', 'init-review-system' ) );
}

// Field: vote_position (radio)
function init_plugin_suite_review_system_field_vote_position() {
	$options = get_option( INIT_PLUGIN_SUITE_RS_OPTION );
	$current = $options['vote_position'] ?? 'none';

	init_plugin_suite_review_system_render_radio_group( 'vote_position', $current, [
	    'none'           => __( 'No (manual shortcode only)', 'init-review-system' ),
	    'before'         => __( 'Before content', 'init-review-system' ),
	    'after'          => __( 'After content', 'init-review-system' ),
	    'before_comment' => __( 'Before comment form', 'init-review-system' ),
	    'after_comment'  => __( 'After comment form', 'init-review-system' ),
	], __( 'Auto-display star rating system at selected position.', 'init-review-system' ) );
}

// Reusable radio group
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

function init_plugin_suite_review_system_field_criteria( $args ) {
    $i = (int) $args['index'];
    $options = get_option( INIT_PLUGIN_SUITE_RS_OPTION );
    $value = $options[ "criteria_$i" ] ?? '';
    printf(
        '<input type="text" name="%1$s[criteria_%2$s]" value="%3$s" class="regular-text" placeholder="%4$s">',
        esc_attr( INIT_PLUGIN_SUITE_RS_OPTION ),
        esc_attr( $i ),
        esc_attr( $value ),
        esc_attr__( 'Leave blank to disable', 'init-review-system' )
    );
}

// Add to admin menu
add_action( 'admin_menu', 'init_plugin_suite_review_system_add_settings_page' );
function init_plugin_suite_review_system_add_settings_page() {
	add_options_page(
		__( 'Init Review System Settings', 'init-review-system' ),
		__( 'Init Review System', 'init-review-system' ),
		'manage_options',
		INIT_PLUGIN_SUITE_RS_SLUG,
		'init_plugin_suite_review_system_render_settings_page'
	);
}

// Render UI
function init_plugin_suite_review_system_render_settings_page() {
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Init Review System Settings', 'init-review-system' ); ?></h1>
		<form method="post" action="options.php">
			<?php
			settings_fields( INIT_PLUGIN_SUITE_RS_OPTION );
			do_settings_sections( INIT_PLUGIN_SUITE_RS_SLUG );
			submit_button();
			?>
		</form>
		<h2><?php esc_html_e('Shortcode Builder', 'init-review-system'); ?></h2>
        <div id="shortcode-builder-target" data-plugin="init-review-system"></div>
	</div>
	<?php
}
