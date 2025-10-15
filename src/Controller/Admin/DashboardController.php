<?php
namespace App\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;

// --- Imports des Entités ---
use App\Entity\User;
use App\Entity\Client;
use App\Entity\UserRequest;
use App\Entity\Produit;
use App\Entity\Commande;
use App\Entity\Fournisseur;
use App\Entity\Facture;
use App\Entity\Devis;

// --- Imports des Repositories (pour la logique du dashboard) ---
use App\Repository\CommandeRepository;
use App\Repository\CommandeProduitRepository;

// --- Imports des Dashboards et CRUDs Externes ---
use App\Controller\Commercial\CommercialDashboardController;
use App\Controller\Pao\PaoDashboardController;
use App\Controller\Production\ProductionDashboardController;
use App\Controller\Admin\CommandeCrudController; // Le CRUD "global" de l'admin
use App\Controller\Admin\FactureCrudController;
use App\Controller\Admin\DevisCrudController;
// On utilise le chemin complet pour éviter toute ambiguïté
use App\Controller\Pao\PaoCommandeCrudController as PaoCrudController;
use App\Controller\Production\ProductionCommandeCrudController as ProductionCrudController;
use App\Repository\UserRepository;

#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
class DashboardController extends AbstractDashboardController
{
    private CommandeRepository $commandeRepository;
    private CommandeProduitRepository $commandeProduitRepository;
    private RequestStack $requestStack;
    private AdminUrlGenerator $adminUrlGenerator;
    private UserRepository $userRepository; 

