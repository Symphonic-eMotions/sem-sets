<?php

declare(strict_types=1);

namespace App\Midi\Dto;

final class MidiSummary
{
    public function __construct(
        public readonly int $tempoMicrosecondsPerQuarter,
        public readonly int $bpm,
        public readonly int $timebase,
        public readonly int $trackCount,
        public readonly float $durationSeconds,
    ) {
    }

    public function getDurationFormatted(): string
    {
        return gmdate('i:s', (int) round($this->durationSeconds));
    }
}
