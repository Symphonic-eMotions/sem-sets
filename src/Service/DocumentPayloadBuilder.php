<?php
declare(strict_types=1);

namespace App\Service;

use App\Entity\Document;
use App\Entity\DocumentTrack;
use App\Entity\EffectSettingsKeyValue;
use App\Entity\InstrumentPart;

final class DocumentPayloadBuilder
{
    public function __construct(
        private readonly PayloadBlockFactory $payloadBlockFactory,
    ) {}

    public function buildPayloadJson(Document $doc): string
    {
        $bpm            = (float) $doc->getSetBPM();
        $levelDurations = array_map('intval', $doc->getLevelDurations());

        // ------- niveauTrack (heeft dezelfde vorm als je werkende JSON) -------
        $niveauTrackDefaults = $this->payloadBlockFactory->build('niveauTrack');
        if (!is_array($niveauTrackDefaults)) {
            $niveauTrackDefaults = [];
        }

        $instrumentsConfig = [];

        /* @var DocumentTrack $t */
        foreach ($doc->getTracks() as $t) {

            // 1) LoopLength ophalen (altijd array<int>)
            $loopLengthBars = method_exists($t, 'getLoopLength')
                ? $t->getLoopLength()
                : [];

            $loopLengthBars = array_values(array_map('intval', $loopLengthBars));

            $beatsPerBar = (int) ($doc->getTimeSignatureNumerator() ?? 4);
            $loopLengthBeats = $this->loopLengthBarsToBeats($loopLengthBars, $beatsPerBar);

            // 1b) LoopsToGrid uit eerste InstrumentPart
            $loopsToGrid = [];
            $parts = $t->getInstrumentParts();

            if ($parts && !$parts->isEmpty()) {
                /** @var InstrumentPart $firstPart */
                $firstPart = $parts->first();
                $loopsToGrid = array_values(
                    array_map('intval', $firstPart->getLoopsToGrid() ?? [])
                );
            }

            // 1c) LoopsToLevel: één loop-index per level (nu default: overal loop 0 / A)
            $levels = $t->getLevels() ?? [];
            $loopsToLevel = [];
            if (!empty($levels)) {
                $loopsToLevel = array_fill(0, count($levels), 0);
            }

            // 2) MIDI-bestanden
            $midi      = [];
            $midiLabel = null;

            if ($t->getMidiAsset()) {
                $orig = $t->getMidiAsset()->getOriginalName();
                $name = pathinfo($orig, PATHINFO_FILENAME) ?: $orig;
                $ext  = strtolower(pathinfo($orig, PATHINFO_EXTENSION) ?: 'mid');

                // Mooie naam op basis van midi file
                $midiLabel = $this->humanizeLabel($name);

                $midi[] = [
                    'midiFileName' => $name,
                    'midiFileExt'  => $ext,
                    'loopLength'   => $loopLengthBeats,
                    'loopsToGrid'  => $loopsToGrid,
                    'loopsToLevel' => $loopsToLevel,
                ];
            }

            // 3) EXS preset → exsFiles-blok + label
            $exsPreset       = method_exists($t, 'getExsPreset') ? $t->getExsPreset() : null;
            $exsFiles        = null;
            $instrumentType  = null;
            $exsLabel        = null;

            if ($exsPreset) {
                $exsFiles = [[
                    'exsFileExt'  => 'exs',
                    'exsFileName' => $exsPreset,
                ]];
                $instrumentType = 'exsSampler';
                $exsLabel       = $this->humanizeLabel($exsPreset); // "Cellos Legato", ...
            }

            // 4) Track / instrument naam opbouwen
            $instrumentName = null;

            if ($exsLabel) {
                $instrumentName = $exsLabel;
            } elseif ($midiLabel) {
                $instrumentName = $midiLabel;
            } else {
                // fallback: trackId humanizen
                $instrumentName = $this->humanizeLabel($t->getTrackId() ?? '') ?? $t->getTrackId();
            }

            // -----------------------------------------------------------------
            // 4a) Effects-config + binding-map voor parts
            // -----------------------------------------------------------------
            $effectsConfig   = [];
            $effectParamMeta = [];

            // Bouw effect-config + mapping van param-ID -> meta
            foreach ($t->getTrackEffects() as $te) {
                $preset = $te->getPreset();
                if (!$preset) {
                    continue;
                }

                $config = $preset->getConfig(); // volledige effect-JSON
                if (is_array($config)) {
                    $effectsConfig[] = $config;
                }

                // Basisnaam
                $effectLabel = $preset->getName();

                // Zoek een evt. "mooie" naam (TYPE_NAME)
                if (method_exists($preset, 'getKeysValues')) {
                    foreach ($preset->getKeysValues() as $kv) {
                        if ($kv->getType() === EffectSettingsKeyValue::TYPE_NAME && $kv->getValue() !== null) {
                            $effectLabel = $kv->getValue();
                        }
                    }

                    // Verzamel param-meta per EffectSettingsKeyValue-id
                    foreach ($preset->getKeysValues() as $kv) {
                        if ($kv->getType() !== EffectSettingsKeyValue::TYPE_PARAM) {
                            continue;
                        }

                        $keyName = $kv->getKeyName();
                        $range   = null;

                        if (
                            is_array($config)
                            && array_key_exists($keyName, $config)
                            && is_array($config[$keyName])
                            && isset($config[$keyName]['range'])
                            && is_array($config[$keyName]['range'])
                        ) {
                            $range = array_values($config[$keyName]['range']);
                        }

                        $effectParamMeta[$kv->getId()] = [
                            'effectName' => $effectLabel,   // bv. "lowPassFilter"
                            'parameter'  => $keyName,      // bv. "cutoffFrequency"
                            'range'      => $range,        // bv. [10, 20000]
                        ];
                    }
                }
            }

            // TODO Binding voor velocity weer fixen?
            // Vaste sequencer-binding (voor "Velocity")
            $bindingMap['seq:velocity'] = [
                'nodeType'  => 'sequencer',
                'nodeName'  => '',
                'parameter' => 'velocity',
            ];

            // -----------------------------------------------------------------
            // 4b) InstrumentParts + damperTarget
            // -----------------------------------------------------------------
            $gridCells = max(
                1,
                (int) $doc->getGridColumns() * (int) $doc->getGridRows()
            );

            $partsConfig = [];

            /* @var InstrumentPart $part */
            foreach ($t->getInstrumentParts() as $part) {
                $aoiRaw = $part->getAreaOfInterest();
                $aoi    = $this->parseAreaOfInterest($aoiRaw, $gridCells);

                $damperTarget = null;
                $parameterKey = null;

                // EFFECT target
                if ($part->getTargetType() === InstrumentPart::TARGET_TYPE_EFFECT) {
                    $kv = $part->getTargetEffectParam();
                    if ($kv) {
                        $meta = $effectParamMeta[$kv->getId()] ?? null;
                        if ($meta) {
                            $damperTarget = [
                                'nodeType'  => 'effect',
                                'parameter' => $meta['parameter'],
                                'trackId'   => $t->getTrackId(),
                                'nodeName'  => $meta['effectName'],
                            ];

                            if ($meta['range'] !== null) {
                                $damperTarget['parameterRange'] = $meta['range'];
                            }

                            $parameterKey = $meta['parameter'];
                        }
                    }
                }

                // SEQUENCER target (bijv. velocity)
                elseif (
                    $part->getTargetType() === InstrumentPart::TARGET_TYPE_SEQUENCER
                    && $part->getTargetSequencerParam() === 'velocity'
                ) {
                    $damperTarget = [
                        'nodeType'  => 'sequencer',
                        'parameter' => 'velocity',
                        'trackId'   => $t->getTrackId(),
                        'nodeName'  => '',
                    ];

                    $parameterKey = 'velocity';
                }

                // Node settings toevoegen als er een target is
                if ($damperTarget !== null) {
                    $minimal  = $part->getMinimalLevel()  ?? 0.10;
                    $rampUp   = $part->getRampSpeed()     ?? 0.02;
                    $rampDown = $part->getRampSpeedDown() ?? 0.04;

                    $damperTarget['nodeSettings'] = [
                        'minimalLevel'  => (float) $minimal,
                        'rampSpeed'     => (float) $rampUp,
                        'rampSpeedDown' => (float) $rampDown,
                    ];
                }

                // Mooie naam voor de part
                $instrumentPartName = $this->buildPartName($instrumentName, $parameterKey);

                // Alleen damperTarget in JSON als hij echt bestaat
                $partConfig = [
                    'onlinePartId'       => $part->getPartId(),
                    'areaOfInterest'     => $aoi,
                    'instrumentPartName' => $instrumentPartName,
                ];

                if ($damperTarget !== null) {
                    $partConfig['damperTarget'] = $damperTarget;
                }

                $partsConfig[] = $partConfig;
            }

            $levels = array_values(array_map('intval', $t->getLevels()));
            $levels = array_keys(
                array_filter(
                    array_map('intval', $levels),
                    fn ($value) => $value === 1
                )
            );

            // 5) Basis track-config
            $trackConfig = [
                'onlineTrackId'   => $t->getId(),
                'trackId'         => $t->getTrackId(),
                'levels'          => $levels,
                'midiFiles'       => $midi,
                'instrumentType'  => $instrumentType,   // null of 'exsSampler'
                'exsFiles'        => $exsFiles,         // null of array
                'instrumentName'  => $instrumentName,
                'instrumentParts' => $partsConfig,
                'effects'         => $effectsConfig,
            ];

            // 6) Merge niveauTrack-blok PER TRACK
            // Defaults eerst, track-specifiek mag overschrijven
            $trackConfig = array_merge($niveauTrackDefaults, $trackConfig);

            // 7) Track toevoegen aan instrumentsConfig LIST
            $instrumentsConfig[] = $trackConfig;
        }

        // Basis payload op SET-niveau
        $payload = [
            'onlineDocumentId'  => $doc->getId(),
            'gridColumns'       => $doc->getGridColumns(),
            'gridRows'          => $doc->getGridRows(),
            'published'         => $doc->isPublished(),
            'semVersion'        => $doc->getSemVersion(),
            'setVersion'        => $doc->getHeadVersion()?->getVersionNr() ?? 1,
            'setName'           => $doc->getTitle(),
            'setBPM'            => $bpm,
            'setTimeSignature'  => $doc->getTimeSignatureNumerator(),
            'levelDurations'    => $levelDurations,
            'instrumentsConfig' => $instrumentsConfig,
        ];

        // ------- niveauSet (heeft dezelfde vorm als je werkende JSON) -------
        $niveauSet = $this->payloadBlockFactory->build('niveauSet');
        if (is_array($niveauSet)) {
            $payload = array_merge($payload, $niveauSet);
        }

        // ------- masterEffects (masterTrackEffects-blok) -------
        $masterEffectsSet = $this->payloadBlockFactory->build('masterEffects');
        if (is_array($masterEffectsSet)) {
            $payload = array_merge($payload, $masterEffectsSet);
        }

        return (string) json_encode(
            $payload,
            JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_PRETTY_PRINT
        );
    }

