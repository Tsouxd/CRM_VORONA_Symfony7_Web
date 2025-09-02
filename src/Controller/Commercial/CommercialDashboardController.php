<?php

namespace App\Controller\Commercial;

use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Controller\Admin\CommandeCrudController;
use App\Entity\User;
use App\Entity\Client;
use App\Entity\Produit;
use App\Entity\Commande;
use App\Entity\Fournisseur;
use App\Entity\CommandeProduit;
use App\Entity\CategorieDepense;
use App\Entity\CategorieRevenu;
use App\Entity\Paiement;
use App\Repository\CommandeRepository;
use App\Repository\CommandeProduitRepository;
use Symfony\Component\Security\Http\Attribute\IsGranted;
#[IsGranted('ROLE_COMMERCIAL')]

final class CommercialDashboardController extends AbstractDashboardController
{
    #[Route('/commercial/gestion', name: 'commercial_dashboard')]
    public function index(): Response
    {
        $url = $this->container->get(AdminUrlGenerator::class)
            ->setController(\App\Controller\Admin\ProduitCrudController::class)
            ->setController(\App\Controller\Admin\CommandeCrudController::class)
            ->generateUrl();

        return $this->redirect($url);
    }

        public function configureDashboard(): Dashboard
        {
            return Dashboard::new()->setTitle('Espace Commercial');
        }
    
    public function configureMenuItems(): iterable
    {
        yield MenuItem::linktoDashboard('Tableau de bord', 'fa fa-home');
        yield MenuItem::linkToCrud('Clients', 'fas fa-users', Client::class);
        yield MenuItem::linkToCrud('Fournisseurs', 'fas fa-thumbs-up', Fournisseur::class);
        yield MenuItem::linkToCrud('Produits', 'fas fa-box', Produit::class);
        yield MenuItem::linkToCrud('Commandes', 'fas fa-shopping-cart', Commande::class)
            ->setController(CommandeCrudController::class); 
        /*  
        'fas fa-users' : Icône des utilisateurs.
        'fas fa-cogs' : Icône d'engrenages ou de réglages.
        'fas fa-folder' : Icône de dossier.
        'fas fa-book' : Icône de livre.
        'fas fa-briefcase' : Icône de mallette.
        'fas fa-chart-bar' : Icône de graphique en barres.
        'fas fa-calendar' : Icône de calendrier.
        'fas fa-pen' : Icône de stylo.
        'fas fa-shopping-cart' : Icône de panier d'achat.
        'fas fa-envelope' : Icône d'enveloppe. 
        fas fa-home : Icône de maison
        fas fa-user : Icône d'utilisateur
        fas fa-cog : Icône d'engrenage
        fas fa-search : Icône de recherche
        fas fa-envelope : Icône d'enveloppe
        fas fa-star : Icône d'étoile
        fas fa-cloud : Icône de nuage
        fas fa-trash : Icône de corbeille
        fas fa-folder : Icône de dossier
        fas fa-calendar : Icône de calendrier
        fas fa-bar-chart : Icône de graphique à barres
        fas fa-camera : Icône d'appareil photo
        fas fa-lock : Icône de cadenas
        fas fa-bell : Icône de cloche
        fas fa-map-marker : Icône de marqueur de carte
        fas fa-money-bill : Icône de billet de banque
        fas fa-phone : Icône de téléphone
        fas fa-code : Icône de code
        fas fa-file-pdf : Icône de fichier PDF
        fas fa-thumbs-up : Icône de pouce en l'air
        */
    }
}