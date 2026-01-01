(function () {
    function renderSaveNotice() {
        return `
            <div class="midi-save-notice">
                <strong>Wijziging opgeslagen?</strong> Klik eerst op <strong>Opslaan</strong>
                zodat de MIDI-gegevens geladen kunnen worden en berekeningen uitgevoerd worden.
                <span class="hint">Na opslaan verschijnt hier weer de analyse (maatsoort, maten, loops).</span>
            </div>
        `;
    }

    function onMidiChange(selectEl) {
        const targetId = selectEl.getAttribute('data-info-target');
        if (!targetId) return;

        const infoEl = document.getElementById(targetId);
        if (!infoEl) return;

        // Geen melding als geleegd wordt
        const hasValue = selectEl.value && selectEl.value !== '';
        infoEl.innerHTML = hasValue ? renderSaveNotice() : '';
    }

    document.addEventListener('change', function (e) {
        const el = e.target;
        if (!(el instanceof HTMLSelectElement)) return;
        if (!el.classList.contains('js-midi-select')) return;

        onMidiChange(el);
    });
})();
