<?php

namespace App\Form;

use App\Entity\DevisLigne;
use App\Entity\Produit;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DevisLigneType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('produit', EntityType::class, [
                'class' => Produit::class,
                'choice_label' => 'nom',
                'placeholder' => 'Choisissez un produit',
                'attr' => ['class' => 'devis-produit-select'],
                'choice_attr' => function(?Produit $produit) {
                    return $produit ? ['data-prix' => $produit->getPrix()] : [];
                },
            ])
            ->add('quantite', IntegerType::class, [
                'attr' => ['min' => 1, 'class' => 'devis-quantite']
            ])
            ->add('prixUnitaire', MoneyType::class, [
                'label' => 'Prix Unitaire',
                'currency' => 'MGA',
                'divisor' => 1,
                'mapped' => false,
                'scale' => 0,
                'disabled' => true,
                'attr' => ['class' => 'devis-prix-unitaire'],
            ])
            ->add('total', MoneyType::class, [
                'label' => 'Total',
                'currency' => 'MGA',
                'divisor' => 1,
                'mapped' => false,
                'scale' => 0,
                'disabled' => true,
                'attr' => ['class' => 'devis-total'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => DevisLigne::class,
        ]);
    }
}