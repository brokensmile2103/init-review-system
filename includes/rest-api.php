<?php
/**
 * REST API endpoints.
 *
 * @package InitReviewSystem
 */

defined( 'ABSPATH' ) || exit;

add_action( 'rest_api_init', 'init_plugin_suite_review_system_register_rest_routes' );

/**
 * Đăng ký các REST route của plugin.
 *
 * @return void
 */
function init_plugin_suite_review_system_register_rest_routes() {
	register_rest_route(
		INIT_PLUGIN_SUITE_RS_NAMESPACE,
		'/vote',
		array(
			'methods'             => 'POST',
			'callback'            => 'init_plugin_suite_review_system_rest_submit_vote',
			'permission_callback' => 'init_plugin_suite_review_system_rest_permission_vote',
			'args'                => array(
				'post_id' => array(
					'required' => true,
					'type'     => 'integer',
				),
				'score'   => array(
					'required' => true,
					'type'     => 'number',
				),
			),
		)
	);

	register_rest_route(
		INIT_PLUGIN_SUITE_RS_NAMESPACE,
		'/submit-criteria-review',
		array(
			'methods'             => 'POST',
			'callback'            => 'init_plugin_suite_review_system_rest_submit_criteria_review',
			'permission_callback' => 'init_plugin_suite_review_system_rest_permission_vote',
			'args'                => array(
				'post_id'        => array(
					'required' => true,
					'type'     => 'integer',
				),
				'scores'         => array(
					'required' => true,
					'type'     => 'object',
				),
				'review_content' => array(
					'required' => true,
					'type'     => 'string',
				),
			),
		)
	);

	// Public endpoint - no need to protect.
	register_rest_route(
		INIT_PLUGIN_SUITE_RS_NAMESPACE,
		'/get-criteria-reviews',
		array(
			'methods'             => 'GET',
			'callback'            => 'init_plugin_suite_review_system_rest_get_criteria_reviews',
			'permission_callback' => '__return_true',
			'args'                => array(
				'post_id'  => array(
					'required' => true,
					'type'     => 'integer',
				),
				'page'     => array(
					'default' => 1,
					'type'    => 'integer',
				),
				'per_page' => array(
					'default' => 10,
					'type'    => 'integer',
				),
			),
		)
	);

	register_rest_route(
		INIT_PLUGIN_SUITE_RS_NAMESPACE,
		'/reactions/summary',
		array(
			'methods'             => 'GET',
			'callback'            => 'init_plugin_suite_review_system_rest_get_reactions_summary',
			'permission_callback' => '__return_true',
			'args'                => array(
				'post_id' => array(
					'required' => true,
					'type'     => 'integer',
				),
			),
		)
	);

	register_rest_route(
		INIT_PLUGIN_SUITE_RS_NAMESPACE,
		'/reactions/toggle',
		array(
			'methods'             => 'POST',
			'callback'            => 'init_plugin_suite_review_system_rest_toggle_reaction',
			'permission_callback' => 'is_user_logged_in',
			'args'                => array(
				'post_id'  => array(
					'required' => true,
					'type'     => 'integer',
				),
				'reaction' => array(
					'required' => true,
					'type'     => 'string',
				),
			),
		)
	);
}

/**
 * Permission callback cho /vote và /submit-criteria-review.
 *
 * @return bool
 */
function init_plugin_suite_review_system_rest_permission_vote() {
	$options = get_option( INIT_PLUGIN_SUITE_RS_OPTION );
	return empty( $options['require_login'] ) || is_user_logged_in();
}

/**
 * Kiểm tra header X-WP-Nonce của request hiện tại.
 *
 * @return bool
 */
function init_plugin_suite_review_system_rest_verify_nonce() {
	$nonce = isset( $_SERVER['HTTP_X_WP_NONCE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_WP_NONCE'] ) ) : '';
	return (bool) wp_verify_nonce( $nonce, 'wp_rest' );
}

/**
 * Tách nội dung (đã lowercase) thành các từ theo ranh giới Unicode.
 *
 * @param string $text Nội dung.
 * @return string[]
 */
function init_plugin_suite_review_system_tokenize( $text ) {
	$tokens = preg_split( '/[^\p{L}\p{N}]+/u', (string) $text, -1, PREG_SPLIT_NO_EMPTY );
	return is_array( $tokens ) ? $tokens : array();
}

/**
 * Lowercase an toàn với Unicode.
 *
 * @param string $text Chuỗi đầu vào.
 * @return string
 */
