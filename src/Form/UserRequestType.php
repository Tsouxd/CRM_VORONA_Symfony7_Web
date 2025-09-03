<?php
// src/Form/UserRequestType.php
namespace App\Form;

use App\Entity\UserRequest;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UserRequestType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('username', TextType::class, [
                'label' => false,
                'attr' => [
                    'placeholder' => 'Nom d’utilisateur souhaité', // Plus poli
                    'class' => 'input-style'
                ],
            ])
            ->add('password', PasswordType::class, [
                'label' => false,
                'attr' => [
                    'placeholder' => 'Mot de passe envisagé', // Plus poli
                    'class' => 'input-style'
                ],
            ])
            ->add('roleDemander', TextType::class, [
                'label' => false,
                'required' => false, // Important si le champ est nullable dans l'entité
                'attr' => [
                    'placeholder' => 'Rôle souhaité (ex: COMMERCIAL)', // Plus poli
                    'class' => 'input-style'
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => UserRequest::class,
        ]);
    }
}