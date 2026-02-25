<?php
defined( 'ABSPATH' ) || exit;

// Đăng ký REST API
add_action( 'rest_api_init', function() {
    // Custom permission callback
    $permission_vote = function () {
        $options = get_option( INIT_PLUGIN_SUITE_RS_OPTION );
        $require_login = ! empty( $options['require_login'] );
        return ! $require_login || is_user_logged_in();
    };

    register_rest_route( INIT_PLUGIN_SUITE_RS_NAMESPACE, '/vote', [
        'methods'             => 'POST',
        'callback'            => 'init_plugin_suite_review_system_rest_submit_vote',
        'permission_callback' => $permission_vote,
        'args'                => [
            'post_id' => [
                'required' => true,
                'type'     => 'integer',
            ],
            'score' => [
                'required' => true,
                'type'     => 'number',
            ],
        ],
    ] );

    register_rest_route( INIT_PLUGIN_SUITE_RS_NAMESPACE, '/submit-criteria-review', [
        'methods'             => 'POST',
        'callback'            => 'init_plugin_suite_review_system_rest_submit_criteria_review',
        'permission_callback' => $permission_vote,
        'args'                => [
            'post_id' => [
                'required' => true,
                'type'     => 'integer',
            ],
            'scores' => [
                'required' => true,
                'type'     => 'object',
            ],
            'review_content' => [
                'required' => true,
                'type'     => 'string',
            ],
        ],
    ] );

    // Public endpoint - no need to protect
    register_rest_route( INIT_PLUGIN_SUITE_RS_NAMESPACE, '/get-criteria-reviews', [
        'methods'             => 'GET',
        'callback'            => 'init_plugin_suite_review_system_rest_get_criteria_reviews',
        'permission_callback' => '__return_true',
        'args'                => [
            'post_id' => [
                'required' => true,
                'type'     => 'integer',
            ],
            'page' => [
                'default' => 1,
                'type'    => 'integer',
            ],
            'per_page' => [
                'default' => 10,
                'type'    => 'integer',
            ],
        ],
    ] );

    register_rest_route( INIT_PLUGIN_SUITE_RS_NAMESPACE, '/reactions/summary', [
        'methods'             => 'GET',
        'callback'            => 'init_plugin_suite_review_system_rest_get_reactions_summary',
        'permission_callback' => '__return_true',
        'args'                => [
            'post_id' => ['required' => true, 'type' => 'integer'],
        ],
    ] );

    register_rest_route( INIT_PLUGIN_SUITE_RS_NAMESPACE, '/reactions/toggle', [
        'methods'             => 'POST',
        'callback'            => 'init_plugin_suite_review_system_rest_toggle_reaction',
        'permission_callback' => function () {
            return is_user_logged_in();
        },
        'args'                => [
            'post_id'  => ['required' => true, 'type' => 'integer'],
            'reaction' => ['required' => true, 'type' => 'string'],
        ],
    ] );
} );