function init_plugin_suite_review_system_strtolower( $text ) {
	return function_exists( 'mb_strtolower' ) ? mb_strtolower( (string) $text, 'UTF-8' ) : strtolower( (string) $text );
}

/**
 * Tách danh sách từ/cụm từ cấm (mỗi dòng một mục) thành mảng lowercase.
 *
 * @param string $text Nội dung textarea trong settings.
 * @return string[]
 */
function init_plugin_suite_review_system_parse_moderation_list( $text ) {
	$text  = str_replace( array( "\r\n", "\r" ), "\n", (string) $text );
	$lines = array_filter(
		array_map( 'trim', explode( "\n", $text ) ),
		static function ( $v ) {
			return '' !== $v;
		}
	);

	return array_values( array_map( 'init_plugin_suite_review_system_strtolower', $lines ) );
}

/**
 * Kiểm duyệt nội dung review: từ cấm, cụm từ cấm, và các kiểm tra chất lượng
 * đơn giản. Thứ tự kiểm tra & mã lỗi giữ nguyên như trước.
 *
 * @since 2.0.1
 *
 * @param string $content Nội dung review (đã sanitize).
 * @param array  $options Settings của plugin.
 * @return true|WP_Error
 */
function init_plugin_suite_review_system_moderate_review_content( $content, $options ) {
	$content_lc = init_plugin_suite_review_system_strtolower( wp_specialchars_decode( $content ) );

	// Tokenize MỘT lần, dùng chung cho kiểm tra từ cấm và kiểm tra lặp từ.
	$tokens = init_plugin_suite_review_system_tokenize( $content_lc );

	// 1) Banned words: khớp chính xác theo từ (không phân biệt hoa thường).
	$bw_list = init_plugin_suite_review_system_parse_moderation_list( $options['banned_words'] ?? '' );
	if ( $bw_list && $tokens ) {
		$bw_hash = array_fill_keys( $bw_list, true );
		foreach ( $tokens as $tk ) {
			if ( isset( $bw_hash[ $tk ] ) ) {
				return new WP_Error(
					'banned_word_detected',
					__( 'Your review contains banned words.', 'init-review-system' ),
					array(
						'status' => 400,
						'hit'    => $tk,
					)
				);
			}
		}
	}

	// 2) Banned phrases: chứa chuỗi con (không phân biệt hoa thường).
	$bp_list = init_plugin_suite_review_system_parse_moderation_list( $options['banned_phrases'] ?? '' );
	foreach ( $bp_list as $ph ) {
		$found = function_exists( 'mb_stripos' ) ? mb_stripos( $content_lc, $ph, 0, 'UTF-8' ) : stripos( $content_lc, $ph );
		if ( false !== $found ) {
			return new WP_Error(
				'banned_phrase_detected',
				__( 'Your review contains banned phrases.', 'init-review-system' ),
				array(
					'status' => 400,
					'hit'    => $ph,
				)
			);
		}
	}

	// 3a) Không có khoảng trắng (thường là chuỗi hash/URL dán vào).
	$min_len_for_ws_check = (int) apply_filters( 'init_plugin_suite_review_system_min_len_for_ws_check', 20 );
	$content_len          = function_exists( 'mb_strlen' ) ? mb_strlen( $content, 'UTF-8' ) : strlen( $content );
	if ( $content_len >= $min_len_for_ws_check && ! preg_match( '/\s/u', $content ) ) {
		return new WP_Error(
			'no_whitespace',
			__( 'Your review appears to contain no whitespace. Please rewrite it more naturally.', 'init-review-system' ),
			array( 'status' => 400 )
		);
	}

	// 3b) Lặp một từ quá nhiều lần.
	$repeat_threshold = (int) apply_filters( 'init_plugin_suite_review_system_repetition_threshold', 8 );
	if ( $tokens ) {
		$counts   = array();
		$max_word = '';
		$max_cnt  = 0;
		foreach ( $tokens as $tk ) {
			$counts[ $tk ] = ( $counts[ $tk ] ?? 0 ) + 1;
			if ( $counts[ $tk ] > $max_cnt ) {
				$max_cnt  = $counts[ $tk ];
				$max_word = $tk;
			}
		}

		if ( $max_cnt >= $repeat_threshold ) {
			return new WP_Error(
				'excessive_repetition',
				__( 'Your review repeats the same word too many times.', 'init-review-system' ),
				array(
					'status'    => 400,
					'word'      => $max_word,
					'count'     => $max_cnt,
					'threshold' => $repeat_threshold,
				)
			);
		}
	}

	return true;
}

