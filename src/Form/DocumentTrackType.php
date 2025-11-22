<?php
declare(strict_types=1);

namespace App\Form;

use App\Entity\Asset;
use App\Entity\Document;
use App\Entity\DocumentTrack;
use App\Entity\InstrumentPart;
use App\Repository\AssetRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class DocumentTrackType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $e) {
            /** @var DocumentTrack|null $track */
            $track = $e->getData();
            if (!$track) { return; }

            if ($track->getInstrumentParts()->count() === 0) {
                $track->addInstrumentPart(new InstrumentPart());
            }
        });

        /** @var Document|null $doc */
        $doc = $options['document'] ?? null;

        $builder->add('trackId', HiddenType::class, [
            'required' => false,
            'empty_data' => '',
        ])
        // Levels als collection<int>
        ->add('levels', CollectionType::class, [
            'label' => 'Levels',
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
        ])
        ->add('loopLength', TextType::class, [
            'label'    => 'Looplengte (maten)',
            'required' => false,
            'mapped'   => false,
            'attr'     => [
                'class' => 'js-loop-length-raw',
            ],
        ])
        ->add('loopLengthOverride', IntegerType::class, [
            'label'    => 'Basismaten',
            'required' => false,
            'mapped'   => true, // direct naar DocumentTrack::loopLengthOverride
            'attr'     => [
                'class' => 'js-loop-base-input',
                'min'   => 1,
                'step'  => 1,
            ],
        ])
            // InstrumentParts
        ->add('instrumentParts', CollectionType::class, [
            'entry_type'    => InstrumentPartType::class,
            'allow_add'     => true,
            'allow_delete'  => true,
            'by_reference'  => false,
            'prototype'     => true,
        ])

            // ENKELE midiAsset (EntityType)
        ->add('midiAsset', EntityType::class, [
            'class' => Asset::class,
            'choice_label' => fn(Asset $a) => $a->getOriginalName(),
            'choice_value' => 'id',
            'placeholder' => '— Geen bestand gekozen —',
            'required' => false,
            'query_builder' => function (AssetRepository $er) use ($doc) {
                // Als document niet doorgegeven is: lege lijst
                if (!$doc || !$doc->getId()) {
                    return $er->createQueryBuilder('a')->andWhere('1=0');
                }
                return $er->createQueryBuilder('a')
                    ->andWhere('a.document = :doc')->setParameter('doc', $doc)
                    ->orderBy('a.id', 'DESC');
            },
        ])

        ->add('exsPreset', ChoiceType::class, [
            'label'       => 'EXS preset',
            'required'    => false,
            'placeholder' => '— Geen preset —',
            'choices'     => array_combine(self::exsPresets(), self::exsPresets()),
            // label == value (AdvancedFM etc.)
        ]);


    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => DocumentTrack::class,
            'document' => null,
        ]);
        $resolver->setAllowedTypes('document', [Document::class, 'null']);
    }

    private static function exsPresets(): array
    {
        return [
            'AdvancedFM',
            'AfricanMarimba',
            'BassGuitarSmall',
            'BassoonSoloLegato',
            'BassoonSolo',
            'BoerenOrgel',
            'BrassyLead',
            'Celesta',
            'CellosLegato',
            'CellosLegatoSlowAttack',
            'CellosPizzicato',
            'ClarinetsLegato',
            'CowBellAgogo',
            'ElectricBass',
            'EXS808',
            'FatFilt',
            'FrenchHorns',
            'FrenchHornsLegato',
            'FullStringsPizzicato',
            'FluteSolo2',
            'GlassMarimba',
            'Glockenspiel',
            'harp',
            'JazzBassWav',
            'JP8Unifix',
            'JP8UnifixEuroPaPa',
            'JvRhodesMkV',
            'MellotronFlutes',
            'orchestralKitSmall',
            'PiccoloLegato',
            'PinchHarmonics',
            'smallYamahaPiano',
            'StandardKitSmall',
            'SuitcaseElectricPiano',
            'SynthPads',
            'TekkbrassNatuur',
            'TrancyHook',
            'TrumpetsSmall',
            'TubaSolo',
            'TubularBells',
            'TubularStation',
            'vibraphone',
            'ViolinsLegatoLongRelease',
            'Xylophone',
        ];
    }

}
