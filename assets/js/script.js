// === Init Review System — front-end ===
// v2.0.1:
// - Khởi tạo được cả khi script bị defer/delay bởi plugin tối ưu (DOMContentLoaded đã qua).
// - Mỗi phần tử chỉ được gắn event đúng 1 lần, kể cả khi bắn lại
//   'init-review-system:criteria-loaded' (trước đây có thể gửi review 2 lần).
// - localStorage bị chặn (Safari private, chính sách trình duyệt) không còn làm vỡ script.
// - Các hàm global cũ được giữ nguyên tên và cách dùng.

// ==== Helpers dùng chung ====
function initReviewSystemOnReady(fn) {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', fn);
    } else {
        fn();
    }
}

const initReviewSystemStorage = {
    get(key) {
        try {
            return window.localStorage.getItem(key);
        } catch (e) {
            return null;
        }
    },
    set(key, value) {
        try {
            window.localStorage.setItem(key, value);
        } catch (e) {
            // Bỏ qua: không lưu được thì chỉ mất cờ "đã vote" phía client.
        }
    }
};

// Đánh dấu phần tử đã được gắn event; trả về false nếu đã gắn từ trước.
function initReviewSystemBindOnce(el, key) {
    if (!el) return false;
    const flag = 'irsBound' + key;
    if (el.dataset[flag] === '1') return false;
    el.dataset[flag] = '1';
    return true;
}

// === Review 5 sao (chống spam click + double click + pending .hovering bền vững) ===
function initReviewSystemVoteBlocks(root) {
    (root || document).querySelectorAll('.init-review-system').forEach(block => {
        if (initReviewSystemBindOnce(block, 'Vote')) {
            initReviewSystemVoteBlock(block);
        }
    });
}

function initReviewSystemVoteBlock(block) {
    const postId     = parseInt(block.dataset.postId, 10);
    const stars      = block.querySelectorAll('.star');
    const info       = block.querySelector('.init-review-info');
    const localKey   = `init_review_voted_${postId}`;
    const needDouble = !!(window.InitReviewSystemData && InitReviewSystemData.double_click_to_rate);

    let voted        = !!initReviewSystemStorage.get(localKey);
    let avgScore     = getAverageScore(info);
    let isSubmitting = false; // Chống spam submit.

    // Trạng thái "chờ xác nhận" cho double click.
    let pendingValue = null;
    let pendingAt    = 0;
    let pendingTimer = null;
    const PENDING_MS = 2200;

    const clearPending = () => {
        pendingValue = null;
        pendingAt = 0;
        if (pendingTimer) {
            clearTimeout(pendingTimer);
            pendingTimer = null;
        }
    };

    highlightStars(stars, Math.round(avgScore));

    if (!canVote(voted)) {
        block.classList.add('init-review-disabled');
        disableStars(stars);
    }

    stars.forEach(star => {
        const value = parseInt(star.dataset.value, 10);

        star.addEventListener('mouseenter', () => {
            // Khi đang pending thì không cho hover phá .hovering.
            if (pendingValue !== null) return;
            highlightStars(stars, value, 'hovering');
        });

        star.addEventListener('mouseleave', () => {
            // Khi đang pending thì giữ nguyên .hovering.
            if (pendingValue !== null) return;
            clearStars(stars, 'hovering');
            highlightStars(stars, Math.round(avgScore), 'active');
        });

        star.addEventListener('click', () => {
            // Đã vote rồi hoặc đang gửi request thì thôi.
            if (!canVote(voted) || isSubmitting) return;

            if (needDouble) {
                const now = Date.now();

                // Lần 1 → pending (hoặc đổi chọn / hết hạn pending trước đó).
                if (pendingValue !== value || (now - pendingAt) > PENDING_MS) {
                    pendingValue = value;
                    pendingAt = now;

                    clearStars(stars, 'hovering');
                    clearStars(stars, 'active');
                    highlightStars(stars, value, 'hovering');

                    if (pendingTimer) clearTimeout(pendingTimer);
                    pendingTimer = setTimeout(() => {
                        clearPending();
                        clearStars(stars, 'hovering');
                        highlightStars(stars, Math.round(avgScore), 'active');
                    }, PENDING_MS);

                    return; // Chưa submit, chờ click lần 2.
                }

                // Lần 2 → confirm & submit.
                clearPending();
                clearStars(stars, 'hovering');
            }

            isSubmitting = true; // Khóa mọi click tiếp theo.

            submitVote(postId, value, stars, block, info, localKey, {
                onSuccess: newScore => {
                    avgScore = newScore;
                    voted = true;
                    clearPending();
                    clearStars(stars, 'hovering');
                },
                onFinally: () => {
                    // Thành công: canVote() trả false + block đã disable. Lỗi: cho bấm lại.
                    isSubmitting = false;
                }
            });
        });
    });
}

