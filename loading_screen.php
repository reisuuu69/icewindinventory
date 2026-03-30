<style>
    #icewind-loader {
        position: fixed;
        top: 0; left: 0;
        width: 100%; height: 100%;
        z-index: 9999;
        display: none;
        align-items: center;
        justify-content: center;
        flex-direction: column;
        gap: 20px;
        background: rgba(255, 255, 255, 0.75);
        backdrop-filter: blur(6px);
        -webkit-backdrop-filter: blur(6px);
        opacity: 0;
        transition: opacity 0.3s ease;
    }

    .iw-wrap {
        position: relative;
        width: 80px; height: 80px;
        display: flex; align-items: center; justify-content: center;
    }

    .iw-ring {
        position: absolute;
        top: 0; left: 0;
        width: 80px; height: 80px;
        border-radius: 50%;
        border: 3px solid transparent;
        border-top-color: #00c8f8;
        border-right-color: #3b82f6;
        animation: iw-spin 1.1s cubic-bezier(.6,.1,.4,.9) infinite;
    }

    .iw-ring2 {
        position: absolute;
        top: 8px; left: 8px;
        width: 64px; height: 64px;
        border-radius: 50%;
        border: 2px solid transparent;
        border-bottom-color: #00c8f8;
        border-left-color: #a5f3fc;
        opacity: 0.6;
        animation: iw-spin 1.6s cubic-bezier(.6,.1,.4,.9) infinite reverse;
    }

    .iw-flake {
        width: 44px; height: 44px;
        animation: iw-spin 4s linear infinite;
    }

    .iw-dots {
        display: flex; gap: 8px; align-items: center;
    }

    .iw-dot {
        width: 8px; height: 8px;
        border-radius: 50%;
        background: linear-gradient(135deg, #00c8f8, #3b82f6);
        animation: iw-bounce 1.3s ease-in-out infinite;
    }

    .iw-dot:nth-child(2) { animation-delay: 0.2s; }
    .iw-dot:nth-child(3) { animation-delay: 0.4s; }

    @keyframes iw-spin { to { transform: rotate(360deg); } }

    @keyframes iw-bounce {
        0%, 80%, 100% { transform: scale(0.65); opacity: 0.45; }
        40%            { transform: scale(1.25); opacity: 1; }
    }
</style>

<div id="icewind-loader">
    <div class="iw-wrap">
        <div class="iw-ring"></div>
        <div class="iw-ring2"></div>
        <svg class="iw-flake" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
            <defs>
                <linearGradient id="iw-fg" x1="0" y1="0" x2="64" y2="64" gradientUnits="userSpaceOnUse">
                    <stop offset="0%" stop-color="#00c8f8"/>
                    <stop offset="100%" stop-color="#3b82f6"/>
                </linearGradient>
            </defs>
            <circle cx="32" cy="32" r="4.5" fill="url(#iw-fg)"/>
            <line x1="32" y1="5" x2="32" y2="59" stroke="url(#iw-fg)" stroke-width="3.5" stroke-linecap="round"/>
            <line x1="5.3" y1="20.5" x2="58.7" y2="43.5" stroke="url(#iw-fg)" stroke-width="3.5" stroke-linecap="round"/>
            <line x1="5.3" y1="43.5" x2="58.7" y2="20.5" stroke="url(#iw-fg)" stroke-width="3.5" stroke-linecap="round"/>
            <line x1="32" y1="5" x2="25" y2="13" stroke="url(#iw-fg)" stroke-width="2.5" stroke-linecap="round"/>
            <line x1="32" y1="5" x2="39" y2="13" stroke="url(#iw-fg)" stroke-width="2.5" stroke-linecap="round"/>
            <line x1="32" y1="59" x2="25" y2="51" stroke="url(#iw-fg)" stroke-width="2.5" stroke-linecap="round"/>
            <line x1="32" y1="59" x2="39" y2="51" stroke="url(#iw-fg)" stroke-width="2.5" stroke-linecap="round"/>
            <line x1="5.3" y1="20.5" x2="14" y2="20.5" stroke="url(#iw-fg)" stroke-width="2.5" stroke-linecap="round"/>
            <line x1="5.3" y1="20.5" x2="9.5" y2="13" stroke="url(#iw-fg)" stroke-width="2.5" stroke-linecap="round"/>
            <line x1="58.7" y1="43.5" x2="50" y2="43.5" stroke="url(#iw-fg)" stroke-width="2.5" stroke-linecap="round"/>
            <line x1="58.7" y1="43.5" x2="54.5" y2="51" stroke="url(#iw-fg)" stroke-width="2.5" stroke-linecap="round"/>
            <line x1="5.3" y1="43.5" x2="14" y2="43.5" stroke="url(#iw-fg)" stroke-width="2.5" stroke-linecap="round"/>
            <line x1="5.3" y1="43.5" x2="9.5" y2="51" stroke="url(#iw-fg)" stroke-width="2.5" stroke-linecap="round"/>
            <line x1="58.7" y1="20.5" x2="50" y2="20.5" stroke="url(#iw-fg)" stroke-width="2.5" stroke-linecap="round"/>
            <line x1="58.7" y1="20.5" x2="54.5" y2="13" stroke="url(#iw-fg)" stroke-width="2.5" stroke-linecap="round"/>
        </svg>
    </div>

    <div class="iw-dots">
        <div class="iw-dot"></div>
        <div class="iw-dot"></div>
        <div class="iw-dot"></div>
    </div>
</div>

<script>
(function () {
    var loader = document.getElementById('icewind-loader');

    /* ── Show / Hide ──────────────────────────────────────── */
    function showLoader() {
        loader.style.display = 'flex';
        setTimeout(function () { loader.style.opacity = '1'; }, 10);
    }

    function hideLoader() {
        loader.style.opacity = '0';
        setTimeout(function () { loader.style.display = 'none'; }, 320);
    }

    /* ── Hide loader when browser restores page (after CSV
          download, back button, bfcache restore, etc.) ────── */
    window.addEventListener('pageshow', function (e) {
        hideLoader();
    });

    /* ── Helpers ──────────────────────────────────────────── */
    function isExportLink(el) {
        var href = el.getAttribute('href') || '';
        return href.indexOf('export=') !== -1;
    }

    function isModalToggle(el) {
        // Bootstrap modal open triggers — should NOT show loader
        return el.hasAttribute('data-bs-toggle') &&
               el.getAttribute('data-bs-toggle') === 'modal';
    }

    function isModalDismiss(el) {
        return el.hasAttribute('data-bs-dismiss');
    }

    function isAlertClose(el) {
        return el.classList.contains('btn-close') && isModalDismiss(el);
    }

    function isDeleteButton(btn) {
        // Delete buttons use confirm() — we only show loader if confirmed
        return btn.getAttribute('onclick') &&
               btn.getAttribute('onclick').indexOf('confirm(') !== -1;
    }

    /* ── 1. ALL form submits (search, add, edit, delete,
              login) — but skip if user cancelled confirm() ── */
    document.addEventListener('submit', function (e) {
        // Don't show for export GET forms
        var form = e.target;
        var action = (form.getAttribute('action') || '') + window.location.search;
        if (action.indexOf('export=') !== -1) return;

        // Find the submit button that was clicked
        var activeBtn = document.activeElement;
        if (activeBtn && isDeleteButton(activeBtn)) {
            // confirm() blocks — if we reach here the user clicked OK
            showLoader();
            return;
        }

        showLoader();
    }, true); // capture phase so it fires before confirm()

    /* ── 2. Delete buttons — show loader only AFTER confirm OK ─ */
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('button, a');
        if (!btn) return;

        /* Skip: modal open/close buttons */
        if (isModalToggle(btn) || isModalDismiss(btn)) return;

        /* Skip: export CSV links */
        if (btn.tagName === 'A' && isExportLink(btn)) return;

        /* Skip: alert close (×) buttons */
        if (btn.classList.contains('btn-close')) return;

        /* Skip: Cancel buttons inside modals */
        if (btn.getAttribute('data-bs-dismiss') === 'modal') return;

        /* Sidebar / navbar nav links → show loader (page navigation) */
        if (btn.tagName === 'A' && btn.classList.contains('nav-link')) {
            showLoader();
            return;
        }

        /* Navbar brand link → show loader */
        if (btn.tagName === 'A' && btn.classList.contains('navbar-brand')) {
            showLoader();
            return;
        }

        /* Logout link */
        if (btn.tagName === 'A' && (btn.getAttribute('href') || '').indexOf('logout') !== -1) {
            showLoader();
            return;
        }

        /* Dashboard quick-nav links (.nav-link-item) */
        if (btn.tagName === 'A' && btn.classList.contains('nav-link-item')) {
            showLoader();
            return;
        }

        /* Edit buttons (open modal — do NOT show loader) */
        if (btn.getAttribute('onclick') && btn.getAttribute('onclick').indexOf('editItem') !== -1) {
            return;
        }

        /* Delete confirm buttons — the submit event handles the
           actual loader show after confirm; nothing extra needed here */

    }, false);

    /* ── 3. Login form submit button ───────────────────────── */
    /* Covered by the generic submit listener above */

})();
</script>