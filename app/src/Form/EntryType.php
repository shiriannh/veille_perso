<?php

namespace App\Form;

use App\Entity\Entry;
use App\Entity\Source;
use App\Enum\EntryStatus;
use App\Enum\MediaType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

class EntryType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('source', EntityType::class, [
                'label' => 'Source',
                'class' => Source::class,
                'choice_label' => 'name',
            ])
            ->add('title', TextType::class, [
                'label' => 'Titre',
            ])
            ->add('mediaType', ChoiceType::class, [
                'label' => "Type d'oeuvre",
                'choices' => MediaType::cases(),
                'choice_label' => static fn (MediaType $type): string => $type->label(),
            ])
            ->add('authorOrStudio', TextType::class, [
                'label' => 'Auteur / studio',
                'required' => false,
            ])
            ->add('originalUrl', UrlType::class, [
                'label' => 'URL originale',
                'required' => false,
            ])
            ->add('rawContent', TextareaType::class, [
                'label' => 'Contenu brut repéré',
                'required' => false,
                'attr' => ['rows' => 6],
            ])
            ->add('spottedAt', DateTimeType::class, [
                'label' => 'Repéré le',
                'input' => 'datetime_immutable',
                'widget' => 'single_text',
            ])
            ->add('interestLevel', IntegerType::class, [
                'label' => "Niveau d'intérêt (0-5)",
                'attr' => ['min' => 0, 'max' => 5],
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'Statut',
                'choices' => EntryStatus::cases(),
                'choice_label' => static fn (EntryStatus $status): string => $status->label(),
            ])
            ->add('personalTagsText', TextType::class, [
                'label' => 'Tags personnels',
                'mapped' => false,
                'required' => false,
                'help' => 'Séparer les tags par des virgules.',
            ]);

        $builder->addEventListener(FormEvents::PRE_SET_DATA, static function (FormEvent $event): void {
            $entry = $event->getData();
            if (!$entry instanceof Entry) {
                return;
            }

            $event->getForm()->get('personalTagsText')->setData($entry->getPersonalTagsAsString());
        });

        $builder->addEventListener(FormEvents::POST_SUBMIT, static function (FormEvent $event): void {
            $entry = $event->getData();
            if (!$entry instanceof Entry) {
                return;
            }

            $entry->setPersonalTagsFromString($event->getForm()->get('personalTagsText')->getData());
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Entry::class,
        ]);
    }
}