/**
 * POST /vote — xử lý vote 5 sao.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function init_plugin_suite_review_system_rest_submit_vote( $request ) {
	$post_id = absint( $request['post_id'] );
	$score   = floatval( $request['score'] );

	if ( ! $post_id || ! init_plugin_suite_review_system_can_view_post( $post_id ) || $score <= 0 || $score > 5 ) {
		return new WP_Error( 'invalid_request', __( 'Invalid post ID or score.', 'init-review-system' ), array( 'status' => 400 ) );
	}

	$options = get_option( INIT_PLUGIN_SUITE_RS_OPTION );

	// Nếu yêu cầu đăng nhập → phải kiểm tra đăng nhập + nonce.
	if ( ! empty( $options['require_login'] ) ) {
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'login_required', __( 'Login required to vote.', 'init-review-system' ), array( 'status' => 403 ) );
		}

		if ( ! init_plugin_suite_review_system_rest_verify_nonce() ) {
			return new WP_Error( 'invalid_nonce', __( 'Invalid nonce.', 'init-review-system' ), array( 'status' => 403 ) );
		}
	}

	// Nếu bật kiểm tra IP → chặn IP trùng.
	if ( ! empty( $options['strict_ip_check'] ) && init_plugin_suite_review_system_is_ip_recent( $post_id, 'simple' ) ) {
		return new WP_Error( 'duplicate_ip', __( 'You have already voted recently.', 'init-review-system' ), array( 'status' => 429 ) );
	}

	// Cộng dồn atomic (tránh lost update khi nhiều vote xảy ra cùng lúc).
	$new_total_score = init_plugin_suite_review_system_atomic_increment_meta( $post_id, '_init_review_total', $score );
	$new_total_count = (int) init_plugin_suite_review_system_atomic_increment_meta( $post_id, '_init_review_count', 1 );
	$new_avg         = $new_total_count > 0 ? round( $new_total_score / $new_total_count, 2 ) : 0;

	// Avg/weighted chỉ là giá trị hiển thị được tính lại từ total/count (nguồn
	// sự thật) mỗi lần vote, nên tự "chữa lành" ngay ở lượt vote kế tiếp.
	update_post_meta( $post_id, '_init_review_avg', $new_avg );

	// Weighted score dùng điểm trung bình toàn site (cache 1 giờ). Trước đây
	// transient này bị xoá sau MỖI lượt vote, khiến gần như lượt vote nào cũng
	// phải chạy lại AVG() trên toàn bảng postmeta — rất nặng với site lớn,
	// trong khi trung bình toàn site gần như không đổi sau một lượt vote.
	$weighted_score = init_plugin_suite_review_system_refresh_weighted_score( $post_id, $new_avg, $new_total_count );

	// Hook mở rộng.
	do_action( 'init_plugin_suite_review_system_after_vote', $post_id, $score, $new_avg, $new_total_count, $weighted_score );

	return rest_ensure_response(
		array(
			'success'     => true,
			'post_id'     => $post_id,
			'score'       => $new_avg,
			'total_votes' => $new_total_count,
			'max_score'   => 5,
		)
	);
}

/**
 * POST /submit-criteria-review — xử lý review nhiều tiêu chí.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function init_plugin_suite_review_system_rest_submit_criteria_review( $request ) {
	$post_id        = absint( $request['post_id'] );
	$scores         = (array) $request['scores'];
	$review_content = sanitize_textarea_field( $request['review_content'] );

	if ( ! $post_id || ! init_plugin_suite_review_system_can_view_post( $post_id ) || empty( $scores ) || empty( $review_content ) ) {
		return new WP_Error( 'invalid_data', __( 'Invalid request data.', 'init-review-system' ), array( 'status' => 400 ) );
	}

	$options = get_option( INIT_PLUGIN_SUITE_RS_OPTION );

	$require_login   = apply_filters( 'init_plugin_suite_review_system_require_login', ! empty( $options['require_login'] ) );
	$strict_ip_check = ! empty( $options['strict_ip_check'] );

	$user_logged_in = is_user_logged_in();
	$user_id        = $user_logged_in ? get_current_user_id() : 0;

	if ( $require_login && ! $user_logged_in ) {
		return new WP_Error( 'login_required', __( 'Login required to submit review.', 'init-review-system' ), array( 'status' => 403 ) );
	}

	if ( $user_logged_in && ! init_plugin_suite_review_system_rest_verify_nonce() ) {
		return new WP_Error( 'invalid_nonce', __( 'Invalid nonce.', 'init-review-system' ), array( 'status' => 403 ) );
	}

	// Validate scores.
	$valid_scores = array();
	foreach ( $scores as $label => $val ) {
		if ( is_numeric( $val ) && $val >= 1 && $val <= 5 ) {
			$valid_scores[ sanitize_text_field( $label ) ] = floatval( $val );
		}
	}

	if ( 0 === count( $valid_scores ) ) {
		return new WP_Error( 'no_valid_scores', __( 'No valid scores provided.', 'init-review-system' ), array( 'status' => 400 ) );
	}

	// Chặn duplicate theo user ID.
	if ( $user_id && init_plugin_suite_review_system_has_user_reviewed( $post_id, $user_id ) ) {
		return new WP_Error( 'duplicate_review', __( 'You have already submitted a review.', 'init-review-system' ), array( 'status' => 409 ) );
	}

	// Chặn duplicate theo IP nếu bật. Chỉ KIỂM TRA ở đây, chưa ghi nhận IP:
	// trước đây IP bị ghi nhận ngay tại bước này, nên nếu review bị từ chối ở
	// bước kiểm duyệt phía dưới (từ cấm, lặp từ...) thì khách không thể sửa
	// lại và gửi lần nữa. IP chỉ được ghi nhận sau khi lưu review thành công.
	$check_ip = ( 0 === $user_id && $strict_ip_check );
	if ( $check_ip && init_plugin_suite_review_system_is_ip_recent( $post_id, 'criteria', false ) ) {
		return new WP_Error( 'duplicate_ip', __( 'You have already submitted a review from this IP.', 'init-review-system' ), array( 'status' => 409 ) );
	}

	// Moderation: từ cấm, cụm từ cấm & kiểm tra chất lượng đơn giản.
	$moderation = init_plugin_suite_review_system_moderate_review_content( $review_content, (array) $options );
	if ( is_wp_error( $moderation ) ) {
		return $moderation;
	}

	$insert_id = init_plugin_suite_review_system_add_criteria_review(
		$post_id,
		$user_id,
		$valid_scores,
		$review_content,
		'approved'
	);

	if ( ! $insert_id ) {
		return new WP_Error( 'db_error', __( 'Could not insert review.', 'init-review-system' ), array( 'status' => 500 ) );
	}

	if ( $check_ip ) {
		init_plugin_suite_review_system_mark_ip_recent( $post_id, 'criteria' );
	}

	$avg_score = round( array_sum( $valid_scores ) / count( $valid_scores ), 2 );

	do_action( 'init_plugin_suite_review_system_after_criteria_review', $post_id, $user_id, $avg_score, $review_content, $valid_scores );

	$review   = init_plugin_suite_review_system_get_review_by_id( $insert_id );
	$criteria = init_plugin_suite_review_system_get_criteria_by_post_id( $post_id );
	$enriched = init_plugin_suite_review_system_enrich_reviews( array( (array) $review ), 80 );
	$html     = init_plugin_suite_review_system_render_review_item( $enriched[0], $criteria, $post_id );

	// Cache score_summary đã được invalidate bên trong add_criteria_review()
	// (hook after_insert) nên đây là số liệu THẬT mới nhất từ DB.
	$summary = init_plugin_suite_review_system_get_score_summary_by_post_id( $post_id );
	$total   = init_plugin_suite_review_system_get_total_reviews_by_post_id( $post_id );

	return rest_ensure_response(
		array(
			'success'   => true,
			'message'   => __( 'Review submitted successfully.', 'init-review-system' ),
			'avg'       => $avg_score,
			'review_id' => $insert_id,
			'html'      => $html,
			'summary'   => array(
				'overall_avg' => $summary['overall_avg'],
				'breakdown'   => $summary['breakdown'],
				'total'       => $total,
			),
		)
	);
}

/**
 * Render HTML một review qua template review-item.php (cho phép theme override).
 *
 * Template nhận các biến $review, $criteria và $post_id như trước.
 *
 * @since 2.0.1
 *
 * @param array    $review   Dữ liệu review.
 * @param string[] $criteria Danh sách tiêu chí.
 * @param int      $post_id  ID bài viết (mặc định lấy từ review).
 * @return string
 */
