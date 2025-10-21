<?php

namespace App\Form;

use App\Entity\Client;
use App\Entity\Devis;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ClientOrNewClientForDevisType extends AbstractType
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
            
        // NOUVEAU Listener POST_SUBMIT
        $builder->addEventListener(FormEvents::POST_SUBMIT, function(FormEvent $event) {
            $form = $event->getForm();

            // Récupération du Devis parent
            $devis = $form->getParent()->getData();
            if (!$devis instanceof Devis) {
                return;
            }

            // On récupère la valeur du bouton radio
            $choice = $form->get('choice')->getData();

            if ($choice === 'existing') {
                // On récupère l'objet Client directement depuis le champ du formulaire
                $client = $form->get('existingClient')->getData();
                if ($client instanceof Client) {
                    $devis->setClient($client);
                }
            } elseif ($choice === 'new') {
                // Ici, getData() retourne l'objet Client qui vient d'être créé et hydraté par le formulaire
                $client = $form->get('newClient')->getData();

                if ($client instanceof Client) {
                    // Petite vérification pour ne pas persister un client vide
                    if ($client->getNom() !== null || $client->getEmail() !== null) {
                        // Il faut persister le nouveau client car il n'est pas encore géré par Doctrine
                        $this->entityManager->persist($client);
                        $devis->setClient($client);
                    }
                }
            }
        });


        // Listener PRE_SET_DATA pour l'édition (celui-ci est correct et doit rester)
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function(FormEvent $event) {
            $form = $event->getForm();
            // On récupère l'objet Devis depuis le formulaire parent
            $devis = $form->getParent()->getData();

            // Si le devis existe et a un client associé
            if ($devis && $devis->getClient() && $devis->getClient()->getId()) {
                // On pré-sélectionne "Client existant"
                $form->get('choice')->setData('existing');
                // On pré-remplit le champ de sélection avec le client du devis
                $form->get('existingClient')->setData($devis->getClient());
            } else {
                // Par défaut, on peut laisser sur "Client existant"
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