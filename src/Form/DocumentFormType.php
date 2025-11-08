<?php

// src/Form/DocumentFormType.php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Document;
use App\Enum\SemVersion;
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
        /** @var Document $doc */
        $doc = $options['data']; // je huidige document

        $builder
            ->add('title', TextType::class, [
                'constraints' => [new Assert\NotBlank(), new Assert\Length(max:200)],
            ])
            ->add('slug', HiddenType::class, [
                'mapped' => false,
                'required' => false,
            ])
            ->add('semVersion', ChoiceType::class, [
                'choices' => array_combine(
                    array_map(fn($c)=>$c->value, SemVersion::cases()),
                    SemVersion::cases()
                ),
                'choice_label' => fn(SemVersion $v) => $v->value,
                'choice_value' => fn(?SemVersion $v) => $v?->value,
            ])
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
                'rounding_mode' => \NumberFormatter::ROUND_HALFUP, // <- Ook hier: "Multiple definitions exist for class 'NumberFormatter' "
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Range(min: 0, max: 999.99),
                ],
                'help' => '2 decimalen.',
            ])


            //            ->add('levelDurations', CollectionType::class, [
//                'label' => 'Level durations',
//                'entry_type' => NumberType::class,
//                'entry_options' => [
//                    'html5' => true,
//                    'scale' => 0,
//                    'constraints' => [
//                        new Assert\NotNull(),
//                        new Assert\Type('numeric'),
//                        new Assert\Range(min:1, max: 32767),
//                    ],
//                ],
//                'allow_add' => true,
//                'allow_delete' => true,
//                'by_reference' => false,
//                'prototype' => true,
//                'help' => 'Voeg per level een duur (in steps/maat) toe.',
//            ])

            ->add('tracks', CollectionType::class, [
                'entry_type'    => DocumentTrackType::class,
                'entry_options' => ['document' => $doc],
                'allow_add'     => true,
                'allow_delete'  => true,
                'by_reference'  => false,
            ])

            ->add('midiFiles', FileType::class, [
                'label' => 'MIDI-bestanden (.mid)',
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
                'help' => 'Je kunt meerdere .mid/.midi files selecteren (max 10 MB per bestand).',
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
