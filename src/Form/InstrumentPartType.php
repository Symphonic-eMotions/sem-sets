<?php
declare(strict_types=1);

namespace App\Form;

use App\Entity\InstrumentPart;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class InstrumentPartType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('areaOfInterest', TextType::class, [
                'label'    => false,
                'required' => false,
                'mapped'   => false,
                'attr'     => ['class' => 'js-aoi-raw'],
            ])

            // loopsToGrid als raw JSON/CSV, unmapped
            ->add('loopsToGrid', TextType::class, [
                'label'    => false,
                'required' => false,
                'mapped'   => false,
                'attr'     => [
                    'class' => 'js-loops-grid-raw',
                ],
            ])

            // Synthetisch veld: bevat "effect:123" of "seq:velocity"
            ->add('targetBinding', HiddenType::class, [
                'required' => false,
                'mapped'   => false,
                'attr'     => [
                    'class' => 'js-target-binding-hidden',
                ],
            ])

            ->add('targetRangeLow', HiddenType::class, [
                'required' => false,
            ])
            ->add('targetRangeHigh', HiddenType::class, [
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => InstrumentPart::class,
        ]);
    }
}
