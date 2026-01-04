<?php

declare(strict_types=1);

namespace App\Midi\Dto;

final class MidiSummary
{
    public function __construct(
        public readonly int   $tempoMicrosecondsPerQuarter,
        public readonly float $bpm,
        public readonly int   $timebase,
        public readonly int   $trackCount,
        public readonly float $durationSeconds,
        public readonly ?int  $timeSignatureNumerator   = null,
        public readonly ?int  $timeSignatureDenominator = null,
        public readonly ?int  $barCount                 = null,
    ) {
    }

    public function getDurationFormatted(): string
    {
        return gmdate('i:s', (int) round($this->durationSeconds));
    }

    public function hasTimeSignature(): bool
    {
        return $this->timeSignatureNumerator !== null && $this->timeSignatureDenominator !== null;
    }
}
