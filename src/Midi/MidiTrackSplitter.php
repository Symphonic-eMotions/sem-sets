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

final class MidiTrackSplitter
{
    public function __construct(
        private readonly AssetStorage $assetStorage,
        private readonly PhpMidiFile $phpMidiFile, // we willen getInnerMidi()
    ) {}

    /**
     * Split een MIDI-asset in losse track-bestanden en sla ze als nieuwe Assets op.
     *
     * @return Asset[] nieuw aangemaakte assets
     *
     * @throws FilesystemException
     */
    public function splitAssetIntoTrackAssets(Document $doc, Asset $asset, ?User $user): array
    {
        $tmp = $this->assetStorage->createLocalTempFile($asset);

        // Laad in MidiDuration via adapter
        $this->phpMidiFile->loadFromFile($tmp);
        $midi = $this->phpMidiFile->getInnerMidi();

        $trackCount = $midi->getTrackCount();
        if ($trackCount <= 1) {
            return [];
        }

        $track0 = $midi->getTrack(0);
        $track0HasNotes = $this->trackHasNotes($track0);

        // Basisnaam zonder extensie
        $baseName = pathinfo($asset->getOriginalName(), PATHINFO_FILENAME);

        $created = [];

        // Meestal wil je tracks 1…N-1 splitsen.
        // Als track 0 ook noten bevat, nemen we die ook mee als "track_0".
        $start = $track0HasNotes ? 0 : 1;

        for ($tn = $start; $tn < $trackCount; $tn++) {
            $track = $midi->getTrack($tn);

            // Skip lege tracks (behalve als je ze juist wil forceren)
            if ($this->isEffectivelyEmptyTrack($track)) {
                continue;
            }

            $label = $this->extractTrackLabel($track) ?? ('track_' . $tn);
            $safeLabel = $this->slugForFilename($label);

            // Voorbeeld: "SongName__02__Piano_RH.mid"
            $prettyIndex = str_pad((string)$tn, 2, '0', STR_PAD_LEFT);
            $newOriginalName = sprintf('%s__%s__%s.mid', $baseName, $prettyIndex, $safeLabel);

            // Bouw nieuwe MidiDuration met track0 (optioneel) + deze track
            $out = new MidiDuration();
            $out->open($midi->getTimebase());

            // Belangrijk: tracks-array direct zetten (library gebruikt public var $tracks)
            $outTracks = [];

            if (!$track0HasNotes) {
                $outTracks[] = $this->ensureTrkEnd($track0);
            }
            $outTracks[] = $this->ensureTrkEnd($track);

            $out->tracks = $outTracks;

            // Schrijf naar tijdelijk bestand en store als Asset
            $tmpOut = tempnam(sys_get_temp_dir(), 'midi_split_');
            if ($tmpOut === false) {
                throw new RuntimeException('Kon geen tijdelijk bestand aanmaken voor gesplitste MIDI.');
            }

            $out->saveMidFile($tmpOut);

            $binary = file_get_contents($tmpOut);
            if ($binary === false || $binary === '') {
                throw new RuntimeException('Kon gesplitste MIDI niet lezen uit tmp bestand.');
            }

            $created[] = $this->assetStorage->store(
                doc: $doc,
                originalName: $newOriginalName,
                mime: 'audio/midi', // of 'audio/mid' / 'application/octet-stream' als je wilt
                size: strlen($binary),
                binary: $binary,
                user: $user
            );
        }

        return $created;
    }

    private function trackHasNotes(array $track): bool
    {
        foreach ($track as $line) {
            // "123 On ch=1 n=60 v=100"
            $parts = explode(' ', $line);
            if (($parts[1] ?? null) === 'On' || ($parts[1] ?? null) === 'Off') {
                return true;
            }
        }
        return false;
    }

    private function isEffectivelyEmptyTrack(array $track): bool
    {
        // Lege track of alleen TrkEnd/meta zonder inhoud
        foreach ($track as $line) {
            $parts = explode(' ', $line);
            $type = $parts[1] ?? null;

            if ($type === 'On' || $type === 'Off' || $type === 'Par' || $type === 'PrCh' || $type === 'Pb') {
                return false;
            }
            if ($type === 'Meta' && ($parts[2] ?? null) !== 'TrkEnd') {
                return false;
            }
        }
        return true;
    }

    private function extractTrackLabel(array $track): ?string
    {
        $instrName = null;

        foreach ($track as $line) {
            // zoek "Meta TrkName" eerst
            if (str_contains($line, ' Meta TrkName ')) {
                return $this->extractQuotedText($line);
            }
            // anders InstrName als fallback
            if ($instrName === null && str_contains($line, ' Meta InstrName ')) {
                $instrName = $this->extractQuotedText($line);
            }
        }

        return $instrName;
    }

    private function extractQuotedText(string $line): ?string
    {
        $start = strpos($line, '"');
        $end   = strrpos($line, '"');
        if ($start === false || $end === false || $end <= $start) {
            return null;
        }
        $txt = substr($line, $start + 1, $end - $start - 1);
        $txt = trim($txt);
        return $txt !== '' ? $txt : null;
    }

    private function ensureTrkEnd(array $track): array
    {
        if (empty($track)) {
            return ['0 Meta TrkEnd'];
        }

        $last = $track[count($track) - 1];
        if (str_contains($last, ' Meta TrkEnd')) {
            return $track;
        }

        // Gebruik tijd van laatste event
        $parts = explode(' ', $last);
        $t = isset($parts[0]) ? (int)$parts[0] : 0;
        $track[] = $t . ' Meta TrkEnd';
        return $track;
    }

    private function slugForFilename(string $s): string
    {
        $s = trim($s);
        $s = preg_replace('/\s+/', '_', $s) ?? $s;
        $s = preg_replace('/[^A-Za-z0-9._-]/', '_', $s) ?? $s;
        $s = preg_replace('/_+/', '_', $s) ?? $s;
        $s = trim($s, '._-');
        return $s !== '' ? $s : 'track';
    }
}
