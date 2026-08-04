<?php
defined( 'ABSPATH' ) || exit;

// ============================================================================
// Block Editor integration
// ----------------------------------------------------------------------------
// 4 block tương ứng 1-1 với 4 shortcode đã có: [init_review_score],
// [init_review_system], [init_review_criteria], [init_reactions]. Mỗi block
// dùng "render" trong block.json (PHP, từ WP 6.1+) trỏ tới file render.php —
// file này chỉ gọi lại đúng hàm shortcode gốc (đã được tách thành hàm có tên
// trong includes/shortcodes.php), nên KHÔNG có logic hiển thị nào bị lặp
// lại/lệch so với shortcode.
//
// Phần JS (assets/js/blocks-editor.js) là vanilla JS thuần, không build step,
// dùng ServerSideRender để preview ngay trong Block Editor.
//
// Kiến trúc này đồng bộ với Init View Count và Init Live Search (block.json +
// render.php + 1 file JS chung cho toàn bộ block).
// ============================================================================

add_filter( 'block_categories_all', 'init_plugin_suite_review_system_block_category', 10, 2 );
/**
 * Thêm 1 category riêng trong block inserter cho gọn, thay vì rơi vào "Widgets".
 *
 * @param array                   $categories     Danh sách category hiện có.
 * @param WP_Block_Editor_Context $editor_context Context hiện tại của editor (không dùng tới,
 *                                                nhưng bắt buộc phải khai báo theo đúng chữ ký
 *                                                mà hook 'block_categories_all' truyền vào).
 * @return array
 */
// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
function init_plugin_suite_review_system_block_category( $categories, $editor_context ) {
    return array_merge(
        [
            [
                'slug'  => 'init-review-system',
                'title' => __( 'Init Review System', 'init-review-system' ),
                'icon'  => 'star-filled',
            ],
        ],
        $categories
    );
}

add_action( 'init', 'init_plugin_suite_review_system_register_style_handles', 5 );
/**
 * Đăng ký (không enqueue) các handle CSS front-end, để block.json của 4
 * block có thể tham chiếu qua "style" — WordPress sẽ tự enqueue đúng lúc,
 * đúng chỗ (cả trong Block Editor lẫn ngoài front-end) khi block thực sự
 * được dùng.
 *
 * Cả 2 handle này cũng được đăng ký/enqueue độc lập ở nơi khác (style chính
 * qua wp_enqueue_scripts trong file plugin chính, style reactions qua
 * shortcode [init_reactions]) — đăng ký lại ở đây với cùng src là an toàn
 * (WordPress chỉ ghi đè bằng dữ liệu giống hệt), và đảm bảo handle luôn tồn
 * tại sớm cho riêng cơ chế "style" của block.json, kể cả khi trang chỉ dùng
 * block mà chưa từng gọi tới shortcode.
 *
 * Ưu tiên chạy trước (priority 5) hàm đăng ký block bên dưới.
 *
 * @return void
 */
function init_plugin_suite_review_system_register_style_handles() {
    wp_register_style(
        'init-review-system-style',
        INIT_PLUGIN_SUITE_RS_ASSETS_URL . 'css/style.css',
        [],
        INIT_PLUGIN_SUITE_RS_VERSION
    );

    wp_register_style(
        'init-review-system-reactions',
        INIT_PLUGIN_SUITE_RS_ASSETS_URL . 'css/reactions.css',
        [],
        INIT_PLUGIN_SUITE_RS_VERSION
    );
}

add_action( 'init', 'init_plugin_suite_review_system_register_blocks', 10 );
/**
 * Đăng ký script cho Block Editor và 4 block type.
 *
 * @return void
 */
function init_plugin_suite_review_system_register_blocks() {
    if ( ! function_exists( 'register_block_type' ) ) {
        return;
    }

    wp_register_script(
        'init-review-system-blocks-editor',
        INIT_PLUGIN_SUITE_RS_ASSETS_URL . 'js/blocks-editor.js',
        [
            'wp-blocks',
            'wp-element',
            'wp-block-editor',
            'wp-components',
            'wp-i18n',
            'wp-server-side-render',
        ],
        INIT_PLUGIN_SUITE_RS_VERSION,
        true
    );

    if ( function_exists( 'wp_set_script_translations' ) ) {
        wp_set_script_translations(
            'init-review-system-blocks-editor',
            'init-review-system',
            INIT_PLUGIN_SUITE_RS_PATH . 'languages'
        );
    }

    register_block_type( INIT_PLUGIN_SUITE_RS_PATH . 'blocks/review-score' );
    register_block_type( INIT_PLUGIN_SUITE_RS_PATH . 'blocks/review-system' );
    register_block_type( INIT_PLUGIN_SUITE_RS_PATH . 'blocks/review-criteria' );
    register_block_type( INIT_PLUGIN_SUITE_RS_PATH . 'blocks/reactions' );
}
