<?php
/**
 * Template: Reactions Bar
 * Vars:
 * - $post_id
 * - $class
 * - $types (key => [label, emoji])
 * - $counts (key => int)
 * - $require_login (bool)
 * - $is_logged_in (bool)
 */

defined('ABSPATH') || exit;

$wrapper_class = trim('init-reaction-bar ' . ($class ?? ''));

$user_rx = '';
if ( ! empty( $is_logged_in ) ) {
    $user_rx = init_plugin_suite_review_system_get_user_reaction( $post_id, get_current_user_id() );
}
?>
<div class="<?php echo esc_attr($wrapper_class); ?>"
     data-post-id="<?php echo esc_attr($post_id); ?>"
     data-user-rx="<?php echo esc_attr($user_rx); ?>">

    <div class="init-reaction-title">
        <?php echo esc_html__('What do you think?', 'init-review-system'); ?>
    </div>

    <?php
    // === Total reactions (inline)
    $total_reactions = 0;
    if ( ! empty( $counts ) && is_array( $counts ) ) {
        foreach ( $counts as $c ) {
            $total_reactions += max( 0, (int) $c );
        }
    }
    $formatted_total = number_format_i18n( (int) $total_reactions );

    $label_with_number = sprintf(
        _n( '%s reaction', '%s reactions', (int) $total_reactions, 'init-review-system' ),
        '<span id="irs-total-reactions-' . esc_attr( $post_id ) . '">' . esc_html( $formatted_total ) . '</span>'
    );

    echo '<div class="init-reaction-total" aria-live="polite">';
    echo wp_kses( $label_with_number, [ 'span' => [ 'id' => [] ] ] );
    echo '</div>';
    ?>

    <div class="init-reaction-list">
        <?php foreach ( ( $types ?? [] ) as $key => $t ):
            $label = isset($t[0]) ? $t[0] : ucfirst($key);
            $emoji = isset($t[1]) ? $t[1] : '';
            $count = isset($counts[$key]) ? (int) $counts[$key] : 0;

            $is_active   = ( $user_rx === $key );
            $is_disabled = ( $require_login && ! $is_logged_in );
            $btn_class   = 'init-rx'
                         . ( $is_active ? ' is-active' : '' )
                         . ( $is_disabled ? ' is-disabled' : '' );
        ?>
            <button
                type="button"
                class="<?php echo esc_attr($btn_class); ?>"
                data-rx="<?php echo esc_attr($key); ?>"
                <?php disabled( $is_disabled ); ?>
                aria-pressed="<?php echo $is_active ? 'true' : 'false'; ?>"
                aria-label="<?php echo esc_attr($label); ?>"
            >
                <?php if ($emoji !== ''): ?>
                    <span class="rx-emoji" aria-hidden="true"><?php echo esc_html($emoji); ?></span>
                <?php endif; ?>
                <span class="rx-count" data-key="<?php echo esc_attr($key); ?>">
                    <?php echo esc_html( number_format_i18n( $count ) ); ?>
                </span>
                <span class="rx-label"><?php echo esc_html($label); ?></span>
            </button>
        <?php endforeach; ?>
    </div>

    <?php if ($require_login && ! $is_logged_in): ?>
        <div class="init-reaction-login-hint">
            <a href="<?php echo esc_url( wp_login_url( get_permalink( $post_id ) ) ); ?>">
                <?php echo esc_html__( 'Log in', 'init-review-system' ); ?>
            </a>
            <?php echo esc_html__( 'to join the reactions.', 'init-review-system' ); ?>
        </div>
    <?php endif; ?>
</div>
