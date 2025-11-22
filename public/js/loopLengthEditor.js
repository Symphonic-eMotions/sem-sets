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
                    } catch (e) {
                        // negeren
                    }
                } else {
                    arr = raw.split(',').map(function (v) { return parseInt(v, 10); });
                }

                return arr
                    .map(function (v) { return parseInt(v, 10); })
                    .filter(function (v) { return !Number.isNaN(v) && v > 0; });
            }

            function storeValue(values) {
                if (!hiddenInput) {
                    return;
                }
                hiddenInput.value = '[' + values.join(',') + ']';
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

                values.forEach(function (len, idx) {
                    const chip = document.createElement('span');
                    chip.className = 'loop-chip';
                    chip.textContent = 'Loop ' + (idx + 1) + ': ' + len + ' maten';
                    chipsContainer.appendChild(chip);
                });
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

                return new Array(count).fill(quantized);
            }

            let current = parseValueFromHidden();
            if (!current.length) {
                const base = computeEffectiveBase();
                if (base > 0) {
                    current = [base];
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
                    if (base <= 0) {
                        return;
                    }
                    current = [base];
                } else if (loopsCount === 1) {
                    const base = computeEffectiveBase();
                    if (base <= 0) {
                        return;
                    }
                    current = [base];
                } else {
                    const next = recalcForSegmentCount(loopsCount);
                    if (!next.length) {
                        return;
                    }
                    current = next;
                }

                storeValue(current);
                renderChips(current);
            }

            if (resetBtn) {
                resetBtn.addEventListener('click', function () {
                    // Override loslaten
                    if (baseInput) {
                        baseInput.value = '';
                    }

                    const base = computeEffectiveBase();
                    if (base <= 0) {
                        return;
                    }

                    // Basismaten veld ook invullen met de berekende basis
                    if (baseInput) {
                        baseInput.value = String(base);
                    }

                    current = [base];
                    storeValue(current);
                    renderChips(current);
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