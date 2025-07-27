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

    // Hook mở rộng
    do_action( 'init_plugin_suite_review_system_after_vote', $post_id, $score, $new_avg, $new_total_count );

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
    $per_page  = max( 1, absint( $request->get_param( 'per_page' ) ) );

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
