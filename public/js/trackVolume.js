(function () {
    'use strict';

    function syncTrackVolumeDisplays(root = document) {
        root.querySelectorAll('.track-volume-slider').forEach((input) => {
            const id = input.id;
            if (!id) {
                return;
            }

            const valueDisplay = root.querySelector('.volume-value[data-input="' + id + '"]')
                || document.querySelector('.volume-value[data-input="' + id + '"]');
            if (!valueDisplay) {
                return;
            }

            const value = input.value === '' ? '0' : input.value;
            valueDisplay.textContent = value + ' dB';
        });
    }

    document.addEventListener('input', (event) => {
        const target = event.target;
        if (!(target instanceof HTMLInputElement)) {
            return;
        }

        if (!target.classList.contains('track-volume-slider')) {
            return;
        }

        const id = target.id;
        if (!id) {
            return;
        }

        const card = target.closest('.track-card') || document;
        const valueDisplay = card.querySelector('.volume-value[data-input="' + id + '"]')
            || document.querySelector('.volume-value[data-input="' + id + '"]');
        if (!valueDisplay) {
            return;
        }

        const value = target.value === '' ? '0' : target.value;
        valueDisplay.textContent = value + ' dB';
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => syncTrackVolumeDisplays(document));
    } else {
        syncTrackVolumeDisplays(document);
    }

    document.addEventListener('turbo:load', () => syncTrackVolumeDisplays(document));
    document.addEventListener('turbo:render', () => syncTrackVolumeDisplays(document));

    // Hook for scripts that inject new cards from Symfony prototypes.
    window.syncTrackVolumeDisplays = syncTrackVolumeDisplays;
})();
