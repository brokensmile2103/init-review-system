// === Review 5 sao ===
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.init-review-system').forEach(initBlock);

    function initBlock(block) {
        const postId = parseInt(block.dataset.postId, 10);
        const stars = block.querySelectorAll('.star');
        const info = block.querySelector('.init-review-info');
        const localKey = `init_review_voted_${postId}`;

        let voted = !!localStorage.getItem(localKey);
        let avgScore = getAverageScore(info);

        highlightStars(stars, Math.round(avgScore));

        if (!canVote(voted)) {
            block.classList.add('init-review-disabled');
            disableStars(stars);
        }

        stars.forEach(star => {
            const value = parseInt(star.dataset.value, 10);

            star.addEventListener('mouseenter', () => {
                highlightStars(stars, value, 'hovering');
            });

            star.addEventListener('mouseleave', () => {
                clearStars(stars, 'hovering');
                highlightStars(stars, Math.round(avgScore), 'active');
            });

            star.addEventListener('click', () => {
                if (!canVote(voted)) return;
                submitVote(postId, value, stars, block, info, localKey, newScore => {
                    avgScore = newScore;
                    voted = true;
                });
            });
        });
    }

    function submitVote(postId, value, stars, block, info, localKey, onSuccess) {
        fetch(`${InitReviewSystemData.rest_url}/vote`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': InitReviewSystemData.nonce
            },
            body: JSON.stringify({ post_id: postId, score: value })
        })
        .then(res => res.json())
        .then(data => {
            if (!data.success) return console.warn('[InitReviewSystem] Vote failed:', data);

            localStorage.setItem(localKey, value);
            if (info) {
                info.innerHTML =
                    `<strong>${data.score.toFixed(1)}</strong><sub>/5</sub> (${data.total_votes})`;
            }

            highlightStars(stars, Math.round(data.score));
            disableStars(stars);
            block.classList.add('init-review-disabled');

            if (typeof onSuccess === 'function') {
                onSuccess(data.score);
            }
        })
        .catch(err => console.error('[InitReviewSystem] API error:', err));
    }

    function canVote(voted) {
        const d = window.InitReviewSystemData || {};
        if (voted) return false;
        if (d.require_login && !d.is_logged_in) return false;
        return true;
    }

    function getAverageScore(el) {
        if (!el) return 0;
        const match = el.textContent.match(/^([\d.]+)/);
        return match ? parseFloat(match[1]) : 0;
    }

    function highlightStars(stars, value, cls = 'active') {
        stars.forEach(star => {
            const v = parseInt(star.dataset.value, 10);
            star.classList.toggle(cls, v <= value);
        });
    }

    function clearStars(stars, cls = 'active') {
        stars.forEach(star => star.classList.remove(cls));
    }

    function disableStars(stars) {
        stars.forEach(star => star.style.pointerEvents = 'none');
    }
});

