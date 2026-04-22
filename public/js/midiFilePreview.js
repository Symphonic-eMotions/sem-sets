/**
 * MIDI File Preview Controller
 *
 * Beheert canvas-based MIDI previews in de "MIDI bestanden" sectie.
 * Hergebruikt MidiPianoRollRenderer voor tekenen en MidiLoopPlayback voor audio.
 */

(function() {
    'use strict';

    // Globals
    let playbackManager = null;
    let activePreviews = {};  // { previewId: {canvas, ctx, midiData, state} }

    function init() {
        if (!window.MidiPianoRollRenderer || !window.MidiLoopPlayback) {
            console.warn('MIDI file preview: required libraries not available');
            return;
        }

        // Delegated listener voor Play knoppen
        document.addEventListener('click', onPlayClick);
        document.addEventListener('click', onStopClick);

        // Listen voor custom event bij toggle
        document.addEventListener('midiPreviewOpened', onPreviewOpened);
    }

    /**
     * Luister naar midiPreviewOpened event (fired door toggleMidiPreview)
     */
    function onPreviewOpened(e) {
        const panel = e.target.closest('.chip-preview');
        if (!panel) return;

        const previewId = panel.id;
        if (!previewId || activePreviews[previewId]) {
            return; // Al geinitialiseerd of geen ID
        }

        initializePreview(previewId);
    }

    /**
     * Laad MIDI en teken canvas voor eerste keer
     */
    async function initializePreview(previewId) {
        const panel = document.getElementById(previewId);
        if (!panel) return;

        const midiUrl = panel.dataset.midiUrl;
        if (!midiUrl) {
            console.warn('MIDI file preview: no midiUrl data attribute');
            return;
        }

        const canvas = panel.querySelector('.js-file-preview-canvas');
        if (!canvas) {
            console.warn('MIDI file preview: no canvas found');
            return;
        }

        try {
            const ctx = canvas.getContext('2d');
            const midiData = await window.MidiPianoRollRenderer.loadMidi(midiUrl);

            // Bepaal beatsPerBar uit MIDI header
            const timeSig = midiData.header.timeSignatures[0];
            const beatsPerBar = timeSig ? timeSig.timeSignature[0] : 4;

            // Kalibreer en teken
            window.MidiPianoRollRenderer.calibrate(canvas, midiData, beatsPerBar);

            const state = {
                beatsPerBar: beatsPerBar,
                pixelsPerQuarter: 40,
                pitchHeight: 4,
                selectionStartBeat: null,
                selectionEndBeat: null,
                loopIndex: null
            };

            window.MidiPianoRollRenderer.draw(canvas, ctx, midiData, state);

            // Sla preview state op
            activePreviews[previewId] = {
                canvas: canvas,
                ctx: ctx,
                midiData: midiData,
                midiUrl: midiUrl,
                state: state,
                beatsPerBar: beatsPerBar
            };

            // Enable play button
            const playBtn = panel.querySelector('.js-file-preview-play');
            if (playBtn) {
                playBtn.disabled = false;
            }

        } catch (error) {
            console.error('Error loading MIDI preview:', error);
        }
    }

    /**
     * Play-knop geklikt
     */
    function onPlayClick(e) {
        const btn = e.target.closest('.js-file-preview-play');
        if (!btn) return;

        e.preventDefault();
        e.stopPropagation();

        const panel = btn.closest('.chip-preview');
        if (!panel) return;

        const previewId = panel.id;
        const preview = activePreviews[previewId];

        if (!preview) {
            console.warn('Preview not initialized');
            return;
        }

        startPlayback(previewId);
    }

    /**
     * Stop-knop geklikt
     */
    function onStopClick(e) {
        const btn = e.target.closest('.js-file-preview-stop');
        if (!btn) return;

        e.preventDefault();
        e.stopPropagation();

        const panel = btn.closest('.chip-preview');
        if (!panel) return;

        stopPlayback(panel.id);
    }

    /**
     * Start afspelen van volledige MIDI
     */
    async function startPlayback(previewId) {
        const preview = activePreviews[previewId];
        if (!preview || !preview.midiData) {
            return;
        }

        // Maak playback manager als nog niet gemaakt
        if (!playbackManager) {
            playbackManager = new window.MidiLoopPlayback();
        }

        // Stop any existing playback
        if (window.stopAllLoopPlayback) {
            window.stopAllLoopPlayback();
        }
        playbackManager.stopPlayback();

        const panel = document.getElementById(previewId);
        const playBtn = panel.querySelector('.js-file-preview-play');
        const stopBtn = panel.querySelector('.js-file-preview-stop');

        playBtn.disabled = true;
        stopBtn.disabled = false;

        try {
            const midiData = preview.midiData;
            const ppq = midiData.header.ppq;
            let endTicks = 0;

            // Bereken totale duur
            midiData.tracks.forEach(track => {
                track.notes.forEach(note => {
                    const noteEnd = note.ticks + note.durationTicks;
                    if (noteEnd > endTicks) endTicks = noteEnd;
                });
            });

            const totalBeats = endTicks / ppq;

            // BPM en timesig uit header
            const tempos = midiData.header.tempos;
            const bpm = tempos && tempos[0] ? tempos[0].bpm : 120;

            const timeSigData = midiData.header.timeSignatures[0];
            const timeSig = timeSigData
                ? `${timeSigData.timeSignature[0]}/${timeSigData.timeSignature[1]}`
                : '4/4';

            // Speel volledig MIDI af als één loop
            await playbackManager.playLoopSegment(
                preview.midiUrl,
                0,
                [{ offset: 0, length: Math.round(totalBeats) }],
                bpm,
                timeSig,
                null,  // geen tone preset
                0      // default volume
            );

            // Bij klaar: enable buttons opnieuw
            playBtn.disabled = false;
            stopBtn.disabled = true;

        } catch (error) {
            console.error('Playback error:', error);
            playBtn.disabled = false;
            stopBtn.disabled = true;
        }
    }

    /**
     * Stop playback
     */
    function stopPlayback(previewId) {
        if (playbackManager) {
            playbackManager.stopPlayback();
        }

        const panel = document.getElementById(previewId);
        if (!panel) return;

        const playBtn = panel.querySelector('.js-file-preview-play');
        const stopBtn = panel.querySelector('.js-file-preview-stop');

        if (playBtn) playBtn.disabled = false;
        if (stopBtn) stopBtn.disabled = true;
    }

    /**
     * Cleanup on page unload
     */
    function setupCleanup() {
        window.addEventListener('beforeunload', () => {
            if (playbackManager) {
                playbackManager.stopPlayback();
                playbackManager.dispose();
            }
        });
    }

    // Initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    setupCleanup();
})();
