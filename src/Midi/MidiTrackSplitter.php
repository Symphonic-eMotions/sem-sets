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

        // Basisnaam zonder extensie
        $baseName = pathinfo((string) $asset->getOriginalName(), PATHINFO_FILENAME);

        // Header/meta die we uit track0 willen behouden (tempo/maat/key)
        $headerMeta = $this->extractHeaderMeta($track0);

        $created = [];

        // We splitten alleen "muziektracks" (meestal 1..N-1)
        for ($tn = 0; $tn < $trackCount; $tn++) {
            $track = $midi->getTrack($tn);

            // Skip tracks zonder hoorbare noten
            if (!$this->trackHasNotes($track)) {
                continue;
            }

            $label = $this->extractTrackLabel($track) ?? ('track_' . $tn);
            $safeLabel = $this->slugForFilename($label);

            // Voorbeeld: "SongName-Right_Hand-01.mid"
            $prettyIndex = str_pad((string) $tn, 2, '0', STR_PAD_LEFT);
            $newOriginalName = sprintf('%s-%s-%s.mid', $baseName, $safeLabel, $prettyIndex);

            // Bouw 1-track MIDI: header-meta + track events
            $mergedTrack = $this->mergeHeaderMetaIntoTrack($headerMeta, $track);

            $out = new MidiDuration();
            $out->open($midi->getTimebase());
            $out->tracks = [
                $this->ensureTrkEnd($mergedTrack),
            ];

            $tmpOut = tempnam(sys_get_temp_dir(), 'midi_split_');
            if ($tmpOut === false) {
                throw new RuntimeException('Kon geen tijdelijk bestand aanmaken voor gesplitste MIDI.');
            }

            $out->saveMidFile($tmpOut);

            $binary = file_get_contents($tmpOut);
            @unlink($tmpOut);

            if ($binary === false || $binary === '') {
                throw new RuntimeException('Kon gesplitste MIDI niet lezen uit tmp bestand.');
            }

            $created[] = $this->assetStorage->store(
                doc: $doc,
                originalName: $newOriginalName,
                mime: 'audio/midi',
                size: strlen($binary),
                binary: $binary,
                user: $user
            );
        }

        return $created;
    }

    private function extractHeaderMeta(array $track0): array
    {
        $wanted = ['Tempo', 'TimeSig', 'KeySig'];
        $picked = [];

        foreach ($track0 as $line) {
            if (!str_contains($line, ' Meta ')) {
                continue;
            }

            $parts = explode(' ', trim($line));
            $type  = $parts[2] ?? null;
            if (!$type || !in_array($type, $wanted, true)) {
                continue;
            }

            // forceer tijd 0
            $parts[0] = '0';
            $picked[] = implode(' ', $parts);
        }

        // Dedup op type: eerste wint
        $out = [];
        $seen = [];
        foreach ($picked as $l) {
            $p = explode(' ', $l);
            $t = $p[2] ?? '';
            if ($t === '' || isset($seen[$t])) {
                continue;
            }
            $seen[$t] = true;
            $out[] = $l;
        }

        return $out;
    }

    private function mergeHeaderMetaIntoTrack(array $headerMeta, array $track): array
    {
        if (empty($headerMeta)) {
            return $track;
        }

        $stripTypes = ['Tempo', 'TimeSig', 'KeySig'];
        $clean = [];

        foreach ($track as $line) {
            if (str_contains($line, ' Meta ')) {
                $parts = explode(' ', trim($line));
                $type  = $parts[2] ?? null;
                if ($type && in_array($type, $stripTypes, true)) {
                    continue;
                }
            }
            $clean[] = $line;
        }

        return array_values(array_merge($headerMeta, $clean));
    }

    private function trackHasNotes(array $track): bool
    {
        foreach ($track as $line) {
            $parts = explode(' ', $line);
            $type  = $parts[1] ?? null;

            if ($type === 'Off') {
                return true;
            }

            if ($type === 'On') {
                // zoek v=...
                foreach ($parts as $p) {
                    if (str_starts_with($p, 'v=')) {
                        return ((int)substr($p, 2)) > 0;
                    }
                }
                // als er geen v= is: beschouw als noot (defensief)
                return true;
            }
        }
        return false;
    }

//    private function isEffectivelyEmptyTrack(array $track): bool
//    {
//        // Lege track of alleen TrkEnd/meta zonder inhoud
//        foreach ($track as $line) {
//            $parts = explode(' ', $line);
//            $type = $parts[1] ?? null;
//
//            if ($type === 'On' || $type === 'Off' || $type === 'Par' || $type === 'PrCh' || $type === 'Pb') {
//                return false;
//            }
//            if ($type === 'Meta' && ($parts[2] ?? null) !== 'TrkEnd') {
//                return false;
//            }
//        }
//        return true;
//    }

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
