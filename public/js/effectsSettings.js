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
    const groups = effectsData.map(eff => ({
        label: eff.effectName,
        presetId: eff.presetId,
        options: (eff.params || []).map(p => ({
            id: 'effect:' + String(p.id),
            text: p.key,
            range: Array.isArray(p.range) ? p.range : null
        }))
    }));

    // Sequencer: velocity krijgt standaard een range [0,1]
    groups.push({
        label: 'Sequencer',
        presetId: null,
        options: [
            { id: 'seq:velocity', text: 'Velocity', range: [0, 1] }
        ]
    });

    return groups;
}

function syncBindingToHidden(selectEl) {
    const hiddenId = selectEl.dataset.bindInput;
    if (!hiddenId) return;

    const hidden = document.getElementById(hiddenId);
    if (!hidden) return;

    hidden.value = selectEl.value || '';
}

function fillPartSelect(selectEl, grouped) {
    const currentFromData  = selectEl.dataset.currentId || null;
    const currentFromValue = selectEl.value || null;
    const currentValue     = currentFromData || currentFromValue || null;

    selectEl.innerHTML = '';

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
            o.value = opt.id;      // "effect:123" of "seq:velocity"
            o.textContent = opt.text;
            if (currentValue && currentValue === opt.id) {
                o.selected = true;
            }
            optgroup.appendChild(o);
        });

        selectEl.appendChild(optgroup);
    });

    if (currentValue && selectEl.value !== currentValue) {
        selectEl.value = '';
    }

    // Zorg dat hidden veld direct in sync is
    syncBindingToHidden(selectEl);
}

function refreshTrackPartSelects(trackCard) {
    const effectsData = parseEffectsData(trackCard);
    const grouped = buildGroupedOptions(effectsData);

    // --- Nieuw: rangeMap per track ---
    const rangeMap = {};
    grouped.forEach(group => {
        (group.options || []).forEach(opt => {
            if (opt.range) {
                rangeMap[opt.id] = opt.range;    // bv "effect:13" → [10,20000]
            }
        });
    });
    trackCard.rangeMap = rangeMap; // opslaan op trackCard DOM node

    // Bestaande select-vuller
    trackCard
        .querySelectorAll('select.js-target-effect-param')
        .forEach(sel => fillPartSelect(sel, grouped));
}

//Override slider renderen
function parseOverridesJson(effectCard) {
    const input = effectCard.querySelector('input.js-effect-overrides-json');
    if (!input) return { input: null, data: {} };

    const raw = (input.value || '').trim();
    if (!raw) return { input, data: {} };

    try {
        const decoded = JSON.parse(raw);
        if (decoded && typeof decoded === 'object') {
            return { input, data: decoded };
        }
    } catch (e) {}

    return { input, data: {} };
}

function writeOverridesJson(input, data) {
    if (!input) return;
    input.value = JSON.stringify(data);
}

function clamp01(x) {
    const n = Number(x);
    if (!Number.isFinite(n)) return 0;
    return Math.max(0, Math.min(1, n));
}

function rawFromNorm(norm, minVal, maxVal) {
    const n = clamp01(norm);
    return minVal + (maxVal - minVal) * n;
}

function normFromRaw(raw, minVal, maxVal) {
    const r = Number(raw);
    if (!Number.isFinite(r) || maxVal === minVal) return 0;
    return clamp01((r - minVal) / (maxVal - minVal));
}

// Sliders renderen in de effect-card voor overrides
function ensureOverridesUI(effectCard) {
    let ui = effectCard.querySelector('.effect-overrides-ui');
    if (!ui) {
        ui = document.createElement('div');
        ui.className = 'effect-overrides-ui';
        ui.style.marginTop = '10px';
        ui.style.paddingTop = '10px';
        ui.style.borderTop = '1px solid rgba(255,255,255,0.08)';
        effectCard.appendChild(ui);
    }
    return ui;
}

