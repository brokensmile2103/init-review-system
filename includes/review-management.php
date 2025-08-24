<?php
defined( 'ABSPATH' ) || exit;

/**
 * Add review management submenu
 */
add_action( 'admin_menu', 'init_plugin_suite_review_system_add_management_page', 11 );

function init_plugin_suite_review_system_add_management_page() {
    add_submenu_page(
        INIT_PLUGIN_SUITE_RS_SLUG,
        __( 'Manage Reviews', 'init-review-system' ),
        __( 'Manage Reviews', 'init-review-system' ),
        'manage_options',
        'init-review-management',
        'init_plugin_suite_review_system_render_management_page'
    );
}

/**
 * Handle review management actions
 */
add_action( 'admin_init', 'init_plugin_suite_review_system_handle_management_actions' );

function init_plugin_suite_review_system_handle_management_actions() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : '';
    $review_id = isset( $_GET['review_id'] ) ? absint( $_GET['review_id'] ) : 0;
    
    if ( ! $action || ! $review_id ) {
        return;
    }

    // Verify nonce
    $nonce_action = "review_{$action}_{$review_id}";
    if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), $nonce_action ) ) {
        wp_die( esc_html__( 'Security check failed.', 'init-review-system' ) );
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'init_criteria_reviews';

    switch ( $action ) {
        case 'delete':
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $result = $wpdb->delete( $table_name, [ 'id' => $review_id ], [ '%d' ] );
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            
            if ( $result ) {
                add_action( 'admin_notices', function() {
                    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Review deleted successfully.', 'init-review-system' ) . '</p></div>';
                });
            } else {
                add_action( 'admin_notices', function() {
                    echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Failed to delete review.', 'init-review-system' ) . '</p></div>';
                });
            }
            break;

        case 'approve':
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $result = $wpdb->update( $table_name, [ 'status' => 'approved' ], [ 'id' => $review_id ], [ '%s' ], [ '%d' ] );
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            
            if ( $result !== false ) {
                add_action( 'admin_notices', function() {
                    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Review approved.', 'init-review-system' ) . '</p></div>';
                });
            }
            break;

        case 'reject':
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $result = $wpdb->update( $table_name, [ 'status' => 'rejected' ], [ 'id' => $review_id ], [ '%s' ], [ '%d' ] );
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            
            if ( $result !== false ) {
                add_action( 'admin_notices', function() {
                    echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__( 'Review rejected.', 'init-review-system' ) . '</p></div>';
                });
            }
            break;
    }

    // Redirect to avoid resubmission
    wp_safe_redirect( admin_url( 'admin.php?page=init-review-management' ) );
    exit;
}

/**
 * Handle bulk actions
 */
add_action( 'admin_init', 'init_plugin_suite_review_system_handle_bulk_actions' );

function init_plugin_suite_review_system_handle_bulk_actions() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $action = isset( $_POST['action'] ) ? sanitize_text_field( wp_unslash( $_POST['action'] ) ) : '';
    if ( $action === '-1' ) {
        $action = isset( $_POST['action2'] ) ? sanitize_text_field( wp_unslash( $_POST['action2'] ) ) : '';
    }

    if ( ! $action || $action === '-1' ) {
        return;
    }

    if ( ! isset( $_POST['reviews'] ) || ! is_array( $_POST['reviews'] ) ) {
        return;
    }

    // Verify nonce
    if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'bulk_reviews_action' ) ) {
        wp_die( esc_html__( 'Security check failed.', 'init-review-system' ) );
    }

    $review_ids = array_map( 'absint', wp_unslash( $_POST['reviews'] ) );
    $review_ids = array_filter( $review_ids );

    if ( empty( $review_ids ) ) {
        return;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'init_criteria_reviews';
    $placeholders = implode( ',', array_fill( 0, count( $review_ids ), '%d' ) );

    switch ( $action ) {
        case 'delete':
            $placeholders = implode( ',', array_fill( 0, count( $review_ids ), '%d' ) );
            // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
            $result = $wpdb->query( 
                $wpdb->prepare( 
                    "DELETE FROM $table_name WHERE id IN ($placeholders)", 
                    ...$review_ids 
                )
            );
            // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
            
            if ( $result ) {
                add_action( 'admin_notices', function() use ( $result ) {
                    printf(
                        '<div class="notice notice-success is-dismissible"><p>%s</p></div>',
                        // translators: %d is the number of reviews deleted.
                        esc_html( sprintf( _n( '%d review deleted.', '%d reviews deleted.', $result, 'init-review-system' ), $result ) )
                    );
                });
            }
            break;

        case 'approve':
            $placeholders = implode( ',', array_fill( 0, count( $review_ids ), '%d' ) );
            // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
            $result = $wpdb->query( 
                $wpdb->prepare( 
                    "UPDATE $table_name SET status = 'approved' WHERE id IN ($placeholders)", 
                    ...$review_ids 
                )
            );
            // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
            
            if ( $result ) {
                add_action( 'admin_notices', function() use ( $result ) {
                    printf(
                        '<div class="notice notice-success is-dismissible"><p>%s</p></div>',
                        // translators: %d is the number of reviews approved.
                        esc_html( sprintf( _n( '%d review approved.', '%d reviews approved.', $result, 'init-review-system' ), $result ) )
                    );
                });
            }
            break;

        case 'reject':
            $placeholders = implode( ',', array_fill( 0, count( $review_ids ), '%d' ) );
            // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
            $result = $wpdb->query( 
                $wpdb->prepare( 
                    "UPDATE $table_name SET status = 'rejected' WHERE id IN ($placeholders)", 
                    ...$review_ids 
                )
            );
            // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
            
            if ( $result ) {
                add_action( 'admin_notices', function() use ( $result ) {
                    printf(
                        '<div class="notice notice-warning is-dismissible"><p>%s</p></div>',
                        // translators: %d is the number of reviews rejected.
                        esc_html( sprintf( _n( '%d review rejected.', '%d reviews rejected.', $result, 'init-review-system' ), $result ) )
                    );
                });
            }
            break;
    }
}

