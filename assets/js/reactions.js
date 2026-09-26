// === Init Review System — Reactions bar ===
// v2.0.1:
// - Khởi tạo được cả khi script bị defer/delay (DOMContentLoaded đã qua).
// - Nhiều thanh reactions cùng một bài chỉ gọi /reactions/summary MỘT lần.
// - Mỗi thanh chỉ được gắn event 1 lần; có thể khởi tạo lại cho nội dung
//   chèn động bằng window.initReviewSystemReactions() hoặc CustomEvent
//   'init-review-system:reactions-loaded' trên document.
(function () {
    'use strict';

    function $all(selector, root) {
        return Array.prototype.slice.call((root || document).querySelectorAll(selector));
    }

    function formatNumber(n) {
        try {
            return new Intl.NumberFormat().format(n);
        } catch (e) {
            return String(n);
        }
    }

    function getConfig() {
        return (typeof window !== 'undefined' && (window.InitReviewSystemData || window.InitReviewReactionsData)) || {};
    }

    var hasOwn = Object.prototype.hasOwnProperty;
    var userReaction = Object.create(null); // postId => slug hiện tại của user.
    var summaryRequested = Object.create(null); // postId => true khi đã gọi summary.
    var isBusy = false;

    function barsOf(postId) {
        return $all('.init-reaction-bar[data-post-id="' + postId + '"]');
    }

    // Cập nhật số đếm + trạng thái active cho mọi thanh của một bài viết.
    function render(postId, counts, currentRx) {
        barsOf(postId).forEach(function (bar) {
            $all('.init-rx', bar).forEach(function (btn) {
                var key = btn.getAttribute('data-rx');
                var countEl = btn.querySelector('.rx-count');
                if (countEl && counts[key] !== undefined) {
                    countEl.textContent = formatNumber(counts[key]);
                }

                var active = key === currentRx;
                btn.classList.toggle('is-active', active);
                btn.classList.toggle('active', active);
                btn.setAttribute('aria-pressed', active ? 'true' : 'false');
            });
        });

        var total = 0;
        for (var k in counts) {
            if (hasOwn.call(counts, k)) {
                total += parseInt(counts[k] || 0, 10);
            }
        }

        var totalEl = document.getElementById('irs-total-reactions-' + postId);
        if (totalEl) {
            totalEl.textContent = formatNumber(total);
        }
    }

    // Lấy số đếm mới nhất (bỏ qua page cache) + reaction hiện tại của user.
    function fetchSummary(postId) {
        var cfg = getConfig();
        if (!cfg.rest_url || summaryRequested[postId]) return;
        summaryRequested[postId] = true;

        fetch(cfg.rest_url + '/reactions/summary?post_id=' + postId, { credentials: 'same-origin' })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data && data.success) {
                    var rx = hasOwn.call(data, 'user_reaction') && data.user_reaction
                        ? data.user_reaction
                        : (userReaction[postId] || '');
                    userReaction[postId] = rx;
                    render(postId, data.counts || {}, rx);
                }
            })
            .catch(function () {
                render(postId, {}, userReaction[postId] || '');
            });
    }

    function toggle(postId, reaction) {
        var cfg = getConfig();
        if (!cfg.rest_url || isBusy) return;
        isBusy = true;

        var buttons = $all('.init-reaction-bar[data-post-id="' + postId + '"] .init-rx');
        buttons.forEach(function (btn) {
            btn.classList.add('is-busy');
            btn.disabled = true;
        });

        fetch(cfg.rest_url + '/reactions/toggle', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': cfg.nonce || ''
            },
            body: JSON.stringify({ post_id: postId, reaction: reaction })
        })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data && data.success) {
                    var rx = (hasOwn.call(data, 'user_reaction') && data.user_reaction) || '';
                    userReaction[postId] = rx;
                    render(postId, data.counts || {}, rx);
                }
            })
            .catch(function (err) {
                console.error('[IRS][reactions] toggle error:', err);
            })
            .finally(function () {
                isBusy = false;
                buttons.forEach(function (btn) {
                    btn.classList.remove('is-busy');
                    if (btn.dataset.initialDisabled !== '1') {
                        btn.disabled = false;
                    }
                });
            });
    }

    function initBar(bar) {
        if (bar.dataset.irsBound === '1') return;
        bar.dataset.irsBound = '1';

        var postId = parseInt(bar.getAttribute('data-post-id') || '0', 10);
        if (!postId) return;

        userReaction[postId] = bar.getAttribute('data-user-rx') || userReaction[postId] || '';
        render(postId, {}, userReaction[postId]);
        fetchSummary(postId);

        $all('.init-rx', bar).forEach(function (btn) {
            btn.dataset.initialDisabled = btn.disabled ? '1' : '0';
            btn.addEventListener('click', function () {
                if (btn.disabled) return;
                var rx = btn.getAttribute('data-rx');
                if (rx) toggle(postId, rx);
            });
        });
    }

    function initAll(root) {
        $all('.init-reaction-bar', root && root.querySelectorAll ? root : document).forEach(initBar);
    }

    window.initReviewSystemReactions = initAll;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { initAll(); });
    } else {
        initAll();
    }

    document.addEventListener('init-review-system:reactions-loaded', function () { initAll(); });
})();
