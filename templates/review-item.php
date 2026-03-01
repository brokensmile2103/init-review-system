<?php
defined( 'ABSPATH' ) || exit;

$user_id     = intval( $review['user_id'] ?? 0 );
$scores      = $review['criteria_scores'] ?? [];
$author_name = $review['display_name'] ?? __( 'Anonymous', 'init-review-system' );
$avatar_url  = $review['avatar_url']   ?? INIT_PLUGIN_SUITE_RS_ASSETS_URL . '/img/default-avatar.svg';
?>

<div class="init-review-item">
    <div class="init-review-top">
        <div class="init-review-avatar">
            <img src="<?php echo esc_url( $avatar_url ); ?>" width="48" height="48" alt="<?php echo esc_attr( $author_name ); ?>">
        </div>
        <div class="init-review-header">
            <div class="author-and-stars">
                <h3 class="author"><?php echo esc_html( $author_name ); ?></h3>
                <div class="init-review-stars">
                    <?php
                    $avg = floatval( $review['avg_score'] ?? 0 );
                    for ( $i = 1; $i <= 5; $i++ ):
                        $active = $i <= round( $avg ); ?>
                        <svg class="star <?php echo $active ? 'active' : ''; ?>" width="20" height="20" viewBox="0 0 64 64">
                            <path fill="currentColor" d="M63.9 24.28a2 2 0 0 0-1.6-1.35l-19.68-3-8.81-18.78a2 2 0 0 0-3.62 0l-8.82 18.78-19.67 3a2 2 0 0 0-1.13 3.38l14.3 14.66-3.39 20.7a2 2 0 0 0 2.94 2.07L32 54.02l17.57 9.72a2 2 0 0 0 2.12-.11 2 2 0 0 0 .82-1.96l-3.38-20.7 14.3-14.66a2 2 0 0 0 .46-2.03"></path>
                        </svg>
                    <?php endfor; ?>
                </div>
            </div>
            <div class="review-date">
                <?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $review['created_at'] ?? 'now' ) ) ); ?>
            </div>
        </div>
    </div>

    <div class="init-review-body">
        <div class="init-review-text">
            <?php echo esc_html( $review['review_content'] ?? '' ); ?>
        </div>
        <div class="init-review-criteria-breakdown">
            <?php foreach ( $criteria as $label ): ?>
                <?php if ( isset( $scores[ $label ] ) ): ?>
                    <span class="criteria-score">
                        <strong><?php echo esc_html( $label ); ?></strong>: <?php echo esc_html( floatval( $scores[ $label ] ) ); ?> / 5
                    </span><br class="visible-mobile">
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>
</div>
