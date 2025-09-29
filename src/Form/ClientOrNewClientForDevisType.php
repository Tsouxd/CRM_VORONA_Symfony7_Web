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
    private $entityManager;

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

        // Gestion à la soumission
        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) {
            $data = $event->getData();
            $form = $event->getForm();

            $devis = $form->getParent()->getData();

            if (!$devis instanceof Devis) {
                return;
            }

            if (isset($data['choice']) && $data['choice'] === 'existing') {
                if (!empty($data['existingClient'])) {
                    $client = $this->entityManager->getRepository(Client::class)->find($data['existingClient']);
                    $devis->setClient($client);
                }
            } elseif (isset($data['choice']) && $data['choice'] === 'new') {
                $newClientData = $data['newClient'] ?? [];

                $client = new Client();
                $client->setNom($newClientData['nom'] ?? null);
                $client->setEmail($newClientData['email'] ?? null);
                $client->setTelephone($newClientData['telephone'] ?? null);

                $devis->setClient($client);
            }
        });

        // Gestion en mode édition
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) {
            $devis = $event->getForm()->getParent()->getData();
            $form = $event->getForm();

            if ($devis && $devis->getClient() && $devis->getClient()->getId()) {
                $form->get('choice')->setData('existing');
                $form->get('existingClient')->setData($devis->getClient());
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