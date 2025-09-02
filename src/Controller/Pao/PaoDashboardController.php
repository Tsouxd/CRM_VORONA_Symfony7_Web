<?php
namespace App\Controller\Pao;

use App\Controller\Production\ProductionCommandeCrudController;
use App\Entity\Commande;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;

class PaoDashboardController extends AbstractDashboardController
{
    #[Route('/pao', name: 'pao_dashboard')]
    public function index(): Response
    {
        return $this->render('pao/dashboard.html.twig');
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
        yield MenuItem::linkToDashboard('Tableau de bord', 'fa fa-home');
        yield MenuItem::linkToCrud('Pao à traiter', 'fa fa-pencil-ruler', Commande::class)
            ->setController(PaoCommandeCrudController::class);
    }
}