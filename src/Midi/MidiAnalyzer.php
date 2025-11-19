<?php

declare(strict_types=1);

namespace App\Midi;

use App\Midi\Dto\MidiSummary;
use LogicException;

class MidiAnalyzer
{
    public function __construct(
        private readonly MidiFileInterface $midiFile,
    ) {
    }

    public function summarize(string $path): MidiSummary
    {
        // adapter laadt file
        if ($this->midiFile instanceof PhpMidiFile) {
            $this->midiFile->loadFromFile($path);
        } else {
            // of: via aparte load-methode als je een andere implementatie hebt
            throw new LogicException('Current MidiFileInterface implementation does not support loadFromFile.');
        }

        $timeSig = $this->midiFile->getTimeSignature();
        $barCount = $this->midiFile->getBarCount();

        return new MidiSummary(
            tempoMicrosecondsPerQuarter: $this->midiFile->getTempoMicrosecondsPerQuarter(),
            bpm:                          $this->midiFile->getBpm(),
            timebase:                     $this->midiFile->getTimebase(),
            trackCount:                   $this->midiFile->getTrackCount(),
            durationSeconds:              $this->midiFile->getDurationSeconds(),
            timeSignatureNumerator:       $timeSig['numerator']   ?? null,
            timeSignatureDenominator:     $timeSig['denominator'] ?? null,
            barCount:                     $barCount,
        );
    }
}