    /**
     * Normaliseert areaOfInterest naar een binaire array met vaste lengte.
     *
     * @param string|array<int,int>|null $raw
     * @param int                        $expectedCells totaal aantal grid-cellen (cols * rows)
     *
     * @return array<int,int> 0/1 per cel
     */
    private function parseAreaOfInterest(mixed $raw, int $expectedCells): array
    {
        $values = [];

        // 1) Als het al een array is (Doctrine json type), direct normaliseren
        if (is_array($raw)) {
            $values = array_map('intval', $raw);
        }

        // 2) Als het een string is (bijv. "[1,0,1]" of "1,0,1")
        elseif (is_string($raw)) {
            $str = trim($raw);
            if ($str !== '') {
                if (str_starts_with($str, '[')) {
                    // JSON-array
                    $decoded = json_decode($str, true);
                    if (is_array($decoded)) {
                        $values = array_map('intval', $decoded);
                    }
                } else {
                    // "1,0,1" → explode
                    $parts  = explode(',', $str);
                    $values = array_map('intval', $parts);
                }
            }
        }

        // Alles wat geen array/string is → lege array
        // (bijv. null, of foute data)
        // -> laten we gewoon als [] staan

        // 3) Binaire normalisatie: alles naar 0/1
        $values = array_map(static function ($v) {
            return (intval($v) === 1) ? 1 : 0;
        }, $values);

        // 4) Lengte corrigeren naar expectedCells
        if ($expectedCells > 0) {
            // te lang → afkappen
            $values = array_slice($values, 0, $expectedCells);

            $len = count($values);

            if ($len === 0) {
                // leeg → default: alles aan
                $values = array_fill(0, $expectedCells, 1);
            } elseif ($len < $expectedCells) {
                // te kort → aanvullen met nullen
                $values = array_pad($values, $expectedCells, 0);
            }
        }

        return array_values($values);
    }

