<?php
declare(strict_types=1);

namespace App\Form;

use App\Entity\InstrumentPart;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class InstrumentPartType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('areaOfInterest', TextType::class, [
            'label'    => false,      // label doen we in Twig
            'required' => false,
            'mapped'   => false,      // raw string input zoals bij tracks-AOI
            'attr'     => [
                'class' => 'js-aoi-raw',
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => InstrumentPart::class,
        ]);
    }
}
