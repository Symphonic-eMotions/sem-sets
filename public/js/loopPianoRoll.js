/**
 * Piano Roll Loop Editor
 *
 * Opens a modal with a canvas showing MIDI notes,
 * allowing the user to select a specific segment (offset + length)
 * for a loop.
 */

(function() {
    'use strict';

    // State
    const state = {
        modal: null,
        canvas: null,
        ctx: null,
        infoSpan: null,
        
        midiData: null,
        documentId: null,
        assetId: null,
        editorEl: null,
        loopIndex: null,
        
        beatsPerBar: 4,
        pixelsPerQuarter: 40,
        pitchHeight: 4,
        minPitch: 21,
        maxPitch: 108,
        
        selectionStartBeat: null,
        selectionEndBeat: null,
        isDragging: false,
        dragStartBeat: null,
        dragMode: null,
        dragMoveOffset: 0,
        dragMoveLength: 0
    };


    function init() {
        state.modal = document.getElementById('piano-roll-modal');
        if (!state.modal) return;

        state.canvas = document.getElementById('piano-roll-canvas');
        state.ctx = state.canvas.getContext('2d');
        state.infoSpan = document.getElementById('pr-selection-info');

        // Close/Cancel buttons
        document.querySelectorAll('.js-pr-close, .js-pr-cancel').forEach(btn => {
            btn.addEventListener('click', closeModal);
        });

        // Save button
        const saveBtn = document.querySelector('.js-pr-save');
        if (saveBtn) {
            saveBtn.addEventListener('click', saveSelection);
        }

        // Preview Play button
        const playBtn = document.querySelector('.js-pr-play');
        if (playBtn) {
            playBtn.addEventListener('mousedown', onPlayBtnMouseDown);
            playBtn.addEventListener('mouseup', onPlayBtnMouseUp);
            playBtn.addEventListener('mouseleave', onPlayBtnMouseUp);
            playBtn.addEventListener('touchstart', (e) => { e.preventDefault(); onPlayBtnMouseDown(e); });
            playBtn.addEventListener('touchend', (e) => { e.preventDefault(); onPlayBtnMouseUp(e); });
        }

        // Stepper controls
        const lengthInput = document.querySelector('.js-pr-length-input');
        const minusBtn = document.querySelector('.js-pr-length-minus');
        const plusBtn = document.querySelector('.js-pr-length-plus');

        if (lengthInput) {
            lengthInput.addEventListener('change', onLengthInputChange);
            lengthInput.addEventListener('input', onLengthInputChange);
        }
        if (minusBtn) {
            minusBtn.addEventListener('click', (e) => {
                e.preventDefault();
                adjustLoopLength(-1);
            });
        }
        if (plusBtn) {
            plusBtn.addEventListener('click', (e) => {
                e.preventDefault();
                adjustLoopLength(1);
            });
        }

        // Canvas mouse events
        state.canvas.addEventListener('mousedown', onMouseDown);
        state.canvas.addEventListener('mousemove', onMouseMove);
        window.addEventListener('mouseup', onMouseUp);

        // Edit buttons delegatie
        document.addEventListener('click', onEditClick);
    }

    async function onEditClick(e) {
        const btn = e.target.closest('.loop-edit-btn');
        if (!btn) return;

        const editor = btn.closest('.js-loop-editor');
        if (!editor) return;

        state.loopIndex = parseInt(btn.dataset.loopIndex, 10);
        state.editorEl = editor;
        state.assetId = editor.dataset.midiAssetId;
        state.documentId = editor.dataset.documentId;
        
        const timeSig = editor.dataset.timesig || '4/4';
        state.beatsPerBar = parseInt(timeSig.split('/')[0], 10) || 4;

        if (!state.assetId || !state.documentId) {
            alert('Geef eerst een MIDI-bestand op (en sla op) voordat je de piano roll kunt gebruiken.');
            return;
        }

        openModal();
        await loadMidiAndDraw();
        
        // Lees huidige loop settings om initieel te selecteren
        const inputId = editor.dataset.inputId;
        const hiddenInput = document.getElementById(inputId);
        if (hiddenInput && hiddenInput.value) {
            try {
                let arr = [];
                let raw = hiddenInput.value.trim();
                if (raw.startsWith('[')) {
                    arr = JSON.parse(raw);
                } else {
                    arr = raw.split(',').map(v => parseInt(v, 10));
                }
                
                let cur = arr[state.loopIndex];
                if (cur !== undefined) {
                    if (typeof cur === 'object' && cur !== null) {
                        state.selectionStartBeat = cur.offset;
                        state.selectionEndBeat = cur.offset + cur.length;
                    } else if (typeof cur === 'number') {
                        // Oude stijl, bereken offset
                        let offset = 0;
                        for (let i = 0; i < state.loopIndex; i++) {
                            offset += (typeof arr[i] === 'number' ? arr[i] : (arr[i]?.length / state.beatsPerBar || 0));
                        }
                        state.selectionStartBeat = offset * state.beatsPerBar;
                        state.selectionEndBeat = state.selectionStartBeat + (cur * state.beatsPerBar);
                    }
                }
            } catch (e) {
                console.error(e);
            }
        }
        
        if (state.selectionStartBeat === null) {
            state.selectionStartBeat = 0;
            state.selectionEndBeat = 4 * state.beatsPerBar;
        }
        
        draw();
    }

    function openModal() {
        state.modal.removeAttribute('hidden');
        document.body.style.overflow = 'hidden';
        state.ctx.clearRect(0, 0, state.canvas.width, state.canvas.height);
        state.ctx.fillStyle = '#fff';
        state.ctx.fillText("Loading MIDI data...", 10, 20);
        state.infoSpan.textContent = "Laden...";
    }

    function closeModal() {
        if (playbackManager) playbackManager.stopPlayback();
        isPlayingPreview = false;
        const btn = document.querySelector('.js-pr-play');
        if (btn) btn.classList.remove('playing');

        // Close full MIDI preview if open
        if (typeof window.closeMidiFilePreview === 'function') {
            window.closeMidiFilePreview();
        }

        state.modal.setAttribute('hidden', '');
        document.body.style.overflow = '';
        state.midiData = null;
    }

    async function loadMidiAndDraw() {
        const url = `/documents/${state.documentId}/assets/${state.assetId}/download`;

        if (!window.MidiPianoRollRenderer) {
            state.infoSpan.textContent = "Piano roll renderer niet geladen.";
            return;
        }

        try {
            state.midiData = await window.MidiPianoRollRenderer.loadMidi(url);
            calibrateCanvas();
            draw();
            updateInfoSpan();
        } catch (e) {
            state.infoSpan.textContent = "Fout bij laden MIDI: " + e.message;
            console.error(e);
        }
    }

    function calibrateCanvas() {
        if (!state.midiData || !window.MidiPianoRollRenderer) return;

        const pitches = window.MidiPianoRollRenderer.calibrate(
            state.canvas,
            state.midiData,
            state.beatsPerBar,
            state.pixelsPerQuarter
        );
        state.minPitch = pitches.minPitch;
        state.maxPitch = pitches.maxPitch;
    }

    function draw() {
        if (!state.midiData || !state.ctx || !window.MidiPianoRollRenderer) return;

        window.MidiPianoRollRenderer.draw(state.canvas, state.ctx, state.midiData, {
            beatsPerBar: state.beatsPerBar,
            pixelsPerQuarter: state.pixelsPerQuarter,
            pitchHeight: state.pitchHeight,
            minPitch: state.minPitch,
            maxPitch: state.maxPitch,
            selectionStartBeat: state.selectionStartBeat,
            selectionEndBeat: state.selectionEndBeat,
            loopIndex: state.loopIndex
        });
    }

    /**
     * Export selected loop from MIDI file
     */
    function exportLoopFromMidi(offset, length) {
        const modal = document.getElementById('piano-roll-modal');
        const midiUrl = modal.dataset.previewUrl;
        const fileName = modal.dataset.previewFileName;
        const bpm = parseInt(modal.dataset.previewBpm, 10) || 120;
        const timeSig = modal.dataset.previewTimeSig || '4/4';

        console.log('Export loop from MIDI:', {
            file: fileName,
            offset: offset,
            length: length,
            bpm: bpm,
            timeSig: timeSig
        });

        // TODO: Implementeer MIDI export logica
        // For now: show confirmation
        const startBar = (offset / state.beatsPerBar) + 1;
        const lengthBars = length / state.beatsPerBar;
        alert(`Geselecteerde loop:\n${fileName}\nMaat ${startBar} - ${lengthBars} maten\n\n(Export functie in development)`);

        closeModal();
    }

    function xToBeat(x) {
        return x / state.pixelsPerQuarter;
    }
    
    function snapToGrid(beat) {
        // Snap per kwartnoot
        return Math.round(beat);
    }

    function adjustLoopLength(delta) {
        if (state.selectionStartBeat === null) return;

        const currentLength = Math.abs(state.selectionEndBeat - state.selectionStartBeat);
        const newLength = Math.max(1, currentLength + delta);

        state.selectionEndBeat = state.selectionStartBeat + newLength;

        const lengthInput = document.querySelector('.js-pr-length-input');
        if (lengthInput) {
            lengthInput.value = newLength;
        }

        draw();
        updateInfoSpan();
    }

    function onLengthInputChange(e) {
        if (state.selectionStartBeat === null) return;

        const newLength = Math.max(1, parseInt(e.target.value, 10) || 1);
        state.selectionEndBeat = state.selectionStartBeat + newLength;

        draw();
        updateInfoSpan();
    }

    // Mouse interactions
    function onMouseDown(e) {
        if (!state.midiData) return;
        if (e.button !== 0) return; // only left click
        
        const rect = state.canvas.getBoundingClientRect();
        const x = e.clientX - rect.left;
        let beat = xToBeat(x);
        
        state.isDragging = true;
        
        // Check of we in de bestaande selectie klikken
        const minB = Math.min(state.selectionStartBeat ?? -1, state.selectionEndBeat ?? -1);
        const maxB = Math.max(state.selectionStartBeat ?? -1, state.selectionEndBeat ?? -1);
        
        if (state.selectionStartBeat !== null && state.selectionEndBeat !== null && beat >= minB && beat <= maxB && maxB > minB) {
            // We verplaatsen de hele loop
            state.dragMode = 'move';
            state.dragStartBeat = beat;
            state.dragMoveLength = maxB - minB;
            state.dragMoveOffset = beat - minB; // afstand van muis tot linker rand van de loop
            state.canvas.style.cursor = 'grabbing';
        } else {
            // Nieuwe selectie maken
            state.dragMode = 'create';
            beat = snapToGrid(beat);
            state.dragStartBeat = beat;
            state.selectionStartBeat = beat;
            state.selectionEndBeat = beat; // initially zero length until drag
            state.canvas.style.cursor = 'crosshair';
        }
        
        draw();
        updateInfoSpan();
    }
    
    function onMouseMove(e) {
        const rect = state.canvas.getBoundingClientRect();
        let x = e.clientX - rect.left;
        let beat = xToBeat(x);
        
        if (!state.isDragging) {
            // Hover states (verander muis icoon als we over de selectie zweven)
            if (state.selectionStartBeat !== null && state.selectionEndBeat !== null) {
                const minB = Math.min(state.selectionStartBeat, state.selectionEndBeat);
                const maxB = Math.max(state.selectionStartBeat, state.selectionEndBeat);
                if (beat >= minB && beat <= maxB && maxB > minB) {
                    state.canvas.style.cursor = 'grab';
                } else {
                    state.canvas.style.cursor = 'crosshair';
                }
            } else {
                state.canvas.style.cursor = 'crosshair';
            }
            return;
        }
        
        // limieten tijden verplaatsing
        if (x < 0) x = 0;
        if (x > state.canvas.width) x = state.canvas.width;
        beat = xToBeat(x);
        
        if (state.dragMode === 'move') {
            // Verplaats de hele selectie met behoud van lengte
            let newStart = snapToGrid(beat - state.dragMoveOffset);
            if (newStart < 0) newStart = 0;
            
            state.selectionStartBeat = newStart;
            state.selectionEndBeat = newStart + state.dragMoveLength;
        } else {
            // Creëer nieuwe selectie
            beat = Math.max(0, snapToGrid(beat));
            state.selectionEndBeat = beat;
        }

        draw();
        updateInfoSpan();
    }
    
    function onMouseUp(e) {
        if (!state.isDragging) return;
        state.isDragging = false;
        
        if (state.selectionStartBeat !== null && state.selectionEndBeat !== null) {
            // Zorg dat start altijd kleiner is dan end
            if (state.selectionStartBeat > state.selectionEndBeat) {
                let tmp = state.selectionStartBeat;
                state.selectionStartBeat = state.selectionEndBeat;
                state.selectionEndBeat = tmp;
            }
            
            // Reset cursor
            const minB = Math.min(state.selectionStartBeat, state.selectionEndBeat);
            const maxB = Math.max(state.selectionStartBeat, state.selectionEndBeat);
            const rect = state.canvas.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const beat = xToBeat(x);
            
            if (beat >= minB && beat <= maxB && maxB > minB) {
                state.canvas.style.cursor = 'grab';
            } else {
                state.canvas.style.cursor = 'crosshair';
            }
        }
        
        draw();
        updateInfoSpan();
    }

    function updateInfoSpan() {
        if (state.selectionStartBeat === null || state.selectionEndBeat === null) {
            state.infoSpan.textContent = "Selecteer een gebied op de tijdlijn...";
            return;
        }

        const length = Math.abs(state.selectionEndBeat - state.selectionStartBeat);
        const bars = (length / state.beatsPerBar).toFixed(1);

        state.infoSpan.textContent = `Offset: ${state.selectionStartBeat} kwartnoten | Lengte: ${length} kwartnoten (${bars} maten)`;

        // Update stepper input
        const lengthInput = document.querySelector('.js-pr-length-input');
        if (lengthInput) {
            lengthInput.value = length;
        }
    }

    function saveSelection() {
        const modal = document.getElementById('piano-roll-modal');
        const isFullMidiPreview = modal && modal.dataset.isFullMidiPreview === 'true';

        const length = Math.abs(state.selectionEndBeat - state.selectionStartBeat);
        if (length <= 0) {
            alert('Selecteer een gebied breder dan 0.');
            return;
        }

        const offset = Math.min(state.selectionStartBeat, state.selectionEndBeat);

        // ========== MIDI File Preview: Export ==========
        if (isFullMidiPreview) {
            exportLoopFromMidi(offset, length);
            return;
        }

        // ========== Loop Editor: Save ==========
        if (!state.editorEl) return;
        
        // Update het formulier
        const inputId = state.editorEl.dataset.inputId;
        const hiddenInput = document.getElementById(inputId);
        if (hiddenInput) {
            let current = [];
            if (hiddenInput.value) {
                try {
                    current = JSON.parse(hiddenInput.value);
                } catch (e) {
                    console.error('saveSelection parse error', e);
                }
            }
            
            // Controleer of de JSON echt objecten heeft. 
            // Als niet (oud formaat in dom?), converteren we ze eerst
            if (current.length > 0 && typeof current[0] === 'number') {
                let objs = [];
                let currOffset = 0;
                current.forEach(b => {
                    let q = b * state.beatsPerBar;
                    objs.push({ offset: currOffset, length: q });
                    currOffset += q;
                });
                current = objs;
            }
            
            // Zorg dat we lang genoeg zijn (als gebruiker Loop B (index 1) opent maar A niet bestond)
            while (current.length <= state.loopIndex) {
                let lastObj = current[current.length - 1] || { offset: 0, length: 4 * state.beatsPerBar };
                current.push({ offset: lastObj.offset + lastObj.length, length: 4 * state.beatsPerBar });
            }
            
            // Overschrijf
            current[state.loopIndex] = {
                offset: offset,
                length: length
            };
            
            hiddenInput.value = JSON.stringify(current);
            console.log('Saved loop segments:', current);
            
            // Trigger een custom event zodat the loop chips kunnen verversen
            // (De loopLengthEditor code pikt dit eventueel niet op als we niet verversen,
            // we kunnen een "change" event firen op hiddenInput of direct reloaden)
            const event = new Event('change', { bubbles: true });
            hiddenInput.dispatchEvent(event);
            
            // Maar hidden field heeft geen directe "change" listener in loopLengthEditor die de html opnieuw tekent, 
            // loopLengthEditor baseert zich op 'let current = parseValueFromHidden()'. We kunnen de update force triggering.
            // Voor nu is er een tijdelijke oplossing: force refresh the loops grid track
            if (typeof window.refreshLoopsGridForTrack === 'function') {
                const card = state.editorEl.closest('.track-card');
                if (card) window.refreshLoopsGridForTrack(card);
            }
            
            // Her-teken de chips in de UI
            // Aangezien de logic geprivatiseerd is in loopLengthEditor,
            // kunnen we een "loopsChanged" evt sturen als we dat ondersteunen, of
            // gewoon de parent updaten. We zagen `updateLoopsFromBaseChange()` maar is in closure.
            // Een reload van het DOM deeltje is best:
        }
        
        closeModal();
    }

    // --- Playback integration ---
    let playbackManager = null;
    let isPlayingPreview = false;

    function onPlayBtnMouseDown(e) {
        const modal = document.getElementById('piano-roll-modal');
        const isFullMidiPreview = modal && modal.dataset.isFullMidiPreview === 'true';

        if (!playbackManager && window.MidiLoopPlayback) {
            playbackManager = new window.MidiLoopPlayback();
        }

        if (!playbackManager) {
            console.warn('MidiLoopPlayback not available');
            return;
        }

        // Stop globals
        if (typeof window.stopAllLoopPlayback === 'function') {
            window.stopAllLoopPlayback();
        }
        playbackManager.stopPlayback();

        const btn = document.querySelector('.js-pr-play');
        if (btn) btn.classList.add('playing');
        isPlayingPreview = true;

        // ========== Full MIDI preview mode ==========
        if (isFullMidiPreview) {
            const midiUrl = modal.dataset.previewUrl;
            const bpm = parseFloat(modal.dataset.previewBpm) || 120;
            const timeSignature = modal.dataset.previewTimeSig || '4/4';

            // Play the SELECTED loop segment (not the whole MIDI)
            const startBeat = Math.min(state.selectionStartBeat, state.selectionEndBeat);
            const length = Math.abs(state.selectionEndBeat - state.selectionStartBeat);

            if (length <= 0) return;

            const loopLengths = [
                { offset: startBeat, length: length }
            ];

            playbackManager
                .playLoopSegment(midiUrl, 0, loopLengths, bpm, timeSignature, null, 0)
                .catch(error => {
                    console.error('MIDI preview error:', error);
                    onPlayBtnMouseUp();
                });
            return;
        }

        // ========== Loop editor mode ==========
        if (!state.editorEl || !state.documentId || !state.assetId) return;

        const bpm = parseFloat(state.editorEl.dataset.bpm) || 120;
        const timeSignature = state.editorEl.dataset.timesig || '4/4';
        const presetId = state.editorEl.dataset.tonePreset;

        let volumeDb = 0;
        const trackCard = state.editorEl.closest('.track-card');
        if (trackCard) {
            const volumeSlider = trackCard.querySelector('.js-track-volume-input');
            if (volumeSlider) {
                volumeDb = parseFloat(volumeSlider.value) || 0;
            }
        }

        const midiUrl = `/documents/${state.documentId}/assets/${state.assetId}/download`;

        // Pak start offset en speellengte direct uit de huidige ongeopgeslagen muis/canvas selectie
        const startBeat = Math.min(state.selectionStartBeat, state.selectionEndBeat);
        const length = Math.abs(state.selectionEndBeat - state.selectionStartBeat);

        if (length <= 0) return;

        const mockLoopLengths = [
            { offset: startBeat, length: length }
        ];

        playbackManager
            .playLoopSegment(midiUrl, 0, mockLoopLengths, bpm, timeSignature, presetId, volumeDb)
            .catch(error => {
                console.error('Piano roll preview error:', error);
                onPlayBtnMouseUp();
            });
    }

    function onPlayBtnMouseUp(e) {
        if (!isPlayingPreview) return;
        if (playbackManager) playbackManager.stopPlayback();
        const btn = document.querySelector('.js-pr-play');
        if (btn) btn.classList.remove('playing');
        isPlayingPreview = false;
    }

    /**
     * Initialize preview mode state (for MIDI file preview)
     * @param {object} previewState - { startBeat, endBeat, beatsPerBar, minPitch, maxPitch, midiData }
     */
    window.initLoopPreviewState = function(previewState) {
        state.selectionStartBeat = previewState.startBeat;
        state.selectionEndBeat = previewState.endBeat;
        state.beatsPerBar = previewState.beatsPerBar;
        state.minPitch = previewState.minPitch;
        state.maxPitch = previewState.maxPitch;
        state.midiData = previewState.midiData;
        state.loopIndex = 0;
        draw();
        updateInfoSpan();
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
