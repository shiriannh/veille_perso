<?php

namespace App\Form;

use App\Enum\MediaType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SynthesisReportGenerateType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Titre',
                'required' => false,
                'attr' => ['placeholder' => 'Synthese du jour'],
            ])
            ->add('fromDate', DateTimeType::class, [
                'label' => 'Depuis',
                'required' => false,
                'widget' => 'single_text',
            ])
            ->add('toDate', DateTimeType::class, [
                'label' => 'Jusqu a',
                'required' => false,
                'widget' => 'single_text',
            ])
            ->add('mediaTypes', ChoiceType::class, [
                'label' => 'Medias a inclure',
                'required' => false,
                'multiple' => true,
                'choices' => $this->mediaTypeChoices(),
                'help' => 'Laisser vide pour inclure tous les medias.',
            ])
            ->add('includeMaybeRelevant', CheckboxType::class, [
                'label' => 'Inclure aussi les entrees a verifier',
                'required' => false,
            ])
            ->add('maybeMinimumScore', IntegerType::class, [
                'label' => 'Score minimum pour les entrees a verifier',
                'required' => false,
                'attr' => ['min' => 0, 'max' => 100],
                'help' => 'Applique seulement aux decisions maybe_relevant.',
            ])
            ->add('minimumInterestLevel', IntegerType::class, [
                'label' => 'Interet minimum',
                'required' => false,
                'attr' => ['min' => 0, 'max' => 5],
            ])
            ->add('excludeCommercial', CheckboxType::class, [
                'label' => 'Exclure les contenus commerciaux',
                'required' => false,
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'Notes',
                'required' => false,
                'attr' => ['rows' => 3],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_token_id' => 'generate_synthesis_report',
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function mediaTypeChoices(): array
    {
        $choices = [];
        foreach (MediaType::cases() as $mediaType) {
            $choices[$mediaType->label()] = $mediaType->value;
        }

        return $choices;
    }
}