/**
 * Get reviews with filters and pagination
 */
function init_plugin_suite_review_system_get_reviews_for_admin( $filters = [] ) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'init_criteria_reviews';

    $where_conditions = [];
    $where_values = [];

    // Filter by status
    if ( ! empty( $filters['status'] ) && $filters['status'] !== 'all' ) {
        $where_conditions[] = 'status = %s';
        $where_values[] = sanitize_text_field( $filters['status'] );
    }

    // Filter by post ID
    if ( ! empty( $filters['post_id'] ) ) {
        $where_conditions[] = 'post_id = %d';
        $where_values[] = absint( $filters['post_id'] );
    }

    // Filter by user ID
    if ( ! empty( $filters['user_id'] ) ) {
        $where_conditions[] = 'user_id = %d';
        $where_values[] = absint( $filters['user_id'] );
    }

    // Search in review content
    if ( ! empty( $filters['search'] ) ) {
        $where_conditions[] = 'review_content LIKE %s';
        $where_values[] = '%' . $wpdb->esc_like( sanitize_text_field( $filters['search'] ) ) . '%';
    }

    $where_clause = '';
    if ( ! empty( $where_conditions ) ) {
        $where_clause = 'WHERE ' . implode( ' AND ', $where_conditions );
    }

    // Count total
    // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $count_sql = "SELECT COUNT(*) FROM $table_name $where_clause";
    // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    
    if ( ! empty( $where_values ) ) {
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
        $total = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, ...$where_values ) );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
    } else {
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
        $total = (int) $wpdb->get_var( $count_sql );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
    }

    // Get paginated results
    $per_page = 20;
    // phpcs:disable WordPress.Security.NonceVerification.Recommended
    $paged = isset( $_GET['paged'] ) ? max( 1, absint( wp_unslash( $_GET['paged'] ) ) ) : 1;
    // phpcs:enable WordPress.Security.NonceVerification.Recommended
    $offset = ( $paged - 1 ) * $per_page;

    // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
    $results_sql = "SELECT * FROM $table_name $where_clause ORDER BY created_at DESC LIMIT %d OFFSET %d";
    // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
    $query_values = array_merge( $where_values, [ $per_page, $offset ] );

    // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
    $results = $wpdb->get_results( $wpdb->prepare( $results_sql, ...$query_values ), ARRAY_A );
    // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared

    // Unserialize criteria scores
    foreach ( $results as &$review ) {
        $review['criteria_scores'] = maybe_unserialize( $review['criteria_scores'] );
    }

    return [
        'reviews' => $results,
        'total' => $total,
        'per_page' => $per_page,
        'paged' => $paged,
        'total_pages' => ceil( $total / $per_page ),
    ];
}

/**
 * Render management page
 */