function renderOverridesSliders(effectCard, presetInfo) {
    const ui = ensureOverridesUI(effectCard);

    // Lees huidige overridesJson
    const { input, data } = parseOverridesJson(effectCard);

    // Zorg dat overrides exact dezelfde keys heeft als preset params:
    const wantedKeys = (presetInfo.params || []).map(pi => String(pi.key));
    const hasAllKeys = wantedKeys.every(k => data[k] && typeof data[k] === 'object' && ('value' in data[k]));

    let overrides = data;

    // Als leeg / incompleet: maak defaults
    if (!hasAllKeys) {
        overrides = buildDefaultOverridesForPreset(presetInfo);
        writeOverridesJson(input, overrides);
    } else {
        // Ruim keys op die niet meer bestaan (effect gewijzigd / preset update)
        Object.keys(overrides).forEach(k => {
            if (!wantedKeys.includes(k)) delete overrides[k];
        });
        // En voeg eventuele ontbrekende toe (bij effect update)
        wantedKeys.forEach(k => {
            if (!overrides[k]) {
                const def = buildDefaultOverridesForPreset(presetInfo);
                overrides[k] = def[k];
            }
        });
        writeOverridesJson(input, overrides);
    }

    // UI leegmaken en opnieuw opbouwen
    ui.innerHTML = '';

    (presetInfo.params || []).forEach(p => {
        const key = p.key;
        const metaRange = Array.isArray(p.range) && p.range.length === 2 ? p.range : null;

        // Range is verplicht voor slider; als geen range, dan tonen we geen slider (of disabled)
        const minVal = metaRange ? Number(metaRange[0]) : 0;
        const maxVal = metaRange ? Number(metaRange[1]) : 1;

        const row = document.createElement('div');
        row.className = 'effect-override-row';
        row.style.display = 'grid';
        row.style.gridTemplateColumns = '140px 1fr 80px';
        row.style.gap = '10px';
        row.style.alignItems = 'center';
        row.style.marginTop = '8px';

        const label = document.createElement('div');
        label.textContent = key;
        label.style.fontSize = '12px';
        label.style.opacity = '0.9';

        const slider = document.createElement('input');
        slider.type = 'range';
        slider.min = '0';
        slider.max = '1';
        slider.step = '0.001';
        slider.disabled = !metaRange;

        const valueBadge = document.createElement('div');
        valueBadge.style.fontSize = '12px';
        valueBadge.style.textAlign = 'right';
        valueBadge.style.opacity = metaRange ? '0.95' : '0.5';

        // init
        const currentRaw = overrides[key]?.value;
        slider.value = String(normFromRaw(currentRaw, minVal, maxVal));
        valueBadge.textContent = metaRange ? formatRangeValue(currentRaw) : '—';

        slider.addEventListener('input', () => {
            if (!metaRange) return;

            const raw = rawFromNorm(slider.value, minVal, maxVal);
            overrides[key] = overrides[key] || {};
            overrides[key].value = raw;
            overrides[key].range = metaRange;

            valueBadge.textContent = formatRangeValue(raw);
            writeOverridesJson(input, overrides);
        });

        row.appendChild(label);
        row.appendChild(slider);
        row.appendChild(valueBadge);
        ui.appendChild(row);
    });
}

// Bouw defaults voor een preset, op basis van presetInfo.params
function buildDefaultOverridesForPreset(presetInfo) {
    const out = {};
    (presetInfo.params || []).forEach(p => {
        const key = p.key;
        const range = Array.isArray(p.range) && p.range.length === 2 ? p.range : null;

        let value = p.defaultValue;

        // Fallbacks als defaultValue ontbreekt:
        if (value === null || value === undefined) {
            if (range) {
                // midden van range als neutraal startpunt
                value = (Number(range[0]) + Number(range[1])) / 2;
            } else {
                value = 0;
            }
        }

        out[key] = {
            value: value,
            range: range, // opslaan mag, is handig voor debug/export
        };
    });
    return out;
}

