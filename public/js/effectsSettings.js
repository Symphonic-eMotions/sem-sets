// =====================================================
// Effects & effect-parameters UI (Document edit scherm)
// =====================================================

// ----------- Helpers voor effect → params mapping -----------

function parseEffectsData(trackCard) {
    try {
        return JSON.parse(trackCard.dataset.effects || '[]');
    } catch (e) {
        return [];
    }
}

function buildGroupedOptions(effectsData) {
    // [{ label, presetId, options:[{id,text}] }]
    return effectsData.map(eff => ({
        label: eff.effectName,
        presetId: eff.presetId,
        options: (eff.params || []).map(p => ({
            id: String(p.id),
            text: p.key
        }))
    }));
}

function fillPartSelect(selectEl, grouped) {
    // Huidige waarde:
    // - van de server (data-current-id)
    // - of uit de DOM (net gekozen in de UI)
    const currentFromData  = selectEl.dataset.currentId || null;
    const currentFromValue = selectEl.value || null;
    const currentValue     = currentFromData || currentFromValue || null;

    selectEl.innerHTML = '';

    // Placeholder
    const ph = document.createElement('option');
    ph.value = '';
    ph.textContent = '— kies parameter —';
    selectEl.appendChild(ph);

    grouped.forEach(group => {
        if (!group.options.length) return;

        const optgroup = document.createElement('optgroup');
        optgroup.label = group.label;
        optgroup.dataset.presetId = group.presetId;

        group.options.forEach(opt => {
            const o = document.createElement('option');
            o.value = opt.id;
            o.textContent = opt.text;
            if (currentValue && currentValue === opt.id) {
                o.selected = true;
            }
            optgroup.appendChild(o);
        });

        selectEl.appendChild(optgroup);
    });

    // Als de opgeslagen ID niet meer bestaat → reset naar leeg
    if (currentValue && selectEl.value !== currentValue) {
        selectEl.value = '';
    }
}

function refreshTrackPartSelects(trackCard) {
    const effectsData = parseEffectsData(trackCard);
    const grouped = buildGroupedOptions(effectsData);

    trackCard
        .querySelectorAll('select.js-target-effect-param')
        .forEach(sel => fillPartSelect(sel, grouped));
}

/**
 * Recompute data-effects based on current preset selects in the UI.
 * Nodig voor prototype/new cards waar data-effects leeg is.
 */
function rebuildEffectsDataFromUI(trackCard) {
    const effects = [];

    trackCard.querySelectorAll('.effect-card select').forEach(presetSelect => {
        const presetId = presetSelect.value;
        if (!presetId) return;

        const presetInfo = window.EFFECT_PRESET_MAP?.[presetId];
        if (!presetInfo) return;

        effects.push(presetInfo);
    });

    trackCard.dataset.effects = JSON.stringify(effects);
}

// ------------ Guards voor veilig verwijderen ------------

function canRemoveEffectCard(trackCard, presetIdToRemove) {
    if (!presetIdToRemove) return true;

    // alle gekozen target params in parts
    const usedParamIds = new Set(
        Array.from(trackCard.querySelectorAll('select.js-target-effect-param'))
            .map(s => s.value)
            .filter(v => v)
    );

    if (!usedParamIds.size) return true;

    const presetInfo = window.EFFECT_PRESET_MAP?.[presetIdToRemove];
    if (!presetInfo) return true;

    const presetParamIds = new Set(
        (presetInfo.params || []).map(p => String(p.id))
    );

    for (const usedId of usedParamIds) {
        if (presetParamIds.has(usedId)) {
            return false;
        }
    }

    return true;
}

// ------------ Wiring van individuele effect-cards ------------

function renumberEffectPositions(container) {
    const cards = container.querySelectorAll('.effect-card');
    cards.forEach((card, i) => {
        const pos = card.querySelector('input.effect-position');
        if (pos) pos.value = String(i);
    });
}

