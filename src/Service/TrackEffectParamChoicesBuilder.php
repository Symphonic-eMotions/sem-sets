<?php
declare(strict_types=1);

namespace App\Service;

use App\Entity\DocumentTrack;
use App\Entity\EffectSettingsKeyValue;

final class TrackEffectParamChoicesBuilder
{
    /**
     * @return array<string, array<string, EffectSettingsKeyValue>>
     *  [
     *    'lowPassFilter' => ['cutoffFrequency' => kvEntity, 'resonance' => kvEntity],
     *    'reverbHall'    => [...],
     *  ]
     */
    public function build(DocumentTrack $track): array
    {
        $grouped = [];

        foreach ($track->getTrackEffects() as $trackEffect) {
            $effectSettings = $trackEffect->getEffectSettings(); // aannemende relatie
            if (!$effectSettings) { continue; }

            $effectName = null;
            $params = [];

            foreach ($effectSettings->getKeysValues() as $kv) {
                if ($kv->getType() === EffectSettingsKeyValue::TYPE_NAME) {
                    $effectName = $kv->getValue(); // "lowPassFilter"
                }
                if ($kv->getType() === EffectSettingsKeyValue::TYPE_PARAM) {
                    $params[$kv->getKeyName()] = $kv; // "cutoffFrequency" => entity
                }
            }

            if ($effectName && $params) {
                ksort($params);
                $grouped[$effectName] = $params;
            }
        }

        ksort($grouped);
        return $grouped;
    }
}
