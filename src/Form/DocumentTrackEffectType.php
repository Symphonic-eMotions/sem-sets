<?php
declare(strict_types=1);

namespace App\Form;

use App\Entity\DocumentTrackEffect;
use App\Entity\EffectSettings;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
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
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => DocumentTrackEffect::class,
        ]);
    }
}
