<?php


declare(strict_types=1);

namespace App\Midi;

use MidiDuration;

// jouw legacy class, zorg dat die via Composer/autoload beschikbaar is

class PhpMidiFile implements MidiFileInterface
{
    private MidiDuration $midi;

    public function __construct(?MidiDuration $midi = null)
    {
        $this->midi = $midi ?? new MidiDuration();
    }

    /**
     * Laad een bestand in de onderliggende MidiDuration
     */
    public function loadFromFile(string $path): void
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new \InvalidArgumentException(sprintf(
                'MIDI-bestand niet gevonden of niet leesbaar: "%s"',
                $path
            ));
        }

        $this->midi->importMid($path);
    }

    public function getTempoMicrosecondsPerQuarter(): int
    {
        return $this->midi->getTempo();
    }

    public function getBpm(): int
    {
        return $this->midi->getBpm();
    }

    public function getTimebase(): int
    {
        return $this->midi->getTimebase();
    }

    public function getTrackCount(): int
    {
        return $this->midi->getTrackCount();
    }

    public function getDurationSeconds(): float
    {
        return $this->midi->getDuration();
    }

    /**
     * Optioneel: toegang tot de onderliggende MidiDuration voor advanced use cases
     */
    public function getInnerMidi(): MidiDuration
    {
        return $this->midi;
    }
}
