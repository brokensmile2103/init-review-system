(function () {
    document.addEventListener('DOMContentLoaded', function () {
        const i18n = (window.InitReviewSystemShortcodeBuilder && window.InitReviewSystemShortcodeBuilder.i18n) || {};
        const t = function (key, fallback) {
            return i18n[key] || fallback;
        };

        const target = document.querySelector('[data-plugin="init-review-system"]');
        if (!target) return;

        const buttons = [
            {
                label: t('init_review_score', 'Review Score'),
                shortcode: 'init_review_score',
                attributes: {
                    id: { label: t('id', 'Post ID'), type: 'number', default: '' },
                    icon: { label: t('icon', 'Show Icon'), type: 'checkbox', default: true },
                    schema: { label: t('schema', 'Enable Schema.org'), type: 'checkbox', default: false },
                    class: { label: t('class', 'Custom Class'), type: 'text', default: '' }
                }
            },
            {
                label: t('init_review_system', 'Review System'),
                shortcode: 'init_review_system',
                attributes: {
                    id: { label: t('id', 'Post ID'), type: 'number', default: '' },
                    schema: { label: t('schema', 'Enable Schema.org'), type: 'checkbox', default: false },
                    class: { label: t('class', 'Custom Class'), type: 'text', default: '' }
                }
            },
            {
                label: t('init_review_criteria', 'Review Criteria'),
                shortcode: 'init_review_criteria',
                attributes: {
                    id: { label: t('id', 'Post ID'), type: 'number', default: '' },
                    schema: { label: t('schema', 'Enable Schema.org'), type: 'checkbox', default: false },
                    class: { label: t('class', 'Custom Class'), type: 'text', default: '' },
                    per_page: { label: t('per_page', 'Posts per page'), type: 'number', default: '0' }
                }
            },
            {
                label: t('init_reactions', 'Reactions Bar'),
                shortcode: 'init_reactions',
                attributes: {
                    id:   { label: t('id', 'Post ID'), type: 'number', default: '' },
                    class:{ label: t('class', 'Custom Class'), type: 'text', default: '' },
                    css:  { label: t('css', 'Load CSS'), type: 'checkbox', default: true }
                }
            }
        ];

        const panel = renderShortcodeBuilderPanel({
            title: t('init_review_system', 'Init Review System'),
            buttons: buttons.map(function (btn) {
                return {
                    label: btn.label,
                    dashicon: 'editor-code',
                    className: 'button-default',
                    onClick: function () {
                        initShortcodeBuilder({
                            shortcode: btn.shortcode,
                            config: {
                                label: btn.label,
                                attributes: btn.attributes
                            }
                        });
                    }
                };
            })
        });

        target.appendChild(panel);
    });
})();
