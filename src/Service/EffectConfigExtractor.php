<?php
declare(strict_types=1);

namespace App\Service;

final class EffectConfigExtractor
{
    /**
     * @return array{effectName: string|null, params: string[]}
     */
    public function extract(array $config): array
    {
        $effectName = null;
        if (isset($config['effectName']) && is_string($config['effectName'])) {
            $effectName = $config['effectName'];
        }

        $params = [];
        foreach ($config as $key => $value) {
            if ($key === 'effectName') {
                continue;
            }

            // Alleen top-level keys; optioneel: filter op array met range/value
            // if (is_array($value) && array_key_exists('value', $value)) { ... }
            $params[] = (string) $key;
        }

        sort($params);

        return [
            'effectName' => $effectName,
            'params' => array_values(array_unique($params)),
        ];
    }
}
