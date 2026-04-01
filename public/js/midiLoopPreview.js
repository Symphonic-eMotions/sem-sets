/**
 * MIDI Loop Preview UI Controller
 *
 * Manages the hold-to-play interaction for loop preview buttons.
 * Handles UI state, event listeners, and visual feedback.
 *
 * Usage:
 *   initLoopPreviews(editorElement);
 */

(function() {
    'use strict';

    // Global singleton playback manager
    let playbackManager = null;
    let activePlayBtn = null;

    /**
     * Initialize loop preview buttons for a track editor
     * @param {HTMLElement} editorElement - The loop-editor div
     */
    function initLoopPreviews(editorElement) {
        if (!editorElement) {
            console.warn('Loop editor element not found');
            return;
        }

        // Initialize playback manager if needed
        if (!playbackManager && window.MidiLoopPlayback) {
            playbackManager = new window.MidiLoopPlayback();
        }

        if (!playbackManager) {
            console.warn('MidiLoopPlayback not available');
            return;
        }

        // Find all play buttons in this editor
        const playBtns = editorElement.querySelectorAll('.loop-play-btn');

        playBtns.forEach(btn => {
            // Mousedown: start playback
            btn.addEventListener('mousedown', (e) => {
                e.preventDefault();
                e.stopPropagation();
                onPlayBtnMouseDown(e, btn, editorElement);
            });

            // Mouseup: stop playback
            btn.addEventListener('mouseup', (e) => {
                e.preventDefault();
                e.stopPropagation();
                onPlayBtnMouseUp(e);
            });

            // Mouseleave: stop if user moves away while holding
            btn.addEventListener('mouseleave', (e) => {
                if (activePlayBtn === btn) {
                    onPlayBtnMouseUp(e);
                }
            });

            // Touch support for mobile (optional)
            btn.addEventListener('touchstart', (e) => {
                e.preventDefault();
                onPlayBtnMouseDown(e, btn, editorElement);
            });

            btn.addEventListener('touchend', (e) => {
                e.preventDefault();
                onPlayBtnMouseUp(e);
            });
        });

        console.log(`Initialized ${playBtns.length} loop preview buttons`);
    }

    /**
     * Handle mousedown on play button - start playback
     * @private
     */
    function onPlayBtnMouseDown(e, btn, editorElement) {
        // Only proceed if we have a valid MIDI asset
        const assetId = editorElement.dataset.midiAssetId;
        const documentId = editorElement.dataset.documentId;
        const bpm = parseFloat(editorElement.dataset.bpm);
        const presetId = editorElement.dataset.tonePreset;

        if (!assetId || !documentId || !bpm) {
            console.warn('Missing MIDI asset info for playback');
            return;
        }

        // Stop any currently active playback
        if (activePlayBtn && activePlayBtn !== btn) {
            stopPlayback(activePlayBtn);
        }

        // Get loop configuration
        const loopIndex = parseInt(btn.dataset.loopIndex, 10);
        const loopValues = parseLoopValues(editorElement);
        const timeSignature = editorElement.dataset.timesig || '4/4';

        if (!loopValues || loopValues.length === 0) {
            console.warn('No loop values found');
            return;
        }

        // Build MIDI URL
        const midiUrl = `/documents/${documentId}/assets/${assetId}/download`;

        // Visual feedback: add playing state
        btn.classList.add('playing');
        activePlayBtn = btn;

        // Start playback
        playbackManager
            .playLoopSegment(midiUrl, loopIndex, loopValues, bpm, timeSignature, presetId)
            .catch(error => {
                console.error('Playback error:', error);
                stopPlayback(btn);
            });
    }

    /**
     * Handle mouseup on play button - stop playback
     * @private
     */
    function onPlayBtnMouseUp(e) {
        if (activePlayBtn) {
            stopPlayback(activePlayBtn);
        }
    }

    /**
     * Stop playback and remove visual feedback
     * @private
     */
    function stopPlayback(btn) {
        if (playbackManager) {
            playbackManager.stopPlayback();
        }

        btn.classList.remove('playing');

        if (activePlayBtn === btn) {
            activePlayBtn = null;
        }
    }

    /**
     * Parse loop length values from the hidden input
     * Handles multiple formats: JSON "[32,32]", CSV "32,32", or array
     *
     * @param {HTMLElement} editorElement - The loop-editor div
     * @returns {number[]} Array of bar counts for each loop
     * @private
     */
    function parseLoopValues(editorElement) {
        // Get input ID from data attribute
        const inputId = editorElement.dataset.inputId;
        if (!inputId) {
            console.warn('data-input-id not found on loop editor');
            return [];
        }

        const hiddenInput = document.getElementById(inputId);
        if (!hiddenInput) {
            console.warn('Loop length input not found with id:', inputId);
            return [];
        }

        const raw = hiddenInput.value;

        if (!raw) {
            return [];
        }

        let values = [];

        // Try JSON format first: "[32,32]"
        if (raw.startsWith('[')) {
            try {
                values = JSON.parse(raw);
            } catch (e) {
                console.warn('Failed to parse loop JSON:', e);
                return [];
            }
        }
        // Try CSV format: "32,32"
        else if (raw.includes(',')) {
            values = raw
                .split(',')
                .map(v => parseInt(v.trim(), 10))
                .filter(v => !isNaN(v) && v > 0);
        }
        // Single value: "64"
        else {
            const num = parseInt(raw, 10);
            if (!isNaN(num) && num > 0) {
                values = [num];
            }
        }

        return values;
    }

    /**
     * Stop all active playback (cleanup)
     * @private
     */
    function stopAllPlayback() {
        if (playbackManager) {
            playbackManager.stopPlayback();
        }
        if (activePlayBtn) {
            activePlayBtn.classList.remove('playing');
            activePlayBtn = null;
        }
    }

    /**
     * Cleanup on page unload
     * @private
     */
    function setupCleanup() {
        window.addEventListener('beforeunload', () => {
            stopAllPlayback();
            if (playbackManager) {
                playbackManager.dispose();
            }
        });
    }

    /**
     * Listen for loop changes (loop added/removed) and update button states
     * @private
     */
    function setupLoopChangeListeners() {
        document.addEventListener('loopsChanged', (e) => {
            // When loops change, stop current playback
            stopAllPlayback();

            // The loop structure has changed, buttons will be re-rendered
            // We could optionally reinitialize here if needed
        });

        // Listen for tone preset changes to update data attributes in real-time
        document.addEventListener('change', (e) => {
            const el = e.target;
            // Check if this is a tonePreset select field
            if (el instanceof HTMLSelectElement && el.name && el.name.includes('[tonePreset]')) {
                const trackCard = el.closest('.track-card');
                if (trackCard) {
                    const loopEditor = trackCard.querySelector('.js-loop-editor');
                    if (loopEditor) {
                        loopEditor.dataset.tonePreset = el.value;
                        console.log(`Updated tone preset for track: ${el.value}`);
                        
                        // If currently playing, stop it to force synth rebuild on next play
                        stopAllPlayback();
                    }
                }
            }
        });
    }

    // Initialization on document ready
    function init() {
        // Find all loop editors and initialize them
        const loopEditors = document.querySelectorAll('.loop-editor.js-loop-editor');

        loopEditors.forEach(editor => {
            initLoopPreviews(editor);
        });

        // Setup global event listeners
        setupCleanup();
        setupLoopChangeListeners();

        console.log(`Loop preview initialized for ${loopEditors.length} tracks`);
    }

    // Run on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        // DOM already loaded
        init();
    }

    // Export for manual initialization
    window.initLoopPreviews = initLoopPreviews;
    window.stopAllLoopPlayback = stopAllPlayback;
})();
