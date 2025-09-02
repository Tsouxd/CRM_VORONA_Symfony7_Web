<?php
// src/Controller/RegistrationController.php
namespace App\Controller;

use App\Entity\UserRequest;
use App\Form\UserRequestType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register')]
    public function register(Request $request, EntityManagerInterface $em, AuthenticationUtils $authenticationUtils): Response
    {
        $userRequest = new UserRequest();
        $registrationForm = $this->createForm(UserRequestType::class, $userRequest);

        $registrationForm->handleRequest($request);
        if ($registrationForm->isSubmitted() && $registrationForm->isValid()) {
            $em->persist($userRequest);
            $em->flush();

            $this->addFlash('success', 'Votre demande de création de compte a été transmise à l\'administrateur. Nous vous remercions de votre patience. Vous serez informé(e) dès que votre compte sera actif.');
            
            // REDIRECTION MODIFIÉE : Reste sur la page de demande de compte
            return $this->redirectToRoute('app_register'); 
        }

        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login_register.html.twig', [
            'registrationForm' => $registrationForm->createView(),
            'last_username' => $lastUsername,
            'error' => $error,
            // AJOUT : Indiquer que le panneau de demande doit être actif
            'show_signup_panel' => true, 
        ]);
    }
}