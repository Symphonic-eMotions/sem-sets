<?php
declare(strict_types=1);

namespace App\Form;

use App\Entity\Asset;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

final class MidiFileRefType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('assetId', EntityType::class, [
                'class' => Asset::class,
                'choice_label' => fn(Asset $a) => $a->getOriginalName(),
                'choice_value' => 'id',
                'placeholder' => '— Kies MIDI bestand —',
                'constraints' => [new Assert\NotNull()],
            ])
            ->add('loopLength', CollectionType::class, [
                'entry_type' => IntegerType::class,
                'allow_add' => true, 'allow_delete' => true, 'by_reference' => false,
                'constraints' => [new Assert\All([new Assert\Type('integer'), new Assert\Positive()])],
            ]);
    }
}
