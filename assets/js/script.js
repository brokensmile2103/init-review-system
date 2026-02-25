// === Review 5 sao (v2.2: chống spam click + double click + pending .hovering bền vững) ===
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.init-review-system').forEach(initBlock);

    function initBlock(block) {
        const postId   = parseInt(block.dataset.postId, 10);
        const stars    = block.querySelectorAll('.star');
        const info     = block.querySelector('.init-review-info');
        const localKey = `init_review_voted_${postId}`;
        const needDouble = !!(window.InitReviewSystemData && InitReviewSystemData.double_click_to_rate);

        let voted       = !!localStorage.getItem(localKey);
        let avgScore    = getAverageScore(info);
        let isSubmitting = false; // chống spam submit

        // Trạng thái "chờ xác nhận" cho double click
        let pendingValue = null;
        let pendingAt    = 0;
        let pendingTimer = null;
        const PENDING_MS = 2200;

        highlightStars(stars, Math.round(avgScore));

        if (!canVote(voted)) {
            block.classList.add('init-review-disabled');
            disableStars(stars);
        }

        stars.forEach(star => {
            const value = parseInt(star.dataset.value, 10);

            star.addEventListener('mouseenter', () => {
                // Khi đang pending thì không cho hover phá .hovering
                if (pendingValue !== null) return;
                highlightStars(stars, value, 'hovering');
            });

            star.addEventListener('mouseleave', () => {
                // Khi đang pending thì giữ nguyên .hovering
                if (pendingValue !== null) return;

                clearStars(stars, 'hovering');
                const base = (pendingValue !== null) ? pendingValue : Math.round(avgScore);
                highlightStars(stars, base, 'active');
            });

            star.addEventListener('click', () => {
                // Đã vote rồi hoặc đang gửi request thì thôi
                if (!canVote(voted) || isSubmitting) return;

                // Double click mode
                if (needDouble) {
                    const now = Date.now();

                    // Lần 1 → pending (hoặc đổi chọn / hết hạn pending trước đó)
                    if (pendingValue !== value || (now - pendingAt) > PENDING_MS) {
                        pendingValue = value;
                        pendingAt = now;

                        // Reset dấu vết cũ
                        clearStars(stars, 'hovering');
                        clearStars(stars, 'active');

                        // Đánh dấu pending bằng .hovering
                        highlightStars(stars, value, 'hovering');

                        // (Re)start auto-timeout cho pending
                        if (pendingTimer) clearTimeout(pendingTimer);
                        pendingTimer = setTimeout(() => {
                            pendingValue = null;
                            pendingAt = 0;
                            clearStars(stars, 'hovering');
                            highlightStars(stars, Math.round(avgScore), 'active');
                            pendingTimer = null;
                        }, PENDING_MS);

                        return; // Chưa submit, chờ click lần 2
                    }

                    // Lần 2 → confirm & submit
                    pendingValue = null;
                    pendingAt = 0;
                    if (pendingTimer) {
                        clearTimeout(pendingTimer);
                        pendingTimer = null;
                    }
                    clearStars(stars, 'hovering');
                }

                // Single click mode HOẶC click xác nhận lần 2
                isSubmitting = true; // khóa mọi click tiếp theo

                submitVote(
                    postId,
                    value,
                    stars,
                    block,
                    info,
                    localKey,
                    {
                        onSuccess: newScore => {
                            avgScore = newScore;
                            voted = true;
                            pendingValue = null;
                            pendingAt = 0;
                            if (pendingTimer) {
                                clearTimeout(pendingTimer);
                                pendingTimer = null;
                            }
                            clearStars(stars, 'hovering');
                        },
                        onFinally: () => {
                            // Nếu thành công thì canVote() sẽ trả false + block đã disable
                            // Nếu fail thì cho user bấm lại
                            isSubmitting = false;
                        }
                    }
                );
            });
        });
    }

    function submitVote(postId, value, stars, block, info, localKey, callbacks = {}) {
        const { onSuccess, onError, onFinally } = callbacks;

        fetch(`${InitReviewSystemData.rest_url}/vote`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': InitReviewSystemData.nonce
            },
            body: JSON.stringify({ post_id: postId, score: value })
        })
        .then(async res => {
            const payload = await res.json().catch(() => ({}));

            if (!res.ok || !payload.success) {
                // === DUPLICATE IP → coi như đã vote ===
                if (payload?.code === 'duplicate_ip' || res.status === 429) {
                    // Lưu local để khóa UI
                    localStorage.setItem(localKey, value);

                    // Disable UI
                    highlightStars(stars, value);
                    disableStars(stars);
                    block.classList.add('init-review-disabled');

                    if (typeof onSuccess === 'function') {
                        // Không có score mới → giữ avg cũ
                        onSuccess(getAverageScore(info));
                    }

                    return;
                }

                // === Lỗi khác → vẫn cho vote lại ===
                console.warn('[InitReviewSystem] Vote failed:', payload);

                if (typeof onError === 'function') {
                    onError(payload);
                }
                return;
            }

            localStorage.setItem(localKey, value);
            if (info) {
                info.innerHTML = `<strong>${payload.score.toFixed(1)}</strong><sub>/5</sub> (${payload.total_votes})`;
            }

            highlightStars(stars, Math.round(payload.score));
            disableStars(stars);
            block.classList.add('init-review-disabled');

            if (typeof onSuccess === 'function') {
                onSuccess(payload.score);
            }
        })
        .catch(err => {
            console.error('[InitReviewSystem] API error:', err);
            if (typeof onError === 'function') {
                onError(err);
            }
        })
        .finally(() => {
            if (typeof onFinally === 'function') {
                onFinally();
            }
        });
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

    const precheckCfg = window.InitReviewSystemData?.precheck || { enabled: false, minLenWhitespaceCheck: 20, repeatThreshold: 8 };

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
        clearInlineMsg(form);
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

    let isSubmitting = false;

    form?.addEventListener('submit', async function (e) {
        e.preventDefault();
        if (isSubmitting) return;

        clearInlineMsg(form);
        clearCriteriaErrors(starsGroup);

        const reviewContent = form.querySelector('#init-review-content')?.value?.trim() || '';
        const scores = {};
        const missingRequired = [];

        // Thu thập điểm + tìm tiêu chí bắt buộc chưa chọn
        starsGroup?.forEach(group => {
            const label = group.dataset.label?.trim();
            const isOptional = group.dataset.optional === '1' || group.dataset.optional === 'true';
            const raw = group.dataset.score || '0';
            const score = parseInt(raw, 10);

            if (label) {
                if (score >= 1 && score <= 5) {
                    scores[label] = score;
                } else if (!isOptional) {
                    missingRequired.push(label);
                    group.classList.add('init-review-criteria-error');
                }
            }
        });

        // Validate trước, chưa bật isSubmitting
        if (!reviewContent) {
            notifyError((i18n.validation_error || 'Please select scores and write a review.'), form);
            return;
        }
        if (missingRequired.length > 0) {
            const msg = (InitReviewSystemData?.i18n?.rate_all_criteria)
                ? InitReviewSystemData.i18n.rate_all_criteria
                : `Please rate all required criteria: ${missingRequired.join(', ')}`;
            notifyError(msg, form);
            const firstErr = modal?.querySelector('.init-review-criteria-error');
            firstErr?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            return;
        }

        if (precheckCfg?.enabled) {
            const precheckError = runJsPrechecks(reviewContent, precheckCfg);
            if (precheckError) {
                notifyError(precheckError, form);
                return;
            }
        }

        // Tới đây mới chặn double submit
        isSubmitting = true;

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

            const data = await response.json().catch(() => ({}));

            if (response.ok && data?.success) {
                localStorage.setItem(localKey, '1');
                notifySuccess(i18n.success, form);

                setTimeout(() => {
                    modal?.classList.remove('is-active');
                }, 1000);

                openBtn?.classList.add('is-disabled');
                openBtn?.setAttribute('disabled', 'disabled');

                insertNewReview(reviewContent, scores);
                updateReviewSummaryAfterSubmit(scores);
            } else {
                const msg = mapBackendErrorToMessage(data) || i18n.error;
                console.warn('[Init Review] Submission failed:', data);
                notifyError(msg, form);
            }
        } catch (err) {
            console.error('[Init Review] Network or server error:', err);
            notifyError(i18n.error, form);
        } finally {
            isSubmitting = false;
        }
    });

    // Clear error khi user tương tác lại
    starsGroup?.forEach(group => {
        const stars = group.querySelectorAll('.star');

        const clearGroupErr = () => group.classList.remove('init-review-criteria-error');

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
                clearGroupErr();
                clearInlineMsg(form); // xoá báo lỗi chung nếu có
            });
        });
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

