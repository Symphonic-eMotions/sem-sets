<?php
declare(strict_types=1);

namespace App\Form;

use App\Entity\Asset;
use App\Entity\Document;
use App\Repository\AssetRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class MidiFileRefType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var Document|null $doc */
        $doc = $options['document'] ?? null;

        $builder
            ->add('assetId', EntityType::class, [
                'class' => Asset::class,
                'choice_label' => fn(Asset $a) => $a->getOriginalName(),
                'choice_value' => 'id',
                'placeholder' => $doc
                    ? '— Kies MIDI bestand uit assets van deze set —'
                    : '— Geen documentcontext —',
                'required' => true,
                'query_builder' => function (AssetRepository $er) use ($doc) {
                    // Wanneer er nog geen $doc is (bijv. bij "new" zonder ID), geef lege lijst terug
                    $qb = $er->createQueryBuilder('a')
                        ->andWhere('1=0');

                    if ($doc && $doc->getId()) {
                        $qb = $er->createQueryBuilder('a')
                            ->andWhere('a.document = :doc')
                            ->setParameter('doc', $doc)
                            ->orderBy('a.id', 'DESC');
                    }

                    return $qb;
                },
                'help' => 'Zie sectie “Assets uploaden” hierboven om .mid/.midi te uploaden.',
                'constraints' => [
                    new Assert\NotNull(message: 'Kies een MIDI-bestand of upload er eerst een.')
                ],
            ])
            ->add('loopLength', CollectionType::class, [
                'entry_type' => IntegerType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'constraints' => [new Assert\All([new Assert\Type('integer'), new Assert\Positive()])],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            // deze subform werkt op arrays binnen je JSON, dus geen data_class
            'data_class' => null,
            'document' => null, // wordt door parent gezet
        ]);

        $resolver->setAllowedTypes('document', [Document::class, 'null']);
    }
}
