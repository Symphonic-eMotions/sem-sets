<?php
declare(strict_types=1);

namespace App\Service;

final class EffectConfigMerger
{
    /**
     * Merge preset config met track overrides.
     *
     * Verwacht presetConfig zoals:
     * [
     *   "effectName" => "lowPassFilter",
     *   "cutoffFrequency" => ["range" => [10,20000], "value" => 20000],
     *   "resonance" => ["range" => [-20,40], "value" => -20],
     * ]
     *
     * Overrides zoals:
     * [
     *   "cutoffFrequency" => ["value" => 10344.83, "range" => [10,20000]], // range optioneel
     *   "resonance" => ["value" => 9.46],
     * ]
     */
    public function merge(?array $presetConfig, ?array $overrides): array
    {
        $presetConfig ??= [];
        $overrides ??= [];

        // Start met preset als basis
        $out = $presetConfig;

        foreach ($overrides as $key => $ov) {
            if ($key === 'effectName') {
                continue; // nooit overriden
            }
            if (!is_array($ov) || !array_key_exists('value', $ov)) {
                continue;
            }

            // Alleen overriden als preset deze param kent en het object-structuur heeft
            if (!array_key_exists($key, $presetConfig) || !is_array($presetConfig[$key])) {
                continue;
            }

            $spec = $presetConfig[$key];
            $value = $ov['value'];

            // Clamp op preset range als die bestaat
            if (isset($spec['range']) && is_array($spec['range']) && count($spec['range']) === 2) {
                $min = $spec['range'][0];
                $max = $spec['range'][1];

                if (is_numeric($min) && is_numeric($max) && is_numeric($value)) {
                    $value = max((float)$min, min((float)$max, (float)$value));
                }
            }

            // Schrijf alleen value terug; range blijft die van preset
            $out[$key]['value'] = $value;
        }

        return $out;
    }
}
