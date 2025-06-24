<?php

namespace App\Form;

use App\Entity\Revenu;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RevenuType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('description')
            ->add('montant', MoneyType::class, [
                'currency' => 'MGA',
            ])
            ->add('date', DateType::class, [
                'widget' => 'single_text',
            ])
            ->add('categorie', EntityType::class, [
                'class' => \App\Entity\CategorieRevenu::class,
                'choice_label' => 'nom',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Revenu::class,
            'csrf_protection' => false,
        ]);
    }
}