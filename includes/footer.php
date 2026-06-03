<?php
/**
 * =====================================================================
 * PAAR — footer.php
 * ---------------------------------------------------------------------
 * Closes the .content / .main / .app wrappers opened in header.php +
 * a sidebar_*.php include, and renders a small site footer.
 * =====================================================================
 */
?>
    </div><!-- /.content -->
    <footer class="app-foot">
        <div class="app-foot__left">
            <span class="app-foot__brand-mark"></span>
            <span class="app-foot__brand-name"><?= e(SITE_NAME) ?></span>
            <span class="app-foot__divider" aria-hidden="true"></span>
            <span class="app-foot__tag"><?= e(SITE_TAGLINE) ?></span>
        </div>
        <div class="app-foot__right">
            <a href="<?= e(base_url('privacy.php')) ?>" style="color:var(--text-muted);font-size:12px">Privacy Policy</a>
            <span class="app-foot__divider" aria-hidden="true"></span>
            <span>&copy; <?= date('Y') ?> <?= e(SITE_NAME) ?></span>
            <span class="app-foot__divider" aria-hidden="true"></span>
            <span class="app-foot__credit">Made by <strong>Ndege</strong></span>
        </div>
    </footer>
</main><!-- /.main -->
</div><!-- /.app -->

<!-- Toast notification stack. Populated by main.js (server flashes and
     calls to window.paarToast). Aria-live so screen readers announce
     additions; role="status" keeps the announcement polite. -->
<div id="paarToasts" class="toast-stack" role="region"
     aria-label="Notifications" aria-live="polite" aria-atomic="false"></div>

<!-- Polite SR-only live region for transient announcements such as
     "3 fields need attention" from client-side validation. -->
<div id="paarLive" class="sr-only" role="status"
     aria-live="polite" aria-atomic="true"></div>

<!-- Global confirm dialog — populated by main.js for any [data-confirm] form. -->
<dialog id="paarConfirmDialog" class="paar-modal" aria-labelledby="paarConfirmTitle">
    <form method="dialog" class="paar-modal__card">
        <h2 class="paar-modal__title" id="paarConfirmTitle">Please confirm</h2>
        <p class="paar-modal__body">Are you sure?</p>
        <div class="paar-modal__actions">
            <button type="button" class="btn btn--ghost" data-modal-action="cancel">Cancel</button>
            <button type="button" class="btn btn--primary" data-modal-action="ok">Confirm</button>
        </div>
    </form>
</dialog>

</body>
</html>
