<?php

namespace App\Form;

use App\Entity\InterestProfileRule;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class InterestProfileRuleType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('category', ChoiceType::class, [
                'label' => 'Categorie',
                'choices' => array_flip(InterestProfileRule::CATEGORIES),
            ])
            ->add('value', TextType::class, [
                'label' => 'Valeur',
            ])
            ->add('weight', ChoiceType::class, [
                'label' => 'Poids',
                'choices' => array_flip(InterestProfileRule::WEIGHTS),
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'Active',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => InterestProfileRule::class,
        ]);
    }
}
