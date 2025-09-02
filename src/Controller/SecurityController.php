<?php

namespace App\Controller;

use App\Entity\UserRequest;
use App\Form\UserRequestType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    #[Route(path: '/', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();

        $userRequest = new UserRequest();
        $registrationForm = $this->createForm(UserRequestType::class, $userRequest);
        
        return $this->render('security/login_register.html.twig', [
            'last_username' => $lastUsername, 
            'error' => $error,
            'registrationForm' => $registrationForm->createView(),
            // Pas de 'show_signup_panel' ou false ici, pour que le panneau de connexion soit actif par défaut.
        ]);
    }

    #[Route(path: '/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }
}