// === Review nhiều tiêu chí ===
document.addEventListener('DOMContentLoaded', function () {
    const modal      = document.getElementById('init-review-modal');
    const openBtn    = document.querySelector('.init-review-open-modal');
    const closeBtn   = modal?.querySelector('.init-review-modal-close');
    const starsGroup = modal?.querySelectorAll('.init-review-modal-stars');
    const form       = modal?.querySelector('.init-review-modal-form');
    const postId     = document.querySelector('.init-review-criteria-summary')?.dataset.postId;
    const localKey   = `init_criteria_reviewed_${postId}`;

    const i18n = window.InitReviewSystemData?.i18n || {
        validation_error: 'Please select scores and write a review.',
        success: 'Your review has been submitted!',
        error: 'Submission failed. Please try again later.'
    };

    const hasReviewed = !!localStorage.getItem(localKey);
    const data = window.InitReviewSystemData || {};
    const requireLogin = !!data.require_login;
    const isLoggedIn   = !!data.is_logged_in;
    const canReview    = !hasReviewed && (!requireLogin || isLoggedIn);

    if (openBtn && !canReview) {
        openBtn.classList.add('is-disabled');
        openBtn.setAttribute('disabled', 'disabled');
    }

    openBtn?.addEventListener('click', () => {
        if (!canReview) return;
        modal?.classList.add('is-active');
    });

    closeBtn?.addEventListener('click', () => {
        modal?.classList.remove('is-active');
    });

    modal?.addEventListener('click', e => {
        if (e.target === modal) {
            modal.classList.remove('is-active');
        }
    });

    starsGroup?.forEach(group => {
        const stars = group.querySelectorAll('.star');

        stars.forEach(star => {
            const value = parseInt(star.dataset.value, 10);

            star.addEventListener('mouseenter', () => {
                stars.forEach(s => {
                    const v = parseInt(s.dataset.value, 10);
                    s.classList.toggle('hovering', v <= value);
                });
            });

            star.addEventListener('mouseleave', () => {
                stars.forEach(s => s.classList.remove('hovering'));
            });

            star.addEventListener('click', () => {
                stars.forEach(s => {
                    const v = parseInt(s.dataset.value, 10);
                    s.classList.toggle('active', v <= value);
                });
                group.dataset.score = value;
            });
        });
    });

    form?.addEventListener('submit', async function (e) {
        e.preventDefault();

        const reviewContent = form.querySelector('#init-review-content')?.value.trim();
        const scores = {};

        starsGroup?.forEach(group => {
            const label = group.dataset.label;
            const score = parseInt(group.dataset.score || '0', 10);
            if (label && score >= 1 && score <= 5) {
                scores[label] = score;
            }
        });

        if (!reviewContent || Object.keys(scores).length === 0) {
            console.warn('[Init Review] Validation failed:', { reviewContent, scores });
            console.warn(i18n.validation_error);
            return;
        }

        const payload = {
            post_id: parseInt(postId, 10),
            review_content: reviewContent,
            scores: scores
        };

        try {
            const response = await fetch(`${InitReviewSystemData.rest_url}/submit-criteria-review`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': InitReviewSystemData?.nonce || ''
                },
                body: JSON.stringify(payload)
            });

            const data = await response.json();

            if (data.success) {
                localStorage.setItem(localKey, '1');
                modal?.classList.remove('is-active');

                openBtn?.classList.add('is-disabled');
                openBtn?.setAttribute('disabled', 'disabled');

                insertNewReview(reviewContent, scores);
                updateReviewSummaryAfterSubmit(scores);
            } else {
                console.warn('[Init Review] Submission failed:', data.message || i18n.error);
            }

        } catch (err) {
            console.error('[Init Review] Network or server error:', err);
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && modal?.classList.contains('is-active')) {
            modal.classList.remove('is-active');
        }
    });

    (function applyInitReviewTheme() {
        const config = window.InitPluginSuiteReviewSystemConfig || {};
        const html = document.documentElement;
        let theme = config.theme;

        if (!theme || theme === 'auto') {
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            const stored = localStorage.getItem("darkMode");
            const isDark = stored === "true" || (stored === null && prefersDark);
            theme = isDark ? "dark" : "light";
        }

        const isDarkTheme = theme === "dark";

        if (isDarkTheme) {
            document.querySelector('.init-review-criteria-summary')?.classList.add('dark');
            document.getElementById('init-review-modal')?.classList.add('dark');
        }
    })();
});

