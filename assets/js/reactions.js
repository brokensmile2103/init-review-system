(function () {
    "use strict";

    function qsAll(sel, root) {
        return Array.prototype.slice.call((root || document).querySelectorAll(sel));
    }

    function irsFormatNumber(n) {
        try { return new Intl.NumberFormat().format(n); }
        catch (e) { return String(n); }
    }

    // Global config (from PHP localize)
    var IRS_CFG = (typeof window !== "undefined")
        ? (window.InitReviewSystemData || window.InitReviewReactionsData || {})
        : {};

    // Sticky user reaction state per postId
    var RX_STATE = Object.create(null);

    document.addEventListener("DOMContentLoaded", function () {
        qsAll(".init-reaction-bar").forEach(setupBar);
    });

    function setupBar(bar) {
        var postId = parseInt(bar.getAttribute("data-post-id") || "0", 10);
        if (!postId) return;

        var serverUserRx = bar.getAttribute("data-user-rx") || "";  // có nếu logged-in
        var initialRx = serverUserRx || "";

        RX_STATE[postId] = initialRx; // sticky ngay từ đầu
        updateBarsUI(postId, {}, RX_STATE[postId]); // highlight ngay lập tức

        loadSummary(postId);

        qsAll(".init-rx", bar).forEach(function (btn) {
            // Lưu trạng thái disabled ban đầu để khôi phục đúng (guest giữ disabled)
            btn.dataset.initialDisabled = btn.disabled ? "1" : "0";
            btn.addEventListener("click", function () {
                if (btn.disabled) return;
                var rx = btn.getAttribute("data-rx");
                if (!rx) return;
                toggleReaction(postId, rx);
            });
        });
    }

    function loadSummary(postId) {
        if (!IRS_CFG.rest_url) return;

        fetch(IRS_CFG.rest_url + "/reactions/summary?post_id=" + postId, {
            credentials: "same-origin"
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (!(data && data.success)) return;

            var counts = data.counts || {};
            var eff = (Object.prototype.hasOwnProperty.call(data, "user_reaction") && data.user_reaction)
                ? data.user_reaction
                : (RX_STATE[postId] || "");

            RX_STATE[postId] = eff;
            updateBarsUI(postId, counts, eff);
        })
        .catch(function () {
            // lỗi mạng/API: giữ nguyên state hiện tại
            updateBarsUI(postId, {}, RX_STATE[postId] || "");
        });
    }

    var pending = false;

    function toggleReaction(postId, rx) {
        if (!IRS_CFG.rest_url || pending) return;
        pending = true;

        var allBtns = qsAll('.init-reaction-bar[data-post-id="' + postId + '"] .init-rx');
        allBtns.forEach(function (b) { b.classList.add("is-busy"); b.disabled = true; });

        fetch(IRS_CFG.rest_url + "/reactions/toggle", {
            method: "POST",
            credentials: "same-origin",
            headers: {
                "Content-Type": "application/json",
                "X-WP-Nonce": IRS_CFG.nonce || ""
            },
            body: JSON.stringify({ post_id: postId, reaction: rx })
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (!(data && data.success)) return;

            var counts = data.counts || {};
            var serverRx = (Object.prototype.hasOwnProperty.call(data, "user_reaction") ? (data.user_reaction || "") : "");
            RX_STATE[postId] = serverRx || "";

            updateBarsUI(postId, counts, RX_STATE[postId]);
        })
        .catch(function (err) {
            console.error("[IRS][reactions] toggle error:", err);
        })
        .finally(function () {
            pending = false;
            allBtns.forEach(function (b) {
                b.classList.remove("is-busy");
                // chỉ re-enable nếu ban đầu KHÔNG disabled (guest giữ nguyên disabled)
                if (b.dataset.initialDisabled !== "1") {
                    b.disabled = false;
                }
            });
        });
    }

    function updateBarsUI(postId, counts, userRx) {
        qsAll('.init-reaction-bar[data-post-id="' + postId + '"]').forEach(function (bar) {
            qsAll(".init-rx", bar).forEach(function (btn) {
                var key = btn.getAttribute("data-rx");
                var cntEl = btn.querySelector(".rx-count");

                if (cntEl && typeof counts[key] !== "undefined") {
                    cntEl.textContent = irsFormatNumber(counts[key]);
                }

                var active = (key === userRx);
                // Toggle cả hai class để tương thích CSS cũ/mới
                btn.classList.toggle("is-active", active);
                btn.classList.toggle("active", active);
                btn.setAttribute("aria-pressed", active ? "true" : "false");
            });
        });

        var total = 0;
        for (var k in counts) {
            if (Object.prototype.hasOwnProperty.call(counts, k)) {
                total += parseInt(counts[k] || 0, 10);
            }
        }
        var totalEl = document.getElementById('irs-total-reactions-' + postId);
        if (totalEl) {
            totalEl.textContent = irsFormatNumber(total);
        }
    }
})();