function wireEffectCard(card, container) {
    // Idempotent: niet dubbel listeners toevoegen
    if (card.dataset.effectsWired === '1') {
        return;
    }
    card.dataset.effectsWired = '1';

    const trackCard = card.closest('.track-card');

    const resync = () => {
        if (!trackCard) return;
        rebuildEffectsDataFromUI(trackCard);
        refreshTrackPartSelects(trackCard);
        renumberEffectPositions(container);
    };

    card.querySelector('.js-effect-remove')?.addEventListener('click', () => {
        const presetSelect = card.querySelector('select');
        const presetId = presetSelect?.value;

        if (trackCard && presetId && !canRemoveEffectCard(trackCard, presetId)) {
            alert(
                'Je kunt dit effect niet verwijderen: er is nog een parameter uit dit effect gekoppeld aan een InstrumentPart.'
            );
            return;
        }

        card.remove();
        resync();
    });

    card.querySelector('.js-effect-up')?.addEventListener('click', () => {
        const prev = card.previousElementSibling;
        if (prev) {
            container.insertBefore(card, prev);
            resync();
        }
    });

    card.querySelector('.js-effect-down')?.addEventListener('click', () => {
        const next = card.nextElementSibling;
        if (next) {
            container.insertBefore(next, card);
            resync();
        }
    });
}

// ------------ Effect toevoegen (Symfony collection) ------------

window.addEffect = function addEffect(trackIdx) {
    const container = document.getElementById('effects-' + trackIdx);
    if (!container) return;

    const index = parseInt(container.dataset.index || '0', 10);
    const proto = container.dataset.prototype.replace(/__name__/g, index);
    if (!proto) return;

    const wrap = document.createElement('div');
    wrap.className = 'effect-card';
    wrap.innerHTML = `
        <div class="effect-head">
            <div class="effect-title">${proto}</div>
            <div class="effect-actions">
                <button type="button" class="btn-mini js-effect-up">↑</button>
                <button type="button" class="btn-mini js-effect-down">↓</button>
                <button type="button" class="btn-mini danger js-effect-remove">Verwijder</button>
            </div>
        </div>
    `;

    container.appendChild(wrap);
    container.dataset.index = String(index + 1);

    // Wire & renumber
    wireEffectCard(wrap, container);
    renumberEffectPositions(container);

    // Meteen resyncen na add
    const trackCard = container.closest('.track-card');
    if (trackCard) {
        rebuildEffectsDataFromUI(trackCard);
        refreshTrackPartSelects(trackCard);
        attachEffectsWatcher(trackCard);
    }
};

// ------------ Watchers voor wijzigingen in effect-keuze ------------

function attachEffectsWatcher(trackCard) {
    const effectsContainer = trackCard.querySelector('.effects');
    if (!effectsContainer) return;

    // Idempotent: niet meerdere change-listeners
    if (effectsContainer.dataset.effectsWatcherAttached === '1') {
        return;
    }
    effectsContainer.dataset.effectsWatcherAttached = '1';

    effectsContainer.addEventListener('change', (e) => {
        if (e.target.matches('select')) {
            rebuildEffectsDataFromUI(trackCard);
            refreshTrackPartSelects(trackCard);
        }
    });
}

// ------------ Init voor bestaande DOM na load / Turbo ------------

function initEffectsCollections() {
    document.querySelectorAll('.effects').forEach(container => {
        container.querySelectorAll('.effect-card').forEach(card => {
            wireEffectCard(card, container);
        });
        renumberEffectPositions(container);
    });
}

function initTrackCards() {
    document.querySelectorAll('.track-card').forEach(trackCard => {
        // 1) data-effects van Twig gebruiken (of, als leeg, zelf opbouwen)
        if (!trackCard.dataset.effects || trackCard.dataset.effects === '[]') {
            rebuildEffectsDataFromUI(trackCard);
        }

        // 2) selects vullen (en bestaande keuzes selecteren via data-current-id)
        refreshTrackPartSelects(trackCard);

        // 3) watcher activeren zodat wijzigingen in effecten doorwerken
        attachEffectsWatcher(trackCard);
    });
}

function initEffectsUI() {
    initEffectsCollections();
    initTrackCards();
}

// Turbo + fallback
document.addEventListener('DOMContentLoaded', initEffectsUI);
document.addEventListener('turbo:load', initEffectsUI);
