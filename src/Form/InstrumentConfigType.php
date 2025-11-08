<?php
declare(strict_types=1);

namespace App\Form;

use App\Entity\Document;
use JetBrains\PhpStorm\Deprecated;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

#[Deprecated("use DocumentTrack() instead")]
final class InstrumentConfigType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // Gebruik de 'document' optie van parent; NIET $builder->getData() hier
        $docOption = $options['document'] ?? null;

        $builder
            ->add('trackId', HiddenType::class, [
                'required' => false,
            ])
            ->add('levels', CollectionType::class, [
                'entry_type' => IntegerType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'constraints' => [new Assert\All([new Assert\Type('integer'), new Assert\PositiveOrZero()])],
            ])
            ->add('midiFiles', CollectionType::class, [
                'entry_type' => MidiFileRefType::class,
                'entry_options' => [
                    'document' => $docOption, // belangrijk: document doorgeven
                ],
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,   // werkt op array/json
            'document' => null,
        ]);

        $resolver->setAllowedTypes('document', [Document::class, 'null']);
    }
}
