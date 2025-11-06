<?php

namespace App\Form;

use App\Entity\CommandeProduit;
use App\Entity\Produit;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType; // Use ajouté, c'est bien
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CommandeProduitType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('produit', EntityType::class, [
                'class' => Produit::class,
                'choice_label' => 'nom',
                'placeholder' => 'Choisissez un produit',
                'attr' => [
                    // On ajoute une classe pour pouvoir le cibler en JS plus tard si besoin
                    'class' => 'commande-produit-select' 
                ],
                'choice_attr' => function(?Produit $produit) {
                    return $produit ? ['data-prix' => $produit->getPrix()] : [];
                },
            ])
            ->add('quantite', IntegerType::class, [
                'attr' => ['min' => 1]
            ])
            
            // ÉTAPE 1 : AJOUTER LE CHAMP MANQUANT
            ->add('prixUnitaire', MoneyType::class, [
                'label' => 'Prix Unitaire',
                'currency' => 'MGA',
                'divisor' => 1, // Si vous stockez des floats. Si vous stockez des centimes, mettez 100.
                
                // L'OPTION LA PLUS IMPORTANTE
                'mapped' => false,

                'scale' => 0, 
                
                // C'est mieux de le désactiver pour l'utilisateur
                'disabled' => true, 

                'attr' => [
                    // Classe pour cibler ce champ en JavaScript
                    'class' => 'commande-produit-select',
                ],
            ]);

        // ÉTAPE 2 : VOTRE LOGIQUE D'ÉVÉNEMENT EST MAINTENANT CORRECTE
        $formModifier = function (FormEvent $event) {
            $form = $event->getForm();
            /** @var CommandeProduit|null $commandeProduit */
            $commandeProduit = $event->getData();

            // S'il n'y a pas encore de produit sélectionné (nouvelle ligne ou erreur), on ne fait rien
            if (!$commandeProduit || !$commandeProduit->getProduit()) {
                // On peut même mettre le prix à 0 pour être propre
                $form->get('prixUnitaire')->setData(0);
                return;
            }

            // On met à jour le champ prixUnitaire avec la valeur du produit
            $form->get('prixUnitaire')->setData($commandeProduit->getProduit()->getPrix());
        };

        // Votre listener va maintenant trouver le champ 'prixUnitaire' et fonctionnera
        $builder->addEventListener(FormEvents::PRE_SET_DATA, $formModifier);

        // OPTIONNEL MAIS RECOMMANDÉ : Mettre à jour le prix si l'utilisateur CHANGE le produit
        $builder->get('produit')->addEventListener(
            FormEvents::POST_SUBMIT,
            function (FormEvent $event) {
                // $event->getForm() est le champ 'produit'
                // $event->getForm()->getParent() est le formulaire 'CommandeProduitType'
                $form = $event->getForm()->getParent();
                $produit = $event->getForm()->getData();
                
                if ($produit) {
                    $form->get('prixUnitaire')->setData($produit->getPrix());
                } else {
                    $form->get('prixUnitaire')->setData(0);
                }
            }
        );
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => CommandeProduit::class,
        ]);
    }
}