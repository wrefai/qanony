/**
 * Qanony - Legal Intelligence System
 * Main JavaScript file
 *
 * Provides: AJAX helpers, toast notifications, confirm modals, theme toggle,
 * table pagination, and utility functions.
 */
(function () {
    'use strict';

    const Q = window.Qanony || {};

    // ── CSRF Helper ───────────────────────────────────────────────
    function getCsrf() {
        const meta = document.querySelector('meta[name="' + Q.csrfTokenName + '"]');
        return {
            name: Q.csrfTokenName,
            hash: meta ? meta.getAttribute('content') : Q.csrfHash
        };
    }

    function updateCsrf(newHash) {
        if (newHash) {
            Q.csrfHash = newHash;
            const meta = document.querySelector('meta[name="' + Q.csrfTokenName + '"]');
            if (meta) meta.setAttribute('content', newHash);
        }
    }

    // ── AJAX Fetch Wrapper ────────────────────────────────────────
    Q.ajax = async function (url, options = {}) {
        const defaults = {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
        };

        const opts = { ...defaults, ...options };
        opts.headers = { ...defaults.headers, ...(options.headers || {}) };

        // Add CSRF for POST requests
        if (opts.method === 'POST') {
            const csrf = getCsrf();
            if (opts.body instanceof FormData) {
                opts.body.append(csrf.name, csrf.hash);
            } else if (typeof opts.body === 'string') {
                // URL-encoded
                opts.body += '&' + encodeURIComponent(csrf.name) + '=' + encodeURIComponent(csrf.hash);
            } else {
                if (!opts.headers['Content-Type']) {
                    opts.headers['Content-Type'] = 'application/x-www-form-urlencoded';
                }
                const params = new URLSearchParams(opts.body || {});
                params.append(csrf.name, csrf.hash);
                opts.body = params.toString();
            }
        }

        try {
            const response = await fetch(url, opts);

            // Update CSRF token from response header if present
            const newToken = response.headers.get('X-CSRF-TOKEN');
            if (newToken) updateCsrf(newToken);

            if (response.status === 401) {
                window.location.href = Q.siteUrl + '/auth/login';
                return null;
            }

            // Parse JSON safely — server may return HTML on CSRF 403, 500 errors, etc.
            let data;
            try {
                data = await response.json();
            } catch (parseErr) {
                // Response was not JSON (e.g. CSRF 403 HTML error page).
                // Build a synthetic error so callers still get a usable object.
                data = {
                    status: 'error',
                    message: response.status === 403
                        ? 'CSRF token expired — retrying'
                        : ('Server error: HTTP ' + response.status),
                    _csrfError: response.status === 403,
                    _httpStatus: response.status,
                };
            }

            // Flag JSON 403 responses (CI4 development mode returns JSON exceptions)
            if (response.status === 403 && data && !data._csrfError) {
                data._csrfError = true;
                data._httpStatus = 403;
                if (!data.status) data.status = 'error';
                if (!data.message) data.message = 'CSRF token expired — retrying';
            }

            // Update CSRF from JSON response if present
            if (data && data.csrf_hash) updateCsrf(data.csrf_hash);

            if (!response.ok) {
                throw { status: response.status, data: data };
            }

            return data;
        } catch (err) {
            if (err.status) throw err;
            console.error('AJAX Error:', err);
            Q.toast(Q.lang.error_occurred, 'danger');
            throw err;
        }
    };

    // ── Toast Notifications ───────────────────────────────────────
    Q.toast = function (message, type = 'success', duration = 4000) {
        let container = document.getElementById('toastContainer');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toastContainer';
            container.className = 'toast-container';
            document.body.appendChild(container);
        }

        const icons = {
            success: 'bi-check-circle-fill',
            danger: 'bi-exclamation-triangle-fill',
            warning: 'bi-exclamation-circle-fill',
            info: 'bi-info-circle-fill',
        };

        const toastEl = document.createElement('div');
        toastEl.className = `toast align-items-center text-bg-${type} border-0`;
        toastEl.setAttribute('role', 'alert');
        toastEl.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">
                    <i class="bi ${icons[type] || icons.info} me-2"></i>
                    ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        `;

        container.appendChild(toastEl);
        const toast = new bootstrap.Toast(toastEl, { delay: duration });
        toast.show();

        toastEl.addEventListener('hidden.bs.toast', () => toastEl.remove());
    };

    // ── Confirm Dialog ────────────────────────────────────────────
    Q.confirm = function (message, onConfirm) {
        let modal = document.getElementById('confirmModal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'confirmModal';
            modal.className = 'modal fade confirm-modal';
            modal.setAttribute('tabindex', '-1');
            modal.innerHTML = `
                <div class="modal-dialog modal-dialog-centered modal-sm">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h6 class="modal-title">${Q.lang.confirm}</h6>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body" id="confirmMessage"></div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">${Q.lang.cancel}</button>
                            <button type="button" class="btn btn-danger btn-sm" id="confirmBtn">${Q.lang.yes_delete}</button>
                        </div>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
        }

        document.getElementById('confirmMessage').textContent = message;

        const bsModal = new bootstrap.Modal(modal);
        const confirmBtn = document.getElementById('confirmBtn');

        // Remove old listeners
        const newBtn = confirmBtn.cloneNode(true);
        confirmBtn.parentNode.replaceChild(newBtn, confirmBtn);

        newBtn.addEventListener('click', function () {
            bsModal.hide();
            if (typeof onConfirm === 'function') onConfirm();
        });

        bsModal.show();
    };

    // ── Theme Toggle ──────────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', function () {
        const isForceLight = document.documentElement.dataset.forceLight === '1';

        const themeToggle = document.getElementById('themeToggle');
        if (themeToggle) {
            themeToggle.addEventListener('click', function (e) {
                e.preventDefault();
                if (isForceLight) return; // render page — no toggling
                const html = document.documentElement;
                const current = html.getAttribute('data-bs-theme');
                const next = current === 'dark' ? 'light' : 'dark';
                html.setAttribute('data-bs-theme', next);

                // Update icon
                const icon = themeToggle.querySelector('i');
                if (icon) {
                    icon.className = next === 'dark' ? 'bi bi-sun' : 'bi bi-moon-stars';
                }

                // Persist theme to session (fire and forget)
                fetch(Q.siteUrl + '/theme/set?theme=' + next, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                }).catch(() => {});

                // Store in localStorage for instant No-FOUC load
                localStorage.setItem('qn-theme', next);
                Q.theme = next;
            });
        }

        // Apply saved theme on load (skipped on force-light pages)
        if (!isForceLight) {
            const savedTheme = localStorage.getItem('qn-theme');
            if (savedTheme && savedTheme !== document.documentElement.getAttribute('data-bs-theme')) {
                document.documentElement.setAttribute('data-bs-theme', savedTheme);
                const icon = document.querySelector('#themeToggle i');
                if (icon) {
                    icon.className = savedTheme === 'dark' ? 'bi bi-sun' : 'bi bi-moon-stars';
                }
            }
        }

        // Sync icon with whatever theme is currently active (handles server-side default)
        const activeTheme = document.documentElement.getAttribute('data-bs-theme') || 'light';
        const icon = document.querySelector('#themeToggle i');
        if (icon) {
            icon.className = activeTheme === 'dark' ? 'bi bi-sun' : 'bi bi-moon-stars';
        }

        // Auto-dismiss alerts after 5 seconds
        document.querySelectorAll('.alert-dismissible').forEach(function (alert) {
            setTimeout(function () {
                const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
                if (bsAlert) bsAlert.close();
            }, 5000);
        });
    });

    // ── Pagination Builder ────────────────────────────────────────
    Q.buildPagination = function (container, page, totalPages, onPageChange) {
        if (!container || totalPages <= 1) {
            if (container) container.innerHTML = '';
            return;
        }

        let html = '<nav><ul class="pagination pagination-sm mb-0">';

        // Previous
        html += `<li class="page-item ${page <= 1 ? 'disabled' : ''}">
            <a class="page-link" href="#" data-page="${page - 1}">&laquo;</a>
        </li>`;

        // Page numbers (show max 5 around current)
        const start = Math.max(1, page - 2);
        const end = Math.min(totalPages, page + 2);

        if (start > 1) {
            html += `<li class="page-item"><a class="page-link" href="#" data-page="1">1</a></li>`;
            if (start > 2) html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
        }

        for (let i = start; i <= end; i++) {
            html += `<li class="page-item ${i === page ? 'active' : ''}">
                <a class="page-link" href="#" data-page="${i}">${i}</a>
            </li>`;
        }

        if (end < totalPages) {
            if (end < totalPages - 1) html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
            html += `<li class="page-item"><a class="page-link" href="#" data-page="${totalPages}">${totalPages}</a></li>`;
        }

        // Next
        html += `<li class="page-item ${page >= totalPages ? 'disabled' : ''}">
            <a class="page-link" href="#" data-page="${page + 1}">&raquo;</a>
        </li>`;

        html += '</ul></nav>';
        container.innerHTML = html;

        // Bind click events
        container.querySelectorAll('.page-link[data-page]').forEach(function (link) {
            link.addEventListener('click', function (e) {
                e.preventDefault();
                const p = parseInt(this.getAttribute('data-page'));
                if (p >= 1 && p <= totalPages && p !== page) {
                    onPageChange(p);
                }
            });
        });
    };

    // ── POST Form Helper (for delete actions etc.) ────────────────
    Q.postAction = function (url, onSuccess) {
        Q.confirm(Q.lang.are_you_sure, async function () {
            try {
                const data = await Q.ajax(url, { method: 'POST' });
                if (data && data.success) {
                    Q.toast(data.message || Q.lang.done || 'OK', 'success');
                    if (typeof onSuccess === 'function') onSuccess(data);
                } else if (data && data.error) {
                    Q.toast(data.error, 'danger');
                }
            } catch (err) {
                if (err.data && err.data.error) {
                    Q.toast(err.data.error, 'danger');
                }
            }
        });
    };

    // ── Format File Size ──────────────────────────────────────────
    Q.formatFileSize = function (bytes) {
        if (!bytes || bytes === 0) return '0 B';
        const units = ['B', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(1024));
        return (bytes / Math.pow(1024, i)).toFixed(1) + ' ' + units[i];
    };

    // ── Format Date ───────────────────────────────────────────────
    Q.formatDate = function (dateStr) {
        if (!dateStr) return '-';
        const d = new Date(dateStr);
        return d.toLocaleDateString(Q.locale === 'ar' ? 'ar-KW' : 'en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
        });
    };

    Q.formatDateTime = function (dateStr) {
        if (!dateStr) return '-';
        const d = new Date(dateStr);
        return d.toLocaleString(Q.locale === 'ar' ? 'ar-KW' : 'en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    };

    // Expose globally
    window.Qanony = Q;
})();
