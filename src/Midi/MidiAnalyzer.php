<?php

declare(strict_types=1);

namespace App\Midi;

use App\Midi\Dto\MidiSummary;

class MidiAnalyzer
{
    public function __construct(
        private readonly PhpMidiFile $midiFile,
    ) {
    }

    /**
     * Laadt een MIDI-bestand en geeft een samenvatting terug
     */
    public function summarize(string $path): MidiSummary
    {
        $this->midiFile->loadFromFile($path);

        return new MidiSummary(
            tempoMicrosecondsPerQuarter: $this->midiFile->getTempoMicrosecondsPerQuarter(),
            bpm: $this->midiFile->getBpm(),
            timebase: $this->midiFile->getTimebase(),
            trackCount: $this->midiFile->getTrackCount(),
            durationSeconds: $this->midiFile->getDurationSeconds(),
        );
    }
}
