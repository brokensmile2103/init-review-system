<?php
/**
 * Template: khối review nhiều tiêu chí + modal gửi review (có thể override
 * trong theme tại {theme}/init-review-system/review-criteria-display.php).
 *
 * Vars: $post_id, $user_id, $can_review, $class, $schema, $per_page, $paged,
 * $summary, $total_reviews, $criteria, $reviews.
 *
 * Template luôn được include bên trong scope của một hàm, nên các biến dưới
 * đây là biến cục bộ, không phải biến global.
 *
 * @package InitReviewSystem
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

defined( 'ABSPATH' ) || exit;

// === Prepare data ===.

$aggregate     = $summary['breakdown'] ?? array();
$overall_avg   = $summary['overall_avg'] ?? 0;
$total_reviews = $total_reviews ?? 0;
$current_page  = isset( $paged ) ? max( 1, (int) $paged ) : 1;

$aggregate_json = wp_json_encode( $aggregate );
?>

<div class="init-review-criteria-summary <?php echo esc_attr( $class ); ?>"
	data-post-id="<?php echo esc_attr( $post_id ); ?>"
	data-total-reviews="<?php echo esc_attr( $total_reviews ); ?>"
	data-aggregate='<?php echo esc_attr( $aggregate_json ); ?>'
	data-overall-avg="<?php echo esc_attr( $overall_avg ); ?>">

	<!-- Total Score -->
	<div class="init-review-score-box">
		<div class="init-review-score-value"><?php echo esc_html( number_format( (float) $overall_avg, 1, '.', '' ) ); ?></div>

		<div class="init-review-stars-line init-review-stars">
			<?php
			for ( $i = 1; $i <= 5; $i++ ) :
				$active = $i <= round( $overall_avg );
				?>
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
		<?php
		foreach ( $criteria as $label ) :
			$avg     = isset( $aggregate[ $label ] ) ? floatval( $aggregate[ $label ] ) : 0;
			$percent = $avg * 20;
			?>
			<div class="init-review-criteria-breakdown-row"
				data-label="<?php echo esc_attr( $label ); ?>">
				<div class="label"><?php echo esc_html( $label ); ?></div>
				<div class="bar-bg">
					<div class="bar-fill" style="width: <?php echo esc_attr( round( $percent ) ); ?>%"></div>
				</div>
				<div class="value"><?php echo esc_html( number_format( (float) $avg, 1, '.', '' ) ); ?></div>
			</div>
		<?php endforeach; ?>
	</div>

	<button type="button"
		class="init-review-open-modal init-review-open-modal-btn<?php echo $can_review ? '' : ' is-disabled'; ?>"
		<?php echo $can_review ? '' : 'disabled'; ?>>
		<?php esc_html_e( 'Write a review', 'init-review-system' ); ?>
	</button>

	<!-- Review list -->
	<div class="init-review-feedback-list">
		<?php if ( $reviews ) : ?>
			<?php
			$item_template = init_plugin_suite_review_system_locate_template( 'review-item.php' );

			// Enrich nếu chưa có (shortcode đã enrich sẵn; hàm này bỏ qua review
			// đã có display_name nên gọi lại là an toàn).
			$reviews = init_plugin_suite_review_system_enrich_reviews( $reviews, 48 );

			foreach ( $reviews as $review ) :
				if ( $item_template ) {
					include $item_template;
				}
			endforeach;
			?>
		<?php else : ?>
			<p class="init-review-no-feedback"><?php esc_html_e( 'No reviews yet.', 'init-review-system' ); ?></p>
		<?php endif; ?>
	</div>

	<?php
	if ( isset( $per_page ) && $per_page > 0 ) {
		$total_all_reviews = $total_reviews ?? 0;
		// Chỉ hiện "Tải thêm" khi thực sự còn review sau trang hiện tại, và bắt
		// đầu từ trang kế tiếp (trước đây luôn là trang 2 kể cả khi paged > 1).
		if ( $total_all_reviews > $per_page * $current_page ) :
			?>
		<div class="init-review-load-more-wrapper">
			<a href="#" class="init-review-load-more" data-page="<?php echo esc_attr( $current_page + 1 ); ?>" data-per="<?php echo esc_attr( $per_page ); ?>">
				<?php esc_html_e( 'Load more reviews', 'init-review-system' ); ?>
			</a>
		</div>
			<?php
		endif;
	}
	?>

	<?php
	if ( ! empty( $schema ) && $overall_avg > 0 && $total_reviews > 0 ) :
		$schema_type = init_plugin_suite_review_system_get_schema_type( $post_id );

		$schema_data = array(
			'@context'        => 'https://schema.org',
			'@type'           => $schema_type,
			'name'            => get_the_title( $post_id ),
			'aggregateRating' => array(
				'@type'       => 'AggregateRating',
				'ratingValue' => round( $overall_avg, 2 ),
				'reviewCount' => $total_reviews,
				'bestRating'  => 5,
			),
		);

		$schema_data = apply_filters( 'init_plugin_suite_review_system_schema_data', $schema_data, $post_id, $schema_type );
		?>
		<script type="application/ld+json"><?php echo wp_json_encode( $schema_data ); ?></script>
	<?php endif; ?>

</div>

<!-- Modal HTML -->
<div id="init-review-modal" class="init-review-modal">
	<div class="init-review-modal-content">

		<button type="button" class="init-review-modal-close" aria-label="<?php esc_attr_e( 'Close', 'init-review-system' ); ?>"><svg width="20" height="20" viewBox="0 0 24 24"><path d="m21 21-9-9m0 0L3 3m9 9 9-9m-9 9-9 9" stroke="currentColor" stroke-width="1.1" stroke-linecap="round" stroke-linejoin="round"/></svg></button>

		<h2 class="init-review-modal-title"><?php esc_html_e( 'Submit your review', 'init-review-system' ); ?></h2>

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
				<label for="init-review-content"><?php esc_html_e( 'Review content', 'init-review-system' ); ?></label>
				<textarea id="init-review-content" name="review_content" rows="5" placeholder="<?php esc_attr_e( 'Write your thoughts...', 'init-review-system' ); ?>"></textarea>
			</div>

			<div>
				<button type="submit"><?php esc_html_e( 'Submit Review', 'init-review-system' ); ?></button>
			</div>
		</form>
	</div>
</div>
