<?php

declare(strict_types=1);

namespace App\Midi;

interface MidiFileInterface
{
    /**
     * Laad een MIDI-bestand vanaf disk.
     */
    public function loadFromFile(string $path): void;

    public function getTempoMicrosecondsPerQuarter(): int;

    public function getBpm(): int;

    public function getTimebase(): int;

    public function getTrackCount(): int;

    public function getDurationSeconds(): float;

    /**
     * Eerste gevonden TimeSig (meestal in track 0 bij t=0)
     * Bijvoorbeeld: ['numerator' → 4, 'denominator' → 4]
     * Retourneert null als er geen TimeSig is.
     */
    public function getTimeSignature(): ?array;

    /**
     * Geschat aantal maten op basis van:
     *  - TimeSig
     *  - timebase
     *  - tijd van laatste event
     *
     * Retourneert null als geen TimeSig gevonden kan worden.
     */
    public function getBarCount(): ?int;
}
