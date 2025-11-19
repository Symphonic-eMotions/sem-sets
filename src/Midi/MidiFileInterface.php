<?php

declare(strict_types=1);

namespace App\Midi;

interface MidiFileInterface
{
    /**
     * Microseconds per quarter note (de waarde uit de Tempo-event)
     */
    public function getTempoMicrosecondsPerQuarter(): int;

    /**
     * BPM-afleiding uit tempo (afgerond)
     */
    public function getBpm(): int;

    /**
     * Ticks per kwartnoot (timebase)
     */
    public function getTimebase(): int;

    /**
     * Aantal tracks in het MIDI-bestand
     */
    public function getTrackCount(): int;

    /**
     * Totale duur in seconden
     */
    public function getDurationSeconds(): float;
}