    private function humanizeLabel(?string $value): ?string
    {
        if (!$value) {
            return null;
        }

        // 1) extensie weghalen (voor het geval er toch nog .mid of zo inzit)
        $base = pathinfo($value, PATHINFO_FILENAME) ?: $value;

        // 2) vervang scheidingstekens door spaties
        $base = str_replace(['-', '_', '.'], ' ', $base);

        // 3) camelCase naar spaties: "moonLeftHand" -> "moon Left Hand"
        $base = preg_replace('/(?<!^)([A-Z])/', ' $1', $base);

        // 4) meerdere spaties normaliseren
        $base = preg_replace('/\s+/', ' ', $base);

        // 5) trim + eerste letter hoofdletter, rest laten zoals is
        $base = trim($base);
        if ($base === '') {
            return null;
        }

        return ucfirst($base);
    }

    private function buildPartName(string $instrumentName, ?string $parameterKey): string
    {
        if ($parameterKey === null || $parameterKey === '') {
            return $instrumentName;
        }

        // hergebruik je humanizeLabel als die camelCase al netjes omzet
        $paramLabel = $this->humanizeLabel($parameterKey); // bv "lowPassCutoff" → "Low Pass Cutoff"

        return sprintf('%s', $paramLabel);
    }

    private function loopLengthBarsToBeats(array $bars, int $beatsPerBar): array
    {
        $beatsPerBar = max(1, $beatsPerBar);

        return array_values(array_map(
            static fn (int $barCount): int => max(0, $barCount) * $beatsPerBar,
            array_map('intval', $bars)
        ));
    }

}
