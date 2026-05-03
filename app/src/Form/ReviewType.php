<?php

namespace App\Form;

use App\Entity\Entry;
use App\Entity\Review;
use App\Enum\ReviewVerdict;
use App\Repository\EntryRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ReviewType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $currentEntry = $options['current_entry'];

        $builder
            ->add('entry', EntityType::class, [
                'label' => 'Entrée',
                'class' => Entry::class,
                'choice_label' => 'title',
                'query_builder' => static function (EntryRepository $repository) use ($currentEntry) {
                    $queryBuilder = $repository->createQueryBuilder('entry')
                        ->leftJoin('entry.review', 'review')
                        ->orderBy('entry.title', 'ASC');

                    if ($currentEntry instanceof Entry) {
                        return $queryBuilder
                            ->andWhere('review.id IS NULL OR entry = :currentEntry')
                            ->setParameter('currentEntry', $currentEntry);
                    }

                    return $queryBuilder->andWhere('review.id IS NULL');
                },
            ])
            ->add('summary', TextareaType::class, [
                'label' => 'Résumé',
                'required' => false,
                'attr' => ['rows' => 5],
            ])
            ->add('strengths', TextareaType::class, [
                'label' => 'Points forts',
                'required' => false,
                'attr' => ['rows' => 4],
            ])
            ->add('weaknesses', TextareaType::class, [
                'label' => 'Points faibles',
                'required' => false,
                'attr' => ['rows' => 4],
            ])
            ->add('personalNote', TextareaType::class, [
                'label' => 'Note personnelle',
                'required' => false,
                'attr' => ['rows' => 5],
            ])
            ->add('verdict', ChoiceType::class, [
                'label' => 'Verdict',
                'choices' => ReviewVerdict::cases(),
                'choice_label' => static fn (ReviewVerdict $verdict): string => $verdict->label(),
            ])
            ->add('score', IntegerType::class, [
                'label' => 'Score (0-100)',
                'required' => false,
                'attr' => ['min' => 0, 'max' => 100],
            ])
            ->add('isDraft', CheckboxType::class, [
                'label' => 'Brouillon',
                'required' => false,
            ])
            ->add('isAutoCreated', CheckboxType::class, [
                'label' => 'Créée automatiquement',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'current_entry' => null,
            'data_class' => Review::class,
        ]);

        $resolver->setAllowedTypes('current_entry', [Entry::class, 'null']);
    }
}
