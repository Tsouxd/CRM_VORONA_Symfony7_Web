<?php
namespace App\Controller\Pao;

use App\Entity\Commande;
use App\Repository\CommandeProduitRepository;
use App\Repository\CommandeRepository;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PaoDashboardController extends AbstractDashboardController
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

#[Route('/pao', name: 'pao_dashboard')]
    public function index(): Response
    {
        $user = $this->getUser();
        if (!$user) {
            throw $this->createAccessDeniedException('Utilisateur non connecté.');
        }

        // ===== Fenêtres de temps (basées sur le fuseau horaire du serveur) =====
        $startOfDay       = (new \DateTimeImmutable('today'))->setTime(0, 0, 0);
        $startOfTomorrow  = $startOfDay->modify('+1 day');

        // Semaine : lundi 00:00 -> lundi prochain 00:00
        $startOfWeek      = (new \DateTimeImmutable('monday this week'))->setTime(0, 0, 0);
        $startOfNextWeek  = $startOfWeek->modify('+1 week');

        // ==============================================================
        // 1. ÉTAT ACTUEL DU TRAVAIL (Backlog et Tâches en cours)
        // ==============================================================

        // MODIFIÉ : On compte TOUS les travaux en attente pour cet utilisateur, peu importe la date.
        $worksPendingCount = (int) $this->commandeRepository
            ->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.statutPao = :status')
            ->andWhere('c.pao = :pao')
            ->setParameter('status', Commande::STATUT_PAO_ATTENTE)
            ->setParameter('pao', $user)
            ->getQuery()
            ->getSingleScalarResult();

        // MODIFIÉ : On compte TOUS les travaux en cours pour cet utilisateur.
        // J'ai renommé la variable pour plus de clarté.
        $workInProgressCount = (int) $this->commandeRepository
            ->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.statutPao = :status')
            ->andWhere('c.pao = :pao')
            ->setParameter('status', Commande::STATUT_PAO_EN_COURS)
            ->setParameter('pao', $user)
            ->getQuery()
            ->getSingleScalarResult();


        // ==============================================================
        // 2. ACTIVITÉ DE LA JOURNÉE (Tâches terminées ou en modif aujourd'hui)
        // ==============================================================

        $workDoneToday = (int) $this->commandeRepository
            ->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.statutPao = :fait')
            ->andWhere('c.pao = :pao')
            // MODIFIÉ : On utilise notre nouveau champ !
            ->andWhere('c.paoStatusUpdatedAt >= :start AND c.paoStatusUpdatedAt < :next')
            ->setParameter('fait', Commande::STATUT_PAO_FAIT)
            ->setParameter('pao', $user)
            ->setParameter('start', new \DateTime($startOfDay->format('Y-m-d H:i:s')))
            ->setParameter('next', new \DateTime($startOfTomorrow->format('Y-m-d H:i:s')))
            ->getQuery()
            ->getSingleScalarResult();

        $workModificationToday = (int) $this->commandeRepository
            ->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.statutPao = :status')
            ->andWhere('c.pao = :pao')
            // MODIFIÉ : On utilise notre nouveau champ !
            ->andWhere('c.paoStatusUpdatedAt >= :start AND c.paoStatusUpdatedAt < :next')
            ->setParameter('status', Commande::STATUT_PAO_MODIFICATION)
            ->setParameter('pao', $user)
            ->setParameter('start', new \DateTime($startOfDay->format('Y-m-d H:i:s')))
            ->setParameter('next',  new \DateTime($startOfTomorrow->format('Y-m-d H:i:s')))
            ->getQuery()
            ->getSingleScalarResult();

        // ===== Travaux de la semaine (tous statuts) =====
        // Cette section semble correcte, je la laisse telle quelle.
        $paoStatuses = [
            Commande::STATUT_PAO_ATTENTE,
            Commande::STATUT_PAO_EN_COURS,
            Commande::STATUT_PAO_FAIT,
            Commande::STATUT_PAO_MODIFICATION,
        ];
        $worksThisWeekCount = (int) $this->commandeRepository
            ->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.statutPao IN (:statuses)')
            ->andWhere('c.pao = :pao')
            ->andWhere('c.dateCommande >= :wstart AND c.dateCommande < :wnext')
            ->setParameter('statuses', $paoStatuses)
            ->setParameter('pao', $user)
            ->setParameter('wstart', new \DateTime($startOfWeek->format('Y-m-d H:i:s')))
            ->setParameter('wnext',  new \DateTime($startOfNextWeek->format('Y-m-d H:i:s')))
            ->getQuery()->getSingleScalarResult();

        $worksDoneThisWeekCount = (int) $this->commandeRepository
            ->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.statutPao = :fait')
            ->andWhere('c.pao = :pao')
            // MODIFIÉ : On utilise notre nouveau champ ici aussi pour la cohérence !
            ->andWhere('c.paoStatusUpdatedAt >= :wstart AND c.paoStatusUpdatedAt < :wnext') 
            ->setParameter('fait', Commande::STATUT_PAO_FAIT)
            ->setParameter('pao', $user)
            ->setParameter('wstart', new \DateTime($startOfWeek->format('Y-m-d H:i:s')))
            ->setParameter('wnext', new \DateTime($startOfNextWeek->format('Y-m-d H:i:s')))
            ->getQuery()
            ->getSingleScalarResult();

        // ===== Graph “PAO FAIT” par jour sur un mois (?month=YYYY-MM) =====
        $request    = $this->requestStack->getCurrentRequest();
        $monthParam = $request?->query->get('month');

        if (is_string($monthParam) && preg_match('/^\d{4}-\d{2}$/', $monthParam)) {
            [$y, $m] = explode('-', $monthParam);
            $targetYear  = (int) $y;
            $targetMonth = (int) $m;
        } else {
            $now         = new \DateTimeImmutable('now');
            $targetYear  = (int) $now->format('Y');
            $targetMonth = (int) $now->format('m');
            $monthParam  = sprintf('%04d-%02d', $targetYear, $targetMonth);
        }

        $startOfTargetMonth = (new \DateTimeImmutable(sprintf('%04d-%02d-01', $targetYear, $targetMonth)))->setTime(0, 0, 0);
        $startOfNextMonth   = $startOfTargetMonth->modify('+1 month');

        $paoDoneRows = $this->commandeRepository
            ->createQueryBuilder('c')
            ->select('c.id, c.paoStatusUpdatedAt')
            ->where('c.statutPao = :fait')
            ->andWhere('c.pao = :pao')
            ->andWhere('c.paoStatusUpdatedAt >= :start AND c.paoStatusUpdatedAt < :next')
            ->setParameter('fait', Commande::STATUT_PAO_FAIT)
            ->setParameter('pao', $user)
            ->setParameter('start', new \DateTime($startOfTargetMonth->format('Y-m-d H:i:s')))
            ->setParameter('next', new \DateTime($startOfNextMonth->format('Y-m-d H:i:s')))
            ->getQuery()
            ->getArrayResult();

        // ===== Liste des commandes déjà faites (hors commandes du jour) =====
        $doneCommandsPast = $this->commandeRepository
            ->createQueryBuilder('c')
            ->where('c.statutPao = :fait')
            ->andWhere('c.pao = :pao')
            // MODIFIÉ : On filtre sur le bon champ !
            ->andWhere('c.paoStatusUpdatedAt < :startToday')
            ->setParameter('fait', Commande::STATUT_PAO_FAIT)
            ->setParameter('pao', $user)
            ->setParameter('startToday', new \DateTime($startOfDay->format('Y-m-d H:i:s')))
            // MODIFIÉ : Et on trie par le bon champ !
            ->orderBy('c.paoStatusUpdatedAt', 'DESC')
            ->getQuery()
            ->getResult();

        // Agrégation par jour
        $daysInMonth = (int) $startOfTargetMonth->format('t');
        $byDay = array_fill(1, $daysInMonth, 0);

        foreach ($paoDoneRows as $row) {
            $raw = $row['paoStatusUpdatedAt'] ?? null;
            if ($raw instanceof \DateTimeInterface) {
                $dt = \DateTime::createFromFormat('Y-m-d H:i:s', $raw->format('Y-m-d H:i:s'));
            } else {
                $dt = new \DateTime(is_array($raw) && isset($raw['date']) ? $raw['date'] : (string)$raw);
            }
            if (!$dt) continue;
            $day = (int) $dt->format('j');
            if ($day >= 1 && $day <= $daysInMonth) $byDay[$day]++;
        }

        $chartLabels = [];
        $chartValues = [];
        for ($i = 1; $i <= $daysInMonth; $i++) {
            $chartLabels[] = sprintf('%02d', $i);
            $chartValues[] = $byDay[$i];
        }

        // MISE À JOUR : On passe les nouvelles variables au template Twig
        return $this->render('pao/dashboard.html.twig', [
            'worksPendingCount'        => $worksPendingCount,
            'workInProgressCount'      => $workInProgressCount, // <-- variable renommée
            'workDoneToday'            => $workDoneToday,
            'workModificationToday'    => $workModificationToday,
            'worksThisWeekCount'       => $worksThisWeekCount,
            'worksDoneThisWeekCount'   => $worksDoneThisWeekCount,
            'selectedMonth'            => $monthParam,
            'chartLabels'              => $chartLabels,
            'chartValues'              => $chartValues,
            'doneCommandsPast'         => $doneCommandsPast,
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
        $user = $this->getUser();

        $travauxAFaireCount = (int) $this->commandeRepository
            ->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.pao = :user')
            ->andWhere('c.statutPao IN (:statuses)')
            ->setParameter('user', $user)
            ->setParameter('statuses', [
                Commande::STATUT_PAO_ATTENTE,
                Commande::STATUT_PAO_EN_COURS,
                Commande::STATUT_PAO_MODIFICATION, // si tu veux aussi inclure ceux en modif
            ])
            ->getQuery()
            ->getSingleScalarResult();

        // On initialise le compteur
        $paoCommandesCount = 0;
        
        // On récupère l'utilisateur connecté
        $user = $this->getUser();

        // Si l'utilisateur est un ADMIN, il voit le total de toutes les commandes.
        if ($this->isGranted('ROLE_ADMIN')) {
            $paoCommandesCount = $this->commandeRepository->count([]);
        } 
        // Sinon, si c'est un PAO, il ne voit que le total des commandes qui lui sont assignées.
        elseif ($this->isGranted('ROLE_PAO') && $user) {
            // La méthode count() de Doctrine peut prendre des critères en paramètre !
            $paoCommandesCount = $this->commandeRepository->count(['pao' => $user]);
        }

        yield MenuItem::linktoDashboard('Tableau de bord', 'fa fa-home');

        // Menu Travaux à faire
        yield MenuItem::linkToCrud("Travaux à faire ({$travauxAFaireCount})", 'fa fa-tasks', Commande::class)
            ->setController(PaoCommandeCrudController::class)
            ->setQueryParameter('filtre', 'a_faire');

        // On utilise la variable calculée dans le lien du menu.
        yield MenuItem::linkToCrud("Toutes les Commandes ({$paoCommandesCount})", 'fa fa-archive', Commande::class)
            ->setController(PaoCommandeCrudController::class);
    }
}