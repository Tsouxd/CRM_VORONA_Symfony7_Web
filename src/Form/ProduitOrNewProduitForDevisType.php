<?php
// src/Form/ProduitOrNewProduitForDevisType.php
namespace App\Form;

use App\Entity\Produit;
use App\Entity\Devis;
use App\Entity\DevisLigne;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;

class ProduitOrNewProduitForDevisType extends AbstractType
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('choice', ChoiceType::class, [
                'label' => 'Choisir une option',
                'choices' => [
                    'Produit existant' => 'existing',
                    'Nouveau produit' => 'new',
                ],
                'expanded' => true,
                'multiple' => false,
                'mapped' => false,
                'data' => 'existing',
                'attr' => ['class' => 'produit-choice-radio']
            ])
            ->add('existingProduit', EntityType::class, [
                'class' => Produit::class,
                'choice_label' => 'nom',
                'label' => 'Sélectionner un produit',
                'placeholder' => 'Choisissez un produit',
                'required' => false,
                'mapped' => false,
                'attr' => ['class' => 'existing-produit-block'],
            ])
            ->add('newProduit', \App\Form\ProduitType::class, [
                'label' => false,
                'required' => false,
                'mapped' => false,
                'attr' => ['class' => 'new-produit-block'],
            ])
            ->add('quantite', IntegerType::class, [
                'label' => 'Quantité commandée',
                'required' => true,
                'mapped' => false,
                'attr' => ['class' => 'quantite-field', 'min' => 1]
            ])
            ->add('prixTotal', HiddenType::class, [
                'label' => 'Prix total (MGA)',
                'required' => false,
                'mapped' => false,
                'attr' => [
                    'readonly' => true,
                    'class' => 'prix-total-field',
                    'style' => 'background-color:#f9f9f9;'
                ]
            ]);

        // --- Helper pour remonter au Devis parent ---
        $findDevisFromForm = function($form) {
            $parent = $form->getParent();
            while ($parent !== null) {
                $data = $parent->getData();
                if ($data instanceof Devis) return $data;
                $parent = $parent->getParent();
            }
            return null;
        };

        // --- POST_SUBMIT : création automatique de DevisLigne ---
        $builder->addEventListener(FormEvents::POST_SUBMIT, function(FormEvent $event) use ($findDevisFromForm) {
            $form = $event->getForm();
            $devis = $findDevisFromForm($form);
            if (!$devis instanceof Devis) return;

            $choice = $form->get('choice')->getData();
            $quantite = $form->get('quantite')->getData() ?? 1;

            if ($choice === 'existing') {
                $produit = $form->get('existingProduit')->getData();
                if ($produit instanceof Produit) {
                    $prix = $produit->getPrix();
                    $prixTotal = $quantite * $prix;

                    $ligne = new DevisLigne();
                    $ligne->setDevis($devis)
                          ->setDescriptionProduit($produit->getNom())
                          ->setQuantite($quantite)
                          ->setPrixUnitaire($prix)
                          ->setPrixTotal($prixTotal);

                    $this->entityManager->persist($ligne);
                }
            } elseif ($choice === 'new') {
                $produit = $form->get('newProduit')->getData();
                if ($produit instanceof Produit && $produit->getNom()) {
                    // Persister le produit
                    $this->entityManager->persist($produit);

                    $prix = $produit->getPrix();
                    $prixTotal = $quantite * $prix;

                    $ligne = new DevisLigne();
                    $ligne->setDevis($devis)
                          ->setDescriptionProduit($produit->getNom())
                          ->setQuantite($quantite)
                          ->setPrixUnitaire($prix)
                          ->setPrixTotal($prixTotal);

                    $this->entityManager->persist($ligne);
                }
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
        ]);
    }
}
