/**
 * MIDI Piano Roll Renderer
 *
 * Gedeelde rendering logica voor canvas-based MIDI piano roll.
 * Hergebruikt door loopPianoRoll.js (met selectie-overlay) en midiFilePreview.js (read-only).
 */

(function() {
    'use strict';

    const midiCache = {};

    const renderer = {
        /**
         * Fetch en parse MIDI, met caching
         */
        async loadMidi(url) {
            if (midiCache[url]) {
                return midiCache[url];
            }

            if (!window.Midi) {
                throw new Error("Midi parser not loaded. Include @tonejs/midi script.");
            }

            const response = await fetch(url);
            if (!response.ok) throw new Error("HTTP " + response.status);

            const arrayBuffer = await response.arrayBuffer();
            const midiData = new window.Midi(arrayBuffer);
            midiCache[url] = midiData;
            return midiData;
        },

        /**
         * Kalibreer canvas afmetingen op basis van MIDI-inhoud
         * @param {HTMLCanvasElement} canvas
         * @param {Midi} midiData
         * @param {number} beatsPerBar - Numerator van time signature (bijv. 4)
         * @returns {object} {minPitch, maxPitch}
         */
        calibrate(canvas, midiData, beatsPerBar = 4, pixelsPerQuarter = 40) {
            let minPitch = 127;
            let maxPitch = 0;
            let endTicks = 0;

            midiData.tracks.forEach(track => {
                track.notes.forEach(note => {
                    if (note.midi < minPitch) minPitch = note.midi;
                    if (note.midi > maxPitch) maxPitch = note.midi;
                    const noteEnd = note.ticks + note.durationTicks;
                    if (noteEnd > endTicks) endTicks = noteEnd;
                });
            });

            if (minPitch > maxPitch) {
                minPitch = 21;
                maxPitch = 108;
            }

            minPitch = Math.max(0, minPitch - 2);
            maxPitch = Math.min(127, maxPitch + 2);

            const ppq = midiData.header.ppq;
            const endQuarters = endTicks / ppq;
            const pitchHeight = 4;
            const requiredWidth = Math.max(800, endQuarters * pixelsPerQuarter);
            const requiredHeight = (maxPitch - minPitch + 1) * pitchHeight;

            canvas.width = Math.min(requiredWidth + 100, 3000);
            canvas.height = Math.max(requiredHeight + 40, 200);

            return { minPitch, maxPitch };
        },

        /**
         * Teken MIDI notes + grid op canvas
         *
         * @param {HTMLCanvasElement} canvas
         * @param {CanvasRenderingContext2D} ctx
         * @param {Midi} midiData
         * @param {object} state - Bevat: beatsPerBar, pixelsPerQuarter, pitchHeight, minPitch, maxPitch,
         *                          selectionStartBeat, selectionEndBeat (optioneel), loopIndex (optioneel)
         */
        draw(canvas, ctx, midiData, state) {
            const {
                beatsPerBar = 4,
                pixelsPerQuarter = 40,
                pitchHeight = 4,
                minPitch = 21,
                maxPitch = 108,
                selectionStartBeat = null,
                selectionEndBeat = null,
                loopIndex = null
            } = state;

            const w = canvas.width;
            const h = canvas.height;
            const pitchRange = maxPitch - minPitch + 1;

            // Achtergrond
            ctx.fillStyle = '#0f172a';
            ctx.fillRect(0, 0, w, h);

            const ppq = midiData.header.ppq;
            const pxPerBar = pixelsPerQuarter * beatsPerBar;

            // Teken grid
            ctx.strokeStyle = '#1e293b';
            ctx.lineWidth = 1;
            ctx.beginPath();
            for (let q = 0; q * pixelsPerQuarter < w; q++) {
                const x = q * pixelsPerQuarter;
                if (q % beatsPerBar === 0) {
                    ctx.strokeStyle = '#334155';
                    ctx.moveTo(x, 0);
                    ctx.lineTo(x, h - 30);

                    ctx.fillStyle = '#64748b';
                    ctx.font = '10px sans-serif';
                    ctx.fillText(`M ${q / beatsPerBar + 1}`, x + 5, h - 15);
                } else {
                    ctx.strokeStyle = '#1e293b';
                    ctx.moveTo(x, 0);
                    ctx.lineTo(x, h - 30);
                }
            }
            ctx.stroke();

            // Teken noten
            midiData.tracks.forEach(track => {
                track.notes.forEach(note => {
                    const startBeat = note.ticks / ppq;
                    const lengthBeat = note.durationTicks / ppq;

                    const x = startBeat * pixelsPerQuarter;
                    const width = lengthBeat * pixelsPerQuarter;

                    const relativePitch = note.midi - minPitch;
                    const y = h - 40 - ((relativePitch + 1) * pitchHeight);

                    ctx.fillStyle = '#5865f2';
                    ctx.fillRect(x, y, Math.max(1, width - 1), pitchHeight - 1);
                });
            });

            // Teken selectie-overlay (optioneel)
            if (selectionStartBeat !== null && selectionEndBeat !== null) {
                const minB = Math.min(selectionStartBeat, selectionEndBeat);
                const maxB = Math.max(selectionStartBeat, selectionEndBeat);

                const sx = minB * pixelsPerQuarter;
                const ex = maxB * pixelsPerQuarter;

                ctx.fillStyle = 'rgba(59, 205, 247, 0.2)';
                ctx.fillRect(sx, 0, ex - sx, h);

                ctx.strokeStyle = '#3bcdf7';
                ctx.lineWidth = 2;
                ctx.strokeRect(sx, 0, ex - sx, h);

                // Loop info label
                if (loopIndex !== null) {
                    ctx.fillStyle = '#3bcdf7';
                    ctx.font = 'bold 12px sans-serif';
                    ctx.fillText(`Loop ${loopIndex + 1}`, sx + 5, 20);
                }
            }
        }
    };

    // Export
    window.MidiPianoRollRenderer = renderer;
})();