function submitVote(postId, value, stars, block, info, localKey, callbacks = {}) {
    const { onSuccess, onError, onFinally } = callbacks;
    const data = window.InitReviewSystemData || {};

    fetch(`${data.rest_url}/vote`, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': data.nonce || ''
        },
        body: JSON.stringify({ post_id: postId, score: value })
    })
    .then(async res => {
        const payload = await res.json().catch(() => ({}));

        if (!res.ok || !payload.success) {
            // DUPLICATE IP → coi như đã vote.
            if (payload?.code === 'duplicate_ip' || res.status === 429) {
                initReviewSystemStorage.set(localKey, value);
                highlightStars(stars, value);
                disableStars(stars);
                block.classList.add('init-review-disabled');

                if (typeof onSuccess === 'function') {
                    // Không có score mới → giữ avg cũ.
                    onSuccess(getAverageScore(info));
                }
                return;
            }

            // Lỗi khác → vẫn cho vote lại.
            console.warn('[InitReviewSystem] Vote failed:', payload);
            if (typeof onError === 'function') onError(payload);
            return;
        }

        const score = parseFloat(payload.score) || 0;

        initReviewSystemStorage.set(localKey, value);
        if (info) {
            info.innerHTML = `<strong>${score.toFixed(1)}</strong><sub>/5</sub> (${escapeHtml(payload.total_votes)})`;
        }

        highlightStars(stars, Math.round(score));
        disableStars(stars);
        block.classList.add('init-review-disabled');

        if (typeof onSuccess === 'function') onSuccess(score);
    })
    .catch(err => {
        console.error('[InitReviewSystem] API error:', err);
        if (typeof onError === 'function') onError(err);
    })
    .finally(() => {
        if (typeof onFinally === 'function') onFinally();
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
    const match = el.textContent.trim().match(/^([\d.]+)/);
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
    stars.forEach(star => { star.style.pointerEvents = 'none'; });
}

// === Review nhiều tiêu chí ===
// Hàm riêng (không gắn thẳng vào DOMContentLoaded) để nơi khác (vd: theme
// lazy-load tab Review qua AJAX) có thể gọi lại sau khi khối
// `.init-review-criteria-summary` được chèn vào DOM — chỉ cần bắn CustomEvent
// 'init-review-system:criteria-loaded' trên document. Gọi lại nhiều lần là an
// toàn: phần tử nào đã được gắn event thì bỏ qua.
function initReviewSystemCriteriaModal() {
    const modal      = document.getElementById('init-review-modal');
    const openBtn    = document.querySelector('.init-review-open-modal');
    const summaryEl  = document.querySelector('.init-review-criteria-summary');
    const postId     = summaryEl?.dataset.postId;
    const localKey   = `init_criteria_reviewed_${postId}`;
    const data       = window.InitReviewSystemData || {};

    const hasReviewed  = !!initReviewSystemStorage.get(localKey);
    const requireLogin = !!data.require_login;
    const isLoggedIn   = !!data.is_logged_in;
    const canReview    = !hasReviewed && (!requireLogin || isLoggedIn);

    if (openBtn && !canReview) {
        openBtn.classList.add('is-disabled');
        openBtn.setAttribute('disabled', 'disabled');
    }

    if (initReviewSystemBindOnce(openBtn, 'Open')) {
        openBtn.addEventListener('click', () => {
            if (!canReview) return;
            const currentModal = document.getElementById('init-review-modal');
            currentModal?.classList.add('is-active');
            clearInlineMsg(currentModal?.querySelector('.init-review-modal-form'));
        });
    }

    if (initReviewSystemBindOnce(modal, 'Modal')) {
        initReviewSystemBindModal(modal);
    }

    // Phím Escape: chỉ gắn 1 listener cho cả trang.
    if (initReviewSystemBindOnce(document.documentElement, 'Escape')) {
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                document.querySelectorAll('.init-review-modal.is-active').forEach(m => m.classList.remove('is-active'));
            }
        });
    }

    applyInitReviewTheme(summaryEl, modal);
}

