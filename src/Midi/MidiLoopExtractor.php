<?php

declare(strict_types=1);

namespace App\Midi;

use App\Entity\Asset;
use App\Entity\Document;
use App\Entity\User;
use App\Service\AssetStorage;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use MidiDuration;
use RuntimeException;
use Throwable;

/**
 * Extracts a loop segment from a MIDI file and exports it to a new or existing MIDI file.
 */
final class MidiLoopExtractor
{
    public function __construct(
        private readonly AssetStorage $assetStorage,
        private readonly PhpMidiFile $phpMidiFile,
        private readonly FilesystemOperator $uploadsStorage,
        private readonly EntityManagerInterface $em,
    ) {}

    /**
     * Export a loop segment from source asset to a new or existing target asset
     */
    public function exportLoop(
        Document $doc,
        Asset $sourceAsset,
        int $offsetQuarters,
        int $lengthQuarters,
        string $targetMode,
        ?Asset $targetAsset = null,
        ?string $newFileName = null,
        ?User $user = null,
        ?int $targetOffsetQuarters = null,
    ): Asset {
        if ($offsetQuarters < 0 || $lengthQuarters <= 0) {
            throw new RuntimeException('Invalid offset or length parameters');
        }

        $sourceTmp = $this->assetStorage->createLocalTempFile($sourceAsset);
        try {
            $this->phpMidiFile->loadFromFile($sourceTmp);
            $sourceMidi = $this->phpMidiFile->getInnerMidi();
            $sourceTimebase = $sourceMidi->getTimebase();

            $offsetTicks = (int)($offsetQuarters * $sourceTimebase);
            $lengthTicks = (int)($lengthQuarters * $sourceTimebase);

            // Extract loop notes from source
            $loopTracks = $this->extractLoopTracks($sourceMidi, $offsetTicks, $lengthTicks);

            if ($targetMode === 'new') {
                return $this->createNewMidiAsset($doc, $loopTracks, $sourceMidi, $newFileName, $user);
            } else {
                return $this->appendToExistingMidiAsset($targetAsset, $loopTracks, $sourceTimebase, $user, $targetOffsetQuarters);
            }
        } finally {
            if ($sourceTmp && file_exists($sourceTmp)) {
                @unlink($sourceTmp);
            }
        }
    }

    private function extractLoopTracks(MidiDuration $midi, int $offsetTicks, int $lengthTicks): array {
        $trackCount = $midi->getTrackCount();
        $loopTracks = [];

        for ($tn = 0; $tn < $trackCount; $tn++) {
            $track = $midi->getTrack($tn);
            if (!is_array($track)) continue;

            $filteredTrack = $this->filterTrackByTimeRange($track, $offsetTicks, $offsetTicks + $lengthTicks);

            if (!empty($filteredTrack)) {
                // Shift to start at 0
                $shiftedTrack = $this->shiftTrackTimes($filteredTrack, -$offsetTicks);
                $loopTracks[$tn] = $shiftedTrack;
            }
        }
        return $loopTracks;
    }

    private function filterTrackByTimeRange(array $track, int $startTicks, int $endTicks): array {
        $filtered = [];
        // Essential global types - only keep if at time 0 or within range
        $metaTypes = ['Tempo', 'TimeSig', 'KeySig'];

        foreach ($track as $line) {
            if (!is_string($line)) continue;
            $parts = explode(' ', trim($line));
            if (count($parts) < 2) continue;

            $timestamp = (int)$parts[0];
            $msgType = $parts[1];

            if ($msgType === 'TrkEnd') continue;

            // Only keep meta events if they are at the start (global) or within our segment
            if (in_array($msgType, $metaTypes, true) || str_contains($msgType, 'Meta')) {
                if ($timestamp === 0 || ($timestamp >= $startTicks && $timestamp < $endTicks)) {
                    $filtered[] = $line;
                }
                continue;
            }

            // Keep notes and controllers strictly in range
            if ($timestamp >= $startTicks && $timestamp < $endTicks) {
                $filtered[] = $line;
            }
        }
        return $filtered;
    }

    private function shiftTrackTimes(array $track, int $shiftTicks): array {
        $shifted = [];
        foreach ($track as $line) {
            $parts = explode(' ', trim($line), 2);
            if (count($parts) < 2) {
                $shifted[] = $line;
                continue;
            }
            $timestamp = (int)$parts[0];
            // Only shift non-zero timestamps or if shift is positive
            $newTime = ($timestamp === 0 && $shiftTicks < 0) ? 0 : max(0, $timestamp + $shiftTicks);
            $shifted[] = $newTime . ' ' . $parts[1];
        }
        return $shifted;
    }

    private function ensureTrkEnd(array $track): array {
        $filtered = [];
        $maxTime = 0;
        foreach ($track as $line) {
            $parts = explode(' ', trim((string)$line));
            $t = is_numeric($parts[0] ?? null) ? (int)$parts[0] : 0;
            $maxTime = max($maxTime, $t);
            if (!str_contains($line, 'TrkEnd')) {
                $filtered[] = $line;
            }
        }
        $filtered[] = ($maxTime + 1) . ' Meta TrkEnd';
        return $filtered;
    }