// Xử lý vote
function init_plugin_suite_review_system_rest_submit_vote( $request ) {
    $post_id = absint( $request['post_id'] );
    $score   = floatval( $request['score'] );

    if ( ! get_post( $post_id ) || $score <= 0 || $score > 5 ) {
        return new WP_Error( 'invalid_request', __( 'Invalid post ID or score.', 'init-review-system' ), [ 'status' => 400 ] );
    }

    $options = get_option( INIT_PLUGIN_SUITE_RS_OPTION );

    // Nếu yêu cầu đăng nhập → phải kiểm tra đăng nhập + nonce
    if ( ! empty( $options['require_login'] ) ) {
        if ( ! is_user_logged_in() ) {
            return new WP_Error( 'login_required', __( 'Login required to vote.', 'init-review-system' ), [ 'status' => 403 ] );
        }

        // Kiểm tra nonce nếu có header
        $nonce = isset( $_SERVER['HTTP_X_WP_NONCE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_WP_NONCE'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
            return new WP_Error( 'invalid_nonce', __( 'Invalid nonce.', 'init-review-system' ), [ 'status' => 403 ] );
        }
    }

    // Nếu bật kiểm tra IP → chặn IP trùng
    if ( ! empty( $options['strict_ip_check'] ) && init_plugin_suite_review_system_is_ip_recent( $post_id, 'simple' ) ) {
        return new WP_Error( 'duplicate_ip', __( 'You have already voted recently.', 'init-review-system' ), [ 'status' => 429 ] );
    }

    // Lấy dữ liệu cũ
    $total_score = floatval( get_post_meta( $post_id, '_init_review_total', true ) );
    $total_count = intval( get_post_meta( $post_id, '_init_review_count', true ) );

    // Tính điểm mới
    $new_total_score = $total_score + $score;
    $new_total_count = $total_count + 1;
    $new_avg         = round( $new_total_score / $new_total_count, 2 );

    // Cập nhật meta
    update_post_meta( $post_id, '_init_review_total', $new_total_score );
    update_post_meta( $post_id, '_init_review_count', $new_total_count );
    update_post_meta( $post_id, '_init_review_avg', $new_avg );

    // Tính weighted
    $global_avg = init_plugin_suite_review_system_get_global_avg();
    $min_votes = apply_filters( 'init_plugin_suite_review_system_min_votes_threshold', 50, $post_id );
    $weighted_score = init_plugin_suite_review_system_calculate_weighted_score( $new_avg, $new_total_count, $global_avg, $min_votes );
    update_post_meta( $post_id, '_init_review_weighted', round( $weighted_score, 4 ) );

    // Hook mở rộng
    do_action( 'init_plugin_suite_review_system_after_vote', $post_id, $score, $new_avg, $new_total_count, $weighted_score );

    delete_transient( 'init_plugin_suite_rs_global_avg' );

    return rest_ensure_response([
        'success'     => true,
        'post_id'     => $post_id,
        'score'       => $new_avg,
        'total_votes' => $new_total_count,
        'max_score'   => 5,
    ]);
}

// Xử lí review nhiều tiêu chí
function init_plugin_suite_review_system_rest_submit_criteria_review( $request ) {
    $post_id        = absint( $request['post_id'] );
    $scores         = (array) $request['scores'];
    $review_content = sanitize_textarea_field( $request['review_content'] );

    if ( ! get_post( $post_id ) || empty( $scores ) || empty( $review_content ) ) {
        return new WP_Error( 'invalid_data', __( 'Invalid request data.', 'init-review-system' ), [ 'status' => 400 ] );
    }

    $options = get_option( INIT_PLUGIN_SUITE_RS_OPTION );

    $require_login   = apply_filters( 'init_plugin_suite_review_system_require_login', ! empty( $options['require_login'] ) );
    $strict_ip_check = ! empty( $options['strict_ip_check'] );

    $user_logged_in  = is_user_logged_in();
    $user_id         = $user_logged_in ? get_current_user_id() : 0;

    if ( $require_login && ! $user_logged_in ) {
        return new WP_Error( 'login_required', __( 'Login required to submit review.', 'init-review-system' ), [ 'status' => 403 ] );
    }

    if ( $user_logged_in ) {
        $nonce = isset( $_SERVER['HTTP_X_WP_NONCE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_WP_NONCE'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
            return new WP_Error( 'invalid_nonce', __( 'Invalid nonce.', 'init-review-system' ), [ 'status' => 403 ] );
        }
    }

    // Validate scores
    $valid_scores = [];
    foreach ( $scores as $label => $val ) {
        if ( is_numeric( $val ) && $val >= 1 && $val <= 5 ) {
            $valid_scores[ sanitize_text_field( $label ) ] = floatval( $val );
        }
    }

    if ( count( $valid_scores ) === 0 ) {
        return new WP_Error( 'no_valid_scores', __( 'No valid scores provided.', 'init-review-system' ), [ 'status' => 400 ] );
    }

    // Chặn duplicate theo user ID
    if ( $user_id && init_plugin_suite_review_system_has_user_reviewed( $post_id, $user_id ) ) {
        return new WP_Error( 'duplicate_review', __( 'You have already submitted a review.', 'init-review-system' ), [ 'status' => 409 ] );
    }

    // Chặn duplicate theo IP nếu bật
    if ( $user_id === 0 && $strict_ip_check && init_plugin_suite_review_system_is_ip_recent( $post_id, 'criteria' ) ) {
        return new WP_Error( 'duplicate_ip', __( 'You have already submitted a review from this IP.', 'init-review-system' ), [ 'status' => 409 ] );
    }

    // ===== Moderation: banned words / phrases & simple quality checks =====
    $content_raw = $review_content;

    // Normalize content (lowercase, unicode-safe)
    $lc = function($s) {
        return function_exists('mb_strtolower') ? mb_strtolower($s, 'UTF-8') : strtolower($s);
    };
    $content_lc = $lc( wp_specialchars_decode( $content_raw ) );

    // Pull settings
    $banned_words   = isset($options['banned_words']) ? $options['banned_words'] : '';
    $banned_phrases = isset($options['banned_phrases']) ? $options['banned_phrases'] : '';

    // Parse lists (keep only non-empty trimmed lines)
    $to_lines = static function($text) use ($lc) {
        $text = str_replace(["\r\n","\r"], "\n", (string) $text);
        $lines = array_filter(array_map('trim', explode("\n", $text)), static function($v){ return $v !== ''; });
        // lower for case-insensitive compare
        return array_values(array_map($lc, $lines));
    };

    $bw_list = $to_lines($banned_words);
    $bp_list = $to_lines($banned_phrases);

    // --- 1) Banned words: exact word match (case-insensitive, Unicode word boundaries)
    if ( ! empty($bw_list) ) {
        // Tokenize content to words
        $tokens = preg_split('/[^\p{L}\p{N}]+/u', $content_lc, -1, PREG_SPLIT_NO_EMPTY);
        if ( $tokens ) {
            // Build hash for O(1)
            $bw_hash = array_fill_keys($bw_list, true);
            foreach ( $tokens as $tk ) {
                if ( isset($bw_hash[$tk]) ) {
                    return new WP_Error(
                        'banned_word_detected',
                        __( 'Your review contains banned words.', 'init-review-system' ),
                        [ 'status' => 400, 'hit' => $tk ]
                    );
                }
            }
        }
    }

    // --- 2) Banned phrases: substring (case-insensitive)
    if ( ! empty($bp_list) ) {
        foreach ( $bp_list as $ph ) {
            if ( $ph !== '' && mb_stripos($content_lc, $ph, 0, 'UTF-8') !== false ) {
                return new WP_Error(
                    'banned_phrase_detected',
                    __( 'Your review contains banned phrases.', 'init-review-system' ),
                    [ 'status' => 400, 'hit' => $ph ]
                );
            }
        }
    }

    // --- 3) Simple quality checks (backend; JS sẽ thêm sau)
    // 3a) No whitespace (likely pasted hash/URL soup). Allow short snippets.
    $min_len_for_ws_check = (int) apply_filters('init_plugin_suite_review_system_min_len_for_ws_check', 20);
    if ( ( function_exists('mb_strlen') ? mb_strlen($content_raw, 'UTF-8') : strlen($content_raw) ) >= $min_len_for_ws_check ) {
        if ( ! preg_match('/\s/u', $content_raw) ) {
            return new WP_Error(
                'no_whitespace',
                __( 'Your review appears to contain no whitespace. Please rewrite it more naturally.', 'init-review-system' ),
                [ 'status' => 400 ]
            );
        }
    }

    // 3b) Excessive repetition of a single word
    $repeat_threshold = (int) apply_filters('init_plugin_suite_review_system_repetition_threshold', 8); // default: 8
    $tokens_for_repeat = preg_split('/[^\p{L}\p{N}]+/u', $content_lc, -1, PREG_SPLIT_NO_EMPTY);
    if ( $tokens_for_repeat ) {
        $counts = [];
        $maxWord = '';
        $maxCnt  = 0;
        foreach ( $tokens_for_repeat as $tk ) {
            $counts[$tk] = ($counts[$tk] ?? 0) + 1;
            if ( $counts[$tk] > $maxCnt ) {
                $maxCnt  = $counts[$tk];
                $maxWord = $tk;
            }
        }
        if ( $maxCnt >= $repeat_threshold ) {
            return new WP_Error(
                'excessive_repetition',
                __( 'Your review repeats the same word too many times.', 'init-review-system' ),
                [ 'status' => 400, 'word' => $maxWord, 'count' => $maxCnt, 'threshold' => $repeat_threshold ]
            );
        }
    }

    $insert_id = init_plugin_suite_review_system_add_criteria_review(
        $post_id,
        $user_id,
        $valid_scores,
        $review_content,
        'approved'
    );

    if ( ! $insert_id ) {
        return new WP_Error( 'db_error', __( 'Could not insert review.', 'init-review-system' ), [ 'status' => 500 ] );
    }

    $avg_score = round( array_sum( $valid_scores ) / count( $valid_scores ), 2 );

    do_action( 'init_plugin_suite_review_system_after_criteria_review', $post_id, $user_id, $avg_score, $review_content, $valid_scores );

    return rest_ensure_response([
        'success' => true,
        'message' => __( 'Review submitted successfully.', 'init-review-system' ),
        'avg'     => $avg_score,
    ]);
}

// Lấy các bài review
function init_plugin_suite_review_system_rest_get_criteria_reviews( WP_REST_Request $request ) {
    $post_id   = absint( $request->get_param( 'post_id' ) );
    $page      = max( 1, absint( $request->get_param( 'page' ) ) );
    $per_page  = min( 100, max( 1, absint( $request->get_param( 'per_page' ) ) ) );

    if ( ! get_post( $post_id ) ) {
        return new WP_Error( 'invalid_post', __( 'Invalid post ID.', 'init-review-system' ), [ 'status' => 400 ] );
    }

    $total     = init_plugin_suite_review_system_get_total_reviews_by_post_id( $post_id );
    $max_page  = ceil( $total / $per_page );
    $reviews   = init_plugin_suite_review_system_get_reviews_by_post_id( $post_id, $page, $per_page );

    return rest_ensure_response([
        'success'   => true,
        'post_id'   => $post_id,
        'page'      => $page,
        'per_page'  => $per_page,
        'total'     => $total,
        'max_page'  => $max_page,
        'reviews'   => $reviews,
    ]);
}

/**
 * GET /reactions/summary
 */
function init_plugin_suite_review_system_rest_get_reactions_summary( WP_REST_Request $req ) {
    $post_id = absint( $req->get_param('post_id') );
    $post_id = init_plugin_suite_review_system_assert_post($post_id);
    if ( ! $post_id ) {
        return new WP_Error('invalid_post', __('Invalid post ID.', 'init-review-system'), ['status'=>400]);
    }

    $counts = init_plugin_suite_review_system_get_reaction_counts($post_id);
    $user_rx = is_user_logged_in()
        ? init_plugin_suite_review_system_get_user_reaction($post_id, get_current_user_id())
        : '';

    return rest_ensure_response([
        'success'       => true,
        'post_id'       => $post_id,
        'counts'        => $counts,
        'user_reaction' => $user_rx,
        'types'         => init_plugin_suite_review_system_get_reaction_types(),
    ]);
}

/**
 * POST /reactions/toggle
 */
function init_plugin_suite_review_system_rest_toggle_reaction( WP_REST_Request $req ) {
    $post_id  = absint( $req->get_param('post_id') );
    $reaction = sanitize_key( $req->get_param('reaction') );

    $post_id = init_plugin_suite_review_system_assert_post($post_id);
    if ( ! $post_id ) {
        return new WP_Error(
            'invalid_post',
            __( 'Invalid post ID.', 'init-review-system' ),
            [ 'status' => 400 ]
        );
    }

    // Bắt buộc login
    if ( ! is_user_logged_in() ) {
        return new WP_Error(
            'login_required',
            __( 'Login required.', 'init-review-system' ),
            [ 'status' => 403 ]
        );
    }

    // Verify nonce
    $nonce = isset($_SERVER['HTTP_X_WP_NONCE'])
        ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_WP_NONCE'] ) )
        : '';

    if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
        return new WP_Error(
            'invalid_nonce',
            __( 'Invalid nonce.', 'init-review-system' ),
            [ 'status' => 403 ]
        );
    }

    $user_id = get_current_user_id();

    // Áp dụng logic (add/switch/remove) từ core helpers
    $result = init_plugin_suite_review_system_apply_user_reaction(
        $post_id,
        $user_id,
        $reaction
    );

    if ( empty($result['success']) ) {
        return new WP_Error(
            'rx_failed',
            __( 'Could not update reaction.', 'init-review-system' ),
            [ 'status' => 500 ]
        );
    }

    return rest_ensure_response([
        'success'       => true,
        'post_id'       => $post_id,
        'user_reaction' => $result['current'],  // '' nếu gỡ
        'counts'        => $result['counts'],
        'prev'          => $result['prev'],
    ]);
}
