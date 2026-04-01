/**
 * MIDI Loop Playback Engine
 *
 * Handles Tone.js MIDI parsing, synth playback, and loop management.
 * Plays specific loop segments from MIDI files on repeat.
 *
 * Usage:
 *   const playback = new MidiLoopPlayback();
 *   await playback.playLoopSegment(midiUrl, loopIndex, loopLengths, bpm, timeSignature);
 *   playback.stopPlayback();
 */

class MidiLoopPlayback {
    constructor() {
        this.synth = null;
        this.currentPresetId = null;
        this.currentPlayback = null;
        this.cachedMidiFiles = new Map(); // Cache parsed MIDI files
        this.volNode = null; // Tone.Volume node for track volume control

        // Define available Tone.js presets
        this.presets = {
            'soft-pad': {
                engineType: 'PolySynth',
                voiceType: Tone.Synth,
                options: {
                    oscillator: { type: 'fatsawtooth', count: 3, spread: 30 },
                    envelope: { attack: 0.5, decay: 0.5, sustain: 1, release: 2 }
                }
            },
            'plucky-keys': {
                engineType: 'PolySynth',
                voiceType: Tone.Synth,
                options: {
                    oscillator: { type: 'triangle' },
                    envelope: { attack: 0.005, decay: 0.2, sustain: 0, release: 0.2 }
                }
            },
            'warm-analog': {
                engineType: 'PolySynth',
                voiceType: Tone.AMSynth,
                options: {
                    harmonicity: 2.5,
                    oscillator: { type: 'fatsawtooth' },
                    envelope: { attack: 0.1, decay: 0.2, sustain: 0.5, release: 0.8 },
                    modulation: { type: 'square' }
                }
            },
            'hollow-organ': {
                engineType: 'PolySynth',
                voiceType: Tone.FMSynth,
                options: {
                    harmonicity: 3,
                    modulationIndex: 10,
                    oscillator: { type: 'sine' },
                    envelope: { attack: 0.01, decay: 0, sustain: 1, release: 0.2 },
                    modulation: { type: 'triangle' }
                }
            },
            'percussive-bell': {
                engineType: 'PolySynth',
                voiceType: Tone.FMSynth,
                options: {
                    harmonicity: 5,
                    modulationIndex: 20,
                    oscillator: { type: 'sine' },
                    envelope: { attack: 0.001, decay: 1, sustain: 0, release: 1 },
                    modulation: { type: 'square' }
                }
            },
            'bass-mono': {
                engineType: 'PolySynth',
                voiceType: Tone.MonoSynth,
                options: {
                    oscillator: { type: 'square' },
                    envelope: { attack: 0.05, decay: 0.3, sustain: 0.4, release: 0.1 },
                    filterEnvelope: { attack: 0.01, decay: 0.1, sustain: 0.2, release: 0.2, baseFrequency: 200, octaves: 4 }
                }
            },
            'airy-lead': {
                engineType: 'PolySynth',
                voiceType: Tone.AMSynth,
                options: {
                    harmonicity: 1.5,
                    oscillator: { type: 'sine' },
                    envelope: { attack: 0.2, decay: 0.2, sustain: 0.8, release: 1 },
                    modulation: { type: 'sawtooth' }
                }
            },
            'noisy-texture': {
                engineType: 'PolySynth',
                voiceType: Tone.FMSynth,
                options: {
                    harmonicity: 10,
                    modulationIndex: 50,
                    oscillator: { type: 'sine' },
                    envelope: { attack: 1, decay: 2, sustain: 0.5, release: 3 },
                    modulation: { type: 'sawtooth' }
                }
            }
        };

        // Default synth (fallback)
        this.defaultPresetId = 'plucky-keys';
    }

    /**
     * Get or create a synth for the given preset
     * @param {string} presetId
     * @param {number} volumeDb - Initial volume in decibels (-90 to +12)
     * @returns {Tone.PolySynth}
     * @private
     */
    getOrCreateSynth(presetId, volumeDb = 0) {
        const id = presetId && this.presets[presetId] ? presetId : this.defaultPresetId;

        // Create/update volume node first
        if (!this.volNode) {
            this.volNode = new Tone.Volume(volumeDb).toDestination();
        } else {
            this.volNode.volume.value = volumeDb;
        }

        // If synth already exists for this preset, return it
        if (this.synth && this.currentPresetId === id) {
            return this.synth;
        }

        // Dispose old synth if it exists
        if (this.synth) {
            this.synth.dispose();
        }

        const config = this.presets[id];
        console.log(`Building synth for preset: ${id} with volume: ${volumeDb}dB`);

        // Signal chain: synth → volNode → Destination
        this.synth = new Tone.PolySynth(config.voiceType, config.options).connect(this.volNode);
        this.currentPresetId = id;

        return this.synth;
    }

