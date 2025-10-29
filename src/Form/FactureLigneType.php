<?php
namespace App\Form;

use App\Entity\FactureLigne;
use App\Entity\Produit;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FactureLigneType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('produit', EntityType::class, [
                'class' => Produit::class,
                'choice_label' => 'nom',
                'placeholder' => 'Choisissez un produit',
                'choice_attr' => function(?Produit $produit) {
                    return $produit ? ['data-prix' => $produit->getPrix()] : [];
                },
                'attr' => ['class' => 'facture-produit-select'], // correspond au JS
            ])
            ->add('quantite', IntegerType::class, [
                'label' => 'Quantité',
                'attr' => ['class' => 'facture-quantite'], // correspond au JS
            ])
            ->add('prixUnitaire', MoneyType::class, [
                'currency' => 'MGA',
                'label' => 'Prix unitaire',
                'disabled' => true,
                'divisor' => 1,
                'scale' => 0,
                'attr' => ['class' => 'facture-prix-unitaire'], // correspond au JS
            ])
            ->add('prixTotal', MoneyType::class, [
                'currency' => 'MGA',
                'label' => 'Prix total',
                'disabled' => true,
                'scale' => 0,
                'attr' => ['class' => 'facture-total'], // correspond au JS
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => FactureLigne::class,
        ]);
    }
}