// ==== JS Prechecks (đồng bộ rule với backend qua thresholds) ====
function runJsPrechecks(text, cfg) {
    const i18n = window.InitReviewSystemData?.i18n || {};
    const minLen = Math.max(0, parseInt(cfg?.minLenWhitespaceCheck ?? 20, 10));
    const repeatThreshold = Math.max(2, parseInt(cfg?.repeatThreshold ?? 8, 10));

    // 1) Không có khoảng trắng (khi đủ dài)
    if ((text.length >= minLen) && !/\s/u.test(text)) {
        return i18n.no_whitespace 
            || 'Your review appears to contain no whitespace. Please rewrite it more naturally.';
    }

    // 2) Lặp từ quá nhiều
    const tokens = (text.toLowerCase().normalize('NFKC').match(/[\p{L}\p{N}]+/gu) || []);
    if (tokens.length) {
        const freq = Object.create(null);
        let max = 0;
        for (const t of tokens) {
            freq[t] = (freq[t] || 0) + 1;
            if (freq[t] > max) max = freq[t];
            if (max >= repeatThreshold) break;
        }
        if (max >= repeatThreshold) {
            return i18n.excessive_repetition 
                || 'Your review repeats the same word too many times.';
        }
    }

    // Không check blacklist ở JS (chỉ backend xử lý)
    return null; // pass
}

