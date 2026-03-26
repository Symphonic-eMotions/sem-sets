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

async function splitTracks(url, csrf) {
    try {
        const body = new URLSearchParams({ csrf });
        const res = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body
        });

        const data = await res.json();
        if (!res.ok || !data.ok) {
            alert(data.error || 'Split mislukt');
            return;
        }

        // Simpel: reload zodat nieuwe assets in de lijst staan
        location.reload();
    } catch (e) {
        alert('Split mislukt: ' + e);
    }
}

async function staggerNotes(url, csrf) {
    const offsetTicks = parseInt(
        prompt('Hoeveel ticks verschuiven per noot? (standaard: 1)', '1'),
        10
    );
    if (isNaN(offsetTicks) || offsetTicks < 1) return;

    try {
        const body = new URLSearchParams({ csrf, offsetTicks });
        const res = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body
        });

        const data = await res.json();
        if (!res.ok || !data.ok) {
            alert(data.error || 'Stagger mislukt');
            return;
        }

        // Reload zodat het nieuwe bestand in de lijst staat
        location.reload();
    } catch (e) {
        alert('Stagger mislukt: ' + e);
    }
}
