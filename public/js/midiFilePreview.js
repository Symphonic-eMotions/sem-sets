/**
 * MIDI File Preview Controller
 *
 * Opens the shared piano-roll-modal for MIDI file preview.
 * Reuses loopPianoRoll.js infrastructure with fullMidiPreview flag.
 */

(function() {
    'use strict';

    function init() {
        // Delegated listener voor Preview knoppen in MIDI bestanden section
        document.addEventListener('click', onPreviewClick);
    }

    /**
     * Preview button in MIDI bestanden clicked
     */
    function onPreviewClick(e) {
        const btn = e.target.closest('[data-preview-id]');
        if (!btn || btn.onclick) {
            // Skip: this is handled by toggleMidiPreview in template
            return;
        }
    }

    /**
     * Public function: open piano roll modal for MIDI file preview
     * Called by toggleMidiPreview in template
     */
    window.openMidiFilePreview = function(midiUrl, fileName) {
        if (!window.MidiPianoRollRenderer || !window.MidiLoopPlayback) {
            console.warn('Piano roll preview not available');
            return;
        }

        const modal = document.getElementById('piano-roll-modal');
        if (!modal) {
            console.warn('Piano roll modal not found');
            return;
        }

        // Setup modal for MIDI file preview (fullMidiPreview mode)
        setupPreviewModal(midiUrl, fileName);
        modal.removeAttribute('hidden');
        document.body.style.overflow = 'hidden';
    };

    /**
     * Setup modal state for MIDI file preview
     * Toon DEZELFDE interface als loop editor - met selectie + stepper
     */
    async function setupPreviewModal(midiUrl, fileName) {
        const modal = document.getElementById('piano-roll-modal');
        const canvas = document.getElementById('piano-roll-canvas');
        const ctx = canvas.getContext('2d');
        const infoSpan = document.getElementById('pr-selection-info');
        const modalTitle = modal.querySelector('.modal-header h3');
        const saveBtn = modal.querySelector('.js-pr-save');

        // Update title
        if (modalTitle) {
            modalTitle.textContent = `MIDI Preview: ${fileName}`;
        }

        // Change save button text
        if (saveBtn) {
            saveBtn.textContent = 'Exporteren';
            saveBtn.dataset.exportMode = 'true';
        }

        try {
            const midiData = await window.MidiPianoRollRenderer.loadMidi(midiUrl);

            // Bepaal beatsPerBar uit MIDI header
            const timeSig = midiData.header.timeSignatures[0];
            const beatsPerBar = timeSig ? timeSig.timeSignature[0] : 4;

            // Kalibreer canvas
            const pitches = window.MidiPianoRollRenderer.calibrate(canvas, midiData, beatsPerBar);

            // Bereken totale duur
            const ppq = midiData.header.ppq;
            let endTicks = 0;
            midiData.tracks.forEach(track => {
                track.notes.forEach(note => {
                    const noteEnd = note.ticks + note.durationTicks;
                    if (noteEnd > endTicks) endTicks = noteEnd;
                });
            });
            const totalBeats = Math.round(endTicks / ppq);

            // Teken canvas MET selectie overlay (default: hele MIDI)
            const state = {
                beatsPerBar: beatsPerBar,
                pixelsPerQuarter: 40,
                pitchHeight: 4,
                minPitch: pitches.minPitch,
                maxPitch: pitches.maxPitch,
                selectionStartBeat: 0,         // Start at beginning
                selectionEndBeat: totalBeats,  // Whole MIDI selected by default
                loopIndex: 0                   // Label as "Loop 1"
            };

            window.MidiPianoRollRenderer.draw(canvas, ctx, midiData, state);

            // Store in modal
            const tempos = midiData.header.tempos;
            const bpm = tempos && tempos[0] ? tempos[0].bpm : 120;
            const timeSigStr = timeSig
                ? `${timeSig.timeSignature[0]}/${timeSig.timeSignature[1]}`
                : '4/4';

            modal.dataset.previewUrl = midiUrl;
            modal.dataset.previewBpm = bpm;
            modal.dataset.previewTimeSig = timeSigStr;
            modal.dataset.previewTotalBeats = totalBeats;
            modal.dataset.isFullMidiPreview = 'true';
            modal.dataset.previewFileName = fileName;

            infoSpan.textContent = `Totaal: ${totalBeats} tellen • ${bpm} BPM • ${timeSigStr}`;

        } catch (error) {
            console.error('Error loading MIDI preview:', error);
            infoSpan.textContent = 'Fout bij laden MIDI: ' + error.message;
        }
    }

    /**
     * Close preview modal (restore normal state)
     */
    window.closeMidiFilePreview = function() {
        const modal = document.getElementById('piano-roll-modal');
        const saveBtn = modal.querySelector('.js-pr-save');

        // Stop any playback
        const playBtn = modal.querySelector('.js-pr-play');
        if (playBtn && playBtn.classList.contains('playing')) {
            playBtn.click(); // Trigger mouseup to stop
        }

        // Restore save button
        if (saveBtn) {
            saveBtn.textContent = 'Opslaan';
            delete saveBtn.dataset.exportMode;
        }

        modal.removeAttribute('data-previewUrl');
        modal.removeAttribute('data-previewBpm');
        modal.removeAttribute('data-previewTimeSig');
        modal.removeAttribute('data-previewTotalBeats');
        modal.removeAttribute('data-isFullMidiPreview');
        modal.removeAttribute('data-previewFileName');

        modal.setAttribute('hidden', '');
        document.body.style.overflow = '';
    };

    // Initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
