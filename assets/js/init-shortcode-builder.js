// === Init Shortcode Builder (admin) ===
// v2.0.1:
// - Đọc đúng object i18n được localize (InitReviewSystemShortcodeBuilder),
//   vẫn ưu tiên InitShortcodeBuilder nếu có (dùng chung trong Init Plugin Suite).
// - Nút Copy hoạt động cả khi admin chạy trên HTTP (không có navigator.clipboard).
// - Checkbox mặc định bật mà bị bỏ chọn sẽ sinh ra key="false" (trước đây bị
//   bỏ qua, khiến shortcode vẫn dùng giá trị mặc định true).
// - Label được gán bằng textContent thay vì innerHTML.
(function (w) {
    'use strict';

    function getI18n() {
        return (w.InitShortcodeBuilder && w.InitShortcodeBuilder.i18n)
            || (w.InitReviewSystemShortcodeBuilder && w.InitReviewSystemShortcodeBuilder.i18n)
            || {};
    }

    function renderShortcodeBuilderButton({ label, dashicon, onClick, className = '' }) {
        const btn = document.createElement('button');
        btn.className = `button ${className}`;
        btn.type = 'button';
        btn.style.display = 'inline-flex';
        btn.style.alignItems = 'center';
        btn.style.gap = '6px';
        btn.style.marginRight = '10px';
        btn.style.marginBottom = '10px';
        btn.addEventListener('click', onClick);

        if (dashicon) {
            const icon = document.createElement('span');
            icon.className = `dashicons dashicons-${dashicon}`;
            icon.style.lineHeight = '1';
            btn.appendChild(icon);
        }

        btn.appendChild(document.createTextNode(label || 'Build Shortcode'));
        return btn;
    }

    function copyText(text) {
        if (navigator.clipboard && w.isSecureContext) {
            return navigator.clipboard.writeText(text);
        }

        // Fallback cho môi trường không phải HTTPS.
        return new Promise((resolve, reject) => {
            const ta = document.createElement('textarea');
            ta.value = text;
            ta.setAttribute('readonly', '');
            ta.style.position = 'fixed';
            ta.style.opacity = '0';
            document.body.appendChild(ta);
            ta.select();
            try {
                document.execCommand('copy') ? resolve() : reject(new Error('copy failed'));
            } catch (e) {
                reject(e);
            } finally {
                ta.remove();
            }
        });
    }

    w.initShortcodeBuilder = function ({ shortcode, config }) {
        const i18n = getI18n();
        const t = (key, fallback) => i18n[key] || fallback;

        let modal = document.getElementById('init-shortcode-modal');
        if (modal) modal.remove();

        modal = document.createElement('div');
        modal.id = 'init-shortcode-modal';
        modal.style = `
            position:fixed;top:0;left:0;width:100%;height:100%;
            background:rgba(0,0,0,0.5);z-index:10000;
            display:flex;align-items:center;justify-content:center;
        `;

        const content = document.createElement('div');
        content.id = 'init-shortcode-content';
        content.style = `
            background:#fff;padding:20px;border-radius:4px;
            max-width:600px;width:100%;position:relative;
        `;

        const closeTop = document.createElement('button');
        closeTop.id = 'init-shortcode-close-top';
        closeTop.type = 'button';
        closeTop.setAttribute('aria-label', t('close', 'Close'));
        closeTop.innerHTML = '<svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true"><path d="m21 21-9-9m0 0L3 3m9 9 9-9m-9 9-9 9" stroke="currentColor" stroke-width="1.1" stroke-linecap="round" stroke-linejoin="round"/></svg>';
        closeTop.style = `
            position:absolute;top:10px;right:10px;
            border:none;background:none;font-size:20px;cursor:pointer;
        `;
        content.appendChild(closeTop);

        const title = document.createElement('h2');
        title.textContent = config.label;
        title.style.marginTop = '0';
        content.appendChild(title);

        const table = document.createElement('table');
        table.className = 'form-table';
        const tbody = document.createElement('tbody');
        const values = {};
        const defaults = {};

        for (const [key, attr] of Object.entries(config.attributes)) {
            values[key] = attr.default || '';
            defaults[key] = attr.default;

            const tr = document.createElement('tr');
            const th = document.createElement('th');
            const td = document.createElement('td');

            const label = document.createElement('label');
            label.textContent = attr.label;
            th.appendChild(label);

            if (attr.type === 'select') {
                const select = document.createElement('select');
                select.className = 'regular-text';
                select.setAttribute('data-key', key);
                attr.options.forEach(opt => {
                    const option = document.createElement('option');
                    option.value = opt;
                    option.textContent = opt;
                    if (opt === attr.default) option.selected = true;
                    select.appendChild(option);
                });
                td.appendChild(select);
            } else if (attr.type === 'checkbox') {
                const input = document.createElement('input');
                input.type = 'checkbox';
                input.setAttribute('data-key', key);
                if (attr.default) input.checked = true;
                td.appendChild(input);
                td.append(' ' + attr.label);
            } else {
                const input = document.createElement('input');
                input.type = attr.type;
                input.className = 'regular-text';
                input.value = attr.default || '';
                input.setAttribute('data-key', key);
                td.appendChild(input);
            }

            tr.appendChild(th);
            tr.appendChild(td);
            tbody.appendChild(tr);
        }

        table.appendChild(tbody);
        content.appendChild(table);

        const previewLabel = document.createElement('label');
        const previewStrong = document.createElement('strong');
        previewStrong.textContent = `${t('shortcode_preview', 'Shortcode Preview')}:`;
        previewLabel.appendChild(previewStrong);

        const preview = document.createElement('textarea');
        preview.id = 'shortcode-preview';
        preview.className = 'widefat';
        preview.rows = 3;
        preview.readOnly = true;
        preview.style.marginTop = '4px';
        content.appendChild(previewLabel);
        content.appendChild(document.createElement('br'));
        content.appendChild(preview);

        const actions = document.createElement('p');
        const copyBtn = document.createElement('button');
        copyBtn.id = 'copy-shortcode';
        copyBtn.type = 'button';
        copyBtn.className = 'button button-primary';
        copyBtn.textContent = t('copy', 'Copy');

        const closeBtn = document.createElement('button');
        closeBtn.id = 'close-shortcode';
        closeBtn.type = 'button';
        closeBtn.className = 'button';
        closeBtn.textContent = t('close', 'Close');

        actions.appendChild(copyBtn);
        actions.append(' ');
        actions.appendChild(closeBtn);
        content.appendChild(actions);
        modal.appendChild(content);
        document.body.appendChild(modal);

        const close = () => modal.remove();

        setTimeout(() => {
            modal.addEventListener('click', e => {
                if (e.target === modal) close();
            });
        }, 20);
        closeTop.addEventListener('click', close);
        closeBtn.addEventListener('click', close);

        const updatePreview = () => {
            const parts = [shortcode];
            for (const [key, val] of Object.entries(values)) {
                if (val === true) {
                    parts.push(`${key}="true"`);
                } else if (val === false) {
                    // Chỉ cần ghi rõ "false" khi mặc định là bật.
                    if (defaults[key] === true) parts.push(`${key}="false"`);
                } else if (val !== '') {
                    parts.push(`${key}="${String(val).replace(/"/g, '')}"`);
                }
            }
            preview.value = `[${parts.join(' ')}]`;
        };

        content.querySelectorAll('[data-key]').forEach(el => {
            const handler = () => {
                const key = el.getAttribute('data-key');
                values[key] = el.type === 'checkbox' ? el.checked : el.value;
                updatePreview();
            };
            el.addEventListener('input', handler);
            el.addEventListener('change', handler);
        });

        copyBtn.addEventListener('click', () => {
            copyText(preview.value).then(() => {
                const original = t('copy', 'Copy');
                copyBtn.textContent = t('copied', 'Copied!');
                setTimeout(() => { copyBtn.textContent = original; }, 2000);
            }).catch(() => {
                preview.focus();
                preview.select();
            });
        });

        updatePreview();
    };

    w.renderShortcodeBuilderButton = renderShortcodeBuilderButton;

    w.renderShortcodeBuilderPanel = function ({ title, buttons }) {
        const panel = document.createElement('div');
        panel.style.margin = '16px 0';
        panel.style.border = '1px solid #ccd0d4';
        panel.style.padding = '12px 16px';
        panel.style.borderRadius = '4px';
        panel.style.background = '#f9f9f9';

        const heading = document.createElement('h2');
        heading.textContent = title;
        heading.style.marginTop = '0';
        heading.style.fontSize = '16px';
        heading.style.marginBottom = '12px';
        panel.appendChild(heading);

        const wrap = document.createElement('div');
        wrap.style.display = 'flex';
        wrap.style.flexWrap = 'wrap';
        wrap.style.gap = '10px';
        buttons.forEach(cfg => {
            wrap.appendChild(renderShortcodeBuilderButton(cfg));
        });

        panel.appendChild(wrap);
        return panel;
    };
})(window);
