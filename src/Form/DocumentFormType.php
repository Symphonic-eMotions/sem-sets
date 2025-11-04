<?php

// src/Form/DocumentFormType.php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

final class DocumentFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'constraints' => [new Assert\NotBlank(), new Assert\Length(max:200)],
            ])
            ->add('slug', HiddenType::class, [
                'mapped' => false,
                'required' => false,
            ])
            ->add('semVersion', TextType::class, [
                'constraints' => [new Assert\NotBlank(), new Assert\Regex('/^\d+\.\d+\.\d+$/')],
            ])
            ->add('published', CheckboxType::class, ['required' => false])

            ->add('midiFiles', FileType::class, [
                'label' => 'MIDI-bestanden (.mid)',
                'mapped' => false,
                'required' => false,
                'multiple' => true,
                'constraints' => [
                    new Assert\All([
                        'constraints' => [
                            new Assert\File([
                                // browsers verschillen: vang de bekende varianten + octet-stream
                                'mimeTypes' => [
                                    'audio/midi',
                                    'audio/x-midi',
                                    'audio/mid',
                                    'application/x-midi',
                                    'application/octet-stream',
                                ],
                                // aanvullend: sta .mid/.midi extensies toe—dit helpt als MIME “vreemd” is
                                'extensions' => ['mid', 'midi'],
                                'maxSize' => '10M',
                                'mimeTypesMessage' => 'Upload een geldig MIDI-bestand (.mid/.midi).',
                            ]),
                        ],
                    ]),
                ],
                'help' => 'Je kunt meerdere .mid/.midi files selecteren (max 10 MB per bestand).',
            ])
        ;
    }
}
