// === Init Review System — Shortcode Builder panel (admin settings page) ===
document.addEventListener('DOMContentLoaded', function () {
    const i18n = (window.InitReviewSystemShortcodeBuilder && window.InitReviewSystemShortcodeBuilder.i18n) || {};
    const t = function (key, fallback) {
        return i18n[key] || fallback;
    };

    const target = document.querySelector('[data-plugin="init-review-system"]');
    if (!target) return;

    const idAttr = { label: t('id', 'Post ID'), type: 'number', default: '' };
    const classAttr = { label: t('class', 'Custom Class'), type: 'text', default: '' };

    const shortcodes = [
        {
            label: t('init_review_score', 'Review Score'),
            shortcode: 'init_review_score',
            attributes: {
                id: idAttr,
                icon: { label: t('icon', 'Show Icon'), type: 'checkbox', default: true },
                sub: { label: t('sub', 'Show "/5" Suffix'), type: 'checkbox', default: true },
                show_count: { label: t('show_count', 'Show Review Count'), type: 'checkbox', default: false },
                hide_if_empty: { label: t('hide_if_empty', 'Hide If No Reviews'), type: 'checkbox', default: false },
                class: classAttr
            }
        },
        {
            label: t('init_review_widget', 'Review Widget'),
            shortcode: 'init_review_system',
            attributes: {
                id: idAttr,
                schema: { label: t('schema', 'Enable Schema.org'), type: 'checkbox', default: false },
                class: classAttr
            }
        },
        {
            label: t('init_review_criteria', 'Review Criteria'),
            shortcode: 'init_review_criteria',
            attributes: {
                id: idAttr,
                schema: { label: t('schema', 'Enable Schema.org'), type: 'checkbox', default: false },
                class: classAttr,
                per_page: { label: t('per_page', 'Posts per page'), type: 'number', default: '0' }
            }
        },
        {
            label: t('init_reactions', 'Reactions Bar'),
            shortcode: 'init_reactions',
            attributes: {
                id: idAttr,
                class: classAttr,
                css: { label: t('css', 'Load CSS'), type: 'checkbox', default: true }
            }
        }
    ];

    const panel = renderShortcodeBuilderPanel({
        title: t('init_review_system', 'Init Review System'),
        buttons: shortcodes.map(function (item) {
            return {
                label: item.label,
                dashicon: 'editor-code',
                className: 'button-default',
                onClick: function () {
                    initShortcodeBuilder({
                        shortcode: item.shortcode,
                        config: {
                            label: item.label,
                            attributes: item.attributes
                        }
                    });
                }
            };
        })
    });

    target.appendChild(panel);
});
