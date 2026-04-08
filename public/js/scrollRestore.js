(function () {
    'use strict';

    const FORM_ID = 'document-form';
    const STORAGE_KEY = 'scroll_pos_' + window.location.pathname;
    const MAX_AGE_MS = 10 * 60 * 1000;
    const RESTORE_DELAYS_MS = [0, 80, 220, 450];

    function saveScrollPosition() {
        const payload = {
            y: window.scrollY || 0,
            ts: Date.now(),
        };

        try {
            sessionStorage.setItem(STORAGE_KEY, JSON.stringify(payload));
        } catch (_error) {
            // Ignore storage errors.
        }
    }

    function readScrollPosition() {
        let raw = null;
        try {
            raw = sessionStorage.getItem(STORAGE_KEY);
        } catch (_error) {
            return null;
        }

        if (!raw) {
            return null;
        }

        try {
            const payload = JSON.parse(raw);
            if (
                typeof payload !== 'object'
                || payload === null
                || typeof payload.y !== 'number'
                || typeof payload.ts !== 'number'
            ) {
                sessionStorage.removeItem(STORAGE_KEY);
                return null;
            }

            if ((Date.now() - payload.ts) > MAX_AGE_MS) {
                sessionStorage.removeItem(STORAGE_KEY);
                return null;
            }

            return payload.y;
        } catch (_error) {
            sessionStorage.removeItem(STORAGE_KEY);
            return null;
        }
    }

    function restoreScrollPosition() {
        const savedY = readScrollPosition();
        if (savedY === null) {
            return;
        }

        RESTORE_DELAYS_MS.forEach((delayMs, index) => {
            window.setTimeout(() => {
                window.scrollTo({
                    top: savedY,
                    behavior: 'auto',
                });

                if (index === RESTORE_DELAYS_MS.length - 1) {
                    sessionStorage.removeItem(STORAGE_KEY);
                }
            }, delayMs);
        });
    }

    function bindFormSubmitHandler() {
        const form = document.getElementById(FORM_ID);
        if (!form || form.dataset.scrollRestoreBound === '1') {
            return;
        }

        form.dataset.scrollRestoreBound = '1';
        form.addEventListener('submit', saveScrollPosition);
    }

    function init() {
        bindFormSubmitHandler();
        restoreScrollPosition();
    }

    // Initial full load
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Turbo navigations (after PRG redirect)
    document.addEventListener('turbo:load', init);

    // Fallback for Turbo form submissions in case native submit handlers change.
    document.addEventListener('turbo:submit-start', (event) => {
        const target = event.target;
        if (!(target instanceof HTMLFormElement)) {
            return;
        }
        if (target.id !== FORM_ID) {
            return;
        }
        saveScrollPosition();
    });
})();
