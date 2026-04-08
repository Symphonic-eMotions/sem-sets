(function () {
    'use strict';

    const FORM_ID     = 'document-form';
    const STORAGE_KEY = 'collapsible_state_' + window.location.pathname;
    const ACTION_KEY  = 'collapsible_action_' + window.location.pathname;
    const MAX_AGE_MS  = 10 * 60 * 1000;

    /**
     * Generates a stable string key for a <details> element.
     *
     * Page-level collapsibles have a unique class like "page-collapsible-settings".
     * Track-level collapsibles live inside a ".track-card"; we prefix with the
     * card's zero-based index so each track's sections get their own key.
     */
    function keyFor(details) {
        const classes = Array.from(details.classList);

        const pageClass = classes.find(c => c.startsWith('page-collapsible-'));
        if (pageClass) {
            return pageClass;
        }

        const card = details.closest('.track-card');
        const trackClass = classes.find(
            c => c.startsWith('track-panel-') || c.startsWith('track-collapsible-')
        );

        if (card && trackClass) {
            const cards = Array.from(document.querySelectorAll('.track-card'));
            const idx   = cards.indexOf(card);
            return 'track-' + idx + '-' + trackClass;
        }

        // Fallback: position among all details elements.
        const all = Array.from(document.querySelectorAll('details'));
        return 'details-' + all.indexOf(details);
    }

    function saveState() {
        const openKeys = Array.from(document.querySelectorAll('details[open]'))
            .map(keyFor);

        try {
            sessionStorage.setItem(STORAGE_KEY, JSON.stringify({
                keys: openKeys,
                ts:   Date.now(),
            }));
        } catch (_) {}
    }

    function saveAction(payload) {
        if (!payload || typeof payload !== 'object') {
            return;
        }

        const keys = Array.isArray(payload.keys)
            ? payload.keys.filter(v => typeof v === 'string' && v.trim() !== '')
            : [];

        const action = {
            keys,
            focusLastTrack: payload.focusLastTrack === true,
            openAllInFocusedTrack: payload.openAllInFocusedTrack === true,
            ts: Date.now(),
        };

        try {
            sessionStorage.setItem(ACTION_KEY, JSON.stringify(action));
        } catch (_) {}
    }

    function readAction() {
        let raw;
        try {
            raw = sessionStorage.getItem(ACTION_KEY);
        } catch (_) {
            return null;
        }

        if (!raw) {
            return null;
        }

        let payload;
        try {
            payload = JSON.parse(raw);
        } catch (_) {
            sessionStorage.removeItem(ACTION_KEY);
            return null;
        }

        if (
            !payload
            || typeof payload !== 'object'
            || typeof payload.ts !== 'number'
            || (Date.now() - payload.ts) > MAX_AGE_MS
        ) {
            sessionStorage.removeItem(ACTION_KEY);
            return null;
        }

        return payload;
    }

    function applyAction() {
        const payload = readAction();
        if (!payload) {
            return;
        }

        if (Array.isArray(payload.keys) && payload.keys.length > 0) {
            const keys = new Set(payload.keys);
            document.querySelectorAll('details').forEach((details) => {
                if (keys.has(keyFor(details))) {
                    details.open = true;
                }
            });
        }

        if (payload.focusLastTrack) {
            const cards = Array.from(document.querySelectorAll('#tracks .track-card'));
            const lastCard = cards[cards.length - 1];
            if (lastCard) {
                lastCard.querySelectorAll('details').forEach((details) => {
                    if (payload.openAllInFocusedTrack) {
                        details.open = true;
                    }
                });
            }
        }

        sessionStorage.removeItem(ACTION_KEY);
    }

    function restoreState() {
        let raw;
        try {
            raw = sessionStorage.getItem(STORAGE_KEY);
        } catch (_) {
            return;
        }

        if (!raw) {
            return;
        }

        let payload;
        try {
            payload = JSON.parse(raw);
        } catch (_) {
            sessionStorage.removeItem(STORAGE_KEY);
            return;
        }

        if (
            !payload
            || !Array.isArray(payload.keys)
            || typeof payload.ts !== 'number'
            || (Date.now() - payload.ts) > MAX_AGE_MS
        ) {
            sessionStorage.removeItem(STORAGE_KEY);
            return;
        }

        const openSet = new Set(payload.keys);

        document.querySelectorAll('details').forEach(details => {
            if (openSet.has(keyFor(details))) {
                details.open = true;
            }
        });

        sessionStorage.removeItem(STORAGE_KEY);
    }

    function bindFormSubmitHandler() {
        const form = document.getElementById(FORM_ID);
        if (!form || form.dataset.collapsibleRestoreBound === '1') {
            return;
        }
        form.dataset.collapsibleRestoreBound = '1';
        form.addEventListener('submit', saveState);
    }

    /**
     * Opent een specifieke <details> op basis van een ?open= query-parameter in de URL,
     * en verwijdert daarna de parameter uit de adresbalk zonder een nieuwe history-entry.
     */
    function openFromUrlParam() {
        const params = new URLSearchParams(window.location.search);
        const key    = params.get('open');
        if (!key) {
            return;
        }

        document.querySelectorAll('details').forEach(details => {
            if (keyFor(details) === key) {
                details.open = true;
            }
        });

        params.delete('open');
        const newSearch = params.toString();
        const cleanUrl  = window.location.pathname + (newSearch ? '?' + newSearch : '') + window.location.hash;
        history.replaceState(null, '', cleanUrl);
    }

    function init() {
        window.SemSetCollapsibleRestore = window.SemSetCollapsibleRestore || {};
        window.SemSetCollapsibleRestore.saveAction = saveAction;

        bindFormSubmitHandler();
        restoreState();
        openFromUrlParam();
        applyAction();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    document.addEventListener('turbo:load', init);

    document.addEventListener('turbo:submit-start', (event) => {
        const target = event.target;
        if (target instanceof HTMLFormElement && target.id === FORM_ID) {
            saveState();
        }
    });
})();
