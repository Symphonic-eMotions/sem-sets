<?php
declare(strict_types=1);

namespace App\Form;

use App\Entity\EffectSettings;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class EffectSettingsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $b, array $options): void
    {
        $b
            ->add('name', TextType::class, [
                'label' => 'Effect naam',
                'required' => false,
                'attr' => ['placeholder' => 'Bijv. Reverb warm'],
            ])

            // textarea met raw JSON; setter doet sanity check
            ->add('config', TextareaType::class, [
                'label' => 'Effect JSON',
                'required' => false,
                'attr' => [
                    'rows' => 6,
                    'class' => 'effect-json',
                    'placeholder' => '{ "type": "reverb", "mix": 0.3 }'
                ],
            ])

            ->add('position', HiddenType::class, [
                'required' => false,
                'attr' => ['class' => 'effect-position'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $r): void
    {
        $r->setDefaults([
            'data_class' => EffectSettings::class,
        ]);
    }
}
