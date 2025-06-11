<?php
namespace App\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Annotation\Route;
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

#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
class DashboardController extends AbstractDashboardController
{
    private EntityManagerInterface $entityManager;
    private $commandeRepository;

    public function __construct(EntityManagerInterface $entityManager, CommandeRepository $commandeRepository)
    {
        $this->entityManager = $entityManager;
        $this->commandeRepository = $commandeRepository;
    }

    #[Route('/admin', name: 'admin')]
    public function index(): Response
    {
        // 12 derniers mois
        $monthlyData = $this->commandeRepository->getMonthlyStatistics();
        // Par année
        $yearlyData = $this->commandeRepository->getYearlyStatistics();

        $commandes = $this->commandeRepository->findCommandesDes12DerniersMois();

        $chiffreAffaireTotal = 0;

        foreach ($commandes as $commande) {
            $chiffreAffaireTotal += $commande->getTotal();
        }

        return $this->render('admin/dashboard.html.twig', [
            'chiffreAffaire' => $chiffreAffaireTotal,
            'commandes' => $commandes,
            'monthlyData' => $monthlyData,
            'yearlyData' => $yearlyData
        ]);
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
        ->setTitle('<img src="/assets/logo/green-bird-seeklogo.png" alt="Green Bird Logo" width="130" height="100">');
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linktoDashboard('Tableau de bord', 'fa fa-home');
        yield MenuItem::linkToCrud('Personnels', 'fas fa-camera', User::class);
        yield MenuItem::linkToCrud('Clients', 'fas fa-users', Client::class);
        yield MenuItem::linkToCrud('Fournisseurs', 'fas fa-thumbs-up', Fournisseur::class);
        yield MenuItem::linkToCrud('Produits', 'fas fa-box', Produit::class);
        yield MenuItem::linkToCrud('Commandes', 'fas fa-shopping-cart', Commande::class)
            ->setController(CommandeCrudController::class);
        yield MenuItem::linkToCrud('Commande Produits', 'fas fa-list', CommandeProduit::class);
        yield MenuItem::linkToCrud('Catégorie des dépenses', 'fas fa-cog', CategorieDepense::class);
        yield MenuItem::linkToCrud('Catégorie des révenus', 'fas fa-money-bill', CategorieRevenu::class);
        yield MenuItem::linkToCrud('Paiement', 'fas fa-briefcase', Paiement::class);
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
