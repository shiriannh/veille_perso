<?php

namespace App\Form;

use App\Entity\Source;
use App\Enum\FetchMode;
use App\Enum\SourceType as SourceTypeEnum;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SourceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom',
            ])
            ->add('type', ChoiceType::class, [
                'label' => 'Type',
                'choices' => SourceTypeEnum::cases(),
                'choice_label' => static fn (SourceTypeEnum $type): string => $type->label(),
            ])
            ->add('url', UrlType::class, [
                'label' => 'URL',
                'required' => false,
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'Source active',
                'required' => false,
            ])
            ->add('fetchMode', ChoiceType::class, [
                'label' => 'Mode d’import',
                'choices' => FetchMode::cases(),
                'choice_label' => static fn (FetchMode $fetchMode): string => $fetchMode->label(),
            ])
            ->add('feedUrl', UrlType::class, [
                'label' => 'URL du flux RSS',
                'required' => false,
                'help' => 'Utilisée uniquement si le mode d’import est RSS.',
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'Notes',
                'required' => false,
                'attr' => ['rows' => 4],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Source::class,
        ]);
    }
}
