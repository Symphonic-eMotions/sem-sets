<?php
declare(strict_types=1);

namespace App\Form;

use App\Entity\EffectSettings;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class EffectSettingsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Effect naam',
                'required' => false,
                'empty_data' => '',
                'attr' => ['placeholder' => 'Bijv. Reverb warm'],
            ])

            // textarea met raw JSON; setter doet sanity check
            ->add('config', TextareaType::class, [
                'label' => 'Effect JSON',
                'required' => false,
                'mapped' => false,
                'empty_data' => '',
                'attr' => [
                    'rows' => 6,
                    'class' => 'effect-json',
                    'placeholder' => '{
    "cutoffFrequency": {
        "range": [10, 20000],
        "value": 20000
    },
    "effectName": "lowPassFilter",
    "resonance": {
        "range": [-20, 40],
        "value": -20
    }
}'
                ],
            ])
            // Be compatible with position
            ->add('position', HiddenType::class, [
                'required' => false,
                'attr' => [
                    'class' => 'effect-position',
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => EffectSettings::class,
        ]);
    }
}