function applyInitReviewTheme(summaryEl, modal) {
    const config = window.InitPluginSuiteReviewSystemConfig || {};
    let theme = config.theme;

    if (!theme || theme === 'auto') {
        const prefersDark = !!(window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches);
        const stored = initReviewSystemStorage.get('darkMode');
        const isDark = stored === 'true' || (stored === null && prefersDark);
        theme = isDark ? 'dark' : 'light';
    }

    if (theme === 'dark') {
        summaryEl?.classList.add('dark');
        modal?.classList.add('dark');
    }
}

function initReviewSystemBindModal(modal) {
    const closeBtn   = modal.querySelector('.init-review-modal-close');
    const starsGroup = modal.querySelectorAll('.init-review-modal-stars');
    const form       = modal.querySelector('.init-review-modal-form');
    const i18n       = window.InitReviewSystemData?.i18n || {
        validation_error: 'Please select scores and write a review.',
        success: 'Your review has been submitted!',
        error: 'Submission failed. Please try again later.'
    };
    const precheckCfg = window.InitReviewSystemData?.precheck || { enabled: false, minLenWhitespaceCheck: 20, repeatThreshold: 8 };

    closeBtn?.addEventListener('click', () => {
        modal.classList.remove('is-active');
    });

    modal.addEventListener('click', e => {
        if (e.target === modal) {
            modal.classList.remove('is-active');
        }
    });

    // Gắn event cho sao của từng tiêu chí — 1 lần duy nhất (trước đây bị gắn 2 lần).
    starsGroup.forEach(group => {
        const stars = group.querySelectorAll('.star');

        stars.forEach(star => {
            const value = parseInt(star.dataset.value, 10);

            star.addEventListener('mouseenter', () => {
                stars.forEach(s => {
                    s.classList.toggle('hovering', parseInt(s.dataset.value, 10) <= value);
                });
            });

            star.addEventListener('mouseleave', () => {
                stars.forEach(s => s.classList.remove('hovering'));
            });

            star.addEventListener('click', () => {
                stars.forEach(s => {
                    s.classList.toggle('active', parseInt(s.dataset.value, 10) <= value);
                });
                group.dataset.score = value;
                group.classList.remove('init-review-criteria-error');
                clearInlineMsg(form); // Xoá báo lỗi chung nếu có.
            });
        });
    });

    let isSubmitting = false;

    form?.addEventListener('submit', async function (e) {
        e.preventDefault();
        if (isSubmitting) return;

        clearInlineMsg(form);
        clearCriteriaErrors(starsGroup);

        const data          = window.InitReviewSystemData || {};
        const summaryEl     = document.querySelector('.init-review-criteria-summary');
        const postId        = summaryEl?.dataset.postId;
        const localKey      = `init_criteria_reviewed_${postId}`;
        const reviewContent = form.querySelector('#init-review-content')?.value?.trim() || '';
        const scores = {};
        const missingRequired = [];

        // Thu thập điểm + tìm tiêu chí bắt buộc chưa chọn.
        starsGroup.forEach(group => {
            const label = group.dataset.label?.trim();
            const isOptional = group.dataset.optional === '1' || group.dataset.optional === 'true';
            const score = parseInt(group.dataset.score || '0', 10);

            if (label) {
                if (score >= 1 && score <= 5) {
                    scores[label] = score;
                } else if (!isOptional) {
                    missingRequired.push(label);
                    group.classList.add('init-review-criteria-error');
                }
            }
        });

        // Validate trước, chưa bật isSubmitting.
        if (!reviewContent) {
            notifyError((i18n.validation_error || 'Please select scores and write a review.'), form);
            return;
        }
        if (missingRequired.length > 0) {
            const msg = i18n.rate_all_criteria
                ? i18n.rate_all_criteria
                : `Please rate all required criteria: ${missingRequired.join(', ')}`;
            notifyError(msg, form);
            modal.querySelector('.init-review-criteria-error')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            return;
        }

        if (precheckCfg?.enabled) {
            const precheckError = runJsPrechecks(reviewContent, precheckCfg);
            if (precheckError) {
                notifyError(precheckError, form);
                return;
            }
        }

        // Tới đây mới chặn double submit.
        isSubmitting = true;
        const submitBtn = form.querySelector('[type="submit"]');
        if (submitBtn) submitBtn.disabled = true;

        try {
            const response = await fetch(`${data.rest_url}/submit-criteria-review`, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': data.nonce || ''
                },
                body: JSON.stringify({
                    post_id: parseInt(postId, 10),
                    review_content: reviewContent,
                    scores: scores
                })
            });

            const result = await response.json().catch(() => ({}));

            if (response.ok && result?.success) {
                initReviewSystemStorage.set(localKey, '1');
                notifySuccess(i18n.success, form);

                setTimeout(() => {
                    modal.classList.remove('is-active');
                }, 1000);

                const openBtn = document.querySelector('.init-review-open-modal');
                openBtn?.classList.add('is-disabled');
                openBtn?.setAttribute('disabled', 'disabled');

                if (result.html) {
                    const wrapper = document.querySelector('.init-review-feedback-list');
                    if (wrapper) {
                        document.querySelector('.init-review-no-feedback')?.remove();
                        wrapper.insertAdjacentHTML('afterbegin', result.html);
                    }
                }
                updateReviewSummaryAfterSubmit(result.summary);
            } else {
                console.warn('[Init Review] Submission failed:', result);
                notifyError(mapBackendErrorToMessage(result) || i18n.error, form);
            }
        } catch (err) {
            console.error('[Init Review] Network or server error:', err);
            notifyError(i18n.error, form);
        } finally {
            isSubmitting = false;
            if (submitBtn) submitBtn.disabled = false;
        }
    });
}

