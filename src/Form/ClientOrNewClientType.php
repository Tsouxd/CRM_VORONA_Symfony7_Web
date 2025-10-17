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

        // Ce listener est la clé ! Il se déclenche juste avant la soumission du formulaire.
        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) {
            $data = $event->getData(); // Données brutes du formulaire (ex: ['choice' => 'new', 'newClient' => ['nom' => 'Test']])
            $form = $event->getForm();
            
            // LA LIGNE MAGIQUE : on récupère l'entité Commande du formulaire parent.
            // C'est pour cela que cette méthode fonctionne parfaitement avec le champ non-mappé.
            $commande = $form->getParent()->getData();

            // Si $commande n'est pas un objet (rare, mais sécurité), on ne fait rien.
            if (!$commande instanceof \App\Entity\Commande) {
                return;
            }

            if (isset($data['choice']) && $data['choice'] === 'existing') {
                if (!empty($data['existingClient'])) {
                    $client = $this->entityManager->getRepository(Client::class)->find($data['existingClient']);
                    $commande->setClient($client); // On modifie directement l'objet Commande
                }
            } elseif (isset($data['choice']) && $data['choice'] === 'new') {
                $newClientData = $data['newClient'] ?? [];
                
                $client = new Client();
                $client->setNom($newClientData['nom'] ?? null);
                $client->setEmail($newClientData['email'] ?? null);
                $client->setTelephone($newClientData['telephone'] ?? null);

                $commande->setClient($client); // On attache le NOUVEAU client à la commande.
                                               // `cascade={"persist"}` s'occupera de l'enregistrer.
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