    /**
     * Set the track volume in decibels
     * @param {number} db - Volume in decibels (-90 to +12)
     */
    setVolume(db) {
        if (this.volNode) {
            this.volNode.volume.value = db;
        }
    }

    /**
     * Ensure AudioContext is started (required by modern browsers)
     * @private
     */
    async ensureAudioContextStarted() {
        if (Tone.getContext().state !== 'running') {
            await Tone.start();
        }
    }

    /**
     * Download and parse MIDI file
     * @param {string} url - MIDI file URL
     * @returns {Promise<Tone.Midi>} Parsed MIDI object
     * @private
     */
    async fetchAndParseMidi(url) {
        // Check cache first
        if (this.cachedMidiFiles.has(url)) {
            return this.cachedMidiFiles.get(url);
        }

        try {
            const response = await fetch(url);
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            const arrayBuffer = await response.arrayBuffer();
            // Use @tonejs/midi directly (NOT Tone.Midi)
            const midi = new Midi(arrayBuffer);

            // Cache for future use
            this.cachedMidiFiles.set(url, midi);

            return midi;
        } catch (error) {
            console.error('Failed to fetch/parse MIDI:', error);
            throw error;
        }
    }

    /**
     * Calculate which bars this loop covers
     * @param {number} loopIndex - 0-based loop index
     * @param {number[]} loopLengths - Array of bar counts per loop [32, 32, 16]
     * @returns {Object} { startBar, endBar } (1-indexed)
     * @private
     */
    calculateLoopBars(loopIndex, loopLengths) {
        let startBar = 0;

        // Sum up all previous loops
        for (let i = 0; i < loopIndex; i++) {
            startBar += loopLengths[i];
        }

        const endBar = startBar + loopLengths[loopIndex] - 1;

        return {
            startBar: startBar + 1,      // 1-indexed
            endBar: endBar + 1,          // 1-indexed (inclusive)
            barCount: loopLengths[loopIndex]
        };
    }

    /**
     * Convert bars to beats (quarter notes)
     * @param {number} bars - Number of bars
     * @param {number} beatsPerBar - Beats per bar (usually 4 for 4/4 time)
     * @returns {number} Number of beats
     * @private
     */
    barsToBeats(bars, beatsPerBar) {
        return bars * beatsPerBar;
    }

    /**
     * Convert beats to seconds
     * @param {number} beats - Number of beats (quarter notes)
     * @param {number} bpm - Beats per minute
     * @returns {number} Duration in seconds
     * @private
     */
    beatsToSeconds(beats, bpm) {
        return (beats / bpm) * 60;
    }

    /**
     * Calculate exact time signature beats per bar
     * @param {string} timeSignature - e.g., "4/4", "3/4", "5/4"
     * @returns {number} Beats per bar
     * @private
     */
    getBeatsPerBar(timeSignature) {
        const [numerator] = timeSignature.split('/').map(Number);
        return numerator || 4; // Default to 4/4
    }