function init_plugin_suite_review_system_render_review_item( $review, $criteria, $post_id = 0 ) {
	ob_start();
	init_plugin_suite_review_system_render_template(
		'review-item.php',
		array(
			'review'   => $review,
			'criteria' => $criteria,
			'post_id'  => $post_id ? absint( $post_id ) : absint( $review['post_id'] ?? 0 ),
		)
	);
	return (string) ob_get_clean();
}

/**
 * GET /get-criteria-reviews — lấy các bài review (kèm HTML render sẵn).
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function init_plugin_suite_review_system_rest_get_criteria_reviews( WP_REST_Request $request ) {
	$post_id  = absint( $request->get_param( 'post_id' ) );
	$page     = absint( $request->get_param( 'page' ) );
	$per_page = absint( $request->get_param( 'per_page' ) );

	$data = init_plugin_suite_review_system_get_criteria_reviews_data( $post_id, $page, $per_page );

	if ( is_wp_error( $data ) ) {
		return $data;
	}

	foreach ( $data['reviews'] as &$review ) {
		// Server render HTML từng review — flexible với mọi template.
		$review['html'] = init_plugin_suite_review_system_render_review_item( $review, $data['criteria'], $data['post_id'] );
	}
	unset( $review );

	return rest_ensure_response(
		array(
			'success'  => true,
			'post_id'  => $data['post_id'],
			'page'     => $data['page'],
			'per_page' => $data['per_page'],
			'total'    => $data['total'],
			'max_page' => $data['max_page'],
			'reviews'  => $data['reviews'],
		)
	);
}

/**
 * Chuẩn bị dữ liệu tổng hợp reactions của một bài viết — dùng chung bởi REST
 * endpoint /reactions/summary và ability init-review-system/get-reactions-summary.
 *
 * @param int $post_id ID bài viết.
 * @return array|WP_Error Dữ liệu tổng hợp, hoặc WP_Error nếu post_id không hợp lệ.
 */
