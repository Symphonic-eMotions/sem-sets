<?php
declare(strict_types=1);

namespace App\Form;

use App\Entity\DocumentTrackEffect;
use App\Entity\EffectSettings;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
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
            ->add('overridesJson', HiddenType::class, [
                'mapped'   => false,
                'required' => false,
                'attr'     => [
                    'class' => 'js-effect-overrides-json',
                ],
            ])
            ->add('position', HiddenType::class, [
                'required' => false,
                'attr' => ['class' => 'effect-position'],
            ]);

        // 1) PRE_SET_DATA: init hidden field (full copy) bij load
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $e) {
            $te = $e->getData(); // DocumentTrackEffect|null
            $form = $e->getForm();

            if (!$te) {
                return;
            }

            // Als entity al overrides heeft: gebruik die als basis
            $overrides = $te->getOverrides();

            // Anders: init uit preset config (full copy)
            if (!is_array($overrides) || $overrides === []) {
                $preset = $te->getPreset();
                $config = $preset ? $preset->getConfig() : null;

                // Full copy: voor elke param uit config (behalve effectName) zet value
                $overrides = [];
                if (is_array($config)) {
                    foreach ($config as $key => $spec) {
                        if ($key === 'effectName') continue;
                        if (!is_array($spec)) continue;
                        if (!array_key_exists('value', $spec)) continue;

                        $overrides[$key] = ['value' => $spec['value']];
                    }
                }
            }

            // Hidden field vullen met JSON string
            $form->get('overridesJson')->setData(json_encode($overrides, JSON_UNESCAPED_UNICODE));
        });

        // 2) POST_SUBMIT: decode hidden JSON -> setOverrides() op entity
        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $e) {
            $te = $e->getData(); // DocumentTrackEffect|null
            $form = $e->getForm();

            if (!$te) {
                return;
            }

            $raw = (string) ($form->get('overridesJson')->getData() ?? '');

            if ($raw === '') {
                $te->setOverrides(null);
                return;
            }

            $decoded = json_decode($raw, true);
            if (!is_array($decoded)) {
                // Niet hard failen op form-level; JS is “source of truth”.
                // Je kunt later eventueel form error toevoegen.
                $te->setOverrides(null);
                return;
            }

            $te->setOverrides($decoded);
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => DocumentTrackEffect::class,
        ]);
    }
}
