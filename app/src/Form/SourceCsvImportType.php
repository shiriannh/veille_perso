<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class SourceCsvImportType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('file', FileType::class, [
                'label' => 'Fichier CSV',
                'mapped' => false,
                'constraints' => [
                    new NotBlank(message: 'Choisis un fichier CSV.'),
                ],
            ])
            ->add('simulate', CheckboxType::class, [
                'label' => 'Simulation sans creation',
                'required' => false,
                'mapped' => false,
            ])
            ->add('duplicateRule', ChoiceType::class, [
                'label' => 'Controle des doublons',
                'mapped' => false,
                'choices' => [
                    'Nom seul' => 'name',
                    'Flux RSS seul' => 'feed_url',
                    'Nom + flux RSS' => 'name_feed_url',
                ],
                'data' => 'name',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_token_id' => 'source_csv_import',
        ]);
    }
}
