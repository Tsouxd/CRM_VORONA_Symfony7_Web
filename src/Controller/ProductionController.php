<?php

namespace App\Controller;

use App\Repository\CommandeRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/production')]
#[IsGranted('ROLE_PRODUCTION')]
final class ProductionController extends AbstractController
{
    private CommandeRepository $commandeRepository;

    public function __construct(CommandeRepository $commandeRepository)
    {
        $this->commandeRepository = $commandeRepository;
    }
    
    #[Route('/', name: 'app_production_index')]
    public function index(): Response
    {
        // 1. Récupérer l'utilisateur actuellement connecté
        $user = $this->getUser();

        // 2. Récupérer les statistiques pour le tableau de bord
        $pendingOrdersCount = $this->commandeRepository->countForProduction();
        $totalItemsToProduce = $this->commandeRepository->sumItemsForProduction();
        $productionQueue = $this->commandeRepository->getProductionQueue();

        // Préparer les données pour le graphique
        $chartLabels = [];
        $chartData = [];
        foreach ($productionQueue as $item) {
            $chartLabels[] = $item['productName'];
            $chartData[] = $item['totalQuantity'];
        }

        // 3. Récupérer la liste détaillée des commandes
        $commandes = $this->commandeRepository->findBy(
            ['statut' => ['en cours', 'payée', 'partiellement payée']],
            ['dateCommande' => 'ASC']
        );

        // 4. Envoyer toutes les données, Y COMPRIS L'UTILISATEUR, au template
        return $this->render('production/index.html.twig', [
            'user' => $user, // <-- ON AJOUTE L'UTILISATEUR ICI
            'pendingOrdersCount' => $pendingOrdersCount,
            'totalItemsToProduce' => $totalItemsToProduce,
            'commandes' => $commandes,
            'chartLabels' => json_encode($chartLabels),
            'chartData' => json_encode($chartData),
        ]);
    }

    #[Route('/profil', name: 'app_production_profil')]
    public function profile(): Response
    {
        // Récupère l'utilisateur connecté de manière sécurisée
        $user = $this->getUser();

        // Si pour une raison quelconque l'utilisateur n'est pas trouvé, on le redirige
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        return $this->render('production/profil.html.twig', [
            'user' => $user,
        ]);
    }
}