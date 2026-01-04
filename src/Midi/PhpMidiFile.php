<?php

declare(strict_types=1);

namespace App\Midi;

use InvalidArgumentException;
use MidiDuration;

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
            throw new InvalidArgumentException(sprintf(
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

    public function getBpm(): float
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

    public function getTimeSignature(): ?array
    {
        $trackCount = $this->midi->getTrackCount();
        if ($trackCount === 0) {
            return null;
        }

        // We zoeken TimeSig in track 0
        $track0 = $this->midi->getTrack(0);
        foreach ($track0 as $line) {
            $parts = explode(' ', $line);
            if (!isset($parts[1]) || $parts[1] !== 'TimeSig') {
                continue;
            }

            // Formaat: "t TimeSig 4/4 24 8"
            if (!isset($parts[2])) {
                continue;
            }

            $tsParts = explode('/', $parts[2]);
            if (count($tsParts) !== 2) {
                continue;
            }

            $numerator   = (int) $tsParts[0];
            $denominator = (int) $tsParts[1];

            return [
                'numerator'   => $numerator,
                'denominator' => $denominator,
                // extra info kun je later gebruiken als je wilt:
                'midiClocksPerMetronome'   => isset($parts[3]) ? (int) $parts[3] : null,
                'thirtySecondsPer24Clocks' => isset($parts[4]) ? (int) $parts[4] : null,
            ];
        }

        return null;
    }

    public function getBarCount(): ?int
    {
        $ts = $this->getTimeSignature();
        if ($ts === null) {
            return null;
        }

        $numerator   = $ts['numerator'] ?? 0;
        $denominator = $ts['denominator'] ?? 0;
        $timebase    = $this->midi->getTimebase();

        if ($numerator <= 0 || $denominator <= 0 || $timebase <= 0) {
            return null;
        }

        // ticks per maat:
        // timebase = ticks per kwartnoot
        // beats per maat = numerator
        // nootwaarde per beat = 1/denominator
        //
        // ticks per beat = timebase * (4 / denominator)
        // ticks per maat = beatsPerMeasure * ticksPerBeat
        $ticksPerBeat    = $timebase * (4 / $denominator);
        $ticksPerMeasure = (int) round($numerator * $ticksPerBeat);

        if ($ticksPerMeasure <= 0) {
            return null;
        }

        $lastTick = $this->getLastTick();
        if ($lastTick <= 0) {
            return null;
        }

        // Geschat aantal maten (inclusief laatste, evt. onvolledige maat)
        return (int) ceil($lastTick / $ticksPerMeasure);
    }

    /**
     * Hulp: bepaal hoogste timestamp (ticks) over alle tracks
     */
    private function getLastTick(): int
    {
        $trackCount = $this->midi->getTrackCount();
        $end        = 0;

        for ($i = 0; $i < $trackCount; $i++) {
            $track = $this->midi->getTrack($i);
            if (empty($track)) {
                continue;
            }

            $lastLine = $track[count($track) - 1];
            $parts    = explode(' ', $lastLine);
            $t        = isset($parts[0]) ? (int) $parts[0] : 0;

            if ($t > $end) {
                $end = $t;
            }
        }

        return $end;
    }

    public function getInnerMidi(): MidiDuration
    {
        return $this->midi;
    }
}
