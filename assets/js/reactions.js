(function () {
    "use strict";

    function qsAll(sel, root) {
        return Array.prototype.slice.call((root || document).querySelectorAll(sel));
    }

    // Number formatter for total
    function irsFormatNumber(n) {
        try { return new Intl.NumberFormat().format(n); }
        catch (e) { return String(n); }
    }

    // Cấu hình an toàn: ưu tiên InitReviewSystemData, fallback InitReviewReactionsData
    var IRS_CFG = (typeof window !== "undefined")
        ? (window.InitReviewSystemData || window.InitReviewReactionsData || {})
        : {};

    document.addEventListener("DOMContentLoaded", function () {
        qsAll(".init-reaction-bar").forEach(setupBar);
    });

    function setupBar(bar) {
        var postId = parseInt(bar.getAttribute("data-post-id") || "0", 10);
        if (!postId) return;

        // Load lần đầu
        loadSummary(postId);

        // Lắng nghe click
        qsAll(".init-rx", bar).forEach(function (btn) {
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
                if (data && data.success) {
                    updateBarsUI(postId, data.counts || {}, data.user_reaction || "");
                }
            })
            .catch(function (err) {
                console.warn("[IRS][reactions] summary error:", err);
            });
    }

    var pending = false;

    function toggleReaction(postId, rx) {
        if (!IRS_CFG.rest_url) return;
        if (pending) return;
        pending = true;

        // Khóa mọi nút cùng post để tránh spam trong lúc request
        var allBtns = qsAll('.init-reaction-bar[data-post-id="' + postId + '"] .init-rx');
        allBtns.forEach(function (b) {
            b.classList.add("is-busy");
            b.disabled = true;
        });

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
                if (data && data.success) {
                    updateBarsUI(postId, data.counts || {}, data.user_reaction || "");
                }
            })
            .catch(function (err) {
                console.error("[IRS][reactions] toggle error:", err);
            })
            .finally(function () {
                pending = false;
                allBtns.forEach(function (b) {
                    b.classList.remove("is-busy");
                    b.disabled = false;
                });
            });
    }

    function updateBarsUI(postId, counts, userRx) {
        qsAll('.init-reaction-bar[data-post-id="' + postId + '"]').forEach(function (bar) {
            // update từng nút
            qsAll(".init-rx", bar).forEach(function (btn) {
                var key = btn.getAttribute("data-rx");
                var cntEl = btn.querySelector(".rx-count");

                if (cntEl && typeof counts[key] !== "undefined") {
                    cntEl.textContent = String(counts[key]);
                }

                var active = (key === userRx);
                btn.classList.toggle("is-active", active);
                btn.setAttribute("aria-pressed", active ? "true" : "false");
            });
        });

        // update tổng reactions <span id="irs-total-reactions-{postId}">
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
