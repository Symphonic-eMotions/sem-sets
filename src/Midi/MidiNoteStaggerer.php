<?php

declare(strict_types=1);

namespace App\Midi;

use App\Entity\Asset;
use App\Entity\Document;
use App\Entity\User;
use App\Service\AssetStorage;
use League\Flysystem\FilesystemException;
use MidiDuration;
use RuntimeException;

/**
 * Verschuift noten die exact op hetzelfde moment starten zodat ze niet meer
 * gelijktijdig starten. Hogere noten worden als laatste geplaatst (arpeggering
 * van laag naar hoog), elke volgende noot krijgt +$offsetTicks t.o.v. de vorige.
 *
 * De bijbehorende note-offs worden evenveel verschoven zodat de nootduur bewaard
 * blijft (de library slaat absolute timestamps op, géén duraatwaarden).
 */
final class MidiNoteStaggerer
{
    public function __construct(
        private readonly AssetStorage $assetStorage,
        private readonly PhpMidiFile  $phpMidiFile,
    ) {}

    /**
     * Maakt een nieuwe Asset aan die de gestaggerde versie van $asset bevat.
     *
     * @throws FilesystemException
     */
    public function staggerAsset(
        Document $doc,
        Asset    $asset,
        ?User    $user,
        int      $offsetTicks = 1,
    ): Asset {
        $tmp = $this->assetStorage->createLocalTempFile($asset);

        $this->phpMidiFile->loadFromFile($tmp);
        $midi = $this->phpMidiFile->getInnerMidi();

        @unlink($tmp);

        $trackCount = $midi->getTrackCount();

        $out = new MidiDuration();
        $out->open($midi->getTimebase());

        $out->tracks = [];
        for ($tn = 0; $tn < $trackCount; $tn++) {
            $staggered    = $this->staggerTrack($midi->getTrack($tn), $offsetTicks);
            $out->tracks[] = $this->ensureTrkEnd($staggered);
        }

        $tmpOut = tempnam(sys_get_temp_dir(), 'midi_stagger_');
        if ($tmpOut === false) {
            throw new RuntimeException('Kon geen tijdelijk bestand aanmaken voor gestaggerde MIDI.');
        }

        $out->saveMidFile($tmpOut);
        $binary = file_get_contents($tmpOut);
        @unlink($tmpOut);

        if ($binary === false || $binary === '') {
            throw new RuntimeException('Kon gestaggerde MIDI niet lezen uit tijdelijk bestand.');
        }

        $baseName    = pathinfo((string) $asset->getOriginalName(), PATHINFO_FILENAME);
        $newName     = $baseName . '-staggered.mid';

        return $this->assetStorage->store(
            doc:          $doc,
            originalName: $newName,
            mime:         'audio/midi',
            size:         strlen($binary),
            binary:       $binary,
            user:         $user,
        );
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Verschuift gelijktijdige note-ons in één track en past de bijbehorende
     * note-offs dienovereenkomstig aan.
     *
     * @param  array<int,string> $track
     * @return array<int,string>
     */
    private function staggerTrack(array $track, int $offsetTicks): array
    {
        // -- Stap 1: parse tijden en nooteigenschappen -----------------------
        $times    = [];
        $noteInfo = [];

        foreach ($track as $i => $line) {
            $parts    = explode(' ', trim($line));
            $times[$i] = (int) ($parts[0] ?? 0);
            $msgType  = $parts[1] ?? '';

            if ($msgType !== 'On' && $msgType !== 'Off') {
                continue;
            }

            $ch = null; $note = null; $vel = null;
            foreach ($parts as $p) {
                if (str_starts_with($p, 'ch=')) { $ch   = (int) substr($p, 3); }
                if (str_starts_with($p, 'n='))  { $note = (int) substr($p, 2); }
                if (str_starts_with($p, 'v='))  { $vel  = (int) substr($p, 2); }
            }

            $noteInfo[$i] = [
                'type' => $msgType, // 'On' | 'Off'
                'ch'   => $ch,
                'note' => $note,
                'vel'  => $vel,
            ];
        }

        // -- Stap 2: groepeer note-ons per tijdstip --------------------------
        $onsByTime = [];
        foreach ($noteInfo as $i => $info) {
            if ($info['type'] === 'On' && ($info['vel'] ?? 0) > 0) {
                $onsByTime[$times[$i]][] = $i;
            }
        }

        // -- Stap 3: bereken delta's (lagere noot = eerder = delta 0) --------
        $deltas = []; // index => extra ticks
        foreach ($onsByTime as $indices) {
            if (count($indices) <= 1) {
                continue;
            }

            // sorteer oplopend op nootwaarde: laagste noot start het eerst
            usort($indices, static fn ($a, $b) =>
                ($noteInfo[$a]['note'] ?? 0) <=> ($noteInfo[$b]['note'] ?? 0));

            foreach ($indices as $pos => $idx) {
                if ($pos > 0) {
                    $deltas[$idx] = $pos * $offsetTicks;
                }
            }
        }

        if (empty($deltas)) {
            return $track; // niets te verschuiven
        }

        // -- Stap 4: pas ook de bijbehorende note-offs aan -------------------
        $usedNoteOffs = [];

        foreach ($deltas as $onIdx => $delta) {
            $ch       = $noteInfo[$onIdx]['ch'];
            $note     = $noteInfo[$onIdx]['note'];
            $origTime = $times[$onIdx];

            // Zoek de eerstvolgende Off (of On v=0) voor dit kanaal+noot
            foreach ($noteInfo as $j => $info) {
                if (isset($usedNoteOffs[$j])) {
                    continue;
                }
                if ($j <= $onIdx) {
                    continue; // note-off moet ná note-on komen
                }

                $isNoteOff = ($info['type'] === 'Off')
                    || ($info['type'] === 'On' && ($info['vel'] ?? 1) === 0);

                if ($isNoteOff
                    && $info['ch']   === $ch
                    && $info['note'] === $note
                    && $times[$j]   >= $origTime
                ) {
                    $deltas[$j]       = $delta;
                    $usedNoteOffs[$j] = true;
                    break;
                }
            }
        }

        // -- Stap 5: maak nieuwe tijden en herbouw de regels -----------------
        $events = [];
        foreach ($track as $i => $line) {
            $newTime   = $times[$i] + ($deltas[$i] ?? 0);
            $events[]  = [
                'origIdx' => $i,
                'time'    => $newTime,
                'line'    => $this->replaceTime($line, $newTime),
            ];
        }

        // Stabiel sorteren op tijd (zelfde volgorde bij gelijke tijd)
        usort($events, static fn ($a, $b) =>
            $a['time'] <=> $b['time'] ?: $a['origIdx'] <=> $b['origIdx']);

        return array_column($events, 'line');
    }

    /** Vervang het eerste token (timestamp) in een MIDI-tekstregel. */
    private function replaceTime(string $line, int $newTime): string
    {
        $pos = strpos($line, ' ');
        if ($pos === false) {
            return $line;
        }
        return $newTime . substr($line, $pos);
    }

    /** Zorg dat een track eindigt op een TrkEnd op het juiste tijdstip. */
    private function ensureTrkEnd(array $track): array
    {
        if (empty($track)) {
            return ['0 Meta TrkEnd'];
        }

        // Verwijder eventuele bestaande TrkEnd(s) en bepaal max tijd
        $filtered = [];
        $maxTime  = 0;

        foreach ($track as $line) {
            $t = (int) explode(' ', trim($line))[0];
            if ($t > $maxTime) {
                $maxTime = $t;
            }
            if (!str_contains($line, 'Meta TrkEnd')) {
                $filtered[] = $line;
            }
        }

        $filtered[] = $maxTime . ' Meta TrkEnd';
        return $filtered;
    }
}
