<?php
declare(strict_types=1);

namespace App\Form;

use App\Enum\SemVersion;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

final class NewDocumentFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Set naam',
                'constraints' => [new Assert\NotBlank(), new Assert\Length(max:200)],
            ])
            ->add('slug', HiddenType::class, [
                'mapped' => false,
                'required' => false,
            ])
            ->add('semVersion', ChoiceType::class, [
                'choices' => array_combine(
                    array_map(fn($c)=>$c->value, SemVersion::cases()),
                    SemVersion::cases()
                ),
                'choice_label' => fn(SemVersion $v) => $v->value,
                'choice_value' => fn(?SemVersion $v) => $v?->value,
            ])
        ;
    }
}