// Thêm nhanh bài review vừa gửi
function insertNewReview(reviewContent, scores) {
    const wrapper = document.querySelector('.init-review-feedback-list');
    if (!wrapper) return;

    const cls = window.InitReviewSystemCustomClass || {};
    const cx = (...arr) => arr.filter(Boolean).join(' '); // combine class helper

    const avg = (
        Object.values(scores).reduce((a, b) => a + b, 0) / Object.values(scores).length
    ).toFixed(1);

    let starsHtml = '';
    for (let i = 1; i <= 5; i++) {
        starsHtml += `
            <svg class="star ${i <= Math.round(avg) ? 'active' : ''}" width="20" height="20" viewBox="0 0 64 64">
                <path fill="currentColor" d="M63.9 24.28a2 2 0 0 0-1.6-1.35l-19.68-3-8.81-18.78a2 2 0 0 0-3.62 0l-8.82 18.78-19.67 3a2 2 0 0 0-1.13 3.38l14.3 14.66-3.39 20.7a2 2 0 0 0 2.94 2.07L32 54.02l17.57 9.72a2 2 0 0 0 2.12-.11 2 2 0 0 0 .82-1.96l-3.38-20.7 14.3-14.66a2 2 0 0 0 .46-2.03"></path>
            </svg>`;
    }

    let breakdownHtml = '';
    for (const [label, score] of Object.entries(scores)) {
        breakdownHtml += `
            <span class="criteria-score ${cx(...(cls.scoreItem || []))}">
                <strong>${label}</strong>: ${score} / 5
            </span>
            <br class="${cx(...(cls.scoreBreak || []))}">`;
    }

    const author = InitReviewSystemData?.current_user_name || 'Anonymous';
    const avatarUrl = InitReviewSystemData?.current_user_avatar || `${InitReviewSystemData.assets_url}/img/default-avatar.svg`;

    const today = new Date().toLocaleDateString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });

    const html = `
        <div class="init-review-item ${cx(...(cls.item || []))}">
            <div class="init-review-top ${cx(...(cls.top || []))}">
                <div class="init-review-avatar ${cx(...(cls.avatar || []))}">
                    <img src="${avatarUrl}" width="80" height="80" alt="Avatar" class="${cx(...(cls.avatarImg || []))}">
                </div>
                <div class="init-review-header ${cx(...(cls.header || []))}">
                    <div class="author-and-stars ${cx(...(cls.authorAndStars || []))}">
                        <h3 class="author ${cx(...(cls.author || []))}">${author}</h3>
                        <div class="init-review-stars ${cx(...(cls.stars || []))}">
                            ${starsHtml}
                        </div>
                    </div>
                    <div class="review-date ${cx(...(cls.date || []))}">${today}</div>
                </div>
            </div>

            <div class="init-review-body ${cx(...(cls.body || []))}">
                <div class="init-review-text ${cx(...(cls.text || []))}">${escapeHtml(reviewContent)}</div>
                <div class="init-review-criteria-breakdown ${cx(...(cls.breakdown || []))}">
                    ${breakdownHtml}
                </div>
            </div>
        </div>`;

    document.querySelector('.init-review-no-feedback')?.remove();
    wrapper.insertAdjacentHTML('afterbegin', html);
}

