<?php

namespace App\Form;

use App\Entity\Depense;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use App\Entity\CategorieDepense;

class DepenseType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('description')
            ->add('montant', MoneyType::class)
            ->add('date', DateType::class, [
                'widget' => 'single_text',  // pour avoir un input type="date"
            ])
            ->add('categorie', EntityType::class, [
                'class' => \App\Entity\CategorieDepense::class,
                'choice_label' => 'nom',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Depense::class,
            'csrf_protection' => false,
        ]);
    }
}