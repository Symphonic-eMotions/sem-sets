<?php

// src/Form/DocumentFormType.php

declare(strict_types=1);

namespace App\Form;

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
use Symfony\Component\Validator\Constraints as Assert;

final class DocumentFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $range1to4 = fn() => array_combine([1,2,3,4], [1,2,3,4]);

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

            ->add('gridColumns', ChoiceType::class, [
                'choices' => $range1to4(),
                'label' => 'Grid columns',
                'constraints' => [new Assert\NotBlank(), new Assert\Range(min:1, max:4)],
            ])

            ->add('gridRows', ChoiceType::class, [
                'choices' => $range1to4(),
                'label' => 'Grid rows',
                'constraints' => [new Assert\NotBlank(), new Assert\Range(min:1, max:4)],
            ])

            ->add('setBPM', NumberType::class, [
                'label' => 'BPM',
                'scale' => 2,
                'html5' => true,
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

            ->add('instrumentsConfig', CollectionType::class, [
                'entry_type' => InstrumentConfigType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'label' => 'Tracks',
                'required' => false,
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
}