// ==== JS Prechecks (đồng bộ rule với backend qua thresholds) ====
function runJsPrechecks(text, cfg) {
    const i18n = window.InitReviewSystemData?.i18n || {};
    const minLen = Math.max(0, parseInt(cfg?.minLenWhitespaceCheck ?? 20, 10));
    const repeatThreshold = Math.max(2, parseInt(cfg?.repeatThreshold ?? 8, 10));

    // 1) Không có khoảng trắng (khi đủ dài).
    if ((text.length >= minLen) && !/\s/u.test(text)) {
        return i18n.no_whitespace
            || 'Your review appears to contain no whitespace. Please rewrite it more naturally.';
    }

    // 2) Lặp từ quá nhiều.
    const tokens = (text.toLowerCase().normalize('NFKC').match(/[\p{L}\p{N}]+/gu) || []);
    if (tokens.length) {
        const freq = Object.create(null);
        for (const t of tokens) {
            freq[t] = (freq[t] || 0) + 1;
            if (freq[t] >= repeatThreshold) {
                return i18n.excessive_repetition
                    || 'Your review repeats the same word too many times.';
            }
        }
    }

    // Không check blacklist ở JS (chỉ backend xử lý).
    return null;
}

function mapBackendErrorToMessage(payload) {
    if (!payload) return null;
    const i18n = window.InitReviewSystemData?.i18n || {};
    const code = payload.code || payload?.data?.code;
    const message = payload.message;

    // Ưu tiên message từ server (đã dịch).
    if (typeof message === 'string' && message.trim().length > 0) return message;

    // Fallback theo code -> i18n -> chuỗi mặc định.
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

// Loại bỏ các kí tự HTML.
function escapeHtml(str) {
    return String(str).replace(/[&<>"']/g, m => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    })[m]);
}

// Cập nhật điểm số sau khi gửi — dùng summary THẬT trả về từ server
// (overall_avg/breakdown/total tính trực tiếp từ DB ngay sau khi insert).
function updateReviewSummaryAfterSubmit(summary) {
    const wrapper = document.querySelector('.init-review-criteria-summary');
    if (!wrapper || !summary) return;

    const i18n = window.InitReviewSystemData?.i18n || {
        review_label: 'reviews',
    };

    const overallAvg = parseFloat(summary.overall_avg || 0);
    const breakdown = summary.breakdown || {};
    const totalReviews = parseInt(summary.total || 0, 10);

    wrapper.dataset.totalReviews = totalReviews.toString();
    wrapper.dataset.aggregate = JSON.stringify(breakdown);
    wrapper.dataset.overallAvg = overallAvg.toString();

    // Cập nhật từng tiêu chí (breakdown). So khớp data-label trực tiếp thay vì
    // dựng selector (CSS.escape không có ở một số trình duyệt cũ).
    const rows = wrapper.querySelectorAll('.init-review-criteria-breakdown-row');
    rows.forEach(row => {
        const label = row.dataset.label;
        if (!Object.prototype.hasOwnProperty.call(breakdown, label)) return;

        const avg = parseFloat(breakdown[label]);
        const bar = row.querySelector('.bar-fill');
        const value = row.querySelector('.value');
        if (bar) bar.style.width = `${avg * 20}%`;
        if (value) value.textContent = avg.toFixed(1);
    });

    // Cập nhật UI tổng.
    const scoreBox = wrapper.querySelector('.init-review-score-box');
    if (scoreBox) {
        const avgEl = scoreBox.querySelector('.init-review-score-value');
        const stars = scoreBox.querySelectorAll('.init-review-stars-line .star');
        const count = scoreBox.querySelector('.init-review-score-count');

        if (avgEl) avgEl.textContent = overallAvg.toFixed(1);
        if (count) count.textContent = `${totalReviews} ${i18n.review_label}`;

        stars.forEach((star, index) => {
            star.classList.toggle('active', index + 1 <= Math.round(overallAvg));
        });
    }
}

// Phân trang — cùng lý do như initReviewSystemCriteriaModal(): có thể gọi lại
// khi widget được lazy-load qua AJAX.
function initReviewSystemCriteriaLoadMore() {
    const wrapper = document.querySelector('.init-review-criteria-summary');
    if (!wrapper) return;

    const postId = parseInt(wrapper.dataset.postId, 10);
    const listContainer = wrapper.querySelector('.init-review-feedback-list');
    const loadMoreBtn = wrapper.querySelector('.init-review-load-more');

    if (!loadMoreBtn || !listContainer || !postId) return;
    if (!initReviewSystemBindOnce(loadMoreBtn, 'LoadMore')) return;

    let isLoading = false; // Chặn bấm liên tục làm tải trùng 1 trang.

    loadMoreBtn.addEventListener('click', async function (e) {
        e.preventDefault();
        if (isLoading) return;

        const data = window.InitReviewSystemData || {};
        const currentPage = parseInt(loadMoreBtn.dataset.page || '2', 10);
        const perPage = parseInt(loadMoreBtn.dataset.per || '10', 10);

        isLoading = true;
        loadMoreBtn.setAttribute('aria-busy', 'true');

        try {
            const response = await fetch(`${data.rest_url}/get-criteria-reviews?post_id=${postId}&page=${currentPage}&per_page=${perPage}`, {
                credentials: 'same-origin'
            });
            const result = await response.json();

            if (!result.success || !Array.isArray(result.reviews)) {
                console.warn('[Init Review] Failed to load more reviews:', result);
                return;
            }

            const html = result.reviews.map(review => review.html || '').join('');
            if (html) {
                listContainer.insertAdjacentHTML('beforeend', html);
            }

            // Tăng trang + kiểm tra trang cuối.
            if (currentPage >= result.max_page) {
                loadMoreBtn.style.display = 'none';
            } else {
                loadMoreBtn.dataset.page = (currentPage + 1).toString();
            }
        } catch (err) {
            console.error('[Init Review] AJAX load error:', err);
        } finally {
            isLoading = false;
            loadMoreBtn.removeAttribute('aria-busy');
        }
    });
}

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
        <div class="init-inline-msg init-inline-${escapeHtml(type)}" role="alert" aria-live="polite">
            ${escapeHtml(String(msg || ''))}
        </div>
    `;
}

function clearInlineMsg(formEl) {
    const holder = formEl?.querySelector('.init-review-inline-msg');
    if (holder) holder.innerHTML = '';
}

// Helper xoá class lỗi cho toàn bộ nhóm.
function clearCriteriaErrors(nodeList) {
    nodeList?.forEach(g => g.classList.remove('init-review-criteria-error'));
}

// Backward-compatible wrappers.
function notifyError(msg, formEl)  { showInlineMsg(formEl || document.querySelector('.init-review-modal-form'), msg, 'error'); }
function notifySuccess(msg, formEl){ showInlineMsg(formEl || document.querySelector('.init-review-modal-form'), msg, 'success'); }

// ==== Khởi tạo ====
function initReviewSystemAll() {
    initReviewSystemVoteBlocks();
    initReviewSystemCriteriaModal();
    initReviewSystemCriteriaLoadMore();
}

initReviewSystemOnReady(initReviewSystemAll);
document.addEventListener('init-review-system:criteria-loaded', initReviewSystemAll);
