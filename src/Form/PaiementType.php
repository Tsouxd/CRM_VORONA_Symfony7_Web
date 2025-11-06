<?php

namespace App\Form;

use App\Entity\Paiement;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PaiementType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('montant', MoneyType::class, [
                'label' => 'Montant Payé',
                'currency' => 'MGA',
                'divisor' => 1,
                'scale' => 0,
                'required' => true,
            ])
            ->add('datePaiement', DateTimeType::class, [
                'widget' => 'single_text',
                'required' => true,
                'label' => 'Date du Paiement',
            ])
            ->add('statut', ChoiceType::class, [
                'label' => 'Statut du paiement',
                'choices' => [
                    'Effectué' => Paiement::STATUT_EFFECTUE,
                    'En attente' => Paiement::STATUT_EN_ATTENTE,
                    'À venir' => Paiement::STATUT_A_VENIR,
                    'Annulé' => Paiement::STATUT_ANNULE,
                ],
                //'placeholder' => 'Choisir un statut',
                'required' => true,
            ])
            ->add('referencePaiement', ChoiceType::class, [
                'label' => 'Méthode de Paiement',
                'choices' => [
                    'Espèce' => 'Espèce',
                    'Carte Bancaire' => 'Carte Bancaire',
                    'Mobile Money' => 'Mobile Money',
                    'Virement Bancaire' => 'Virement Bancaire',
                    'Chèque' => 'Chèque',
                ],
            ])
            ->add('detailsPaiement', TextType::class, [
                'label' => 'Détails / Référence',
                'required' => false,
                'help' => 'Ex: Mvola, Orange Money, N° de chèque, Réf. virement...',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Paiement::class,
        ]);
    }
}