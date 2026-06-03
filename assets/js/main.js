/* =====================================================================
   PAAR — main.js
   ---------------------------------------------------------------------
   Small, dependency-free helpers shared across all authenticated pages:
     1. Mobile sidebar toggle (with aria-expanded sync)
     2. Client-side form validation (a11y: aria-invalid + aria-describedby)
     3. Sortable + searchable HTML tables
     4. Toast notifications (consumes server flashes; window.paarToast API)
     5. Confirm-before-submit modal (accessible <dialog>)
     6. SR-only live region announcements
   Page-specific Chart.js setup is added inline per page.
   ===================================================================== */

(function () {
    'use strict';

    /* ---- 1. Mobile sidebar toggle ---------------------------------- */
    // Lazily create a single backdrop element appended to <body>.
    function getBackdrop() {
        let bd = document.getElementById('sidebarBackdrop');
        if (!bd) {
            bd = document.createElement('div');
            bd.id = 'sidebarBackdrop';
            bd.className = 'sidebar__backdrop';
            bd.setAttribute('aria-hidden', 'true');
            document.body.appendChild(bd);
            bd.addEventListener('click', closeSidebar);
        }
        return bd;
    }
    function setMenuExpanded(open) {
        const btn = document.getElementById('menuToggle');
        if (btn) btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    }
    function openSidebar() {
        const sb = document.getElementById('sidebar');
        if (!sb) return;
        sb.classList.add('open');
        getBackdrop().classList.add('visible');
        document.body.classList.add('is-locked');
        setMenuExpanded(true);
    }
    function closeSidebar() {
        const sb = document.getElementById('sidebar');
        if (sb) sb.classList.remove('open');
        const bd = document.getElementById('sidebarBackdrop');
        if (bd) bd.classList.remove('visible');
        document.body.classList.remove('is-locked');
        setMenuExpanded(false);
    }
    function isOpen() {
        const sb = document.getElementById('sidebar');
        return !!(sb && sb.classList.contains('open'));
    }

    document.addEventListener('click', function (e) {
        if (e.target.closest('#menuToggle')) {
            isOpen() ? closeSidebar() : openSidebar();
        }
    });
    // Close drawer on Escape.
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && isOpen()) closeSidebar();
    });
    // If the viewport grows back past the breakpoint, drop the drawer state.
    window.addEventListener('resize', function () {
        if (window.innerWidth > 880 && isOpen()) closeSidebar();
    });

    /* ---- 2. Form validation --------------------------------------- */
    // Adds basic client-side checks. The server is still the source of truth.
    // Accessibility:
    //   - invalid inputs get aria-invalid="true" and aria-describedby={errId}
    //   - the matching .field-error element gets a stable id={errId}
    //   - on submit failure we focus the first invalid field, announce a
    //     summary into the polite live region, and surface a danger toast.
    let _errSeq = 0;
    document.addEventListener('submit', function (e) {
        const form = e.target;
        if (!(form instanceof HTMLFormElement)) return;
        if (form.hasAttribute('data-no-validate')) return;

        let firstInvalid = null;
        let errorCount   = 0;
        const bump = function (el) { if (el) { errorCount++; firstInvalid = firstInvalid || el; } };

        // Email format
        form.querySelectorAll('input[type="email"]').forEach(function (el) {
            const v = el.value.trim();
            if (el.required && (!v || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v))) {
                markError(el, 'Please enter a valid email.');
                bump(el);
            } else clearError(el);
        });

        // Password length (uses minlength attr)
        form.querySelectorAll('input[type="password"]').forEach(function (el) {
            const min = parseInt(el.getAttribute('minlength') || '0', 10);
            if (el.required && el.value.length < min) {
                markError(el, 'Must be at least ' + min + ' characters.');
                bump(el);
            } else clearError(el);
        });

        // Password match (if both fields exist)
        const pw  = form.querySelector('#password');
        const pw2 = form.querySelector('#password_confirm');
        if (pw && pw2 && pw.value !== pw2.value) {
            markError(pw2, 'Passwords do not match.');
            bump(pw2);
        } else if (pw2) clearError(pw2);

        // Required fields
        form.querySelectorAll('[required]').forEach(function (el) {
            if (!el.value || (el.value + '').trim() === '') {
                markError(el, 'This field is required.');
                bump(el);
            }
        });

        // Date sanity (start_date <= end_date)
        const sd = form.querySelector('input[name="start_date"]');
        const ed = form.querySelector('input[name="end_date"]');
        if (sd && ed && sd.value && ed.value && sd.value > ed.value) {
            markError(ed, 'End date must be on or after start date.');
            bump(ed);
        }

        if (firstInvalid) {
            e.preventDefault();
            firstInvalid.focus();
            const summary = errorCount === 1
                ? 'Please correct the highlighted field.'
                : 'Please correct the ' + errorCount + ' highlighted fields.';
            announce(summary);
            paarToast('danger', summary, { duration: 5000 });
        }
    });

    function markError(el, msg) {
        el.classList.add('input--error');
        el.setAttribute('aria-invalid', 'true');

        let errEl = el.nextElementSibling;
        if (!(errEl && errEl.classList && errEl.classList.contains('field-error'))) {
            errEl = document.createElement('div');
            errEl.className = 'field-error';
            el.parentNode.insertBefore(errEl, el.nextSibling);
        }
        if (!errEl.id) errEl.id = 'paar-err-' + (++_errSeq);
        errEl.textContent = msg;

        // Append our error id to aria-describedby (preserve any existing one).
        const existing = (el.getAttribute('aria-describedby') || '').split(/\s+/).filter(Boolean);
        if (existing.indexOf(errEl.id) === -1) {
            existing.push(errEl.id);
            el.setAttribute('aria-describedby', existing.join(' '));
        }
    }
    function clearError(el) {
        el.classList.remove('input--error');
        el.removeAttribute('aria-invalid');

        const next = el.nextElementSibling;
        if (next && next.classList && next.classList.contains('field-error')) {
            const id = next.id;
            next.remove();
            if (id) {
                const remaining = (el.getAttribute('aria-describedby') || '')
                    .split(/\s+/).filter(function (x) { return x && x !== id; });
                if (remaining.length) {
                    el.setAttribute('aria-describedby', remaining.join(' '));
                } else {
                    el.removeAttribute('aria-describedby');
                }
            }
        }
    }

    /* ---- 3. Searchable / sortable tables --------------------------- */
    document.addEventListener('input', function (e) {
        if (!e.target.matches('.js-table-search')) return;
        const sel   = e.target.getAttribute('data-target');
        const table = sel ? document.querySelector(sel) : null;
        if (!table) return;
        const term = e.target.value.toLowerCase().trim();
        table.querySelectorAll('tbody tr').forEach(function (tr) {
            tr.style.display = tr.textContent.toLowerCase().includes(term) ? '' : 'none';
        });
    });

    document.addEventListener('click', function (e) {
        const th = e.target.closest('th[data-sort]');
        if (!th) return;
        const table = th.closest('table');
        if (!table) return;

        const idx  = Array.prototype.indexOf.call(th.parentNode.children, th);
        const tbody = table.querySelector('tbody');
        const rows  = Array.from(tbody.querySelectorAll('tr'));
        const dir   = th.classList.contains('sort-asc') ? 'desc' : 'asc';

        // Reset other headers
        table.querySelectorAll('th[data-sort]').forEach(function (h) {
            h.classList.remove('sort-asc', 'sort-desc');
        });
        th.classList.add(dir === 'asc' ? 'sort-asc' : 'sort-desc');

        const type = th.getAttribute('data-sort'); // 'text' | 'number' | 'date'
        rows.sort(function (a, b) {
            const av = (a.children[idx]?.textContent || '').trim();
            const bv = (b.children[idx]?.textContent || '').trim();
            let cmp;
            if (type === 'number') {
                cmp = parseFloat(av.replace(/[^\d.\-]/g, '')) -
                      parseFloat(bv.replace(/[^\d.\-]/g, ''));
                if (isNaN(cmp)) cmp = 0;
            } else if (type === 'date') {
                cmp = new Date(av) - new Date(bv);
                if (isNaN(cmp)) cmp = av.localeCompare(bv);
            } else {
                cmp = av.localeCompare(bv, undefined, { sensitivity: 'base' });
            }
            return dir === 'asc' ? cmp : -cmp;
        });
        rows.forEach(function (r) { tbody.appendChild(r); });
    });

    /* ---- 4. Toasts + live region ---------------------------------- */
    // window.paarToast(type, msg, opts?)
    //   type     - 'success' | 'info' | 'warning' | 'danger'
    //   msg      - plain text (HTML is escaped via textContent)
    //   opts.duration  - ms before auto-dismiss (default 6000, 0 = sticky)
    //   opts.dismissible - show close button (default true)
    //
    // announce(msg) writes into the polite SR-only live region for screen
    // readers without showing a toast (used for transient confirmations).

    const TOAST_GLYPH = {
        success: '\u2713',   // ✓
        info:    'i',
        warning: '!',
        danger:  '!'
    };

    function getToastStack() {
        let stack = document.getElementById('paarToasts');
        if (!stack) {
            // Defensive: create one if footer.php wasn't included on this page.
            stack = document.createElement('div');
            stack.id = 'paarToasts';
            stack.className = 'toast-stack';
            stack.setAttribute('role', 'region');
            stack.setAttribute('aria-label', 'Notifications');
            stack.setAttribute('aria-live', 'polite');
            document.body.appendChild(stack);
        }
        return stack;
    }

    function paarToast(type, msg, opts) {
        if (!msg) return null;
        const validTypes = ['success', 'info', 'warning', 'danger'];
        if (validTypes.indexOf(type) === -1) type = 'info';
        opts = opts || {};
        const duration    = typeof opts.duration === 'number' ? opts.duration : 6000;
        const dismissible = opts.dismissible !== false;

        const toast = document.createElement('div');
        toast.className = 'toast toast--' + type;
        // Danger gets role=alert (interruptive); others get role=status (polite).
        toast.setAttribute('role', type === 'danger' ? 'alert' : 'status');

        const icon = document.createElement('span');
        icon.className = 'toast__icon';
        icon.setAttribute('aria-hidden', 'true');
        icon.textContent = TOAST_GLYPH[type] || 'i';

        const body = document.createElement('div');
        body.className = 'toast__msg';
        body.textContent = msg;

        toast.appendChild(icon);
        toast.appendChild(body);

        if (dismissible) {
            const close = document.createElement('button');
            close.type = 'button';
            close.className = 'toast__close';
            close.setAttribute('aria-label', 'Dismiss notification');
            close.innerHTML = '&times;';
            close.addEventListener('click', function () { dismissToast(toast); });
            toast.appendChild(close);
        } else {
            // Keep grid columns balanced.
            const spacer = document.createElement('span');
            toast.appendChild(spacer);
        }

        getToastStack().appendChild(toast);

        if (duration > 0) {
            setTimeout(function () { dismissToast(toast); }, duration);
        }
        return toast;
    }

    function dismissToast(toast) {
        if (!toast || !toast.parentNode || toast.classList.contains('is-leaving')) return;
        toast.classList.add('is-leaving');
        setTimeout(function () { toast.remove(); }, 240);
    }

    function announce(msg) {
        const live = document.getElementById('paarLive');
        if (!live) return;
        // Clearing + re-setting forces some screen readers to re-announce.
        live.textContent = '';
        setTimeout(function () { live.textContent = msg; }, 30);
    }

    // Expose globally for page-specific scripts.
    window.paarToast = paarToast;
    window.paarAnnounce = announce;

    // Boot: convert any server-emitted flash banners into toasts.
    function bootstrapServerFlashes() {
        document.querySelectorAll('.alert[data-flash]').forEach(function (el) {
            const cls = (el.className || '').match(/alert--(\w+)/);
            const type = cls ? cls[1] : 'info';
            paarToast(type, el.textContent.trim());
            el.remove();
        });
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bootstrapServerFlashes);
    } else {
        bootstrapServerFlashes();
    }

    /* ---- 5. Confirm-before-submit --------------------------------- */
    // Backward-compatible accessible replacement for window.confirm().
    // Forms opt in by setting:
    //   data-confirm="Are you sure?"              required — body text
    //   data-confirm-title="Suspend patient?"     optional — heading
    //   data-confirm-action="Suspend"             optional — primary button label
    //   data-confirm-variant="danger"             optional — red primary button
    // Falls back to native confirm() if <dialog> isn't supported.

    const dialog = document.getElementById('paarConfirmDialog');
    const dlgTitle = dialog && dialog.querySelector('.paar-modal__title');
    const dlgBody  = dialog && dialog.querySelector('.paar-modal__body');
    const dlgOk    = dialog && dialog.querySelector('[data-modal-action="ok"]');
    const dlgCancel= dialog && dialog.querySelector('[data-modal-action="cancel"]');
    const dialogSupported = !!(dialog && typeof dialog.showModal === 'function');

    let pendingForm = null;     // form we're guarding
    let bypass      = false;    // set true once user has confirmed

    function openConfirmFor(form) {
        const msg     = form.getAttribute('data-confirm') || 'Are you sure?';
        const title   = form.getAttribute('data-confirm-title')   || 'Please confirm';
        const okLabel = form.getAttribute('data-confirm-action')  || 'Confirm';
        const variant = form.getAttribute('data-confirm-variant') || '';

        dlgTitle.textContent = title;
        dlgBody.textContent  = msg;
        dlgOk.textContent    = okLabel;
        dlgOk.classList.remove('btn--primary', 'btn--danger');
        dlgOk.classList.add(variant === 'danger' ? 'btn--danger' : 'btn--primary');

        pendingForm = form;
        dialog.showModal();
        // Default focus on Cancel — safer for destructive actions.
        setTimeout(() => dlgCancel && dlgCancel.focus(), 0);
    }

    if (dialogSupported) {
        dlgOk && dlgOk.addEventListener('click', function () {
            const form = pendingForm;
            dialog.close('ok');
            if (!form) return;
            bypass = true;
            // Re-submit the form, respecting the original submitter when possible.
            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit();
            } else {
                form.submit();
            }
        });
        dlgCancel && dlgCancel.addEventListener('click', function () {
            dialog.close('cancel');
            pendingForm = null;
        });
        // ESC / backdrop close => treat as cancel.
        dialog.addEventListener('close', function () {
            if (dialog.returnValue !== 'ok') pendingForm = null;
        });
        // Click outside the modal card closes it.
        dialog.addEventListener('click', function (ev) {
            if (ev.target === dialog) dialog.close('cancel');
        });
    }

    /* ---- 7. Idle timeout warning ----------------------------------- */
    // Only on authenticated pages (they have .sidebar). Warns at 28 min,
    // matching the server-side SESSION_IDLE_SECONDS = 30 min.
    (function () {
        if (!document.querySelector('.sidebar')) return;   // auth pages only

        var WARN_MS  = 28 * 60 * 1000;   // warn at 28 min
        var idleTimer;

        function resetIdle() {
            clearTimeout(idleTimer);
            idleTimer = setTimeout(showWarning, WARN_MS);
        }

        function showWarning() {
            if (document.getElementById('idle-warning')) return;
            var div = document.createElement('div');
            div.id = 'idle-warning';
            div.setAttribute('role', 'alert');
            div.innerHTML = '<div style="position:fixed;bottom:20px;left:50%;transform:translateX(-50%);'
                + 'background:#0b3d2e;color:#fff;padding:14px 22px;border-radius:12px;z-index:9999;'
                + 'font-size:14px;font-family:inherit;box-shadow:0 8px 24px rgba(0,0,0,0.25);display:flex;gap:14px;align-items:center">'
                + '<span>Your session will expire in 2 minutes due to inactivity.</span>'
                + '<button onclick="this.closest(\'#idle-warning\').remove();resetIdleWarning()" '
                + 'style="background:#00c896;color:#0b3d2e;border:none;padding:6px 14px;border-radius:8px;'
                + 'font-weight:700;cursor:pointer;font-family:inherit">Stay signed in</button></div>';
            document.body.appendChild(div);
        }

        window.resetIdleWarning = resetIdle;

        ['mousemove','keydown','touchstart','click','scroll'].forEach(function (ev) {
            document.addEventListener(ev, function () {
                var w = document.getElementById('idle-warning');
                if (w) w.remove();
                resetIdle();
            }, { passive: true });
        });

        resetIdle();
    }());

    /* ---- 8. Password reveal toggle --------------------------------- */
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('[data-reveal-target]');
        if (!btn) return;
        const inputId = btn.getAttribute('data-reveal-target');
        const input   = document.getElementById(inputId);
        if (!input) return;
        const showing = input.type === 'text';
        input.type = showing ? 'password' : 'text';
        btn.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
        btn.style.opacity = showing ? '0.55' : '1';
    });

    /* ---- 8. Password strength indicator ---------------------------- */
    document.querySelectorAll('[data-pw-strength]').forEach(function (input) {
        const targetId  = input.getAttribute('data-pw-strength');
        const container = document.getElementById(targetId);
        if (!container) return;
        const labels = ['', 'Weak', 'Fair', 'Good', 'Strong'];
        input.addEventListener('input', function () {
            const pw = input.value;
            let score = 0;
            if (pw.length >= 8)                          score++;
            if (/[A-Z]/.test(pw) && /[a-z]/.test(pw))  score++;
            if (/[0-9]/.test(pw))                        score++;
            if (/[^A-Za-z0-9]/.test(pw))                score++;
            container.className = 'pw-strength' + (pw ? ' pw-strength--' + score : '');
            const lbl = container.querySelector('.pw-strength__label');
            if (lbl) lbl.textContent = pw ? labels[score] : '';
        });
    });

    /* ---- 9. Confirm-before-submit ---------------------------------- */
    document.addEventListener('submit', function (e) {
        const form = e.target;
        if (!(form instanceof HTMLFormElement)) return;
        // If client-side validation (section 2) already blocked the submit,
        // don't pop the confirm dialog over an invalid form.
        if (e.defaultPrevented) return;
        const msg = form.getAttribute('data-confirm');
        if (!msg) return;
        if (bypass) {                       // user already confirmed
            bypass = false;
            return;
        }
        e.preventDefault();
        if (dialogSupported) {
            openConfirmFor(form);
        } else if (window.confirm(msg)) {   // graceful fallback
            bypass = true;
            form.submit();
        }
    });
})();
