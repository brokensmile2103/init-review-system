<?php
defined( 'ABSPATH' ) || exit;

// === Prepare data ===

$aggregate      = $summary['breakdown']     ?? [];
$overall_avg    = $summary['overall_avg']   ?? 0;
$total_reviews  = $total_reviews            ?? 0;

?>

<?php
$aggregate_json = esc_attr( wp_json_encode( $aggregate ) );
?>

<div class="init-review-criteria-summary <?php echo esc_attr( $class ); ?>"
     data-post-id="<?php echo esc_attr( $post_id ); ?>"
     data-total-reviews="<?php echo esc_attr( $total_reviews ); ?>"
     data-aggregate='<?php echo esc_attr( $aggregate_json ); ?>'
     data-overall-avg="<?php echo esc_attr( $overall_avg ); ?>">

    <!-- Total Score -->
    <div class="init-review-score-box">
        <div class="init-review-score-value"><?php echo esc_html( number_format( $overall_avg, 1 ) ); ?></div>

        <div class="init-review-stars-line init-review-stars">
            <?php for ($i = 1; $i <= 5; $i++): 
                $active = $i <= round($overall_avg); ?>
                <svg class="star <?php echo $active ? 'active' : ''; ?>" width="20" height="20" viewBox="0 0 64 64">
                    <path fill="currentColor" d="M63.9 24.28a2 2 0 0 0-1.6-1.35l-19.68-3-8.81-18.78a2 2 0 0 0-3.62 0l-8.82 18.78-19.67 3a2 2 0 0 0-1.13 3.38l14.3 14.66-3.39 20.7a2 2 0 0 0 2.94 2.07L32 54.02l17.57 9.72a2 2 0 0 0 2.12-.11 2 2 0 0 0 .82-1.96l-3.38-20.7 14.3-14.66a2 2 0 0 0 .46-2.03"></path>
                </svg>
            <?php endfor; ?>
        </div>

        <div class="init-review-score-count">
            <?php echo esc_html( number_format_i18n( $total_reviews ) ); ?> <?php esc_html_e( 'reviews', 'init-review-system' ); ?>
        </div>
    </div>

    <!-- Criteria breakdown -->
    <div class="init-review-criteria-breakdown-summary">
        <?php foreach ($criteria as $label): 
            $avg     = isset($aggregate[$label]) ? floatval($aggregate[$label]) : 0;
            $percent = $avg * 20;
        ?>
            <div class="init-review-criteria-breakdown-row"
                 data-label="<?php echo esc_attr($label); ?>">
                <div class="label"><?php echo esc_html($label); ?></div>
                <div class="bar-bg">
                    <div class="bar-fill" style="width: <?php echo esc_attr( round($percent) ); ?>%"></div>
                </div>
                <div class="value"><?php echo esc_html( number_format($avg, 1) ); ?></div>
            </div>
        <?php endforeach; ?>
    </div>

    <button 
        class="init-review-open-modal init-review-open-modal-btn<?php echo $can_review ? '' : ' is-disabled'; ?>" 
        <?php echo $can_review ? '' : 'disabled'; ?>>
        <?php esc_html_e( 'Write a review', 'init-review-system' ); ?>
    </button>

    <!-- Review list -->
    <div class="init-review-feedback-list">
        <?php if ($reviews): ?>
            <?php foreach ($reviews as $review): 
                $user_id = intval($review['user_id']);
                $user    = $user_id > 0 ? get_userdata($user_id) : null;
                $scores  = $review['criteria_scores'];

                $author_name = $user ? $user->display_name : __('Anonymous', 'init-review-system');
                ?>
                <div class="init-review-item">
                    <div class="init-review-top">
                        <div class="init-review-avatar">
                            <?php
                            echo $user
                                ? get_avatar($user_id, 48)
                                // phpcs:disable PluginCheck.CodeAnalysis.ImageFunctions.NonEnqueuedImage
                                : '<img src="' . esc_url(INIT_PLUGIN_SUITE_RS_ASSETS_URL) . '/img/default-avatar.svg" width="48" height="48" alt="Default avatar">';
                                // phpcs:enable PluginCheck.CodeAnalysis.ImageFunctions.NonEnqueuedImage
                            ?>
                        </div>
                        <div class="init-review-header">
                            <div class="author-and-stars">
                                <h3 class="author"><?php echo esc_html($author_name); ?></h3>
                                <div class="init-review-stars">
                                    <?php
                                    $avg = floatval($review['avg_score']);
                                    for ($i = 1; $i <= 5; $i++):
                                        $active = $i <= round($avg); ?>
                                        <svg class="star <?php echo $active ? 'active' : ''; ?>" width="20" height="20" viewBox="0 0 64 64">
                                            <path fill="currentColor" d="M63.9 24.28a2 2 0 0 0-1.6-1.35l-19.68-3-8.81-18.78a2 2 0 0 0-3.62 0l-8.82 18.78-19.67 3a2 2 0 0 0-1.13 3.38l14.3 14.66-3.39 20.7a2 2 0 0 0 2.94 2.07L32 54.02l17.57 9.72a2 2 0 0 0 2.12-.11 2 2 0 0 0 .82-1.96l-3.38-20.7 14.3-14.66a2 2 0 0 0 .46-2.03"></path>
                                        </svg>
                                    <?php endfor; ?>
                                </div>
                            </div>
                            <div class="review-date">
                                <?php
                                $timestamp = strtotime($review['created_at']);
                                echo esc_html(date_i18n(get_option('date_format'), $timestamp));
                                ?>
                            </div>
                        </div>
                    </div>

                    <div class="init-review-body">
                        <div class="init-review-text">
                            <?php echo esc_html($review['review_content']); ?>
                        </div>
                        <div class="init-review-criteria-breakdown">
                            <?php foreach ($criteria as $label): ?>
                                <?php if (isset($scores[$label])): ?>
                                    <span class="criteria-score">
                                        <strong><?php echo esc_html($label); ?></strong>: <?php echo esc_html( floatval($scores[$label]) ); ?> / 5
                                    </span><br class="visible-mobile">
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p class="init-review-no-feedback"><?php esc_html_e('No reviews yet.', 'init-review-system'); ?></p>
        <?php endif; ?>
    </div>

    <?php
    if ( isset( $per_page ) && $per_page > 0 ) {
        $total_all_reviews = $total_reviews ?? 0;
        if ( $total_all_reviews > $per_page ) :
        ?>
        <div class="init-review-load-more-wrapper">
            <a href="#" class="init-review-load-more" data-page="2" data-per="<?php echo esc_attr( $per_page ); ?>">
                <?php esc_html_e('Load more reviews', 'init-review-system'); ?>
            </a>
        </div>
    <?php
        endif;
    }
    ?>

    <?php if ( ! empty( $schema ) && $overall_avg > 0 && $total_reviews > 0 ): 
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
                "ratingValue" => round( $overall_avg, 2 ),
                "reviewCount" => $total_reviews,
                "bestRating"  => 5,
            ]
        ];

        $schema_data = apply_filters( 'init_plugin_suite_review_system_schema_data', $schema_data, $post_id, $schema_type );
    ?>
        <script type="application/ld+json"><?php echo wp_json_encode( $schema_data ); ?></script>
    <?php endif; ?>

