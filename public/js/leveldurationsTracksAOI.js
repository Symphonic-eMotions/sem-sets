// leveldurationsTracksAOI.js: LevelDurations + Tracks + AOI (areaOfInterest)
(function() {
    const SET_HIDDEN = '#ld-hidden-inputs';
    const SET_TILES  = '#ld-tiles';
    const TRACKS_CONTAINER_ID = 'tracks';

    // ---------- Generic collection helpers ----------
    window.addCollectionItem = function(containerId) {
        const container = document.getElementById(containerId);
        if (!container) return;

        const index = parseInt(container.dataset.index || '0', 10);
        const proto = container.dataset.prototype?.replace(/__name__/g, index);

        if (!proto) return;

        const wrapper = document.createElement('div');
        wrapper.className = 'collection-item';
        wrapper.innerHTML = proto + '<button type="button" class="btn-mini danger" onclick="removeCollectionItem(this)">×</button>';

        container.appendChild(wrapper);
        container.dataset.index = String(index + 1);
    };

    window.removeCollectionItem = function(btn) {
        const item = btn.closest('.collection-item');
        if (item) item.remove();
    };

    // ---------- Asset delete helper ----------
    window.deleteAsset = function (url, token, filename) {
        if (!confirm(`Weet je zeker dat je ${filename} wilt verwijderen?`)) return;

        const form = document.createElement('form');
        form.method = 'POST';
        form.action = url;

        const tokenInput = document.createElement('input');
        tokenInput.type = 'hidden';
        tokenInput.name = '_token';
        tokenInput.value = token;

        form.appendChild(tokenInput);
        document.body.appendChild(form);
        form.submit();
    };

    // ---------- LevelDurations module ----------
    const LD = {
        _stripRequired(hidden) {
            hidden.querySelectorAll('input').forEach(inp => {
                inp.removeAttribute('required');
                inp.setAttribute('novalidate', 'novalidate');
            });
        },

        // Locked flag
        _createTile(input, idx, locked = false) {
            // For locked tiles, force value to 1 always
            const v = locked ? 1 : (String(input.value || '0') === '1' ? 1 : 0);

            if (String(input.value) !== String(v)) {
                input.value = String(v);
            }

            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'ld-square' + (v ? ' on' : '') + (locked ? ' locked' : '');
            btn.dataset.index = String(idx);

            // aria-pressed is still based on actual stored value
            btn.setAttribute('aria-pressed', v ? 'true' : 'false');

            // VISUAL LABEL:
            // locked tiles show level number (1-based),
            // unlocked tiles show the stored 0/1
            btn.textContent = locked ? String(idx + 1) : String(v);

            if (!locked) {
                btn.addEventListener('click', () => {
                    const now = btn.classList.contains('on') ? 0 : 1;
                    btn.classList.toggle('on', !!now);
                    btn.setAttribute('aria-pressed', now ? 'true' : 'false');
                    btn.textContent = String(now);
                    input.value = String(now);
                });
            } else {
                btn.setAttribute('aria-disabled', 'true');
                btn.title = 'Levels op set-niveau staan altijd aan';
            }

            return btn;
        },

        // Locked flag threaded through
        _rebuild(hiddenSel, tilesSel, locked = false) {
            const hidden = document.querySelector(hiddenSel);
            const tiles  = document.querySelector(tilesSel);
            if (!hidden || !tiles) return;

            tiles.innerHTML = '';
            this._stripRequired(hidden);

            const inputs = hidden.querySelectorAll('.ld-item input');
            inputs.forEach((input, idx) => {
                const btn = this._createTile(input, idx, locked);
                tiles.appendChild(btn);
            });
        },

        add(hiddenSel, tilesSel, locked = false) {
            const hidden = document.querySelector(hiddenSel);
            if (!hidden) return;

            const idx = parseInt(hidden.dataset.index || '0', 10);
            const proto = hidden.dataset.prototype?.replace(/__name__/g, idx);
            if (!proto) return;

            const wrap = document.createElement('div');
            wrap.className = 'ld-item';
            wrap.innerHTML = proto;

            const inp = wrap.querySelector('input');
            if (inp) {
                // locked => always 1, otherwise 0
                inp.value = locked ? '1' : '0';
                inp.removeAttribute('required');
                inp.setAttribute('novalidate', 'novalidate');
            }

            hidden.appendChild(wrap);
            hidden.dataset.index = String(idx + 1);

            this._rebuild(hiddenSel, tilesSel, locked);
        },

        removeLast(hiddenSel, tilesSel, locked = false) {
            const hidden = document.querySelector(hiddenSel);
            if (!hidden) return;

            const items = hidden.querySelectorAll('.ld-item');
            if (!items.length) return;

            items[items.length - 1].remove();
            hidden.dataset.index = String(items.length - 1);

            this._rebuild(hiddenSel, tilesSel, locked);
        },

        seedIfEmpty(hiddenSel, tilesSel, locked = false) {
            const hidden = document.querySelector(hiddenSel);
            if (!hidden) return;

            const items = hidden.querySelectorAll('.ld-item');
            if (!items.length) {
                this.add(hiddenSel, tilesSel, locked);
            } else {
                this._rebuild(hiddenSel, tilesSel, locked);
            }
        },

        resizeTo(hiddenSel, tilesSel, targetLen, locked = false) {
            const hidden = document.querySelector(hiddenSel);
            if (!hidden) return;

            let items = hidden.querySelectorAll('.ld-item');

            if (items.length > targetLen) {
                for (let i = items.length - 1; i >= targetLen; i--) {
                    items[i].remove();
                }
            }

            while (hidden.querySelectorAll('.ld-item').length < targetLen) {
                const i = parseInt(hidden.dataset.index || '0', 10);
                const proto = hidden.dataset.prototype?.replace(/__name__/g, i);
                if (!proto) break;

                const wrap = document.createElement('div');
                wrap.className = 'ld-item';
                wrap.innerHTML = proto;

                const inp = wrap.querySelector('input');
                if (inp) {
                    // locked => always 1
                    inp.value = locked ? '1' : '0';
                    inp.removeAttribute('required');
                    inp.setAttribute('novalidate', 'novalidate');
                }

                hidden.appendChild(wrap);
                hidden.dataset.index = String(i + 1);
            }

            const finalCount = hidden.querySelectorAll('.ld-item').length;
            hidden.dataset.index = String(finalCount);

            if (tilesSel) {
                this._rebuild(hiddenSel, tilesSel, locked);
            }
        }
    };

    window.LD = LD;

    window.removeInstrumentPart = function(buttonEl) {
        const part = buttonEl.closest('.instrument-part');
        if (!part) return;

        const trackCard = part.closest('.track-card');
        if (!trackCard) return;

        // Alle parts binnen deze track (maakt niet uit of uit Twig of JS komen)
        const allParts = trackCard.querySelectorAll('.instrument-part');
        if (allParts.length <= 1) {
            alert('Er moet minimaal één instrument part blijven bestaan.');
            return;
        }

        part.remove();

        // AOI-tiles opnieuw opbouwen voor de resterende parts
        const trackIdx = Array.from(
            document.querySelectorAll('#tracks .track-card')
        ).indexOf(trackCard);

        if (trackIdx !== -1) {
            buildAoiTiles(trackCard, trackIdx);
            if (typeof refreshTrackPartSelects === 'function') {
                refreshTrackPartSelects(trackCard);
            }
        }
    };

    // ===============================
    // AreaOfInterest (raw JSON input)
    // ===============================
    function getDocGrid() {
        const gridSelect = document.querySelector('select[name$="[gridSize]"]');
        if (gridSelect) {
            const m = String(gridSelect.value || '').match(/^(\d+)x(\d+)$/);
            if (m) {
                return { cols: parseInt(m[1], 10), rows: parseInt(m[2], 10) };
            }
        }
        return { cols: 1, rows: 1 };
    }

    function parseRawAoi(inputEl) {
        if (!inputEl || !inputEl.value) return [];
        const raw = inputEl.value.trim();
        if (!raw) return [];

        if (raw.startsWith('[')) {
            try {
                const arr = JSON.parse(raw);
                if (Array.isArray(arr)) return arr.map(v => (parseInt(v,10) === 1 ? 1 : 0));
            } catch(e) {}
        }
        return raw.split(',').map(v => (parseInt(v,10) === 1 ? 1 : 0));
    }

    function storeRawAoi(inputEl, arr) {
        if (!inputEl) return;
        inputEl.value = '[' + arr.join(',') + ']';
    }

    function getTrackLoopCount(card) {
        // zoek de loop-editor binnen deze track-card
        const editor = card.querySelector('.js-loop-editor');
        if (!editor) return 0;

        const inputId = editor.dataset.inputId;
        const hiddenInput = inputId ? document.getElementById(inputId) : null;
        if (!hiddenInput || !hiddenInput.value) return 0;

        let raw = hiddenInput.value.trim();
        let arr = [];

        if (!raw) {
            return 0;
        }

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
            arr = raw.split(',').map(v => parseInt(v, 10));
        }

        arr = arr
            .map(v => parseInt(v, 10))
            .filter(v => !Number.isNaN(v) && v > 0);

        return arr.length;
    }

    function parseRawLoopsGrid(inputEl, loopCount, targetLen) {
        if (!inputEl || loopCount <= 0 || targetLen <= 0) {
            return new Array(targetLen).fill(0);
        }

        let raw = (inputEl.value || '').trim();
        let arr = [];

        if (raw) {
            if (raw.startsWith('[')) {
                try {
                    const parsed = JSON.parse(raw);
                    if (Array.isArray(parsed)) {
                        arr = parsed;
                    }
                } catch (e) {
                    // ignore
                }
            } else {
                arr = raw.split(',').map(v => parseInt(v, 10));
            }
        }

        arr = arr.map(function (v) {
            let n = parseInt(v, 10);
            if (Number.isNaN(n) || n < 0) n = 0;
            if (loopCount > 0) {
                n = n % loopCount;
            }
            return n;
        });

        if (!arr.length) {
            arr = new Array(targetLen).fill(0);
        } else if (arr.length > targetLen) {
            arr = arr.slice(0, targetLen);
        } else if (arr.length < targetLen) {
            arr = arr.concat(new Array(targetLen - arr.length).fill(0));
        }

        return arr;
    }

    function storeRawLoopsGrid(inputEl, arr) {
        if (!inputEl) return;
        inputEl.value = '[' + arr.join(',') + ']';
    }

    function buildLoopsGridTiles(partBlock, card, cols, rows, resetToFirstLoop) {
        const loopsTiles = partBlock.querySelector('.loops-tiles');
        if (!loopsTiles) return;

        const inputId = loopsTiles.dataset.inputId;
        const inputEl = inputId ? document.getElementById(inputId) : null;
        const targetLen = Math.max(1, cols * rows);

        const loopsCount = getTrackLoopCount(card);

        // Als er 0 of 1 loops zijn, tonen we niets / disabled
        if (!inputEl || loopsCount <= 1) {
            loopsTiles.innerHTML = '<em style="font-size: 0.8em;">Voeg loops toe om ze te kunnen plaatsen</em>';
            loopsTiles.classList.add('loops-disabled');
            inputEl.value = '';
            return;
        }

        loopsTiles.classList.remove('loops-disabled');

        let mapping;
        if (resetToFirstLoop) {
            // Alles terug naar loop 0 (= A)
            mapping = new Array(targetLen).fill(0);
        } else {
            mapping = parseRawLoopsGrid(inputEl, loopsCount, targetLen);
        }

        storeRawLoopsGrid(inputEl, mapping);

        loopsTiles.innerHTML = '';
        loopsTiles.style.gridTemplateColumns = `repeat(${cols}, 36px)`;

        const COLORS = window.SEM_LOOP_COLORS || [];
        const LABELS = window.SEM_LOOP_LABELS || 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';

        mapping.forEach((loopIdx, cellIdx) => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'ld-square loop-square';
            const label = LABELS[loopIdx] || String(loopIdx + 1);
            btn.textContent = label;
            btn.dataset.loopIndex = String(loopIdx);
            btn.dataset.cellIndex = String(cellIdx);

            if (COLORS.length) {
                btn.style.backgroundColor = COLORS[loopIdx % COLORS.length];
                btn.style.color = '#fff';
            }

            btn.addEventListener('click', () => {
                // cycle A→B→C→...
                const current = mapping[cellIdx];
                const next = (current + 1) % loopsCount;
                mapping[cellIdx] = next;

                const nextLabel = LABELS[next] || String(next + 1);
                btn.textContent = nextLabel;
                btn.dataset.loopIndex = String(next);

                if (COLORS.length) {
                    btn.style.backgroundColor = COLORS[next % COLORS.length];
                }

                storeRawLoopsGrid(inputEl, mapping);
            });

            loopsTiles.appendChild(btn);
        });
    }

    function buildAoiTiles(card, trackIdx) {
        const { cols, rows } = getDocGrid();
        const targetLen = Math.max(1, cols * rows);

        const partBlocks = card.querySelectorAll('.instrument-part');

        partBlocks.forEach((partBlock) => {
            // AOI
            const tiles = partBlock.querySelector('.aoi-tiles');
            if (!tiles) return;

            const inputId = tiles.dataset.inputId;
            if (!inputId) return;

            const inputEl = document.getElementById(inputId);
            if (!inputEl) return;

            let current = parseRawAoi(inputEl);

            if (current.length === 0) {
                current = new Array(targetLen).fill(1); // default: alles aan
            } else if (current.length > targetLen) {
                current = current.slice(0, targetLen);
            } else if (current.length < targetLen) {
                current = current.concat(new Array(targetLen - current.length).fill(0));
            }

            storeRawAoi(inputEl, current);

            tiles.innerHTML = '';
            tiles.style.gridTemplateColumns = `repeat(${cols}, 36px)`;

            current.forEach((v, i) => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'ld-square' + (v ? ' on' : '');
                btn.textContent = v ? '1' : '0';
                btn.setAttribute('aria-pressed', v ? 'true' : 'false');

                btn.addEventListener('click', () => {
                    const now = btn.classList.contains('on') ? 0 : 1;
                    btn.classList.toggle('on', !!now);
                    btn.textContent = String(now);
                    btn.setAttribute('aria-pressed', now ? 'true' : 'false');

                    current[i] = now;
                    storeRawAoi(inputEl, current);
                });

                tiles.appendChild(btn);
            });

            // NIEUW: loops-to-grid tegels voor deze part
            buildLoopsGridTiles(partBlock, card, cols, rows, false);
        });
    }

    window.refreshLoopsGridForTrack = function (card) {
        const { cols, rows } = getDocGrid();
        const parts = card.querySelectorAll('.instrument-part');

        parts.forEach((partBlock) => {
            // resetToFirstLoop = true → overal weer loop A
            buildLoopsGridTiles(partBlock, card, cols, rows, true);
        });
    };

    function syncAllAoIToDocGrid() {
        document.querySelectorAll('#tracks .track-card').forEach((card, i) => {
            buildAoiTiles(card, i);
        });
    }


    // ---------- Tracks module ----------
    const tracksContainer = document.getElementById(TRACKS_CONTAINER_ID);

    function getSetLevelCount() {
        const hidden = document.querySelector(SET_HIDDEN);
        return hidden ? hidden.querySelectorAll('.ld-item').length : 0;
    }

    function syncAllTracksToSetCount() {
        const targetLen = getSetLevelCount();
        if (!tracksContainer) return;

        document.querySelectorAll('#tracks .track-card').forEach((card, i) => {
            const hidden = card.querySelector('#trk-hidden-' + i);
            const tiles  = card.querySelector('#trk-tiles-' + i);
            if (hidden && tiles) {
                LD.resizeTo('#' + hidden.id, '#' + tiles.id, targetLen);
            }
        });
    }

    // Helper: maak één InstrumentPart DOM-structuur vanuit het Symfony prototype
    function remapInstrumentPartFieldIdentity(field, pIndex) {
        if (!field) return;

        if (typeof field.name === 'string' && field.name !== '') {
            field.name = field.name.replace(
                /(\[instrumentParts]\[)(?:__name__|__part__|\d+)(\])/,
                '$1' + pIndex + '$2'
            );
        }

        if (typeof field.id === 'string' && field.id !== '') {
            field.id = field.id.replace(
                /(_instrumentParts_)(?:__name__|__part__|\d+)(_)/,
                '$1' + pIndex + '$2'
            );
        }
    }

    function createInstrumentPart(partsContainer, trackIdx, pIndex) {
        const proto = partsContainer.dataset.prototype
            ?.replace(/__part__/g, pIndex)
            .replace(/__name__/g, pIndex);
        if (!proto) return null;

        const tmp = document.createElement('div');
        tmp.innerHTML = proto.trim();

        // Verwacht: areaOfInterest + targetBinding uit Symfony-prototype
        const areaField   = tmp.querySelector('[name$="[areaOfInterest]"]') || tmp.firstElementChild;
        const loopsField  = tmp.querySelector('[name$="[loopsToGrid]"]');
        const targetField = tmp.querySelector('[name$="[targetBinding]"]') || (areaField && areaField.nextElementSibling) || null;
        const rangeLowField  = tmp.querySelector('[name$="[targetRangeLow]"]');
        const rangeHighField = tmp.querySelector('[name$="[targetRangeHigh]"]');
        const minimalLevelField = tmp.querySelector('[name$="[minimalLevel]"]');
        const rampSpeedField = tmp.querySelector('[name$="[rampSpeed]"]');
        const rampSpeedDownField = tmp.querySelector('[name$="[rampSpeedDown]"]');

        if (!areaField) {
            return null;
        }

        [
            areaField,
            loopsField,
            targetField,
            rangeLowField,
            rangeHighField,
            minimalLevelField,
            rampSpeedField,
            rampSpeedDownField,
        ].forEach((field) => remapInstrumentPartFieldIdentity(field, pIndex));

        const card = document.createElement('div');
        card.className = 'instrument-part';
        card.dataset.partIndex = String(pIndex);

        const isFirst = (pIndex === 0);

        card.innerHTML = `
            <div class="instrument-part-header-row">
                    <div class="instrument-parts-header">
                        <span class="label">Actieve regio delen</span>
                        <span class="label">Wat stuurt deze regio aan</span>
                    </div>
                    <button type="button"
                            class="btn-mini danger instrument-part-remove"
                            onclick="removeInstrumentPart(this)">
                        Verwijder
                    </button>
                </div>
        
                <div class="instrument-part-grid">
                    <div class="instrument-part-region">
        
                        <div class="aoi-tiles" data-input-id=""></div>
                        <div class="ld-hidden aoi-hidden"></div>
                        
                        ${isFirst ? `
                            <div class="loops-grid-label">
                                <span class="label">Geef midi loops een plaats in het grid</span>
                            </div>
                            <div class="loops-tiles" data-input-id=""></div>
                            <div class="ld-hidden loops-hidden"></div>
                        ` : ''}
                  
                    </div>
                    <div class="part-effect-target">
                        <div class="dynamic-part-extra-fields" style="display:none"></div>
                    </div>
                </div>`;


        // AOI input in hidden wrapper hangen
        const hiddenWrapper = card.querySelector('.instrument-part-region .ld-hidden');
        hiddenWrapper.appendChild(areaField);

        const tilesDiv = card.querySelector('.instrument-part-region .aoi-tiles');
        tilesDiv.dataset.inputId = areaField.id;
        // optioneel: nog steeds een uniek id geven
        tilesDiv.id = `aoi-tiles-${trackIdx}-${pIndex}`;

        // LoopsToGrid input in eigen hidden wrapper
        if (loopsField) {
            const loopsHidden = card.querySelector('.instrument-part-region .loops-hidden');
            if (loopsHidden) {
                loopsHidden.appendChild(loopsField);
            }

            if (isFirst) {
                const loopsTiles = card.querySelector('.instrument-part-region .loops-tiles');
                if (loopsTiles) {
                    loopsTiles.dataset.inputId = loopsField.id;
                    loopsTiles.id = `loops-tiles-${trackIdx}-${pIndex}`;
                }
            }
        }

        // targetBinding hidden + select voor effect/seq
        const targetContainer = card.querySelector('.part-effect-target');

        if (targetField) {
            targetField.classList.add('js-target-binding-hidden');
            targetContainer.appendChild(targetField);
        }

        if (rangeLowField) {
            rangeLowField.classList.add('range-low-hidden');
            targetContainer.appendChild(rangeLowField);
        }

        if (rangeHighField) {
            rangeHighField.classList.add('range-high-hidden');
            targetContainer.appendChild(rangeHighField);
        }

        const extraFields = targetContainer.querySelector('.dynamic-part-extra-fields');
        if (extraFields) {
            const prepareDynamicNumberField = (field, className, fallbackValue) => {
                if (!field) return;
                field.classList.add(className);
                if (String(field.value || '').trim() === '') {
                    field.value = String(fallbackValue);
                }
                field.removeAttribute('required');
                field.type = 'hidden';
                extraFields.appendChild(field);
            };

            prepareDynamicNumberField(minimalLevelField, 'minimal-level-input', 0.1);
            prepareDynamicNumberField(rampSpeedField, 'ramp-speed-input', 0.04);
            prepareDynamicNumberField(rampSpeedDownField, 'ramp-speed-down-input', 0.02);
        }

        const select = document.createElement('select');
        select.className = 'js-target-effect-param';
        if (targetField) {
            select.dataset.bindInput = targetField.id;
        }
        targetContainer.appendChild(select);
        partsContainer.dataset.index = String(pIndex + 1);

        // Card visueel onderaan de instrument-parts-panel zetten
        const panel = partsContainer.closest('.instrument-parts-panel');
        if (panel) {
            panel.appendChild(card);
        } else {
            // Fallback: als er iets mis is, gewoon in de container zetten
            partsContainer.appendChild(card);
        }

        return card;
    }

    function ensureAtLeastOnePart(card, trackIdx) {
        const partsContainer = card.querySelector('#parts-' + trackIdx);
        if (!partsContainer) return;

        // als er al parts zijn, niets doen
        if (card.querySelectorAll('.instrument-part').length > 0) return;

        const pIndex = parseInt(partsContainer.dataset.index || '0', 10);
        const newCard = createInstrumentPart(partsContainer, trackIdx, pIndex);
        if (!newCard) return;

        const trackCard = card.closest('.track-card') || card;

        // AOI-tiles voor alle parts in deze track opnieuw bouwen
        buildAoiTiles(trackCard, trackIdx);

        // Effect-parameterselects vullen op basis van huidige effecten (uit effectsSettings.js)
        if (typeof refreshTrackPartSelects === 'function') {
            refreshTrackPartSelects(trackCard);
        }

        // Sliders onder de select toevoegen
        if (typeof ensureRangeControlsForTrackCard === 'function') {
            ensureRangeControlsForTrackCard(trackCard);
        }

        // select → hidden sync + range
        newCard.querySelectorAll('select.js-target-effect-param').forEach(sel => {
            sel.addEventListener('change', () => {
                if (typeof syncBindingToHidden === 'function') {
                    syncBindingToHidden(sel);
                }
                if (typeof applyRangeForSelect === 'function') {
                    applyRangeForSelect(sel);
                }
            });

            // Bij initialiseren (als er al een waarde is) meteen range zetten
            if (sel.value && typeof applyRangeForSelect === 'function') {
                applyRangeForSelect(sel);
            }
        });

    }

    function wireNewTrackCard(card, idx) {
        // levels...
        const hidden = card.querySelector('#trk-hidden-' + idx);
        const tiles  = card.querySelector('#trk-tiles-' + idx);

        if (hidden && tiles) {
            LD.seedIfEmpty('#' + hidden.id, '#' + tiles.id);
            LD.resizeTo('#' + hidden.id, '#' + tiles.id, getSetLevelCount());
        }

        // parts seeden voor nieuwe tracks
        ensureAtLeastOnePart(card, idx);

        // AOI tiles bouwen
        buildAoiTiles(card, idx);

        if (typeof window.syncTrackVolumeDisplays === 'function') {
            window.syncTrackVolumeDisplays(card);
        }
    }

    function setTrackMidiAsset(card, assetId) {
        const midiSelect = card.querySelector('select[name$="[midiAsset]"]');
        if (!midiSelect) return;
        midiSelect.value = String(assetId);
    }

    function setTrackLevelsActive(card, idx) {
        const hidden = card.querySelector('#trk-hidden-' + idx);
        const tiles = card.querySelector('#trk-tiles-' + idx);
        if (!hidden || !tiles) return;

        hidden.querySelectorAll('.ld-item input').forEach((input) => {
            input.value = '1';
        });

        LD._rebuild('#' + hidden.id, '#' + tiles.id);
    }

    function setTrackExsPreset(card, presetName) {
        const exsSelect = card.querySelector('select[name$="[exsPreset]"]');
        if (!exsSelect) return;

        const hasOption = Array.from(exsSelect.options).some((option) => option.value === presetName);
        exsSelect.value = hasOption ? presetName : '';
    }

    function setTrackVolume(card, value) {
        const volumeInput = card.querySelector('input[name$="[trackVolume]"]');
        if (!volumeInput) return;

        volumeInput.value = String(value);

        if (typeof window.syncTrackVolumeDisplays === 'function') {
            window.syncTrackVolumeDisplays(card);
        }
    }

    function setFirstRegionVelocity(card) {
        const firstPart = card.querySelector('.instrument-part');
        if (!firstPart) return;

        const hiddenBindingInput = firstPart.querySelector('input.js-target-binding-hidden');
        if (hiddenBindingInput) {
            hiddenBindingInput.value = 'seq:velocity';
        }

        const bindingSelect = firstPart.querySelector('select.js-target-effect-param');
        if (!bindingSelect) return;

        const hasVelocityOption = !!bindingSelect.querySelector('option[value="seq:velocity"]');
        if (!hasVelocityOption && typeof refreshTrackPartSelects === 'function') {
            refreshTrackPartSelects(card);
        }

        bindingSelect.dataset.currentId = 'seq:velocity';
        bindingSelect.value = 'seq:velocity';

        if (typeof syncBindingToHidden === 'function') {
            syncBindingToHidden(bindingSelect);
        }

        if (typeof applyRangeForSelect === 'function') {
            applyRangeForSelect(bindingSelect);
        }
    }

    function normalizeToken(value) {
        return String(value || '')
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '');
    }

    function wirePartSelectBindings(partCard) {
        partCard.querySelectorAll('select.js-target-effect-param').forEach((sel) => {
            if (sel.dataset.bindingListenersAttached === '1') {
                return;
            }
            sel.dataset.bindingListenersAttached = '1';

            sel.addEventListener('change', () => {
                if (typeof syncBindingToHidden === 'function') {
                    syncBindingToHidden(sel);
                }
                if (typeof applyRangeForSelect === 'function') {
                    applyRangeForSelect(sel);
                }
            });

            if (sel.value && typeof applyRangeForSelect === 'function') {
                applyRangeForSelect(sel);
            }
        });
    }

    function addInstrumentPartToTrackCard(trackCard, fallbackTrackIdx) {
        const partsContainer = trackCard.querySelector('.instrument-parts');
        if (!partsContainer) {
            return null;
        }

        const idMatch = String(partsContainer.id || '').match(/^parts-(\d+)$/);
        const resolvedTrackIdx = idMatch ? parseInt(idMatch[1], 10) : fallbackTrackIdx;
        if (!Number.isInteger(resolvedTrackIdx)) {
            return null;
        }

        const pIndex = parseInt(partsContainer.dataset.index || '0', 10);
        const newPartCard = createInstrumentPart(partsContainer, resolvedTrackIdx, pIndex);
        if (!newPartCard) {
            return null;
        }

        buildAoiTiles(trackCard, resolvedTrackIdx);

        if (typeof refreshTrackPartSelects === 'function') {
            refreshTrackPartSelects(trackCard);
        }

        if (typeof ensureRangeControlsForTrackCard === 'function') {
            ensureRangeControlsForTrackCard(trackCard);
        }

        wirePartSelectBindings(newPartCard);

        return {
            partCard: newPartCard,
            trackIdx: resolvedTrackIdx,
        };
    }

    function findLowPassPresetValue(effectSelect) {
        let bestValue = null;
        let bestScore = -1;

        Array.from(effectSelect.options || []).forEach((option) => {
            if (!option.value) return;

            const mapEntry = window.EFFECT_PRESET_MAP?.[option.value] || null;
            const tokens = [
                option.textContent,
                option.label,
                mapEntry?.effectName,
                mapEntry?.name,
            ].map(normalizeToken).filter(Boolean);

            let score = 0;
            tokens.forEach((token) => {
                if (token === 'lowpassfilter') score = Math.max(score, 5);
                else if (token.includes('lowpassfilter')) score = Math.max(score, 4);
                else if (token.includes('lowpass')) score = Math.max(score, 3);
                else if (token.includes('low') && token.includes('pass') && token.includes('filter')) score = Math.max(score, 2);
            });

            if (score > bestScore) {
                bestScore = score;
                bestValue = option.value;
            }
        });

        return bestScore > 0 ? bestValue : null;
    }

    function addLowPassEffectToTrack(trackCard, trackIdx) {
        if (typeof window.addEffect !== 'function') {
            return false;
        }

        window.addEffect(trackIdx);

        const effectsContainer = trackCard.querySelector('.effects');
        if (!effectsContainer) {
            return false;
        }

        const effectCards = effectsContainer.querySelectorAll('.effect-card');
        const newEffectCard = effectCards[effectCards.length - 1];
        if (!newEffectCard) {
            return false;
        }

        const effectSelect = newEffectCard.querySelector('select.effect-select, select[name$="[preset]"]');
        if (!effectSelect) {
            return false;
        }

        const lowPassValue = findLowPassPresetValue(effectSelect);
        if (!lowPassValue) {
            return false;
        }

        effectSelect.value = lowPassValue;
        effectSelect.dispatchEvent(new Event('change', { bubbles: true }));

        if (typeof refreshTrackPartSelects === 'function') {
            refreshTrackPartSelects(trackCard);
        }
        if (typeof ensureRangeControlsForTrackCard === 'function') {
            ensureRangeControlsForTrackCard(trackCard);
        }

        return true;
    }

    function findCutoffOption(selectEl) {
        let bestOption = null;
        let bestScore = -1;

        Array.from(selectEl.options || []).forEach((option) => {
            if (!option.value || !option.value.startsWith('effect:')) {
                return;
            }

            const token = normalizeToken(option.textContent || option.label || '');
            let score = 0;
            if (token === 'cutofffrequency') score = 5;
            else if (token.includes('cutofffrequency')) score = 4;
            else if (token.includes('cutoff') && (token.includes('freq') || token.includes('frequency'))) score = 3;
            else if (token.includes('cutoff')) score = 2;

            if (score > bestScore) {
                bestScore = score;
                bestOption = option;
            }
        });

        return bestScore > 0 ? bestOption : null;
    }

    function setPartRange(partCard, lowValue, highValue) {
        const target = partCard.querySelector('.part-effect-target');
        if (!target) return;

        const lowHidden = target.querySelector('.range-low-hidden');
        const highHidden = target.querySelector('.range-high-hidden');
        const lowSlider = target.querySelector('.range-low');
        const highSlider = target.querySelector('.range-high');

        const minVal = parseFloat(target.dataset.rangeMin || '');
        const maxVal = parseFloat(target.dataset.rangeMax || '');

        let low = Number(lowValue);
        let high = Number(highValue);
        if (!Number.isFinite(low) || !Number.isFinite(high)) {
            return;
        }

        if (Number.isFinite(minVal) && Number.isFinite(maxVal) && maxVal !== minVal) {
            low = Math.min(Math.max(low, minVal), maxVal);
            high = Math.min(Math.max(high, minVal), maxVal);
        }

        if (low > high) {
            const tmp = low;
            low = high;
            high = tmp;
        }

        if (lowHidden) lowHidden.value = String(low);
        if (highHidden) highHidden.value = String(high);

        if (lowSlider && highSlider && Number.isFinite(minVal) && Number.isFinite(maxVal) && maxVal !== minVal) {
            const toNorm = (v) => (v - minVal) / (maxVal - minVal);
            lowSlider.value = String(toNorm(low));
            highSlider.value = String(toNorm(high));
            lowSlider.dispatchEvent(new Event('input', { bubbles: true }));
            highSlider.dispatchEvent(new Event('input', { bubbles: true }));
        }
    }

    function configureCutoffForPart(partCard) {
        const selectEl = partCard.querySelector('select.js-target-effect-param');
        if (!selectEl) {
            return false;
        }

        const cutoffOption = findCutoffOption(selectEl);
        if (!cutoffOption) {
            return false;
        }

        selectEl.dataset.currentId = cutoffOption.value;
        selectEl.value = cutoffOption.value;
        selectEl.dispatchEvent(new Event('change', { bubbles: true }));

        if (typeof syncBindingToHidden === 'function') {
            syncBindingToHidden(selectEl);
        }
        if (typeof applyRangeForSelect === 'function') {
            applyRangeForSelect(selectEl);
        }

        setPartRange(partCard, 2200, 20000);
        return true;
    }

    function addTrackFromPrototype() {
        if (!tracksContainer) return;

        const index = parseInt(tracksContainer.dataset.index || '0', 10);
        let html = String(tracksContainer.dataset.prototype || '')
            .replace(/__name__/g, index)
            .replace(/__num__/g, index + 1);

        if (!html) return;

        const wrapper = document.createElement('div');
        wrapper.innerHTML = html;
        const card = wrapper.firstElementChild;
        if (!card) return null;

        tracksContainer.appendChild(card);
        tracksContainer.dataset.index = String(index + 1);

        wireNewTrackCard(card, index);
        return card;
    }

    window.removeTrack = function(btn) {
        btn.closest('.track-card')?.remove();
    };

    window.createTrackFromMidiAsset = function (assetId) {
        if (!tracksContainer) return;

        const trackIndex = parseInt(tracksContainer.dataset.index || '0', 10);
        const newCard = addTrackFromPrototype();
        if (!newCard) return;

        setTrackMidiAsset(newCard, assetId);
        setTrackLevelsActive(newCard, trackIndex);
        setTrackExsPreset(newCard, 'Celesta');
        setTrackVolume(newCard, 0);
        addLowPassEffectToTrack(newCard, trackIndex);
        setFirstRegionVelocity(newCard);

        const secondRegion = addInstrumentPartToTrackCard(newCard, trackIndex);
        if (secondRegion && secondRegion.partCard) {
            configureCutoffForPart(secondRegion.partCard);
        }

        // Na effect/regio refreshes velocity op regio 1 opnieuw afdwingen.
        setFirstRegionVelocity(newCard);

        const form = document.getElementById('document-form');
        if (!form) return;

        if (typeof form.requestSubmit === 'function') {
            form.requestSubmit();
            return;
        }

        form.submit();
    };

    // Publieke helper voor de "+ Nieuw instrument part" knop
    window.addInstrumentPart = function(trackIdx) {
        const tracksContainer = document.getElementById(TRACKS_CONTAINER_ID);
        if (!tracksContainer) return;

        const cards = tracksContainer.querySelectorAll('.track-card');
        const card = cards[trackIdx];
        if (!card) return;

        addInstrumentPartToTrackCard(card, trackIdx);
    };

    // ---------- INIT ----------
    if (tracksContainer) {
        document
            .querySelectorAll('#tracks .track-card')
            .forEach((card, i) => wireNewTrackCard(card, i));
    }

    LD.seedIfEmpty(SET_HIDDEN, SET_TILES, true);
    syncAllTracksToSetCount();
    syncAllAoIToDocGrid();

    const ldAddBtn    = document.getElementById('ld-add');
    const ldRemoveBtn = document.getElementById('ld-remove');
    // luister naar gridSize changes
    const gridSelect = document.querySelector('select[name$="[gridSize]"]');

    if (gridSelect) {
        gridSelect.addEventListener('change', () => {
            syncAllAoIToDocGrid();
        });
    }

    if (ldAddBtn) {
        ldAddBtn.addEventListener('click', () => {
            LD.add(SET_HIDDEN, SET_TILES, true);
            syncAllTracksToSetCount();
        });
    }

    if (ldRemoveBtn) {
        ldRemoveBtn.addEventListener('click', () => {
            LD.removeLast(SET_HIDDEN, SET_TILES, true);
            syncAllTracksToSetCount();
        });
    }

    document.getElementById('add-track')
        ?.addEventListener('click', addTrackFromPrototype);

    const form = document.querySelector('form');
    if (form) {
        form.addEventListener('submit', () => {
            document
                .querySelectorAll('.ld-hidden')
                .forEach(h => LD._stripRequired(h));

            // Dynamisch aangemaakte InstrumentPart velden kunnen hidden zijn.
            // Zorg dat browser-validatie submit niet blokkeert.
            document.querySelectorAll('.dynamic-part-extra-fields input').forEach((input) => {
                input.removeAttribute('required');

                if (String(input.value || '').trim() !== '') {
                    return;
                }

                if (input.classList.contains('minimal-level-input')) {
                    input.value = '0.1';
                } else if (input.classList.contains('ramp-speed-input')) {
                    input.value = '0.04';
                } else if (input.classList.contains('ramp-speed-down-input')) {
                    input.value = '0.02';
                }
            });
        });
    }

})();
