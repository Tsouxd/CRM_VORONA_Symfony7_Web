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
use Symfony\Component\Form\FormError;

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
            
        // =======================================================
        // == VALIDATION DANS PRE_SUBMIT (Rendue plus explicite) ==
        // =======================================================
        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) {
            $data = $event->getData();
            $form = $event->getForm();

            // Si $data est null (cas rare), on ne fait rien.
            if (null === $data) {
                return;
            }

            $choice = $data['choice'] ?? 'existing';

            // On cible explicitement le cas du placeholder qui envoie une chaîne vide.
            $existingClientValue = $data['existingClient'] ?? null;
            if ($choice === 'existing' && ($existingClientValue === null || $existingClientValue === '')) {
                $form->get('existingClient')->addError(new FormError('Vous devez sélectionner un client dans la liste.'));
                // On attache un drapeau pour que le POST_SUBMIT sache que la validation a échoué.
                $form->addError(new FormError('La sélection du client est invalide.')); // Erreur générale sur le form
            }

            $newClientName = $data['newClient']['nom'] ?? '';
            if ($choice === 'new' && empty(trim($newClientName))) {
                $form->get('newClient')->get('nom')->addError(new FormError('Le nom du nouveau client est obligatoire.'));
                $form->addError(new FormError('La saisie du nouveau client est invalide.')); // Erreur générale sur le form
            }
        });

        // =======================================================
        // ===== LOGIQUE DANS POST_SUBMIT (Rendue plus sûre) =====
        // =======================================================
        $builder->addEventListener(FormEvents::POST_SUBMIT, function(FormEvent $event) {
            $form = $event->getForm();

            // SÉCURITÉ : Si le formulaire n'est pas valide (à cause du PRE_SUBMIT),
            // on ne fait absolument rien. Cela empêche d'assigner un client null.
            if (!$form->isValid()) {
                return;
            }

            $devis = $form->getParent()->getData();
            if (!$devis instanceof Devis) {
                return;
            }

            $choice = $form->get('choice')->getData();

            if ($choice === 'existing') {
                $client = $form->get('existingClient')->getData();
                if ($client instanceof Client) {
                    $devis->setClient($client);
                }
            } elseif ($choice === 'new') {
                $client = $form->get('newClient')->getData();
                if ($client instanceof Client) {
                    if ($client->getNom() !== null || $client->getEmail() !== null) {
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