</div>

<!-- Modal HTML -->
<div id="init-review-modal" class="init-review-modal">
    <div class="init-review-modal-content">

        <button class="init-review-modal-close" aria-label="Close"><svg width="20" height="20" viewBox="0 0 24 24"><path d="m21 21-9-9m0 0L3 3m9 9 9-9m-9 9-9 9" stroke="currentColor" stroke-width="1.1" stroke-linecap="round" stroke-linejoin="round"/></svg></button>

        <h2 class="init-review-modal-title"><?php esc_html_e('Submit your review', 'init-review-system'); ?></h2>

        <form class="init-review-modal-form" method="post">
            <?php foreach ( $criteria as $label ) : ?>
                <div class="init-review-modal-line">
                    <span><?php echo esc_html( $label ); ?></span>
                    <div class="init-review-modal-stars" data-label="<?php echo esc_attr( $label ); ?>">
                        <?php for ( $i = 1; $i <= 5; $i++ ) : ?>
                            <span class="star" data-value="<?php echo esc_attr( $i ); ?>">
                                <svg class="i-star" width="20" height="20" viewBox="0 0 64 64">
                                    <path fill="currentColor" d="M63.9 24.28a2 2 0 0 0-1.6-1.35l-19.68-3-8.81-18.78a2 2 0 0 0-3.62 0l-8.82 18.78-19.67 3a2 2 0 0 0-1.13 3.38l14.3 14.66-3.39 20.7a2 2 0 0 0 2.94 2.07L32 54.02l17.57 9.72a2 2 0 0 0 2.12-.11 2 2 0 0 0 .82-1.96l-3.38-20.7 14.3-14.66a2 2 0 0 0 .46-2.03"></path>
                                </svg>
                            </span>
                        <?php endfor; ?>
                    </div>
                </div>
            <?php endforeach; ?>

            <div class="init-review-modal-line">
                <label for="init-review-content"><?php esc_html_e('Review content', 'init-review-system'); ?></label>
                <textarea id="init-review-content" name="review_content" rows="5" placeholder="<?php esc_attr_e('Write your thoughts...', 'init-review-system'); ?>"></textarea>
            </div>

            <div>
                <button type="submit"><?php esc_html_e('Submit Review', 'init-review-system'); ?></button>
            </div>
        </form>
    </div>
</div>
