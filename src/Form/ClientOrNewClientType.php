<?php

namespace App\Form;

use App\Entity\Client;
use App\Entity\Commande;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormError;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ClientOrNewClientType extends AbstractType
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
                    'Client existant' => 'existing',
                    'Nouveau client' => 'new',
                ],
                'expanded' => true,
                'multiple' => false,
                'mapped' => false,
                'data' => 'existing', 
                'attr' => ['class' => 'client-choice-radio']
            ])
            ->add('existingClient', EntityType::class, [
                'class' => Client::class,
                'choice_label' => 'nom',
                'label' => 'Sélectionner un client',
                'placeholder' => 'Choisissez un client',
                'required' => false,
                'mapped' => false,
                'attr' => ['class' => 'existing-client-block'],
            ])
            ->add('newClient', ClientType::class, [
                'label' => false,
                'required' => false,
                'mapped' => false,
                'attr' => ['class' => 'new-client-block'],
            ]);

        // =========================================
        // VALIDATION PRE_SUBMIT
        // =========================================
        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) {
            $data = $event->getData();
            $form = $event->getForm();

            if (null === $data) return;

            $choice = $data['choice'] ?? 'existing';

            // Validation pour client existant
            $existingClientValue = $data['existingClient'] ?? null;
            if ($choice === 'existing' && ($existingClientValue === null || $existingClientValue === '')) {
                $form->get('existingClient')->addError(new FormError('Vous devez sélectionner un client.'));
                $form->addError(new FormError('La sélection du client est invalide.'));
            }

            // Validation pour nouveau client
            $newClientName = $data['newClient']['nom'] ?? '';
            if ($choice === 'new' && empty(trim($newClientName))) {
                $form->get('newClient')->get('nom')->addError(new FormError('Le nom du nouveau client est obligatoire.'));
                $form->addError(new FormError('La saisie du nouveau client est invalide.'));
            }
        });

        // =========================================
        // LOGIQUE POST_SUBMIT
        // =========================================
        $builder->addEventListener(FormEvents::POST_SUBMIT, function(FormEvent $event) {
            $form = $event->getForm();

            if (!$form->isValid()) return;

            $commande = $form->getParent()->getData();
            if (!$commande instanceof Commande) return;

            $choice = $form->get('choice')->getData();

            if ($choice === 'existing') {
                $client = $form->get('existingClient')->getData();
                if ($client instanceof Client) {
                    $commande->setClient($client);
                }
            } elseif ($choice === 'new') {
                $client = $form->get('newClient')->getData();
                if ($client instanceof Client) {
                    if ($client->getNom() !== null || $client->getEmail() !== null) {
                        $this->entityManager->persist($client);
                        $commande->setClient($client);
                    }
                }
            }
        });

        // =========================================
        // PRE_SET_DATA pour l'édition
        // =========================================
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function(FormEvent $event) {
            $form = $event->getForm();
            $commande = $form->getParent()->getData();

            if ($commande && $commande->getClient() && $commande->getClient()->getId()) {
                $form->get('choice')->setData('existing');
                $form->get('existingClient')->setData($commande->getClient());
            } else {
                $form->get('choice')->setData('existing');
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