<?php

namespace App\Form;

use App\Entity\Client;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;

class ClientType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'label' => 'Nom du client',
                'required' => true,
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'required' => true,
            ])
            ->add('telephone', TextType::class, [
                'label' => 'Téléphone',
                'required' => false,
            ])
            ->add('type', ChoiceType::class, [
                'label' => 'Type de client',
                'choices' => [
                    'Particulier' => Client::TYPE_PARTICULIER,
                    'Professionnel' => Client::TYPE_PROFESSIONNEL,
                ],
                'expanded' => true,
                'required' => true,
                'attr' => ['class' => 'client-type-selector'],
                'data' => Client::TYPE_PARTICULIER,
            ])

            // --- Groupe de champs pour les Particuliers ---
            ->add('adresseLivraison', TextareaType::class, [
                'label' => 'Adresse de livraison',
                'required' => false,
                'row_attr' => ['class' => 'client-field particulier-field'],
            ])
            ->add('lieuLivraison', TextType::class, [
                'label' => 'Lieu de livraison (ville, code postal...)',
                'required' => false,
                'row_attr' => ['class' => 'client-field particulier-field'],
            ])
            /*->add('heureLivraison', TimeType::class, [
                'label' => 'Heure de livraison',
                'required' => false,
                'widget' => 'single_text', // un seul input HTML5 type="time"
                'input' => 'datetime',     // valeur stockée en \DateTime
                'row_attr' => ['class' => 'client-field particulier-field'],
            ])*/

            // --- Groupe de champs pour les Professionnels ---
            ->add('nif', TextType::class, [
                'label' => 'NIF',
                'required' => false,
                'row_attr' => ['class' => 'client-field professionnel-field'],
            ])
            ->add('stat', TextType::class, [
                'label' => 'STAT',
                'required' => false,
                'row_attr' => ['class' => 'client-field professionnel-field'],
            ])
            ->add('adresse', TextareaType::class, [
                'label' => 'Adresse (siège social)',
                'required' => false,
                'row_attr' => ['class' => 'client-field professionnel-field'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Client::class,
        ]);
    }
}