    private function createNewMidiAsset(Document $doc, array $loopTracks, MidiDuration $sourceMidi, string $fileName, ?User $user): Asset {
        $out = new MidiDuration();
        $out->open($sourceMidi->getTimebase());
        $out->tracks = [];
        foreach (array_values($loopTracks) as $track) {
            $out->tracks[] = $this->ensureTrkEnd($track);
        }

        $tmpOut = tempnam(sys_get_temp_dir(), 'midi_loop_');
        try {
            $out->saveMidFile($tmpOut);
            $binary = file_get_contents($tmpOut);
            if (!str_ends_with($fileName, '.mid')) $fileName .= '.mid';
            return $this->assetStorage->store($doc, $fileName, 'audio/midi', strlen($binary), $binary, $user);
        } finally {
            if ($tmpOut && file_exists($tmpOut)) @unlink($tmpOut);
        }
    }

    private function appendToExistingMidiAsset(Asset $targetAsset, array $loopTracks, int $sourceTimebase, ?User $user, ?int $targetOffsetQuarters): Asset {
        $targetTmp = $this->assetStorage->createLocalTempFile($targetAsset);
        try {
            $targetMidi = new MidiDuration();
            $targetMidi->importMid($targetTmp);
            $targetTimebase = $targetMidi->getTimebase();

            if ($targetOffsetQuarters !== null) {
                $appendStartTicks = $targetOffsetQuarters * $targetTimebase;
            } else {
                $appendStartTicks = $this->findMaxTimestamp($targetMidi) + $targetTimebase;
            }

            $rescale = $targetTimebase / $sourceTimebase;
            $processedLoopTracks = [];
            foreach ($loopTracks as $tn => $track) {
                $rescaledTrack = [];
                foreach ($track as $line) {
                    $parts = explode(' ', trim((string)$line), 2);
                    if (count($parts) < 2) { $rescaledTrack[] = $line; continue; }
                    
                    $timestamp = (int)$parts[0];
                    // Global events at 0 should stay at 0 in the new context if they are headers,
                    // but here they are part of a loop being appended, so we treat them as part of the sequence.
                    $newTime = (int)($timestamp * $rescale) + $appendStartTicks;
                    $rescaledTrack[] = $newTime . ' ' . $parts[1];
                }
                $processedLoopTracks[$tn] = $rescaledTrack;
            }

            $mergedTracks = [];
            $targetTrackCount = $targetMidi->getTrackCount();
            $maxTrackIndex = max($targetTrackCount - 1, count($processedLoopTracks) > 0 ? max(array_keys($processedLoopTracks)) : 0);

            for ($tn = 0; $tn <= $maxTrackIndex; $tn++) {
                $targetTrack = ($tn < $targetTrackCount) ? $targetMidi->getTrack($tn) : [];
                $cleanTarget = [];
                foreach ((array)$targetTrack as $line) {
                    if (!str_contains((string)$line, 'TrkEnd')) $cleanTarget[] = $line;
                }

                if (isset($processedLoopTracks[$tn])) {
                    $merged = array_merge($cleanTarget, $processedLoopTracks[$tn]);
                    usort($merged, function($a, $b) {
                        return (int)explode(' ', trim((string)$a))[0] <=> (int)explode(' ', trim((string)$b))[0];
                    });
                    $mergedTracks[] = $this->ensureTrkEnd($merged);
                } else {
                    $mergedTracks[] = $this->ensureTrkEnd($cleanTarget);
                }
            }

            $out = new MidiDuration();
            $out->open($targetTimebase);
            $out->tracks = $mergedTracks;

            $tmpOut = tempnam(sys_get_temp_dir(), 'midi_app_');
            try {
                $out->saveMidFile($tmpOut);
                $binary = file_get_contents($tmpOut);
                $this->uploadsStorage->write($targetAsset->getStoragePath(), $binary);
                $targetAsset->setSize(strlen($binary));
                $this->em->flush();
                return $targetAsset;
            } finally {
                if ($tmpOut && file_exists($tmpOut)) @unlink($tmpOut);
            }
        } finally {
            if ($targetTmp && file_exists($targetTmp)) @unlink($targetTmp);
        }
    }

    private function findMaxTimestamp(MidiDuration $midi): int {
        $maxTime = 0;
        for ($tn = 0; $tn < $midi->getTrackCount(); $tn++) {
            $track = $midi->getTrack($tn);
            if (!is_array($track)) continue;
            foreach ($track as $line) {
                $parts = explode(' ', trim((string)$line));
                if (is_numeric($parts[0] ?? null)) $maxTime = max($maxTime, (int)$parts[0]);
            }
        }
        return $maxTime;
    }
}
