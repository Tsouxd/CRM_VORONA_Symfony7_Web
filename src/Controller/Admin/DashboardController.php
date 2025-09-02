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
use App\Repository\CommandeProduitRepository;
use Symfony\Component\HttpFoundation\Request;

#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
class DashboardController extends AbstractDashboardController
{
    private CommandeRepository $commandeRepository;
    private CommandeProduitRepository $commandeProduitRepository;
    private RequestStack $requestStack;

    public function __construct(
        CommandeRepository $commandeRepository,
        CommandeProduitRepository $commandeProduitRepository,
        RequestStack $requestStack
    ) {
        $this->commandeRepository = $commandeRepository;
        $this->commandeProduitRepository = $commandeProduitRepository;
        $this->requestStack = $requestStack;
    }

    #[Route('/admin', name: 'admin')]
    public function index(): Response
    {
        $request = $this->requestStack->getCurrentRequest();
        
        // --- 1. FILTRE PAR PÉRIODE (ne change pas) ---
        $startDateParam = $request->query->get('startDate');
        $endDateParam = $request->query->get('endDate');
        if ($startDateParam && $endDateParam) {
            $startDate = new \DateTime($startDateParam);
            $endDate = (new \DateTime($endDateParam))->setTime(23, 59, 59);
        } else {
            $today = new \DateTime();
            $startDate = (clone $today)->modify('first day of this month')->setTime(0, 0, 0);
            $endDate = (clone $today)->modify('last day of this month')->setTime(23, 59, 59);
        }
        $salesForPeriod = $this->commandeRepository->findTotalSalesBetweenDates($startDate, $endDate);
        $bestProductsForPeriod = $this->commandeProduitRepository->findBestSellingProducts($startDate, $endDate);

        // --- 2. STATS GÉNÉRALES (Jour) ---
        $today = new \DateTime();
        $startOfDay = (clone $today)->setTime(0, 0, 0);
        $endOfDay = (clone $today)->setTime(23, 59, 59);
        $salesToday = $this->commandeRepository->findTotalSalesBetweenDates($startOfDay, $endOfDay);
        $bestProductsToday = $this->commandeProduitRepository->findBestSellingProducts($startOfDay, $endOfDay);

        // --- 3. NOUVELLE LOGIQUE POUR LES FILTRES MENSUELS ET ANNUELS ---
        
        // Pour le filtre par mois
        $availableMonths = $this->commandeRepository->findAvailableMonths();
        $selectedMonth = $request->query->get('month', date('Y-m')); // Prend le mois de l'URL, ou le mois actuel par défaut
        
        $startOfMonth = new \DateTime("first day of $selectedMonth");
        $endOfMonth = (new \DateTime("last day of $selectedMonth"))->setTime(23, 59, 59);
        $bestProductsThisMonth = $this->commandeProduitRepository->findBestSellingProducts($startOfMonth, $endOfMonth);
        
        // Pour le filtre par année
        $availableYears = $this->commandeRepository->findAvailableYears();
        $selectedYear = $request->query->get('year', date('Y'));
        
        $startOfYear = new \DateTime("$selectedYear-01-01");
        $endOfYear = (new \DateTime("$selectedYear-12-31"))->setTime(23, 59, 59);
        $bestProductsThisYear = $this->commandeProduitRepository->findBestSellingProducts($startOfYear, $endOfYear);
        
        // --- 4. DONNÉES HISTORIQUES (ne change pas) ---
        $monthlyData = $this->commandeRepository->getMonthlyStatistics();
        $yearlyData = $this->commandeRepository->getYearlyStatistics();
        
        // --- 5. ENVOI DE TOUTES LES DONNÉES AU TEMPLATE ---
        return $this->render('admin/dashboard.html.twig', [
            // Pour le filtre par période
            'startDate' => $startDate, 'endDate' => $endDate,
            'salesForPeriod' => $salesForPeriod, 'bestProductsForPeriod' => $bestProductsForPeriod,
            // Pour les stats générales du jour
            'salesToday' => $salesToday,
            'bestProductsToday' => $bestProductsToday,
            // Pour le filtre mensuel
            'availableMonths' => $availableMonths,
            'selectedMonth' => $selectedMonth,
            'bestProductsThisMonth' => $bestProductsThisMonth,
            // Pour le filtre annuel
            'availableYears' => $availableYears,
            'selectedYear' => $selectedYear,
            'bestProductsThisYear' => $bestProductsThisYear,
            // Pour les graphiques et tableaux historiques
            'monthlyData' => $monthlyData, 'yearlyData' => $yearlyData,
        ]);
    }

    #[Route('/admin/best-products', name: 'admin_best_products')]
    public function bestProducts(Request $request): Response
    {
        $type = $request->query->get('type', 'month'); // "month" ou "year"
        
        if ($type === 'month') {
            $selectedMonth = $request->query->get('month', date('Y-m'));
            $startOfMonth = new \DateTime("first day of $selectedMonth");
            $endOfMonth = (new \DateTime("last day of $selectedMonth"))->setTime(23, 59, 59);
            $products = $this->commandeProduitRepository->findBestSellingProducts($startOfMonth, $endOfMonth);
            return $this->render('admin/partials/_best_products_list.html.twig', [
                'products' => $products,
                'label' => (new \DateTime($selectedMonth))->format('M Y')
            ]);
        }

        if ($type === 'year') {
            $selectedYear = $request->query->get('year', date('Y'));
            $startOfYear = new \DateTime("$selectedYear-01-01");
            $endOfYear = (new \DateTime("$selectedYear-12-31"))->setTime(23, 59, 59);
            $products = $this->commandeProduitRepository->findBestSellingProducts($startOfYear, $endOfYear);
            return $this->render('admin/partials/_best_products_list.html.twig', [
                'products' => $products,
                'label' => $selectedYear
            ]);
        }

        return new Response('', 400);
    }


    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
        ->setTitle('<img src="/utils/logo/forever-removebg-preview.png" alt="Forever Logo" width="130" height="100">');
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linktoDashboard('Tableau de bord', 'fa fa-home');
        yield MenuItem::linkToCrud('Utilisateurs', 'fas fa-camera', User::class);
        yield MenuItem::linkToCrud('Clients', 'fas fa-users', Client::class);
        yield MenuItem::linkToCrud('Fournisseurs', 'fas fa-thumbs-up', Fournisseur::class);
        yield MenuItem::linkToCrud('Produits', 'fas fa-box', Produit::class);
        yield MenuItem::linkToCrud('Commandes', 'fas fa-shopping-cart', Commande::class)
            ->setController(CommandeCrudController::class); 
        /*yield MenuItem::linkToCrud('Demandes de Modif.', 'fa fa-key', Commande::class)
            ->setQueryParameter('filters[demandeModificationStatut][value]', 'requested')
            ->setCssClass('text-warning')
            ->setPermission('ROLE_ADMIN');*/
        /* yield MenuItem::linkToCrud('Paiement', 'fas fa-briefcase', Paiement::class);*/   
        yield MenuItem::linkToCrud('Catégorie des dépenses', 'fas fa-cog', CategorieDepense::class);
        yield MenuItem::linkToCrud('Catégorie des révenus', 'fas fa-money-bill', CategorieRevenu::class);
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