    public function __construct(
        CommandeRepository $commandeRepository,
        CommandeProduitRepository $commandeProduitRepository,
        RequestStack $requestStack,
        AdminUrlGenerator $adminUrlGenerator,
        UserRepository $userRepository
        
    ) {
        $this->commandeRepository = $commandeRepository;
        $this->commandeProduitRepository = $commandeProduitRepository;
        $this->requestStack = $requestStack;
        $this->adminUrlGenerator = $adminUrlGenerator;
        $this->userRepository = $userRepository;
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
        
        // 5. Récupérer les listes d'utilisateurs
        $commerciaux = $this->userRepository->findByRole('ROLE_COMMERCIAL');
        $paos = $this->userRepository->findByRole('ROLE_PAO');

        // 6. Vérifier si un utilisateur a été sélectionné dans l'URL
        $selectedCommercialId = $request->query->get('commercial_id');
        $selectedPaoId = $request->query->get('pao_id');
        
        $commercialData = null;
        if ($selectedCommercialId) {
            $commercial = $this->userRepository->find($selectedCommercialId);
            if ($commercial) {
                $today = new \DateTime();
                $startOfDay = (clone $today)->setTime(0, 0, 0);
                $endOfDay = (clone $today)->setTime(23, 59, 59);

                // On appelle les nouvelles méthodes dédiées au commercial
                $commercialData = [
                    'user' => $commercial,
                    'salesToday' => $this->commandeRepository->findTotalSalesBetweenDates($startOfDay, $endOfDay, $commercial, 'commercial'),
                    'bestProductsToday' => $this->commandeProduitRepository->findBestSellingProducts($startOfDay, $endOfDay, $commercial, 'commercial'),
                ];
            }
        }
        
        $paoData = null;
        if ($selectedPaoId) {
            $pao = $this->userRepository->find($selectedPaoId);
            if ($pao) {
                // On appelle la nouvelle méthode claire pour les stats PAO
                $stats = $this->commandeRepository->countCommandsByPaoStatusForUser($pao);
                $paoData = [
                    'user' => $pao,
                    'enAttente' => $stats[Commande::STATUT_PAO_ATTENTE] ?? 0,
                    'enCours' => $stats[Commande::STATUT_PAO_EN_COURS] ?? 0,
                    'enModification' => $stats[Commande::STATUT_PAO_MODIFICATION] ?? 0,
                ];
            }
        }

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
            'commerciaux' => $commerciaux,
            'paos' => $paos,
            'selectedCommercialId' => $selectedCommercialId,
            'selectedPaoId' => $selectedPaoId,
            'commercialData' => $commercialData,
            'paoData' => $paoData,
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

    // === NOUVELLE MÉTHODE POUR LE DASHBOARD COMMERCIAL EN AJAX ===
    #[Route('/admin/supervision/commercial-data', name: 'admin_supervision_commercial')]
    public function getCommercialSupervisionData(Request $request): Response
    {
        $commercialId = $request->query->get('commercial_id');
        if (!$commercialId) {
            return new Response(''); // Si aucun ID, on renvoie une réponse vide
        }

        $commercial = $this->userRepository->find($commercialId);
        if (!$commercial) {
            return new Response('<div class="alert alert-danger">Commercial introuvable.</div>');
        }

        $today = new \DateTime();
        $startOfDay = (clone $today)->setTime(0, 0, 0);
        $endOfDay = (clone $today)->setTime(23, 59, 59);

        // On rend UNIQUEMENT le fragment avec les données du commercial
        return $this->render('admin/partials/_commercial_dashboard.html.twig', [
            'salesToday' => $this->commandeRepository->findTotalSalesBetweenDates($startOfDay, $endOfDay, $commercial, 'commercial'),
            'bestProductsToday' => $this->commandeProduitRepository->findBestSellingProducts($startOfDay, $endOfDay, $commercial, 'commercial'),
        ]);
    }

    // === NOUVELLE MÉTHODE POUR LE DASHBOARD PAO EN AJAX ===
    #[Route('/admin/supervision/pao-data', name: 'admin_supervision_pao')]
    public function getPaoSupervisionData(Request $request): Response
    {
        $paoId = $request->query->get('pao_id');
        if (!$paoId) {
            return new Response('');
        }

        $pao = $this->userRepository->find($paoId);
        if (!$pao) {
            return new Response('<div class="alert alert-danger">Utilisateur PAO introuvable.</div>');
        }

        $stats = $this->commandeRepository->countCommandsByPaoStatusForUser($pao);
        
        // On rend UNIQUEMENT le fragment avec les données du PAO
        return $this->render('admin/partials/_pao_dashboard.html.twig', [
            'enAttente' => $stats[Commande::STATUT_PAO_ATTENTE] ?? 0,
            'enCours' => $stats[Commande::STATUT_PAO_EN_COURS] ?? 0,
            'enModification' => $stats[Commande::STATUT_PAO_MODIFICATION] ?? 0,
        ]);
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('<div style="text-align:center;">
                            <img src="/utils/logo/forever-removebg-preview.png" alt="Forever Logo" width="130" height="100">
                        </div>');
    }

    public function configureMenuItems(): iterable
    {
        // === SECTION 1 : LIENS PRINCIPAUX DE L'ADMIN ===
        yield MenuItem::linkToDashboard('Tableau de bord Admin', 'fa fa-home');
        
        yield MenuItem::section('Gestion Principale');
        yield MenuItem::linkToCrud('Utilisateurs', 'fas fa-users', User::class);
        yield MenuItem::linkToCrud('Demandes Utilisateurs', 'fa fa-user-plus', UserRequest::class);
        yield MenuItem::linkToCrud('Clients', 'fas fa-id-card', Client::class);
        yield MenuItem::linkToCrud('Fournisseurs', 'fas fa-truck-moving', Fournisseur::class);
        yield MenuItem::linkToCrud('Produits', 'fas fa-box', Produit::class);

        yield MenuItem::section('Suivi des Départements');

        // === SECTION 2 : SOUS-MENU POUR LE SUIVI COMMERCIAL (CORRIGÉ) ===
        yield MenuItem::subMenu('Suivi Commercial', 'fa fa-dollar-sign')->setSubItems([
            //MenuItem::linkToRoute('Dashboard Commercial', 'fa fa-chart-line', 'commercial_dashboard'),
            MenuItem::linkToCrud('Toutes les Commandes', 'fas fa-shopping-cart', Commande::class)
                ->setController(CommandeCrudController::class),
            MenuItem::linkToCrud('Tous les Devis', 'fas fa-file-alt', Devis::class)
                ->setController(DevisCrudController::class),
            MenuItem::linkToCrud('Toutes les Factures', 'fas fa-file-invoice', Facture::class)
                ->setController(FactureCrudController::class),
        ]);

        // === SECTION 3 : SOUS-MENU POUR LE SUIVI PAO (CORRIGÉ) ===
        yield MenuItem::subMenu('Suivi PAO', 'fa fa-pencil-ruler')->setSubItems([
            //MenuItem::linkToRoute('Dashboard PAO', 'fa fa-palette', 'pao_dashboard'),
            MenuItem::linkToCrud('Travaux à Faire', 'fa fa-tasks', Commande::class)
                ->setController(PaoCrudController::class)
                ->setQueryParameter('filtre', 'a_faire'),
            MenuItem::linkToCrud('Toutes les Commandes PAO', 'fa fa-list-alt', Commande::class)
                ->setController(PaoCrudController::class),
        ]);

        // === SECTION 4 : SOUS-MENU POUR LE SUIVI PRODUCTION (CORRIGÉ) ===
        yield MenuItem::subMenu('Suivi Production', 'fa fa-industry')->setSubItems([
            //MenuItem::linkToRoute('Dashboard Production', 'fa fa-cogs', 'production_dashboard'),
            MenuItem::linkToCrud('Travaux à Faire', 'fa fa-tasks', Commande::class)
                ->setController(ProductionCrudController::class)
                ->setQueryParameter('filtre', 'a_faire'),
                
            MenuItem::linkToCrud('Toutes les Commandes Prod.', 'fa fa-archive', Commande::class)
                ->setController(ProductionCrudController::class),
        ]);
    }
}