function resetOverridesToPresetDefaults(effectCard, presetInfo) {
    const { input } = parseOverridesJson(effectCard);
    if (!input) return;

    const defaults = buildDefaultOverridesForPreset(presetInfo);
    writeOverridesJson(input, defaults);

    // UI direct laten matchen met defaults
    renderOverridesSliders(effectCard, presetInfo);
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

    // Alle gekozen target params in parts (alleen effect-bindings)
    const usedParamIds = new Set(
        Array.from(trackCard.querySelectorAll('select.js-target-effect-param'))
            .map(s => s.value)
            .filter(v => v && v.startsWith('effect:'))
            .map(v => v.substring('effect:'.length)) // p.id als string
    );

    if (!usedParamIds.size) return true;

    const presetInfo = window.EFFECT_PRESET_MAP?.[presetIdToRemove];
    if (!presetInfo) return true; // als we de preset niet kennen: laat verwijderen toe

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

function formatRangeValue(v) {
    const num = Number(v);
    if (!Number.isFinite(num)) return '';

    if (Math.abs(num) >= 1000) {
        return String(Math.round(num));
    }
    if (Math.abs(num) >= 10) {
        return num.toFixed(1);
    }
    return num.toFixed(3)
        .replace(/0+$/, '')
        .replace(/\.$/, '');
}

function wireRangeSliders(container) {
    if (container.dataset.rangeSlidersWired === '1') return;
    container.dataset.rangeSlidersWired = '1';

    const lowSlider  = container.querySelector('.range-low');
    const highSlider = container.querySelector('.range-high');
    const lowHidden  = container.querySelector('.range-low-hidden');
    const highHidden = container.querySelector('.range-high-hidden');
    const lowLabel   = container.querySelector('.range-label-low-value');
    const highLabel  = container.querySelector('.range-label-high-value');

    if (!lowSlider || !highSlider || !lowHidden || !highHidden) return;

    const updateFromSliders = () => {
        const minVal = parseFloat(container.dataset.rangeMin ?? '');
        const maxVal = parseFloat(container.dataset.rangeMax ?? '');

        if (!Number.isFinite(minVal) || !Number.isFinite(maxVal) || maxVal === minVal) {
            return;
        }

        const lowNorm  = Math.min(1, Math.max(0, parseFloat(lowSlider.value  || '0')));
        const highNorm = Math.min(1, Math.max(0, parseFloat(highSlider.value || '1')));

        const lowRaw  = minVal + (maxVal - minVal) * lowNorm;
        const highRaw = minVal + (maxVal - minVal) * highNorm;

        lowHidden.value  = String(lowRaw);
        highHidden.value = String(highRaw);

        if (lowLabel)  lowLabel.textContent  = formatRangeValue(lowRaw);
        if (highLabel) highLabel.textContent = formatRangeValue(highRaw);
    };

    lowSlider.addEventListener('input', updateFromSliders);
    highSlider.addEventListener('input', updateFromSliders);
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

    const initOverridesUI = () => {
        const presetSelect = card.querySelector('select');
        const presetId = presetSelect?.value;
        const presetInfo = presetId ? window.EFFECT_PRESET_MAP?.[presetId] : null;
        if (presetInfo) {
            renderOverridesSliders(card, presetInfo);
        }
    };

    // Remove
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

    // Move up
    card.querySelector('.js-effect-up')?.addEventListener('click', () => {
        const prev = card.previousElementSibling;
        if (prev) {
            container.insertBefore(card, prev);
            resync();
        }
    });

    // Move down
    card.querySelector('.js-effect-down')?.addEventListener('click', () => {
        const next = card.nextElementSibling;
        if (next) {
            container.insertBefore(next, card);
            resync();
        }
    });

    // Initial render (bij bestaande cards)
    initOverridesUI();
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
            //overrides
            const effectCard = e.target.closest('.effect-card');
            const presetId = e.target.value;
            const presetInfo = window.EFFECT_PRESET_MAP?.[presetId];
            if (effectCard && presetInfo) {
                resetOverridesToPresetDefaults(effectCard, presetInfo);
            }
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

function applyRangeForSelect(selectEl) {
    const trackCard = selectEl.closest('.track-card');
    if (!trackCard || !trackCard.rangeMap) return;

    const selected  = selectEl.value;
    const container = selectEl.closest('.part-effect-target');
    if (!container) return;

    const lowSlider  = container.querySelector('.range-low');
    const highSlider = container.querySelector('.range-high');
    const lowHidden  = container.querySelector('.range-low-hidden');
    const highHidden = container.querySelector('.range-high-hidden');
    const lowLabel   = container.querySelector('.range-label-low-value');
    const highLabel  = container.querySelector('.range-label-high-value');

    if (!lowSlider || !highSlider || !lowHidden || !highHidden) return;

    // --- Nieuw: binding-state per container ---
    const previousBinding   = container.dataset.currentBinding || null;
    const currentBinding    = selected || '';
    const firstTimeInit     = container.dataset.rangeInitialized !== '1';
    const bindingHasChanged = !firstTimeInit && previousBinding !== currentBinding;

    const range = trackCard.rangeMap[selected] || null;

    // Geen range → alles uit
    if (!range || !Array.isArray(range) || range.length !== 2) {
        lowSlider.disabled = true;
        highSlider.disabled = true;
        container.dataset.rangeMin = '';
        container.dataset.rangeMax = '';
        lowHidden.value = '';
        highHidden.value = '';
        if (lowLabel)  lowLabel.textContent = '';
        if (highLabel) highLabel.textContent = '';

        container.dataset.currentBinding    = currentBinding;
        container.dataset.rangeInitialized  = '1';
        return;
    }

    const minVal = Number(range[0]);
    const maxVal = Number(range[1]);

    if (!Number.isFinite(minVal) || !Number.isFinite(maxVal) || maxVal === minVal) {
        // Treat as no usable range
        lowSlider.disabled = true;
        highSlider.disabled = true;
        container.dataset.rangeMin = '';
        container.dataset.rangeMax = '';
        lowHidden.value = '';
        highHidden.value = '';
        if (lowLabel)  lowLabel.textContent = '';
        if (highLabel) highLabel.textContent = '';

        container.dataset.currentBinding    = currentBinding;
        container.dataset.rangeInitialized  = '1';
        return;
    }

    container.dataset.rangeMin = String(minVal);
    container.dataset.rangeMax = String(maxVal);

    lowSlider.disabled  = false;
    highSlider.disabled = false;

    // Sliders werken altijd 0..1
    lowSlider.min  = '0';
    lowSlider.max  = '1';
    highSlider.min = '0';
    highSlider.max = '1';

    // Probeer bestaande hidden waarden te gebruiken (bij edit),
    // MAAR niet als we net naar een andere parameter zijn geswitcht.
    let lowRaw  = parseFloat(lowHidden.value);
    let highRaw = parseFloat(highHidden.value);

    const hasStoredLow  = Number.isFinite(lowRaw);
    const hasStoredHigh = Number.isFinite(highRaw);

    if (bindingHasChanged) {
        // Nieuwe parameter → reset naar volledige range
        lowRaw  = minVal;
        highRaw = maxVal;
    } else {
        // Init of zelfde binding → gebruik hidden/anders fallback
        if (!hasStoredLow)  lowRaw  = minVal;
        if (!hasStoredHigh) highRaw = maxVal;
    }

    const norm = (val) => Math.min(1, Math.max(0, (val - minVal) / (maxVal - minVal)));

    lowSlider.value  = String(norm(lowRaw));
    highSlider.value = String(norm(highRaw));

    // Hidden & labels direct updaten
    lowHidden.value  = String(lowRaw);
    highHidden.value = String(highRaw);

    if (lowLabel)  lowLabel.textContent  = formatRangeValue(lowRaw);
    if (highLabel) highLabel.textContent = formatRangeValue(highRaw);

    // Markeer als geïnitialiseerd en onthoud huidige binding
    container.dataset.currentBinding   = currentBinding;
    container.dataset.rangeInitialized = '1';
}
// ook exporteren voor gebruik in andere JS-files
window.applyRangeForSelect = applyRangeForSelect;

function ensureRangeControlsForTrackCard(trackCard) {
    trackCard.querySelectorAll('.part-effect-target').forEach(container => {
        if (container.dataset.rangeControlsAttached === '1') {
            return;
        }
        container.dataset.rangeControlsAttached = '1';

        // LOW slider + labels
        const lowRow = document.createElement('div');
        lowRow.className = 'range-row range-row-low';
        lowRow.style.display = 'flex';
        lowRow.style.alignItems = 'center';
        lowRow.style.gap = '8px';
        lowRow.style.marginTop = '6px';

        const lowLabelLeft = document.createElement('span');
        lowLabelLeft.className = 'range-label range-label-low-value';
        lowLabelLeft.style.minWidth = '60px';

        const lowSlider = document.createElement('input');
        lowSlider.type = 'range';
        lowSlider.className = 'range-slider range-low';
        lowSlider.step = '0.001';
        lowSlider.min = '0';
        lowSlider.max = '1';
        lowSlider.style.flex = '1';

        const lowLabelRight = document.createElement('span');
        lowLabelRight.className = 'range-label range-label-low-spacer';
        lowLabelRight.style.minWidth = '60px';

        lowRow.appendChild(lowLabelLeft);
        lowRow.appendChild(lowSlider);
        lowRow.appendChild(lowLabelRight);

        // HIGH slider + labels
        const highRow = document.createElement('div');
        highRow.className = 'range-row range-row-high';
        highRow.style.display = 'flex';
        highRow.style.alignItems = 'center';
        highRow.style.gap = '8px';
        highRow.style.marginTop = '4px';
        highRow.style.marginBottom = '2px';

        const highLabelLeft = document.createElement('span');
        highLabelLeft.className = 'range-label range-label-high-spacer';
        highLabelLeft.style.minWidth = '60px';

        const highSlider = document.createElement('input');
        highSlider.type = 'range';
        highSlider.className = 'range-slider range-high';
        highSlider.step = '0.001';
        highSlider.min = '0';
        highSlider.max = '1';
        highSlider.style.flex = '1';

        const highLabelRight = document.createElement('span');
        highLabelRight.className = 'range-label range-label-high-value';
        highLabelRight.style.minWidth = '60px';

        highRow.appendChild(highLabelLeft);
        highRow.appendChild(highSlider);
        highRow.appendChild(highLabelRight);

        // Hidden fields: gebruik bestaande Symfony widgets als ze er zijn
        let lowHidden  = container.querySelector('.range-low-hidden');
        let highHidden = container.querySelector('.range-high-hidden');

        if (!lowHidden) {
            lowHidden = document.createElement('input');
            lowHidden.type = 'hidden';
            lowHidden.className = 'range-low-hidden';
            container.appendChild(lowHidden);
        }

        if (!highHidden) {
            highHidden = document.createElement('input');
            highHidden.type = 'hidden';
            highHidden.className = 'range-high-hidden';
            container.appendChild(highHidden);
        }

        container.appendChild(lowRow);
        container.appendChild(highRow);

        wireRangeSliders(container);
    });
}
// zodat leveldurationsTracksAOI.js hem kan aanroepen
window.ensureRangeControlsForTrackCard = ensureRangeControlsForTrackCard;

function initTrackCards() {
    document.querySelectorAll('.track-card').forEach(trackCard => {
        // 1) data-effects van Twig gebruiken (of, als leeg, zelf opbouwen)
        if (!trackCard.dataset.effects || trackCard.dataset.effects === '[]') {
            rebuildEffectsDataFromUI(trackCard);
        }

        // 2) selects vullen (en bestaande keuzes selecteren via data-current-id)
        refreshTrackPartSelects(trackCard);

        // 2b) sliders onder de select toevoegen
        ensureRangeControlsForTrackCard(trackCard);

        // 3) watcher activeren zodat wijzigingen in effecten doorwerken
        attachEffectsWatcher(trackCard);

        // 4) select → hidden sync + range toepassen bij change
        trackCard
            .querySelectorAll('select.js-target-effect-param')
            .forEach(sel => {
                sel.addEventListener('change', () => {
                    syncBindingToHidden(sel);
                    applyRangeForSelect(sel);
                });

                // Bij load: meteen range toepassen als er al een keuze is
                if (sel.value) {
                    applyRangeForSelect(sel);
                }
            });
    });
}

function initEffectsUI() {
    initEffectsCollections();
    initTrackCards();
}

// Turbo + fallback
document.addEventListener('DOMContentLoaded', initEffectsUI);
document.addEventListener('turbo:load', initEffectsUI);