function init_plugin_suite_review_system_render_management_page() {
    // Get current filters with proper sanitization and nonce-less GET handling
    $filters = [
        // phpcs:disable WordPress.Security.NonceVerification.Recommended
        'status' => isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : 'all',
        'post_id' => isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : '',
        'user_id' => isset( $_GET['user_id'] ) ? absint( $_GET['user_id'] ) : '',
        'search' => isset( $_GET['search'] ) ? sanitize_text_field( wp_unslash( $_GET['search'] ) ) : '',
        // phpcs:enable WordPress.Security.NonceVerification.Recommended
    ];

    $data = init_plugin_suite_review_system_get_reviews_for_admin( $filters );
    
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'Manage Reviews', 'init-review-system' ); ?></h1>

        <!-- Filters -->
        <div class="tablenav top">
            <form method="get" class="alignleft">
                <input type="hidden" name="page" value="init-review-management">
                
                <select name="status">
                    <option value="all" <?php selected( $filters['status'], 'all' ); ?>><?php esc_html_e( 'All Statuses', 'init-review-system' ); ?></option>
                    <option value="approved" <?php selected( $filters['status'], 'approved' ); ?>><?php esc_html_e( 'Approved', 'init-review-system' ); ?></option>
                    <option value="pending" <?php selected( $filters['status'], 'pending' ); ?>><?php esc_html_e( 'Pending', 'init-review-system' ); ?></option>
                    <option value="rejected" <?php selected( $filters['status'], 'rejected' ); ?>><?php esc_html_e( 'Rejected', 'init-review-system' ); ?></option>
                </select>

                <input type="number" name="post_id" value="<?php echo esc_attr( $filters['post_id'] ); ?>" placeholder="<?php esc_attr_e( 'Post ID', 'init-review-system' ); ?>" style="width: 80px;">
                
                <input type="number" name="user_id" value="<?php echo esc_attr( $filters['user_id'] ); ?>" placeholder="<?php esc_attr_e( 'User ID', 'init-review-system' ); ?>" style="width: 80px;">
                
                <input type="text" name="search" value="<?php echo esc_attr( $filters['search'] ); ?>" placeholder="<?php esc_attr_e( 'Search content...', 'init-review-system' ); ?>">
                
                <input type="submit" class="button" value="<?php esc_attr_e( 'Filter', 'init-review-system' ); ?>">
                
                <?php if ( array_filter( $filters ) ): ?>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=init-review-management' ) ); ?>" class="button"><?php esc_html_e( 'Clear', 'init-review-system' ); ?></a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Bulk Actions Form -->
        <form method="post">
            <?php wp_nonce_field( 'bulk_reviews_action' ); ?>
            
            <div class="tablenav top">
                <div class="alignleft actions bulkactions">
                    <select name="action">
                        <option value="-1"><?php esc_html_e( 'Bulk Actions', 'init-review-system' ); ?></option>
                        <option value="approve"><?php esc_html_e( 'Approve', 'init-review-system' ); ?></option>
                        <option value="reject"><?php esc_html_e( 'Reject', 'init-review-system' ); ?></option>
                        <option value="delete"><?php esc_html_e( 'Delete', 'init-review-system' ); ?></option>
                    </select>
                    <input type="submit" class="button action" value="<?php esc_attr_e( 'Apply', 'init-review-system' ); ?>">
                </div>
            </div>

            <!-- Reviews Table -->
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <td class="manage-column column-cb check-column">
                            <input type="checkbox" id="cb-select-all-1">
                        </td>
                        <th><?php esc_html_e( 'Review', 'init-review-system' ); ?></th>
                        <th><?php esc_html_e( 'Post', 'init-review-system' ); ?></th>
                        <th><?php esc_html_e( 'User', 'init-review-system' ); ?></th>
                        <th><?php esc_html_e( 'Score', 'init-review-system' ); ?></th>
                        <th><?php esc_html_e( 'Status', 'init-review-system' ); ?></th>
                        <th><?php esc_html_e( 'Date', 'init-review-system' ); ?></th>
                        <th><?php esc_html_e( 'Actions', 'init-review-system' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $data['reviews'] ) ): ?>
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 20px;">
                                <?php esc_html_e( 'No reviews found.', 'init-review-system' ); ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ( $data['reviews'] as $review ): ?>
                            <tr>
                                <th class="check-column">
                                    <input type="checkbox" name="reviews[]" value="<?php echo esc_attr( $review['id'] ); ?>">
                                </th>
                                <td>
                                    <div style="max-width: 300px;">
                                        <?php if ( ! empty( $review['criteria_scores'] ) ): ?>
                                            <div class="criteria-scores" style="margin-bottom: 8px;">
                                                <?php foreach ( $review['criteria_scores'] as $label => $score ): ?>
                                                    <span style="display: inline-block; margin-right: 10px; font-size: 12px; background: #f1f1f1; padding: 2px 6px; border-radius: 3px;">
                                                        <?php echo esc_html( $label ); ?>: <?php echo esc_html( number_format( $score, 1 ) ); ?>/5
                                                    </span>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                        <div style="color: #666; line-height: 1.4;">
                                            <?php echo esc_html( wp_trim_words( $review['review_content'], 15 ) ); ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php 
                                    $post = get_post( $review['post_id'] );
                                    if ( $post ):
                                    ?>
                                        <a href="<?php echo esc_url( get_edit_post_link( $review['post_id'] ) ); ?>" target="_blank">
                                            <?php echo esc_html( wp_trim_words( $post->post_title, 5 ) ); ?>
                                        </a>
                                        <br>
                                        <small style="color: #666;">ID: <?php echo esc_html( $review['post_id'] ); ?></small>
                                    <?php else: ?>
                                        <span style="color: #dc3232;"><?php esc_html_e( 'Post not found', 'init-review-system' ); ?></span>
                                        <br>
                                        <small style="color: #666;">ID: <?php echo esc_html( $review['post_id'] ); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ( $review['user_id'] > 0 ): ?>
                                        <?php 
                                        $user = get_user_by( 'id', $review['user_id'] );
                                        if ( $user ):
                                        ?>
                                            <a href="<?php echo esc_url( get_edit_user_link( $review['user_id'] ) ); ?>" target="_blank">
                                                <?php echo esc_html( $user->display_name ); ?>
                                            </a>
                                            <br>
                                            <small style="color: #666;"><?php echo esc_html( $user->user_email ); ?></small>
                                        <?php else: ?>
                                            <span style="color: #dc3232;"><?php esc_html_e( 'User not found', 'init-review-system' ); ?></span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span style="color: #666;"><?php esc_html_e( 'Guest', 'init-review-system' ); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?php echo esc_html( number_format( $review['avg_score'], 2 ) ); ?>/5</strong>
                                </td>
                                <td>
                                    <?php
                                    $status_colors = [
                                        'approved' => '#46b450',
                                        'pending' => '#ffba00',
                                        'rejected' => '#dc3232',
                                    ];
                                    $status_color = $status_colors[ $review['status'] ] ?? '#666';
                                    ?>
                                    <span style="color: <?php echo esc_attr( $status_color ); ?>; font-weight: 500;">
                                        <?php echo esc_html( ucfirst( $review['status'] ) ); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo esc_html( mysql2date( 'Y/m/d g:i A', $review['created_at'] ) ); ?>
                                </td>
                                <td>
                                    <?php if ( $review['status'] !== 'approved' ): ?>
                                        <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=init-review-management&action=approve&review_id=' . $review['id'] ), "review_approve_{$review['id']}" ) ); ?>" 
                                           class="button-primary" style="margin-right: 5px;">
                                            <?php esc_html_e( 'Approve', 'init-review-system' ); ?>
                                        </a>
                                    <?php endif; ?>
                                    
                                    <?php if ( $review['status'] !== 'rejected' ): ?>
                                        <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=init-review-management&action=reject&review_id=' . $review['id'] ), "review_reject_{$review['id']}" ) ); ?>" 
                                           class="button" style="margin-right: 5px;">
                                            <?php esc_html_e( 'Reject', 'init-review-system' ); ?>
                                        </a>
                                    <?php endif; ?>
                                    
                                    <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=init-review-management&action=delete&review_id=' . $review['id'] ), "review_delete_{$review['id']}" ) ); ?>" 
                                       class="button button-link-delete" 
                                       onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to delete this review?', 'init-review-system' ); ?>')">
                                        <?php esc_html_e( 'Delete', 'init-review-system' ); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td class="manage-column column-cb check-column">
                            <input type="checkbox" id="cb-select-all-2">
                        </td>
                        <th><?php esc_html_e( 'Review', 'init-review-system' ); ?></th>
                        <th><?php esc_html_e( 'Post', 'init-review-system' ); ?></th>
                        <th><?php esc_html_e( 'User', 'init-review-system' ); ?></th>
                        <th><?php esc_html_e( 'Score', 'init-review-system' ); ?></th>
                        <th><?php esc_html_e( 'Status', 'init-review-system' ); ?></th>
                        <th><?php esc_html_e( 'Date', 'init-review-system' ); ?></th>
                        <th><?php esc_html_e( 'Actions', 'init-review-system' ); ?></th>
                    </tr>
                </tfoot>
            </table>

            <div class="tablenav bottom">
                <div class="alignleft actions bulkactions">
                    <select name="action2">
                        <option value="-1"><?php esc_html_e( 'Bulk Actions', 'init-review-system' ); ?></option>
                        <option value="approve"><?php esc_html_e( 'Approve', 'init-review-system' ); ?></option>
                        <option value="reject"><?php esc_html_e( 'Reject', 'init-review-system' ); ?></option>
                        <option value="delete"><?php esc_html_e( 'Delete', 'init-review-system' ); ?></option>
                    </select>
                    <input type="submit" class="button action" value="<?php esc_attr_e( 'Apply', 'init-review-system' ); ?>">
                </div>
                
                <!-- Pagination -->
                <?php if ( $data['total_pages'] > 1 ): ?>
                    <div class="tablenav-pages">
                        <span class="displaying-num">
                            <?php 
                            // translators: %s is the number of items in the list.
                            printf( esc_html( _n( '%s item', '%s items', $data['total'], 'init-review-system' ) ), esc_html( number_format_i18n( $data['total'] ) ) ); 
                            ?>
                        </span>
                        
                        <?php
                        $page_links = paginate_links([
                            'base' => add_query_arg( 'paged', '%#%' ),
                            'format' => '',
                            'prev_text' => '&laquo;',
                            'next_text' => '&raquo;',
                            'total' => $data['total_pages'],
                            'current' => $data['paged'],
                            'type' => 'plain'
                        ]);
                        
                        if ( $page_links ) {
                            echo '<span class="pagination-links">' . $page_links . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                        }
                        ?>
                    </div>
                <?php endif; ?>
            </div>
        </form>

        <!-- Summary Stats -->
        <div style="margin-top: 20px; padding: 15px; background: #f9f9f9; border-radius: 5px;">
            <h3><?php esc_html_e( 'Summary', 'init-review-system' ); ?></h3>
            <?php
            global $wpdb;
            $table_name = $wpdb->prefix . 'init_criteria_reviews';
            
            // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $stats = $wpdb->get_results(
                "SELECT status, COUNT(*) as count FROM $table_name GROUP BY status",
                ARRAY_A
            );
            
            $total_reviews = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table_name" );
            $avg_score = (float) $wpdb->get_var( "SELECT AVG(avg_score) FROM $table_name WHERE status = 'approved'" );
            // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            
            $stats_by_status = [];
            foreach ( $stats as $stat ) {
                $stats_by_status[ $stat['status'] ] = $stat['count'];
            }
            ?>
            <div style="display: flex; gap: 20px; flex-wrap: wrap;">
                <div>
                    <strong><?php esc_html_e( 'Total Reviews:', 'init-review-system' ); ?></strong>
                    <?php echo esc_html( number_format_i18n( $total_reviews ) ); ?>
                </div>
                <div>
                    <strong><?php esc_html_e( 'Approved:', 'init-review-system' ); ?></strong>
                    <span style="color: #46b450;">
                        <?php echo esc_html( number_format_i18n( $stats_by_status['approved'] ?? 0 ) ); ?>
                    </span>
                </div>
                <div>
                    <strong><?php esc_html_e( 'Pending:', 'init-review-system' ); ?></strong>
                    <span style="color: #ffba00;">
                        <?php echo esc_html( number_format_i18n( $stats_by_status['pending'] ?? 0 ) ); ?>
                    </span>
                </div>
                <div>
                    <strong><?php esc_html_e( 'Rejected:', 'init-review-system' ); ?></strong>
                    <span style="color: #dc3232;">
                        <?php echo esc_html( number_format_i18n( $stats_by_status['rejected'] ?? 0 ) ); ?>
                    </span>
                </div>
                <div>
                    <strong><?php esc_html_e( 'Average Score:', 'init-review-system' ); ?></strong>
                    <?php echo esc_html( number_format( $avg_score, 2 ) ); ?>/5
                </div>
            </div>
        </div>
    </div>

    <?php
}

/**
 * Enqueue admin scripts for review management
 */
add_action( 'admin_enqueue_scripts', function( $hook ) {
    if ( $hook !== 'review-system_page_init-review-management' ) {
        return;
    }
    
    wp_enqueue_script(
        'init-review-management-script',
        INIT_PLUGIN_SUITE_RS_ASSETS_URL . 'js/review-management.js',
        [],
        INIT_PLUGIN_SUITE_RS_VERSION,
        true
    );
} );
