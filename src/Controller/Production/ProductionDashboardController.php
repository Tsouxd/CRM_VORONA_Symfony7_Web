<?php
namespace App\Controller\Production;

use App\Entity\Commande;
use App\Repository\CommandeProduitRepository;
use App\Repository\CommandeRepository;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\RequestStack;


class ProductionDashboardController extends AbstractDashboardController
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

    // Dans src/Controller/Production/ProductionDashboardController.php

    #[Route('/production', name: 'production_dashboard')]
    public function index(): Response
    {
        // ==============================================================
        // 1. CALCULS POUR LES CARTES (logique inchangée)
        // ==============================================================
        $startOfDay = (new \DateTimeImmutable('today'))->setTime(0, 0, 0);
        $startOfTomorrow = $startOfDay->modify('+1 day');

        $worksPendingCount = (int) $this->commandeRepository
            ->createQueryBuilder('c')->select('COUNT(c.id)')->where('c.statutProduction = :status')
            ->setParameter('status', Commande::STATUT_PRODUCTION_ATTENTE)->getQuery()->getSingleScalarResult();

        $workInProgressCount = (int) $this->commandeRepository
            ->createQueryBuilder('c')->select('COUNT(c.id)')->where('c.statutProduction = :status')
            ->setParameter('status', Commande::STATUT_PRODUCTION_EN_COURS)->getQuery()->getSingleScalarResult();

        $workFinishedToday = (int) $this->commandeRepository
            ->createQueryBuilder('c')->select('COUNT(c.id)')->where('c.statutProduction = :status')
            ->andWhere('c.productionStatusUpdatedAt >= :start AND c.productionStatusUpdatedAt < :next')
            ->setParameter('status', Commande::STATUT_PRODUCTION_POUR_LIVRAISON)
            ->setParameter('start', new \DateTime($startOfDay->format('Y-m-d H:i:s')))
            ->setParameter('next', new \DateTime($startOfTomorrow->format('Y-m-d H:i:s')))
            ->getQuery()->getSingleScalarResult();

        // =====================================================================
        // 2. NOUVEAU : GESTION DE LA RECHERCHE PAR DATE POUR LA LISTE
        // =====================================================================
        $request = $this->requestStack->getCurrentRequest();
        
        // On récupère les dates depuis l'URL, avec des valeurs par défaut (le mois en cours)
        $dateStartParam = $request?->query->get('date_start');
        $dateEndParam = $request?->query->get('date_end');

        try {
            $dateStart = $dateStartParam ? new \DateTimeImmutable($dateStartParam) : new \DateTimeImmutable('first day of this month');
        } catch (\Exception $e) {
            $dateStart = new \DateTimeImmutable('first day of this month');
        }

        try {
            $dateEnd = $dateEndParam ? new \DateTimeImmutable($dateEndParam) : new \DateTimeImmutable('now');
        } catch (\Exception $e) {
            $dateEnd = new \DateTimeImmutable('now');
        }

        // On s'assure de couvrir toute la journée pour les deux dates
        $dateStart = $dateStart->setTime(0, 0, 0);
        $dateEnd = $dateEnd->setTime(23, 59, 59);

        // On récupère les commandes terminées dans l'intervalle de dates choisi
        $finishedCommandsInRange = $this->commandeRepository
            ->createQueryBuilder('c')
            ->where('c.statutProduction = :status')
            ->andWhere('c.productionStatusUpdatedAt BETWEEN :start AND :end')
            ->setParameter('status', Commande::STATUT_PRODUCTION_POUR_LIVRAISON)
            ->setParameter('start', $dateStart)
            ->setParameter('end', $dateEnd)
            ->orderBy('c.productionStatusUpdatedAt', 'DESC') // Les plus récents en premier
            ->getQuery()
            ->getResult();

        // =====================================================================
        // 3. LISTE DES TRAVAUX DÉJÀ LIVRÉS (logique inchangée)
        // =====================================================================
        $deliveredCommands = $this->commandeRepository
            ->createQueryBuilder('c')
            ->where('c.statutLivraison = :livree')
            ->setParameter('livree', Commande::STATUT_LIVRAISON_LIVREE)
            ->orderBy('c.dateDeLivraison', 'DESC')
            ->getQuery()
            ->getResult();

        return $this->render('production/dashboard.html.twig', [
            // Données pour les cartes
            'worksPendingCount'         => $worksPendingCount,
            'workInProgressCount'       => $workInProgressCount,
            'workFinishedToday'         => $workFinishedToday,
            
            // Données pour la nouvelle liste filtrable
            'finishedCommandsInRange'   => $finishedCommandsInRange,
            'dateStart'                 => $dateStart,
            'dateEnd'                   => $dateEnd,

            // Données pour la liste des commandes livrées
            'deliveredCommands'         => $deliveredCommands,
        ]);
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('<div style="text-align:center;">
                            <img src="/utils/logo/Fichier 11.png" alt="Forever Logo" width="120" height="80">
                        </div>');
    }

    public function configureMenuItems(): iterable
    {
        $user = $this->getUser();

        $travauxAFaireCount = (int) $this->commandeRepository
            ->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.production = :user')
            ->andWhere('c.statutProduction IN (:statuses)')
            ->setParameter('user', $user)
            ->setParameter('statuses', [
                Commande::STATUT_PRODUCTION_ATTENTE,
                Commande::STATUT_PRODUCTION_EN_COURS,
            ])
            ->getQuery()
            ->getSingleScalarResult();

        // On initialise le compteur
        $productionCommandesCount = 0;

        // Si l'utilisateur est un ADMIN, il voit le total de toutes les commandes.
        if ($this->isGranted('ROLE_ADMIN')) {
            $paoCommandesCount = $this->commandeRepository->count([]);
        } 
        // Sinon, si c'est un PAO, il ne voit que le total des commandes qui lui sont assignées.
        elseif ($this->isGranted('ROLE_PRODUCTION') && $user) {
            // La méthode count() de Doctrine peut prendre des critères en paramètre !
            $productionCommandesCount = $this->commandeRepository->count(['production' => $user]);
        }

        yield MenuItem::linkToDashboard('Tableau de bord', 'fa fa-home');
        yield MenuItem::linkToCrud("Commandes à traiter ({$travauxAFaireCount})", 'fa fa-industry', Commande::class)
            ->setController(ProductionCommandeCrudController::class)
            ->setQueryParameter('filtre', 'a_faire');
        yield MenuItem::linkToCrud("Toutes les Commandes ({$productionCommandesCount})", 'fa fa-archive', Commande::class)
            ->setController(ProductionCommandeCrudController::class);
    }
}