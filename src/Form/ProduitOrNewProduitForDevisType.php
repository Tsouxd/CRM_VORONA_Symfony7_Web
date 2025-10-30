<?php
namespace App\Form;

use App\Entity\Produit;
use App\Entity\Devis;
use App\Entity\DevisLigne;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

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

        // ===== VALIDATION : PRE_SUBMIT =====
        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) {
            $data = $event->getData();
            $form = $event->getForm();

            if ($data === null) {
                return;
            }

            $choice = $data['choice'] ?? 'existing';

            // Vérifie si le produit existant est vide
            if ($choice === 'existing' && (empty($data['existingProduit']) || $data['existingProduit'] === '')) {
                $form->get('existingProduit')->addError(new FormError('Vous devez sélectionner un produit existant.'));
                $form->addError(new FormError('Veuillez sélectionner un produit avant de valider.'));
            }

            // Vérifie si le nouveau produit n’a pas de nom
            if ($choice === 'new') {
                $newProduitNom = $data['newProduit']['nom'] ?? '';
                if (empty(trim($newProduitNom))) {
                    $form->get('newProduit')->get('nom')->addError(new FormError('Le nom du nouveau produit est obligatoire.'));
                    $form->addError(new FormError('Veuillez saisir les informations du nouveau produit.'));
                }
            }
        });

        // ===== AJOUT LIGNE DEVIS APRÈS VALIDATION =====
        $builder->addEventListener(FormEvents::POST_SUBMIT, function(FormEvent $event) {
            $form = $event->getForm();
            if (!$form->isValid()) {
                return; // si erreur => on ne fait rien
            }

            // Trouve le devis parent
            $parent = $form->getParent();
            $devis = null;
            while ($parent !== null) {
                $data = $parent->getData();
                if ($data instanceof Devis) {
                    $devis = $data;
                    break;
                }
                $parent = $parent->getParent();
            }
            if (!$devis instanceof Devis) {
                return;
            }

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