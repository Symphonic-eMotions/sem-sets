<?php
declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

final class InstrumentConfigType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('trackId', HiddenType::class, [
                'required' => false, // wij vullen ’m in bij submit als die leeg is
            ])
            ->add('levels', CollectionType::class, [
                'entry_type' => IntegerType::class,
                'allow_add' => true, 'allow_delete' => true, 'by_reference' => false,
                'constraints' => [new Assert\All([new Assert\Type('integer'), new Assert\PositiveOrZero()])],
            ])
            ->add('midiFiles', CollectionType::class, [
                'entry_type' => MidiFileRefType::class,
                'allow_add' => true, 'allow_delete' => true, 'by_reference' => false,
            ]);
    }
}
