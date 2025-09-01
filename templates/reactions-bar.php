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
?>
<div class="<?php echo esc_attr($wrapper_class); ?>" data-post-id="<?php echo esc_attr($post_id); ?>">

    <div class="init-reaction-title">
        <?php echo esc_html__('What do you think?', 'init-review-system'); ?>
    </div>

    <?php
    // === NEW: Total reactions (inline, no extra helper) + ID for JS updates
    $total_reactions = 0;
    if ( ! empty( $counts ) && is_array( $counts ) ) {
        foreach ( $counts as $c ) {
            $total_reactions += max( 0, (int) $c );
        }
    }
    $formatted_total = number_format_i18n( (int) $total_reactions );

    // Build a pluralized label with the number wrapped in a span with unique ID
    $label_with_number = sprintf(
        // translators: %s: total number of reactions.
        _n( '%s reaction', '%s reactions', (int) $total_reactions, 'init-review-system' ),
        '<span id="irs-total-reactions-' . esc_attr( $post_id ) . '">' . esc_html( $formatted_total ) . '</span>'
    );

    // Allow only the span we injected
    echo '<div class="init-reaction-total" aria-live="polite">';
    echo wp_kses( $label_with_number, [ 'span' => [ 'id' => [] ] ] );
    echo '</div>';
    ?>

    <div class="init-reaction-list">
        <?php foreach ( ( $types ?? [] ) as $key => $t ):
            $label = isset($t[0]) ? $t[0] : ucfirst($key);
            $emoji = isset($t[1]) ? $t[1] : '';
            $count = isset($counts[$key]) ? (int) $counts[$key] : 0;
        ?>
            <button
                type="button"
                class="init-rx"
                data-rx="<?php echo esc_attr($key); ?>"
                <?php disabled( $require_login && ! $is_logged_in ); ?>
                aria-pressed="false"
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
