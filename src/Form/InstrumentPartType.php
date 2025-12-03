<?php
declare(strict_types=1);

namespace App\Form;

use App\Entity\InstrumentPart;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
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

            ->add('targetRangeLow', HiddenType::class, [
                'required' => false,
            ])
            ->add('targetRangeHigh', HiddenType::class, [
                'required' => false,
            ])

            // Nieuw: minimal level 0..1, stepper
            ->add('minimalLevel', NumberType::class, [
                'label'    => 'Min. lvl',
                'required' => true,
                'scale'    => 3,
                'html5'    => true,
                'attr'     => [
                    'min'  => 0,
                    'max'  => 1,
                    'step' => 0.001,
                    'class'=> 'minimal-level-input',
                ],
            ])

            // Nieuw: ramp snelheden (up)
            ->add('rampSpeed', ChoiceType::class, [
                'label'       => false,
                'required'    => true,
                'placeholder' => false,
                'choices'     => [
                    '0.02' => 0.02,
                    '0.04' => 0.04,
                    '0.08' => 0.08,
                    '0.16' => 0.16,
                    '0.32' => 0.32,
                    '0.64' => 0.64,
                    '1.00' => 1.00,
                ],
                'attr'        => [
                    'class' => 'ramp-speed-select',
                ],
            ])

            // Nieuw: ramp snelheden (down)
            ->add('rampSpeedDown', ChoiceType::class, [
                'label'       => false,
                'required'    => true,
                'placeholder' => false,
                'choices'     => [
                    '0.02' => 0.02,
                    '0.04' => 0.04,
                    '0.08' => 0.08,
                    '0.16' => 0.16,
                    '0.32' => 0.32,
                    '0.64' => 0.64,
                    '1.00' => 1.00,
                ],
                'attr'        => [
                    'class' => 'ramp-speed-down-select',
                ],
            ])
        ;


        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => InstrumentPart::class,
        ]);
    }
}
