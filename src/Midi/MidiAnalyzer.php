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

        $simultaneousNoteCount = 0;
        if ($this->midiFile instanceof PhpMidiFile) {
            $midi = $this->midiFile->getInnerMidi();
            for ($tn = 0; $tn < $midi->getTrackCount(); $tn++) {
                $simultaneousNoteCount += $this->countSimultaneousInTrack($midi->getTrack($tn));
            }
        }

        return new MidiSummary(
            tempoMicrosecondsPerQuarter: $this->midiFile->getTempoMicrosecondsPerQuarter(),
            bpm:                          $this->midiFile->getBpm(),
            timebase:                     $this->midiFile->getTimebase(),
            trackCount:                   $this->midiFile->getTrackCount(),
            durationSeconds:              $this->midiFile->getDurationSeconds(),
            timeSignatureNumerator:       $timeSig['numerator']   ?? null,
            timeSignatureDenominator:     $timeSig['denominator'] ?? null,
            barCount:                     $barCount,
            simultaneousNoteCount:        $simultaneousNoteCount,
        );
    }

    private function countSimultaneousInTrack(array $track): int
    {
        $onsByTime = [];
        foreach ($track as $line) {
            $parts = explode(' ', trim($line));
            if (($parts[1] ?? '') !== 'On') {
                continue;
            }
            $vel = 127; // default als er geen v= staat
            foreach ($parts as $p) {
                if (str_starts_with($p, 'v=')) {
                    $vel = (int) substr($p, 2);
                    break;
                }
            }
            if ($vel <= 0) {
                continue; // v=0 = note-off in disguise
            }
            $t = (int) $parts[0];
            $onsByTime[$t] = ($onsByTime[$t] ?? 0) + 1;
        }

        $count = 0;
        foreach ($onsByTime as $n) {
            if ($n > 1) {
                $count += $n;
            }
        }
        return $count;
    }
}
