<?php
declare(strict_types=1);

namespace App\Form;

use App\Entity\DocumentTrackEffect;
use App\Entity\EffectSettings;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class DocumentTrackEffectType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('preset', EntityType::class, [
                'class' => EffectSettings::class,
                'choice_label' => 'name',
                'placeholder' => '— Kies effect —',
                'required' => true,
                'label' => false,
                'attr' => ['class' => 'effect-select'],
            ])
            ->add('position', HiddenType::class, [
                'required' => false,
                'attr' => ['class' => 'effect-position'],
            ])
            ->add('overrides', HiddenType::class, [
                // ✅ mapped (default). Dus Symfony leest uit DocumentTrackEffect::$overrides
                'required'   => false,
                'empty_data' => '', // zodat leeg ook echt leeg blijft
                'attr'       => [
                    'class' => 'js-effect-overrides-json',
                ],
            ]);

        // Transformer: array <-> JSON string
        $builder->get('overrides')->addModelTransformer(new CallbackTransformer(
        // model -> view
            fn ($value) => $value ? json_encode($value, JSON_UNESCAPED_UNICODE) : '',
            // view -> model
            fn ($value) => $value ? (json_decode((string)$value, true) ?: null) : null
        ));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => DocumentTrackEffect::class,
        ]);
    }
}
