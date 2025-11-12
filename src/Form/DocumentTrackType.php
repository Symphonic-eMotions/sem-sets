<?php
declare(strict_types=1);

namespace App\Form;

use App\Entity\Asset;
use App\Entity\Document;
use App\Entity\DocumentTrack;
use App\Repository\AssetRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class DocumentTrackType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
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
}
