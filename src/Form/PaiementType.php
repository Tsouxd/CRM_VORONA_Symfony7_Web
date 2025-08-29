<?php

namespace App\Form;

use App\Entity\Paiement;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PaiementType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('montant', MoneyType::class, [
                'label' => 'Montant Payé',
                'currency' => 'MGA', // Ariary
                'divisor' => 1, // Si vous stockez en float, le diviseur est 1
                'scale' => 0, 
            ])
            ->add('datePaiement', DateTimeType::class, [
                'widget' => 'single_text',
                'required' => true,
            ])

            ->add('statut', ChoiceType::class, [
                'label' => 'Statut',
                'choices' => [
                    'Effectué' => 'effectué',
                    'En attente' => 'en attente',
                    'Annulé' => 'annulé',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Paiement::class,
        ]);
    }
}