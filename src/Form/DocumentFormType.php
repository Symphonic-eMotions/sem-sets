<?php

// src/Form/DocumentFormType.php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Document;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Validator\Constraints as Assert;

final class DocumentFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $e) {
            /** @var Document|null $doc */
            $doc = $e->getData();
            if (!$doc) return;

            $setLen = count((array) $doc->getLevelDurations());
            $expectedAreas = $doc->getGridColumns() * $doc->getGridRows();

            foreach ($doc->getTracks() as $t) {
                // --- levels interaction ---
                $levels = array_values((array) $t->getLevels());
                if ($setLen <= 0) {
                    $t->setLevels([]);
                } elseif (count($levels) > $setLen) {
                    $levels = array_slice($levels, 0, $setLen);
                } elseif (count($levels) < $setLen) {
                    $levels = array_merge($levels, array_fill(0, $setLen - count($levels), 0));
                }
                $levels = array_map(static fn($v) => (int)((int)$v === 1), $levels);
                $t->setLevels($levels);

                foreach ($t->getInstrumentParts() as $part) {
                    $aoi = array_values((array) $part->getAreaOfInterest());

                    if ($expectedAreas > 0) {
                        if (count($aoi) === 0) {
                            $aoi = array_fill(0, $expectedAreas, 1);
                        } elseif (count($aoi) > $expectedAreas) {
                            $aoi = array_slice($aoi, 0, $expectedAreas);
                        } elseif (count($aoi) < $expectedAreas) {
                            $aoi = array_merge($aoi, array_fill(0, $expectedAreas - count($aoi), 0));
                        }
                        $aoi = array_map(static fn($v) => (int)((int)$v === 1), $aoi);
                        $part->setAreaOfInterest($aoi);
                    }
                }
            }
        });


        /** @var Document $doc */
        $doc = $options['data'];

        $builder
            ->add('title', TextType::class, [
                'label' => 'Titel',
                'constraints' => [new Assert\NotBlank(), new Assert\Length(max:200)],
            ])
            ->add('slug', HiddenType::class, [
                'mapped' => false,
                'required' => false,
            ])
//            ->add('semVersion', ChoiceType::class, [
//                'choices' => array_combine(
//                    array_map(fn($c)=>$c->value, SemVersion::cases()),
//                    SemVersion::cases()
//                ),
//                'choice_label' => fn(SemVersion $v) => $v->value,
//                'choice_value' => fn(?SemVersion $v) => $v?->value,
//            ])
            ->add('published', CheckboxType::class, ['required' => false])

             // Eén select voor vierkante grids (unmapped)
             ->add('gridSize', ChoiceType::class, [
                 'mapped' => false,
                 'label' => 'Grid',
                 'choices' => $this->squareGridChoices(),
                 'placeholder' => 'Kies een grid',
                 'required' => true,
             ])
            // Preselecteer huidig grid bij edit
            ->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $e) {
                $doc = $e->getData(); // App\Entity\Document|null
                if (!$doc) {
                    return;
                }

                $cols = method_exists($doc, 'getGridColumns') ? $doc->getGridColumns() : null;
                $rows = method_exists($doc, 'getGridRows') ? $doc->getGridRows() : null;

                // selecteer alleen als het exact 1x1, 2x2 of 3x3 is
                if (is_int($cols) && is_int($rows) && $cols === $rows && $cols >= 1 && $cols <= 3) {
                    $e->getForm()->get('gridSize')->setData(sprintf('%dx%d', $cols, $rows));
                } else {
                    // Buiten bereik of niet-vierkant → laat placeholder staan
                    $e->getForm()->get('gridSize')->setData(null);
                }
            })

            ->add('setBPM', NumberType::class, [
                'label' => 'BPM',
                'property_path' => 'setBPM',
                'scale' => 2,
                'html5' => true,
                'input' => 'string',
                'rounding_mode' => \NumberFormatter::ROUND_HALFUP,
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Range(min: 0, max: 999.99),
                ],
            ])


            ->add('levelDurations', CollectionType::class, [
                'label' => 'Aantal levels',
                'entry_type' => NumberType::class,
                'entry_options' => [
                    'html5' => true,
                    'scale' => 0,
                    'required' => false,
                    'attr' => ['class' => 'ld-input', 'min' => 0, 'max' => 1, 'step' => 1, 'inputmode' => 'numeric'],
                    'constraints' => [
                        new Assert\NotNull(),
                        new Assert\Type('numeric'),
                        new Assert\Range(min: 0, max: 1),
                    ],
                ],
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'prototype' => true,
                'attr' => ['class' => 'level-durations'],
                'help' => 'Klik op de vierkante knoppen om 0/1 te togglen, voeg rijen toe of verwijder ze.',
            ])

            ->add('tracks', CollectionType::class, [
                'entry_type'    => DocumentTrackType::class,
                'entry_options' => ['document' => $doc],
                'allow_add'     => true,
                'allow_delete'  => true,
                'by_reference'  => false,
            ])

            ->add('midiFiles', FileType::class, [
                'label' => 'Upload MIDI-bestanden (.mid)',
                'mapped' => false,
                'required' => false,
                'multiple' => true,
                'constraints' => [
                    new Assert\All([
                        'constraints' => [
                            new Assert\File([
                                // browsers verschillen: vang de bekende varianten + octet-stream
                                'mimeTypes' => [
                                    'audio/midi',
                                    'audio/x-midi',
                                    'audio/mid',
                                    'application/x-midi',
                                    'application/octet-stream',
                                ],
                                // aanvullend: sta .mid/.midi extensies toe—dit helpt als MIME “vreemd” is
                                'extensions' => ['mid', 'midi'],
                                'maxSize' => '10M',
                                'mimeTypesMessage' => 'Upload een geldig MIDI-bestand (.mid/.midi).',
                            ]),
                        ],
                    ]),
                ],
//                'help' => 'Je kunt meerdere .mid/.midi files selecteren (max 10 MB per bestand).',
            ])
        ;
    }

    private function squareGridChoices(): array
    {
        // Label => Value
        return [
            '1 × 1' => '1x1',
            '2 × 2' => '2x2',
            '3 × 3' => '3x3',
        ];
    }

}