function init_plugin_suite_review_system_get_reactions_summary_data( $post_id ) {
	$post_id = init_plugin_suite_review_system_assert_post( absint( $post_id ) );
	if ( ! $post_id || ! init_plugin_suite_review_system_can_view_post( $post_id ) ) {
		return new WP_Error( 'invalid_post', __( 'Invalid post ID.', 'init-review-system' ), array( 'status' => 400 ) );
	}

	$counts  = init_plugin_suite_review_system_get_reaction_counts( $post_id );
	$user_rx = is_user_logged_in()
		? init_plugin_suite_review_system_get_user_reaction( $post_id, get_current_user_id() )
		: '';

	return array(
		'post_id'       => $post_id,
		'counts'        => $counts,
		'user_reaction' => $user_rx,
		'types'         => init_plugin_suite_review_system_get_reaction_types(),
	);
}

/**
 * GET /reactions/summary
 *
 * @param WP_REST_Request $req Request.
 * @return WP_REST_Response|WP_Error
 */
function init_plugin_suite_review_system_rest_get_reactions_summary( WP_REST_Request $req ) {
	$data = init_plugin_suite_review_system_get_reactions_summary_data( $req->get_param( 'post_id' ) );

	if ( is_wp_error( $data ) ) {
		return $data;
	}

	return rest_ensure_response( array_merge( array( 'success' => true ), $data ) );
}

/**
 * POST /reactions/toggle
 *
 * @param WP_REST_Request $req Request.
 * @return WP_REST_Response|WP_Error
 */
function init_plugin_suite_review_system_rest_toggle_reaction( WP_REST_Request $req ) {
	$post_id  = init_plugin_suite_review_system_assert_post( absint( $req->get_param( 'post_id' ) ) );
	$reaction = sanitize_key( $req->get_param( 'reaction' ) );

	if ( ! $post_id || ! init_plugin_suite_review_system_can_view_post( $post_id ) ) {
		return new WP_Error(
			'invalid_post',
			__( 'Invalid post ID.', 'init-review-system' ),
			array( 'status' => 400 )
		);
	}

	// Bắt buộc login.
	if ( ! is_user_logged_in() ) {
		return new WP_Error(
			'login_required',
			__( 'Login required.', 'init-review-system' ),
			array( 'status' => 403 )
		);
	}

	if ( ! init_plugin_suite_review_system_rest_verify_nonce() ) {
		return new WP_Error(
			'invalid_nonce',
			__( 'Invalid nonce.', 'init-review-system' ),
			array( 'status' => 403 )
		);
	}

	// Áp dụng logic (add/switch/remove) từ core helpers.
	$result = init_plugin_suite_review_system_apply_user_reaction(
		$post_id,
		get_current_user_id(),
		$reaction
	);

	if ( empty( $result['success'] ) ) {
		return new WP_Error(
			'rx_failed',
			__( 'Could not update reaction.', 'init-review-system' ),
			array( 'status' => 500 )
		);
	}

	return rest_ensure_response(
		array(
			'success'       => true,
			'post_id'       => $post_id,
			'user_reaction' => $result['current'],  // '' nếu gỡ.
			'counts'        => $result['counts'],
			'prev'          => $result['prev'],
		)
	);
}
