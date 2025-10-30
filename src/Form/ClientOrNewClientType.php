<?php

namespace App\Form;

use App\Entity\Client;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\FormError;
use App\Entity\Commande;

class ClientOrNewClientType extends AbstractType
{
    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // Tous vos champs sont "mapped => false", ce qui est correct.
        // Cela signifie que Symfony n'essaiera pas de trouver les propriétés
        // 'choice', 'existingClient', 'newClient' sur une entité.
        // Nous gérons les données nous-mêmes dans les listeners.
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

        // Ce listener gère le cas de l'ÉDITION d'une commande existante.
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function(FormEvent $event) {
            // On récupère la Commande existante pour pré-remplir le formulaire.
            $commande = $event->getForm()->getParent()->getData();
            $form = $event->getForm();

            // Si on est en mode édition et qu'un client est déjà associé...
            if ($commande && $commande->getClient() && $commande->getClient()->getId()) {
                // on coche "Client existant" et on sélectionne le bon client dans la liste.
                $form->get('choice')->setData('existing');
                $form->get('existingClient')->setData($commande->getClient());
            } else {
                 // Pour une nouvelle commande, on coche "Client existant" par défaut.
                $form->get('choice')->setData('existing');
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        // On confirme que ce formulaire n'est lié à aucune classe, ce qui est correct.
        $resolver->setDefaults([
            'data_class' => null, 
        ]);
    }
}