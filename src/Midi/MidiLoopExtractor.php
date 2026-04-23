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
 *
 * The segment is defined by offset and length in quarter notes.
 * All notes within the segment are extracted and shifted to start at tick 0.
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
     *
     * @param Document $doc
     * @param Asset $sourceAsset
     * @param int $offsetQuarters - Loop start in quarter notes
     * @param int $lengthQuarters - Loop length in quarter notes
     * @param 'new'|'existing' $targetMode
     * @param Asset|null $targetAsset - Required if targetMode is 'existing'
     * @param string|null $newFileName - Required if targetMode is 'new'
     * @param User|null $user
     * @return Asset The created or modified asset
     * @throws FilesystemException|RuntimeException
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
    ): Asset {
        if ($offsetQuarters < 0 || $lengthQuarters <= 0) {
            throw new RuntimeException('Invalid offset or length parameters');
        }

        // Load source MIDI
        $sourceTmp = $this->assetStorage->createLocalTempFile($sourceAsset);
        try {
            $this->phpMidiFile->loadFromFile($sourceTmp);
            $sourceMidi = $this->phpMidiFile->getInnerMidi();
            $timebase = $sourceMidi->getTimebase();

            // Convert quarter notes to ticks
            $offsetTicks = (int)($offsetQuarters * $timebase);
            $lengthTicks = (int)($lengthQuarters * $timebase);

            if ($targetMode === 'existing' && !$targetAsset) {
                throw new RuntimeException('Target asset required for existing mode');
            }
            if ($targetMode === 'new' && !$newFileName) {
                throw new RuntimeException('File name required for new mode');
            }

            // Extract loop notes from source
            $loopTracks = $this->extractLoopTracks($sourceMidi, $offsetTicks, $lengthTicks, $timebase);

            // Create or update target MIDI
            if ($targetMode === 'new') {
                return $this->createNewMidiAsset($doc, $loopTracks, $sourceMidi, $newFileName, $user);
            } else {
                return $this->appendToExistingMidiAsset($targetAsset, $loopTracks, $timebase, $user);
            }
        } finally {
            if ($sourceTmp && file_exists($sourceTmp)) {
                @unlink($sourceTmp);
            }
        }
    }

    /**
     * Extract tracks containing only notes within the offset/length range
     * Shifts notes back to start at tick 0
     *
     * @return array[] Array of track arrays, indexed by original track number
     */
    private function extractLoopTracks(
        MidiDuration $midi,
        int $offsetTicks,
        int $lengthTicks,
        int $timebase
    ): array {
        $trackCount = $midi->getTrackCount();
        $loopTracks = [];

        for ($tn = 0; $tn < $trackCount; $tn++) {
            $track = $midi->getTrack($tn);
            if (!is_array($track)) continue;

            $filteredTrack = $this->filterTrackByTimeRange($track, $offsetTicks, $offsetTicks + $lengthTicks);

            if (!empty($filteredTrack)) {
                // Shift notes back to start at tick 0
                $shiftedTrack = $this->shiftTrackTimes($filteredTrack, -$offsetTicks);
                $loopTracks[$tn] = $this->ensureTrkEnd($shiftedTrack);
                error_log(sprintf('Extracted track %d with %d events', $tn, count($shiftedTrack)));
            }
        }

        error_log(sprintf('Total extracted tracks: %d', count($loopTracks)));
        return $loopTracks;
    }

    /**
     * Filter track events to only include those within the time range
     * Note: includes all meta events and control changes for accurate playback
     */
    private function filterTrackByTimeRange(array $track, int $startTicks, int $endTicks): array
    {
        $filtered = [];
        $noteOnCount = 0;
        $noteOffCount = 0;

        // Meta types that we always want to keep (regardless of time)
        $alwaysKeepTypes = ['Tempo', 'TimeSig', 'KeySig', 'Meta'];

        foreach ($track as $line) {
            if (!is_string($line)) continue;
            
            $parts = explode(' ', trim($line));
            if (count($parts) < 2) {
                // Keep events without clear time info (like TrkEnd)
                $filtered[] = $line;
                continue;
            }

            $timestamp = (int)($parts[0] ?? 0);
            $msgType = $parts[1] ?? '';

            // Keep meta events and essential playback settings
            if ($msgType === 'TrkEnd' || in_array($msgType, $alwaysKeepTypes, true) || str_contains($msgType, 'Meta')) {
                // For tempo/timesig, we might want to force them to time 0 if they were before the loop,
                // but for now we just keep them as they are and they will be shifted.
                $filtered[] = $line;
                continue;
            }

            // Keep control change events within range
            if (in_array($msgType, ['ProgramChange', 'ControlChange', 'Pitchwheel', 'PrCh', 'Pb', 'Par'], true)) {
                if ($timestamp >= $startTicks && $timestamp < $endTicks) {
                    $filtered[] = $line;
                }
                continue;
            }

            // Include note-on events strictly within range
            if ($msgType === 'On') {
                if ($timestamp >= $startTicks && $timestamp < $endTicks) {
                    $filtered[] = $line;
                    $noteOnCount++;
                }
                continue;
            }

            // Include note-off events that occur within range
            if ($msgType === 'Off') {
                if ($timestamp >= $startTicks && $timestamp < $endTicks) {
                    $filtered[] = $line;
                    $noteOffCount++;
                }
                continue;
            }
        }

        return $filtered;
    }

    /**
     * Shift all timestamps in a track by the given amount
     */
    private function shiftTrackTimes(array $track, int $shiftTicks): array
    {
        $shifted = [];

        foreach ($track as $line) {
            $parts = explode(' ', trim($line), 2);
            if (count($parts) < 2) {
                $shifted[] = $line;
                continue;
            }

            $timestamp = (int)$parts[0];
            $rest = $parts[1];

            $newTimestamp = max(0, $timestamp + $shiftTicks);
            $shifted[] = $newTimestamp . ' ' . $rest;
        }

        return $shifted;
    }

    /**
     * Ensure track ends with proper TrkEnd event
     */
    private function ensureTrkEnd(array $track): array
    {
        if (empty($track)) {
            return ['0 Meta TrkEnd'];
        }

        // Find the last event that isn't a TrkEnd
        $filtered = [];
        $maxTime = 0;

        foreach ($track as $line) {
            $parts = explode(' ', trim($line));
            $t = is_numeric($parts[0] ?? null) ? (int)$parts[0] : 0;
            if ($t > $maxTime) {
                $maxTime = $t;
            }
            if (!str_contains($line, 'TrkEnd')) {
                $filtered[] = $line;
            }
        }

        $filtered[] = $maxTime . ' Meta TrkEnd';
        return $filtered;
    }

    /**
     * Create a new MIDI asset with the extracted loop
     */
    private function createNewMidiAsset(
        Document $doc,
        array $loopTracks,
        MidiDuration $sourceMidi,
        string $fileName,
        ?User $user
    ): Asset {
        $out = new MidiDuration();
        $out->open($sourceMidi->getTimebase());
        $out->tracks = array_values($loopTracks); // Must be sequential for php-midi

        // Save to temporary file
        $tmpOut = tempnam(sys_get_temp_dir(), 'midi_loop_');
        if ($tmpOut === false) {
            throw new RuntimeException('Failed to create temporary file for loop export');
        }

        try {
            $out->saveMidFile($tmpOut);
            $binary = file_get_contents($tmpOut);

            if ($binary === false || $binary === '') {
                throw new RuntimeException('Failed to read loop MIDI from temporary file');
            }

            // Ensure .mid extension
            if (!str_ends_with($fileName, '.mid')) {
                $fileName .= '.mid';
            }

            // Store as new asset
            return $this->assetStorage->store(
                doc: $doc,
                originalName: $fileName,
                mime: 'audio/midi',
                size: strlen($binary),
                binary: $binary,
                user: $user,
            );
        } finally {
            if ($tmpOut && file_exists($tmpOut)) {
                @unlink($tmpOut);
            }
        }
    }

    /**
     * Append extracted loop to an existing MIDI asset
     */
    private function appendToExistingMidiAsset(
        Asset $targetAsset,
        array $loopTracks,
        int $timebase,
        ?User $user
    ): Asset {
        // Load existing target MIDI
        $targetTmp = null;
        try {
            $targetTmp = $this->assetStorage->createLocalTempFile($targetAsset);
            
            // We need a NEW MidiDuration instance to avoid overwriting the source one if reused
            $targetMidiObj = new MidiDuration();
            $targetMidiObj->importMid($targetTmp);

            // Find the maximum timestamp in target
            $maxTargetTime = $this->findMaxTimestamp($targetMidiObj);
            
            // Shift loop tracks to append after existing content
            // Als het doelbestand leeg is, start dan op 0. Anders na de laatste noot.
            $appendStartTicks = ($maxTargetTime > 0) ? ($maxTargetTime + $timebase) : 0;

            error_log(sprintf('Appending loop at tick %d (maxTargetTime was %d, timebase %d)', $appendStartTicks, $maxTargetTime, $timebase));

            $shiftedLoopTracks = [];
            foreach ($loopTracks as $tn => $track) {
                $shiftedLoopTracks[$tn] = $this->shiftTrackTimes($track, $appendStartTicks);
            }

            // Merge: append loop tracks to target
            $mergedTracks = [];
            $targetTrackCount = $targetMidiObj->getTrackCount();

            // Iterate through ALL tracks involved
            $maxTrackIndex = max($targetTrackCount - 1, count($loopTracks) > 0 ? max(array_keys($loopTracks)) : 0);

            for ($tn = 0; $tn <= $maxTrackIndex; $tn++) {
                $targetTrack = ($tn < $targetTrackCount) ? $targetMidiObj->getTrack($tn) : [];
                if (!is_array($targetTrack)) $targetTrack = [];

                // Remove TrkEnd from the end of target track
                $cleanTarget = [];
                foreach ($targetTrack as $line) {
                    if (!str_contains($line, 'TrkEnd')) {
                        $cleanTarget[] = $line;
                    }
                }

                if (isset($shiftedLoopTracks[$tn])) {
                    $loopTrack = $shiftedLoopTracks[$tn];
                    // Remove TrkEnd from loop track
                    $cleanLoop = [];
                    foreach ($loopTrack as $line) {
                        if (!str_contains($line, 'TrkEnd')) {
                            $cleanLoop[] = $line;
                        }
                    }
                    
                    // Merge and sort by timestamp
                    $merged = array_merge($cleanTarget, $cleanLoop);
                    usort($merged, function($a, $b) {
                        $aTime = (int) explode(' ', trim((string)$a))[0];
                        $bTime = (int) explode(' ', trim((string)$b))[0];
                        return $aTime <=> $bTime;
                    });
                    $mergedTracks[] = $this->ensureTrkEnd($merged);
                } else {
                    $mergedTracks[] = $this->ensureTrkEnd($cleanTarget);
                }
            }

            // Create output MIDI
            $out = new MidiDuration();
            $out->open($timebase);
            $out->tracks = $mergedTracks;

            // Save to temporary file
            $tmpOut = tempnam(sys_get_temp_dir(), 'midi_append_');
            if ($tmpOut === false) {
                throw new RuntimeException('Failed to create temporary file for loop append');
            }

            try {
                $out->saveMidFile($tmpOut);
                $binary = file_get_contents($tmpOut);

                if ($binary === false || $binary === '') {
                    throw new RuntimeException('Failed to read appended MIDI from temporary file');
                }

                // Update existing asset in storage
                $storagePath = $targetAsset->getStoragePath();
                if (!$storagePath) {
                    throw new RuntimeException('Target asset has no storage path');
                }

                $this->uploadsStorage->write($storagePath, $binary);
                $targetAsset->setSize(strlen($binary));
                $this->em->flush();

                return $targetAsset;
            } finally {
                if ($tmpOut && file_exists($tmpOut)) {
                    @unlink($tmpOut);
                }
            }
        } finally {
            if ($targetTmp && file_exists($targetTmp)) {
                @unlink($targetTmp);
            }
        }
    }

    /**
     * Find the maximum timestamp across all tracks
     */
    private function findMaxTimestamp(MidiDuration $midi): int
    {
        $maxTime = 0;

        for ($tn = 0; $tn < $midi->getTrackCount(); $tn++) {
            $track = $midi->getTrack($tn);
            if (!is_array($track)) continue;
            
            foreach ($track as $line) {
                $parts = explode(' ', trim((string)$line));
                if (is_numeric($parts[0] ?? null)) {
                    $maxTime = max($maxTime, (int)$parts[0]);
                }
            }
        }

        return $maxTime;
    }
}
