<?php
declare(strict_types=1);

namespace App\Form;

use App\Entity\EffectSettingsKeyValue;
use App\Entity\InstrumentPart;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
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

            ->add('targetEffectParam', EntityType::class, [
                'class' => EffectSettingsKeyValue::class,
                'choice_label' => 'keyName',   // wordt door JS overschreven
                'placeholder' => '— kies parameter —',
                'required' => false,
                'query_builder' => function (EntityRepository $r) {
                    return $r->createQueryBuilder('kv')
                        ->andWhere('kv.type = :type')
                        ->setParameter('type', EffectSettingsKeyValue::TYPE_PARAM)
                        ->orderBy('kv.keyName', 'ASC');
                },
                'attr' => [
                    'class' => 'js-target-effect-param',
                ],
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
