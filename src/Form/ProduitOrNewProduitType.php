<?php

namespace App\Form;

use App\Entity\Produit;
use App\Entity\CommandeProduit;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ProduitOrNewProduitType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('choice', ChoiceType::class, [
                'label' => 'Type de produit',
                'choices' => [
                    'Produit existant' => 'existing',
                    'Nouveau produit' => 'new',
                ],
                'expanded' => true,
                'multiple' => false,
                'mapped' => false,
                'data' => 'existing',
                'row_attr' => ['class' => 'product-choice-radio'] // Appliquer la classe au conteneur
            ])
            ->add('existingProduit', EntityType::class, [
                'class' => Produit::class,
                'choice_label' => 'nom',
                'label' => 'Sélectionner un produit',
                'placeholder' => 'Choisissez un produit',
                'required' => false,
                'mapped' => false,
                'row_attr' => ['class' => 'existing-product-block'] // Appliquer la classe au conteneur
            ])
            ->add('newProduit', ProduitType::class, [
                'label' => false,
                'required' => false,
                'mapped' => false,
                'row_attr' => ['class' => 'new-product-block'] // Appliquer la classe au conteneur
            ]);

        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) {
            $data = $event->getData();
            $form = $event->getForm();
            /** @var CommandeProduit $commandeProduit */
            $commandeProduit = $form->getParent()->getData();

            if (!$commandeProduit instanceof CommandeProduit) {
                return;
            }

            if (isset($data['choice']) && $data['choice'] === 'existing') {
                if (!empty($data['existingProduit'])) {
                    $produit = $form->get('existingProduit')->getConfig()->getOption('em')->getRepository(Produit::class)->find($data['existingProduit']);
                    $commandeProduit->setProduit($produit);
                }
            } elseif (isset($data['choice']) && $data['choice'] === 'new') {
                $newProduit = new Produit();
                $newProduit->setNom($data['newProduit']['nom'] ?? null);
                $newProduit->setPrix(($data['newProduit']['prix'] ?? 0) * 100);
                $newProduit->setStock($data['newProduit']['stock'] ?? 0);
                $commandeProduit->setProduit($newProduit);
            }
        });

        $builder->addEventListener(FormEvents::PRE_SET_DATA, function(FormEvent $event) {
            /** @var CommandeProduit $commandeProduit */
            $commandeProduit = $event->getForm()->getParent()->getData();
            $form = $event->getForm();

            if ($commandeProduit && $commandeProduit->getProduit() && $commandeProduit->getProduit()->getId()) {
                $form->get('choice')->setData('existing');
                $form->get('existingProduit')->setData($commandeProduit->getProduit());
            } else {
                $form->get('choice')->setData('existing');
            }
        });

        // --- NOUVEAU LISTENER POST_SUBMIT ---
        // C'est lui qui résout le problème de logique.
        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) {
            $form = $event->getForm();
            /** @var CommandeProduit $commandeProduit */
            $commandeProduit = $form->getParent()->getData();
            $produit = $commandeProduit->getProduit();

            // Si un produit a été assigné ET qu'il n'a pas encore d'ID,
            // cela signifie que c'est un NOUVEAU produit.
            if ($produit !== null && $produit->getId() === null) {
                // On le persiste et on le flush IMMÉDIATEMENT.
                $this->entityManager->persist($produit);
                $this->entityManager->flush();

                // À ce stade, le produit existe en base de données avec son stock initial.
                // Le reste du processus de formulaire peut continuer normalement.
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => null]);
    }
}