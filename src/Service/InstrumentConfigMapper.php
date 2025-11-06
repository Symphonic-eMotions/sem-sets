<?php
declare(strict_types=1);

namespace App\Service;

use App\Entity\Asset;

final class InstrumentConfigMapper
{
    /**
     * Normaliseert instrumentsConfig zodat midiFiles[] alleen scalars bevat:
     *  - { assetId: Asset, loopLength: [...] }  ->  { midiFileName, midiFileExt, loopLength }
     */
    public function normalize(array $instrumentsConfig): array
    {
        foreach ($instrumentsConfig as $i => $track) {
            if (!isset($track['midiFiles']) || !is_array($track['midiFiles'])) {
                continue;
            }

            $normalizedMidiFiles = [];
            foreach ($track['midiFiles'] as $mf) {
                // verwacht keys: assetId (Asset of null), loopLength (array<int>)
                $asset = $mf['assetId'] ?? null;
                $loopLength = $mf['loopLength'] ?? [];

                if ($asset instanceof Asset) {
                    $original = $asset->getOriginalName() ?? '';
                    $name = pathinfo($original, PATHINFO_FILENAME) ?: $original;
                    $ext  = strtolower(pathinfo($original, PATHINFO_EXTENSION) ?: 'mid');

                    $normalizedMidiFiles[] = [
                        'midiFileName' => $name,
                        'midiFileExt'  => $ext,
                        'loopLength'   => array_values(array_filter($loopLength, fn($v) => $v !== null && $v !== '')),
                    ];
                } else {
                    // geen asset gekozen: sla over of voeg lege structuur toe
                    // Kies wat jij wilt; hier: overslaan
                }
            }

            $instrumentsConfig[$i]['midiFiles'] = $normalizedMidiFiles;
        }

        return $instrumentsConfig;
    }
}