    /**
     * Filter and schedule notes from MIDI that fall within the loop range
     * @param {Tone.Midi} midi - Parsed MIDI object
     * @param {number} startBeat - Start beat (quarter notes)
     * @param {number} endBeat - End beat (quarter notes)
     * @param {number} bpm - Beats per minute
     * @private
     */
    scheduleLoopNotes(midi, startBeat, endBeat, bpm) {
        if (!midi) {
            console.error('Invalid MIDI object: null/undefined');
            throw new Error('MIDI object is null/undefined');
        }

        // Check for tracks in different possible locations
        let tracks = midi.tracks || midi.track || [];

        if (!Array.isArray(tracks)) {
            console.error('MIDI tracks is not an array:', tracks);
            throw new Error('MIDI tracks are not in array format');
        }

        if (tracks.length === 0) {
            console.warn('MIDI has no tracks');
        }

        const beatsPerSecond = bpm / 60;
        const startSeconds = startBeat / beatsPerSecond;
        const endSeconds = endBeat / beatsPerSecond;
        const loopDurationSeconds = endSeconds - startSeconds;

        // Clear any previously scheduled notes
        Tone.Transport.cancel();

        // Collect all notes from all tracks
        const allNotes = [];
        tracks.forEach(track => {
            if (track.notes && Array.isArray(track.notes)) {
                allNotes.push(...track.notes);
            }
        });

        if (allNotes.length === 0) {
            console.warn('⚠️  No notes found in MIDI file');
        }

        // Filter notes that fall within our loop range
        let scheduledCount = 0;
        allNotes.forEach((note, idx) => {
            // Check if note starts within the loop range
            if (note.time >= startSeconds && note.time < endSeconds) {
                scheduledCount++;
                const relativeTime = note.time - startSeconds;
                const noteDuration = Math.min(note.duration, loopDurationSeconds - relativeTime);

                Tone.Transport.schedule((time) => {
                    this.synth.triggerAttackRelease(
                        note.name,
                        noteDuration,
                        time
                    );
                }, `+${relativeTime}`);
            }
        });

        return loopDurationSeconds;
    }

    /**
     * Play a specific loop segment
     * @param {string} midiUrl - URL to MIDI file
     * @param {number} loopIndex - 0-based loop index
     * @param {number[]} loopLengths - Array of bar counts [32, 32, 16]
     * @param {number} bpm - Beats per minute
     * @param {string} timeSignature - Time signature (e.g., "4/4")
     * @param {string} presetId - ID of the preset to use
     * @param {number} volumeDb - Track volume in decibels (-90 to +12)
     * @returns {Promise<void>}
     */
    async playLoopSegment(midiUrl, loopIndex, loopLengths, bpm, timeSignature, presetId = null, volumeDb = 0) {
        try {
            // Ensure audio context is running
            await this.ensureAudioContextStarted();

            // Initialize/Switch synth if needed
            this.getOrCreateSynth(presetId, volumeDb);

            // Stop any current playback
            this.stopPlayback();

            // Parse MIDI file (cached if possible)
            const midi = await this.fetchAndParseMidi(midiUrl);

            // Calculate which bars this loop covers
            const loopInfo = this.calculateLoopBars(loopIndex, loopLengths);
            const beatsPerBar = this.getBeatsPerBar(timeSignature);

            // Convert bars to beats
            const startBeat = this.barsToBeats(loopInfo.startBar - 1, beatsPerBar); // 0-indexed for calculation
            const endBeat = this.barsToBeats(loopInfo.endBar, beatsPerBar);

            // Set transport BPM
            Tone.Transport.bpm.value = bpm;

            // Schedule all notes in this loop
            const loopDurationSeconds = this.scheduleLoopNotes(midi, startBeat, endBeat, bpm);

            // Setup looping behavior
            Tone.Transport.setLoopPoints(0, loopDurationSeconds);
            Tone.Transport.loop = true;

            // Start transport
            Tone.Transport.start();

            // Store current playback info
            this.currentPlayback = {
                midiUrl,
                loopIndex,
                startBeat,
                endBeat,
                loopDuration: loopDurationSeconds,
                startTime: Tone.now(),
                presetId: this.currentPresetId
            };
        } catch (error) {
            console.error('Failed to play loop:', error);
            this.stopPlayback();
            throw error;
        }
    }

    /**
     * Stop current playback and cleanup
     */
    stopPlayback() {
        if (this.currentPlayback || Tone.Transport.state === 'started') {
            Tone.Transport.stop();
            Tone.Transport.cancel();
            Tone.Transport.loop = false; // Reset looping
            Tone.Transport.loopPoints = [0, Infinity]; // Reset loop boundaries

            if (this.synth) {
                this.synth.releaseAll();
            }

            this.currentPlayback = null;
        }
    }

    /**
     * Check if currently playing
     * @returns {boolean}
     */
    isPlaying() {
        return this.currentPlayback !== null && Tone.Transport.state === 'started';
    }

    /**
     * Dispose and cleanup resources
     */
    dispose() {
        this.stopPlayback();
        this.synth?.dispose();
        this.volNode?.dispose();
        this.cachedMidiFiles.clear();
    }
}

// Export for global use
window.MidiLoopPlayback = MidiLoopPlayback;
