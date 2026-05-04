<?php

namespace App\Form;

use App\Entity\Source;
use App\Entity\FetchModeReference;
use App\Entity\SourceTypeReference;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
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
            ->add('sourceTypeReference', EntityType::class, [
                'label' => 'Type',
                'class' => SourceTypeReference::class,
                'choice_label' => 'name',
                'query_builder' => static fn (EntityRepository $repository) => $repository->createQueryBuilder('reference')
                    ->andWhere('reference.isActive = true')
                    ->orderBy('reference.name', 'ASC'),
            ])
            ->add('url', UrlType::class, [
                'label' => 'URL',
                'required' => false,
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'Source active',
                'required' => false,
            ])
            ->add('fetchModeReference', EntityType::class, [
                'label' => 'Mode d’import',
                'class' => FetchModeReference::class,
                'choice_label' => 'name',
                'query_builder' => static fn (EntityRepository $repository) => $repository->createQueryBuilder('reference')
                    ->andWhere('reference.isActive = true')
                    ->orderBy('reference.name', 'ASC'),
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
