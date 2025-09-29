<?php

namespace App\Form;

use App\Entity\DevisLigne;
use App\Entity\Produit;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DevisLigneType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            // On remplace le champ 'produit' (EntityType) par un champ texte
            ->add('descriptionProduit', TextType::class, [
                'label' => 'Produit / Service',
            ])
            ->add('quantite', IntegerType::class, [
                'attr' => ['class' => 'devis-quantite', 'min' => 1], // On ajoute la classe pour le JS
            ])
            ->add('prixUnitaire', MoneyType::class, [
                'currency' => 'MGA',
                'divisor' => 1,
                'scale' => 0,
                'attr' => ['class' => 'devis-prix-unitaire'], // On ajoute la classe pour le JS
            ])
            ->add('prixTotal', MoneyType::class, [
                'currency' => 'MGA',
                'divisor' => 1,
                'scale' => 0,
                'attr' => ['class' => 'devis-prix-total', 'readonly' => true], // Total non modifiable
            ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => DevisLigne::class,
        ]);
    }
}