function mapBackendErrorToMessage(payload) {
    if (!payload) return null;
    const i18n = window.InitReviewSystemData?.i18n || {};
    const code = payload.code || payload?.data?.code;
    const message = payload.message;

    // Ưu tiên message từ server (đã dịch)
    if (typeof message === 'string' && message.trim().length > 0) return message;

    // Fallback theo code -> i18n -> chuỗi mặc định
    switch (code) {
        case 'invalid_data':           return i18n.invalid_data           || 'Invalid request data.';
        case 'login_required':         return i18n.login_required         || 'Login required to submit review.';
        case 'invalid_nonce':          return i18n.invalid_nonce          || 'Invalid nonce.';
        case 'no_valid_scores':        return i18n.no_valid_scores        || 'No valid scores provided.';
        case 'duplicate_review':       return i18n.duplicate_review       || 'You have already submitted a review.';
        case 'duplicate_ip':           return i18n.duplicate_ip           || 'You have already submitted a review from this IP.';
        case 'banned_word_detected':   return i18n.banned_word_detected   || 'Your review contains banned words.';
        case 'banned_phrase_detected': return i18n.banned_phrase_detected || 'Your review contains banned phrases.';
        case 'no_whitespace':          return i18n.no_whitespace          || 'Your review appears to contain no whitespace. Please rewrite it more naturally.';
        case 'excessive_repetition':   return i18n.excessive_repetition   || 'Your review repeats the same word too many times.';
        case 'db_error':               return i18n.db_error               || 'Could not insert review.';
        default: return null;
    }
}

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
                <strong>${escapeHtml(label)}</strong>: ${score} / 5
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
                        <h3 class="author ${cx(...(cls.author || []))}">${escapeHtml(author)}</h3>
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
    return String(str).replace(/[&<>"']/g, m => ({
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
        const key = String(label);
        const oldAvg = parseFloat(aggregate[key] || 0);
        const newAvg = ((oldAvg * totalReviews) + val) / (totalReviews + 1);
        aggregate[key] = parseFloat(newAvg.toFixed(2));

        // Update UI tiêu chí
        const row = wrapper.querySelector(`.init-review-criteria-breakdown-row[data-label="${CSS.escape(key)}"]`);
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
                            <strong class="author">${escapeHtml(author)}</strong>
                            <div class="init-review-stars">
                                ${[1,2,3,4,5].map(i => `
                                    <svg class="star ${i <= stars ? 'active' : ''}" width="20" height="20" viewBox="0 0 64 64">
                                        <path fill="currentColor" d="M63.9 24.28a2 2 0 0 0-1.6-1.35l-19.68-3-8.81-18.78a2 2 0 0 0-3.62 0l-8.82 18.78-19.67 3a2 2 0 0 0-1.13 3.38l14.3 14.66-3.39 20.7a2 2 0 0 0 2.94 2.07L32 54.02l17.57 9.72a2 2 0 0 0 2.12-.11 2 2 0 0 0 .82-1.96l-3.38-20.7 14.3-14.66a2 2 0 0 0 .46-2.03"></path>
                                    </svg>
                                `).join('')}
                            </div>
                        </div>
                        <div class="review-date">${date.toLocaleDateString()}</div>
                        <div class="init-review-text">${escapeHtml(review.review_content || '')}</div>
                        <div class="init-review-criteria-breakdown">
                            ${Object.entries(scores).map(([label, val]) => `
                                <span class="criteria-score"><strong>${escapeHtml(label)}</strong>: ${val} / 5</span>
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

// ==== Helpers: inline notify ngay sau nút submit ====
function showInlineMsg(formEl, msg, type = 'error') {
    if (!formEl) return;
    const submitBtn = formEl.querySelector('[type="submit"], .init-review-submit');
    let holder = formEl.querySelector('.init-review-inline-msg');

    if (!holder) {
        holder = document.createElement('div');
        holder.className = 'init-review-inline-msg';
        if (submitBtn && submitBtn.parentNode) {
            submitBtn.insertAdjacentElement('afterend', holder);
        } else {
            formEl.appendChild(holder);
        }
    }

    holder.innerHTML = `
        <div class="init-inline-msg init-inline-${type}" role="alert" aria-live="polite">
            ${escapeHtml(String(msg || ''))}
        </div>
    `;
}

function clearInlineMsg(formEl) {
    const holder = formEl?.querySelector('.init-review-inline-msg');
    if (holder) holder.innerHTML = '';
}

// helper xoá class lỗi cho toàn bộ nhóm
function clearCriteriaErrors(nodeList) {
    nodeList?.forEach(g => g.classList.remove('init-review-criteria-error'));
}

// Backward-compatible wrappers
function notifyError(msg, formEl)  { showInlineMsg(formEl || document.querySelector('.init-review-modal-form'), msg, 'error'); }
function notifySuccess(msg, formEl){ showInlineMsg(formEl || document.querySelector('.init-review-modal-form'), msg, 'success'); }