// Loại bỏ các kí tự HTML
function escapeHtml(str) {
    return str.replace(/[&<>"']/g, m => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    })[m]);
}

// Cập nhật điểm số nhanh sau khi gửi
function updateReviewSummaryAfterSubmit(scores) {
    const wrapper = document.querySelector('.init-review-criteria-summary');
    if (!wrapper || typeof scores !== 'object') return;

    const i18n = window.InitReviewSystemData?.i18n || {
        review_label: 'reviews',
    };

    let totalReviews = parseInt(wrapper.dataset.totalReviews || '0', 10);
    const aggregateRaw = wrapper.dataset.aggregate || '{}';
    const aggregate = JSON.parse(aggregateRaw);

    let new_avg_score = 0;
    let num_criteria = 0;

    // Cập nhật từng tiêu chí (breakdown)
    Object.entries(scores).forEach(([label, val]) => {
        val = parseFloat(val);
        const oldAvg = parseFloat(aggregate[label] || 0);
        const newAvg = ((oldAvg * totalReviews) + val) / (totalReviews + 1);
        aggregate[label] = parseFloat(newAvg.toFixed(2));

        // Update UI tiêu chí
        const row = wrapper.querySelector(`.init-review-criteria-breakdown-row[data-label="${label}"]`);
        if (row) {
            const bar = row.querySelector('.bar-fill');
            const value = row.querySelector('.value');
            if (bar) bar.style.width = `${newAvg * 20}%`;
            if (value) value.textContent = newAvg.toFixed(1);
        }

        new_avg_score += val;
        num_criteria++;
    });

    totalReviews += 1;
    wrapper.dataset.totalReviews = totalReviews.toString();
    wrapper.dataset.aggregate = JSON.stringify(aggregate);

    // Tính lại overall_avg chuẩn
    const oldOverallAvg = parseFloat(wrapper.dataset.overallAvg || '0');
    const user_avg_score = num_criteria ? new_avg_score / num_criteria : 0;
    const newOverallAvg = ((oldOverallAvg * (totalReviews - 1)) + user_avg_score) / totalReviews;
    wrapper.dataset.overallAvg = newOverallAvg.toFixed(2);

    // Cập nhật UI tổng
    const scoreBox = wrapper.querySelector('.init-review-score-box');
    if (scoreBox) {
        const avgEl = scoreBox.querySelector('.init-review-score-value');
        const stars = scoreBox.querySelectorAll('.init-review-stars-line .star');
        const count = scoreBox.querySelector('.init-review-score-count');

        if (avgEl) avgEl.textContent = newOverallAvg.toFixed(1);
        if (count) count.textContent = `${totalReviews} ${i18n.review_label}`;

        stars.forEach((star, index) => {
            const i = index + 1;
            star.classList.toggle('active', i <= Math.round(newOverallAvg));
        });
    }
}

// Phân trang
document.addEventListener('DOMContentLoaded', function () {
    const wrapper = document.querySelector('.init-review-criteria-summary');
    if (!wrapper) return;

    const postId = parseInt(wrapper.dataset.postId, 10);
    const listContainer = wrapper.querySelector('.init-review-feedback-list');
    const loadMoreBtn = wrapper.querySelector('.init-review-load-more');

    if (!loadMoreBtn || !listContainer || !postId) return;

    loadMoreBtn.addEventListener('click', async function (e) {
        e.preventDefault();

        const currentPage = parseInt(loadMoreBtn.dataset.page || '2', 10);
        const perPage = parseInt(loadMoreBtn.dataset.per || '10', 10);

        try {
            const response = await fetch(`${InitReviewSystemData.rest_url}/get-criteria-reviews?post_id=${postId}&page=${currentPage}&per_page=${perPage}`);
            const data = await response.json();

            if (!data.success || !Array.isArray(data.reviews)) {
                console.warn('[Init Review] Failed to load more reviews:', data);
                return;
            }

            // Render mỗi review vào listContainer
            data.reviews.forEach(review => {
                const item = document.createElement('div');
                item.className = 'init-review-item';

                const avatarUrl = review.user_id > 0
                    ? InitReviewSystemData.current_user_avatar
                    : InitReviewSystemData.assets_url + '/img/default-avatar.svg';

                const author = review.user_id > 0
                    ? (review.display_name || InitReviewSystemData.current_user_name || 'Anonymous')
                    : 'Anonymous';

                const stars = Math.round(parseFloat(review.avg_score || 0));
                const scores = review.criteria_scores || {};
                const date = new Date(review.created_at);

                item.innerHTML = `
                    <div class="init-review-avatar">
                        <img src="${avatarUrl}" width="48" height="48" alt="Avatar">
                    </div>
                    <div class="init-review-content">
                        <div class="init-review-header">
                            <strong class="author">${author}</strong>
                            <div class="init-review-stars">
                                ${[1,2,3,4,5].map(i => `
                                    <svg class="star ${i <= stars ? 'active' : ''}" width="20" height="20" viewBox="0 0 64 64">
                                        <path fill="currentColor" d="M63.9 24.28a2 2 0 0 0-1.6-1.35l-19.68-3-8.81-18.78a2 2 0 0 0-3.62 0l-8.82 18.78-19.67 3a2 2 0 0 0-1.13 3.38l14.3 14.66-3.39 20.7a2 2 0 0 0 2.94 2.07L32 54.02l17.57 9.72a2 2 0 0 0 2.12-.11 2 2 0 0 0 .82-1.96l-3.38-20.7 14.3-14.66a2 2 0 0 0 .46-2.03"></path>
                                    </svg>
                                `).join('')}
                            </div>
                        </div>
                        <div class="review-date">${date.toLocaleDateString()}</div>
                        <div class="init-review-text">${review.review_content || ''}</div>
                        <div class="init-review-criteria-breakdown">
                            ${Object.entries(scores).map(([label, val]) => `
                                <span class="criteria-score"><strong>${label}</strong>: ${val} / 5</span>
                            `).join('')}
                        </div>
                    </div>
                `;

                listContainer.appendChild(item);
            });

            // Tăng trang + kiểm tra trang cuối
            if (currentPage >= data.max_page) {
                loadMoreBtn.style.display = 'none';
            } else {
                loadMoreBtn.dataset.page = (currentPage + 1).toString();
            }

        } catch (err) {
            console.error('[Init Review] AJAX load error:', err);
        }
    });
});
