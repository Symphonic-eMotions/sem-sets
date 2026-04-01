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
        this.currentPlayback = null;
        this.isInitialized = false;
        this.cachedMidiFiles = new Map(); // Cache parsed MIDI files

        this.initializeSynth();
    }

    /**
     * Initialize the piano synth
     */
    initializeSynth() {
        if (this.isInitialized) return;

        // Piano synth with realistic ADSR envelope
        this.synth = new Tone.PolySynth(Tone.Synth, {
            oscillator: {
                type: 'triangle',
                count: 3,
                spread: 30
            },
            envelope: {
                attack: 0.008,    // Quick attack
                decay: 0.1,       // Decay to sustain
                sustain: 0.3,     // Mid-level sustain
                release: 0.5      // Long release for piano tail
            }
        }).toDestination();

        this.isInitialized = true;
    }

    /**
     * Ensure AudioContext is started (required by modern browsers)
     * @private
     */
    async ensureAudioContextStarted() {
        if (Tone.Synth.prototype.constructor.context?.state !== 'running') {
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

            // DEBUG: Check MIDI structure
            console.log('MIDI object parsed:', {
                hasTracks: !!midi.tracks,
                trackCount: midi.tracks?.length,
                tracks: midi.tracks,
                keys: Object.keys(midi),
                fullObject: midi
            });

            // Log all properties to find where notes are
            console.log('🔍 MIDI object properties:');
            for (let key in midi) {
                if (midi.hasOwnProperty(key) || key in midi) {
                    console.log(`  ${key}:`, midi[key]);
                }
            }

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

        console.log('📋 All notes count:', allNotes.length);
        console.log('🔍 Time range (seconds):', { startSeconds, endSeconds, loopDurationSeconds });

        // Filter notes that fall within our loop range
        let scheduledCount = 0;
        allNotes.forEach((note, idx) => {
            // Check if note starts within the loop range
            if (note.time >= startSeconds && note.time < endSeconds) {
                scheduledCount++;
                const relativeTime = note.time - startSeconds;
                const noteDuration = Math.min(note.duration, loopDurationSeconds - relativeTime);

                console.log(`  Note ${idx}: ${note.name} at ${note.time.toFixed(3)}s (relative: ${relativeTime.toFixed(3)}s, duration: ${noteDuration.toFixed(3)}s)`);

                Tone.Transport.schedule((time) => {
                    console.log(`🔊 Triggering ${note.name} at time ${time}`);
                    this.synth.triggerAttackRelease(
                        note.name,
                        noteDuration,
                        time
                    );
                }, `+${relativeTime}`);
            }
        });

        console.log(`📍 Scheduled ${scheduledCount} notes for loop (out of ${allNotes.length} total)`);
        return loopDurationSeconds;
    }

    /**
     * Play a specific loop segment
     * @param {string} midiUrl - URL to MIDI file
     * @param {number} loopIndex - 0-based loop index
     * @param {number[]} loopLengths - Array of bar counts [32, 32, 16]
     * @param {number} bpm - Beats per minute
     * @param {string} timeSignature - Time signature (e.g., "4/4")
     * @returns {Promise<void>}
     */
    async playLoopSegment(midiUrl, loopIndex, loopLengths, bpm, timeSignature) {
        try {
            console.log('🎹 playLoopSegment START:', { loopIndex, loopLengths, bpm, timeSignature });

            // Stop any current playback
            this.stopPlayback();

            // Ensure audio context is running
            console.log('⏸ Ensuring AudioContext is started...');
            await this.ensureAudioContextStarted();
            console.log('✓ AudioContext state:', Tone.Synth.prototype.constructor.context?.state);

            // Parse MIDI file (cached if possible)
            console.log('📥 Fetching MIDI:', midiUrl);
            const midi = await this.fetchAndParseMidi(midiUrl);

            // Calculate which bars this loop covers
            const loopInfo = this.calculateLoopBars(loopIndex, loopLengths);
            const beatsPerBar = this.getBeatsPerBar(timeSignature);

            console.log('📊 Loop info:', { loopInfo, beatsPerBar });

            // Convert bars to beats
            const startBeat = this.barsToBeats(loopInfo.startBar - 1, beatsPerBar); // 0-indexed for calculation
            const endBeat = this.barsToBeats(loopInfo.endBar, beatsPerBar);

            console.log('🎵 Beat range:', { startBeat, endBeat });

            // Set transport BPM
            Tone.Transport.bpm.value = bpm;
            console.log('⏱ Transport BPM set to:', bpm);

            // Schedule all notes in this loop
            console.log('📍 Scheduling notes...');
            const loopDurationSeconds = this.scheduleLoopNotes(midi, startBeat, endBeat, bpm);
            console.log('✓ Loop duration:', loopDurationSeconds.toFixed(2), 'seconds');

            // Setup looping behavior
            Tone.Transport.setLoopPoints(0, loopDurationSeconds);
            Tone.Transport.loop = true;
            console.log('🔄 Loop points set:', { start: 0, end: loopDurationSeconds });

            // Start transport
            console.log('▶ Starting Tone.Transport...');
            Tone.Transport.start();
            console.log('✓ Transport state:', Tone.Transport.state);

            // Store current playback info
            this.currentPlayback = {
                midiUrl,
                loopIndex,
                startBeat,
                endBeat,
                loopDuration: loopDurationSeconds,
                startTime: Tone.now()
            };

            console.log(`✅ Playing loop ${loopIndex} (bars ${loopInfo.startBar}-${loopInfo.endBar}, ${loopDurationSeconds.toFixed(2)}s)`);
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
        if (this.currentPlayback) {
            Tone.Transport.stop();
            Tone.Transport.cancel();
            this.synth.triggerRelease();

            console.log('Playback stopped');
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
        this.cachedMidiFiles.clear();
        this.isInitialized = false;
    }
}

// Export for global use
window.MidiLoopPlayback = MidiLoopPlayback;
