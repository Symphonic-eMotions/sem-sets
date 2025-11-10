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
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class DocumentTrackType extends AbstractType
{
    public function buildForm(FormBuilderInterface $b, array $opt): void
    {
        /** @var Document|null $doc */
        $doc = $opt['document'] ?? null;

        $b->add('trackId', HiddenType::class, [
            'required' => false,
            'empty_data' => '',
        ])
        // Levels als collection<int>
        ->add('levels', CollectionType::class, [
            'entry_type' => IntegerType::class,
            'allow_add' => true,
            'allow_delete' => true,
            'by_reference' => false,
            'required' => false,
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

    public function configureOptions(OptionsResolver $r): void
    {
        $r->setDefaults([
            'data_class' => DocumentTrack::class,
            'document' => null,  // <- belangrijk
        ]);
        $r->setAllowedTypes('document', [Document::class, 'null']);
    }
}
