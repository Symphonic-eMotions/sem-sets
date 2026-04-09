// loopLengthEditor.js Loop editor (loopLength + override)
(function () {
    function initLoopEditors() {
        const editors = document.querySelectorAll('.js-loop-editor');

        editors.forEach(function (editor) {
            if (editor.dataset.loopInitialized === '1') {
                return;
            }
            editor.dataset.loopInitialized = '1';

            const totalBars = parseInt(editor.dataset.totalBars || '0', 10);
            const timeSig   = editor.dataset.timesig || editor.dataset.timeSig || '4/4';
            const [numStr, denStr] = timeSig.split('/');
            const groupSize = parseInt(numStr || '4', 10) || 4;

            const hiddenInputId = editor.dataset.inputId;
            const hiddenInput   = document.getElementById(hiddenInputId);
            const chipsContainer = editor.querySelector('.js-loop-chips');
            const baseInput      = editor.querySelector('.js-loop-base-input');

            const resetBtn   = editor.querySelector('.js-loop-reset');
            const addBtn     = editor.querySelector('.js-loop-add');
            const removeBtn  = editor.querySelector('.js-loop-remove');
            const baseDecBtn = editor.querySelector('.js-loop-base-dec');
            const baseIncBtn = editor.querySelector('.js-loop-base-inc');

            function parseValueFromHidden() {
                if (!hiddenInput || !hiddenInput.value) {
                    return [];
                }

                let raw = hiddenInput.value.trim();
                let arr = [];

                if (raw.startsWith('[')) {
                    try {
                        const parsed = JSON.parse(raw);
                        if (Array.isArray(parsed)) {
                            arr = parsed;
                        }
                    } catch (e) {}
                } else {
                    arr = raw.split(',').map(function (v) { return parseInt(v, 10); });
                }

                // Oude array van ints (maten) converteren naar objects (kwartnoten)
                if (arr.length > 0 && typeof arr[0] === 'number') {
                    let objs = [];
                    let currentOffset = 0;
                    arr.forEach(function(bars) {
                        let b = parseInt(bars, 10);
                        if (!Number.isNaN(b) && b > 0) {
                            let q = b * groupSize;
                            objs.push({ offset: currentOffset, length: q });
                            currentOffset += q;
                        }
                    });
                    return objs;
                }

                // Normal object array (al gemigreerd via backend)
                return arr.filter(function(v) { return typeof v === 'object' && v !== null && v.length > 0; })
                          .map(function(v) {
                              return {
                                  offset: parseInt(v.offset, 10) || 0,
                                  length: parseInt(v.length, 10) || 0
                              };
                          });
            }

            function storeValue(values) {
                if (!hiddenInput) {
                    return;
                }
                hiddenInput.value = JSON.stringify(values);
            }

            function renderChips(values) {
                chipsContainer.innerHTML = '';

                if (!values.length) {
                    const span = document.createElement('span');
                    span.className = 'loop-empty';
                    span.textContent = 'Geen looplengte berekend';
                    chipsContainer.appendChild(span);
                    return;
                }

                const COLORS = window.SEM_LOOP_COLORS || [];
                const LABELS = window.SEM_LOOP_LABELS || 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
                const midiAssetId = editor.dataset.midiAssetId;

                values.forEach(function (loopObj, idx) {
                    const chip = document.createElement('span');
                    chip.className = 'loop-chip loop-chip-colored';

                    // A, B, C… of anders fallback naar 1,2,3
                    const label = LABELS[idx] || String(idx + 1);

                    // Create chip content wrapper for flex layout
                    const chipContent = document.createElement('div');
                    chipContent.style.display = 'flex';
                    chipContent.style.alignItems = 'center';
                    chipContent.style.gap = '6px';

                    // Add play button if MIDI asset exists
                    if (midiAssetId) {
                        const playBtn = document.createElement('button');
                        playBtn.type = 'button';
                        playBtn.className = 'loop-play-btn';
                        playBtn.innerHTML = '▶';
                        playBtn.title = 'Voorbeeld afspelen (ingedrukt houden)';
                        playBtn.dataset.loopIndex = idx;
                        playBtn.dataset.loopLength = loopObj.length; // old/new compatible kwartnoten
                        playBtn.dataset.loopOffset = loopObj.offset; // offset meegeven
                        chipContent.appendChild(playBtn);
                    }

                    // Edit button toevoegen voor piano roll (Plan Fase 2)
                    const editBtn = document.createElement('button');
                    editBtn.type = 'button';
                    editBtn.className = 'loop-edit-btn';
                    editBtn.innerHTML = '✎';
                    editBtn.title = 'Loop bewerken';
                    editBtn.dataset.loopIndex = idx;
                    chipContent.appendChild(editBtn);

                    // Add text label
                    const textSpan = document.createElement('span');
                    // maten voor weergave
                    const lenMaten = loopObj.length / groupSize; 
                    textSpan.textContent = 'Loop ' + label + ': ' + lenMaten + ' maten';
                    chipContent.appendChild(textSpan);

                    chip.appendChild(chipContent);

                    if (COLORS.length) {
                        chip.style.backgroundColor = COLORS[idx % COLORS.length];
                        chip.style.color = '#fff';
                    }

                    chipsContainer.appendChild(chip);
                });

                // Initialize loop previews if available
                if (typeof window.initLoopPreviews === 'function') {
                    window.initLoopPreviews(editor);
                }
            }

            function getOverrideBase() {
                if (!baseInput) {
                    return null;
                }

                const raw = (baseInput.value || '').trim();
                if (!raw) {
                    return null;
                }

                const val = parseInt(raw, 10);
                if (Number.isNaN(val) || val < 1) {
                    return null;
                }

                return val;
            }

            function computeEffectiveBase() {
                let computedBase = null;

                if (totalBars > 0 && groupSize > 0) {
                    const q = Math.floor(totalBars / groupSize) * groupSize;
                    if (q >= 1) {
                        computedBase = q;
                    }
                }

                const override = getOverrideBase();

                let candidate;
                if (override !== null) {
                    candidate = override;
                } else if (computedBase !== null) {
                    candidate = computedBase;
                } else {
                    candidate = 8;
                }

                if (groupSize > 0) {
                    const q = Math.floor(candidate / groupSize) * groupSize;
                    return Math.max(1, q);
                }

                return Math.max(1, candidate);
            }

            function recalcForSegmentCount(count) {
                const base = computeEffectiveBase();
                if (base <= 0 || count <= 0 || groupSize <= 0) {
                    return [];
                }

                const rawSegment = base / count;
                const quantized  = Math.floor(rawSegment / groupSize) * groupSize;

                if (quantized <= 0) {
                    return [];
                }

                let objs = [];
                let currentOffset = 0;
                let qLength = quantized * groupSize;
                for (let i = 0; i < count; i++) {
                    objs.push({ offset: currentOffset, length: qLength });
                    currentOffset += qLength;
                }
                return objs;
            }

            let current = parseValueFromHidden();
            if (!current.length) {
                const base = computeEffectiveBase();
                if (base > 0) {
                    current = [{ offset: 0, length: base * groupSize }];
                    storeValue(current);

                    // Toon de berekende basis ook in het basismaten-veld
                    if (baseInput && !(baseInput.value || '').trim()) {
                        baseInput.value = String(base);
                    }
                }
            }
            renderChips(current);

            function updateLoopsFromBaseChange() {
                const loopsCount = current.length || 1;

                if (loopsCount <= 0) {
                    const base = computeEffectiveBase();
                    if (base <= 0) return;
                    current = [{ offset: 0, length: base * groupSize }];
                } else if (loopsCount === 1) {
                    const base = computeEffectiveBase();
                    if (base <= 0) return;
                    current = [{ offset: 0, length: base * groupSize }];
                } else {
                    // Update the lengths but keep the existing sequence logic since they changed base size
                    const next = recalcForSegmentCount(loopsCount);
                    if (!next.length) return;
                    current = next;
                }

                storeValue(current);
                renderChips(current);
            }

            function notifyLoopsChanged() {
                if (typeof window.refreshLoopsGridForTrack !== 'function') {
                    return;
                }
                const card = editor.closest('.track-card');
                if (!card) {
                    return;
                }
                window.refreshLoopsGridForTrack(card);
            }

            if (resetBtn) {
                resetBtn.addEventListener('click', function () {
                    // Override loslaten
                    if (baseInput) {
                        baseInput.value = '';
                    }

                    const base = computeEffectiveBase();
                    if (base <= 0) return;

                    // Basismaten veld ook invullen met de berekende basis
                    if (baseInput) {
                        baseInput.value = String(base);
                    }

                    current = [{ offset: 0, length: base * groupSize }];
                    storeValue(current);
                    renderChips(current);

                    // Aantal loops is nu 1 → Loops naar grid verbergen + reset
                    notifyLoopsChanged();
                });
            }

            if (addBtn) {
                addBtn.addEventListener('click', function () {
                    const nextCount = (current.length || 1) + 1;
                    const next = recalcForSegmentCount(nextCount);
                    if (!next.length) {
                        return;
                    }
                    current = next;
                    storeValue(current);
                    renderChips(current);

                    // Aantal loops verhoogd, reset alle tiles naar loop A
                    notifyLoopsChanged();
                });
            }

            if (removeBtn) {
                removeBtn.addEventListener('click', function () {
                    if (current.length <= 1) {
                        return;
                    }
                    const nextCount = current.length - 1;
                    const next = recalcForSegmentCount(nextCount);
                    if (!next.length) {
                        return;
                    }
                    current = next;
                    storeValue(current);
                    renderChips(current);

                    // Aantal loops verlaagd, reset alle tiles naar loop A
                    notifyLoopsChanged();
                });
            }

            if (baseDecBtn && baseInput) {
                baseDecBtn.addEventListener('click', function () {
                    const raw = (baseInput.value || '').trim();

                    let val;
                    if (!raw) {
                        val = computeEffectiveBase();
                    } else {
                        val = parseInt(raw, 10);
                        if (Number.isNaN(val) || val < 1) {
                            val = computeEffectiveBase();
                        }
                    }

                    const step = groupSize > 0 ? groupSize : 1;
                    val = val - step;
                    if (val < 1) {
                        val = 1;
                    }

                    baseInput.value = String(val);
                    updateLoopsFromBaseChange();
                });
            }

            if (baseIncBtn && baseInput) {
                baseIncBtn.addEventListener('click', function () {
                    const raw = (baseInput.value || '').trim();

                    let val;
                    if (!raw) {
                        val = computeEffectiveBase();
                    } else {
                        val = parseInt(raw, 10);
                        if (Number.isNaN(val) || val < 1) {
                            val = computeEffectiveBase();
                        }
                    }

                    const step = groupSize > 0 ? groupSize : 1;
                    val = val + step;

                    baseInput.value = String(val);
                    updateLoopsFromBaseChange();
                });
            }

            if (baseInput) {
                baseInput.addEventListener('change', function () {
                    const raw = (baseInput.value || '').trim();
                    if (!raw) {
                        baseInput.value = '';
                        updateLoopsFromBaseChange();
                        return;
                    }

                    const val = parseInt(raw, 10);
                    if (Number.isNaN(val) || val < 1) {
                        baseInput.value = '';
                    } else {
                        baseInput.value = String(val);
                    }

                    updateLoopsFromBaseChange();
                });
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initLoopEditors);
    } else {
        initLoopEditors();
    }

    document.addEventListener('turbo:load', initLoopEditors);
    document.addEventListener('turbo:render', initLoopEditors);
})();