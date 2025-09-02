<?php
namespace App\Controller\Production;

use App\Controller\Production\ProductionCommandeCrudController;
use App\Entity\Commande;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ProductionDashboardController extends AbstractDashboardController
{
    #[Route('/production', name: 'production_dashboard')]
    public function index(): Response
    {
        return $this->render('production/dashboard.html.twig');
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Tableau de bord', 'fa fa-home');
        yield MenuItem::linkToCrud('Commandes à traiter', 'fas fa-industry', Commande::class)
            ->setController(ProductionCommandeCrudController::class);
    }
}
