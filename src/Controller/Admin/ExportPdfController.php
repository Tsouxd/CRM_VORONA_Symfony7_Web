<?php
namespace App\Controller\Admin;

use App\Entity\Commande;
use App\Service\PdfService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ExportPdfController extends AbstractController
{
    #[Route('/admin/export-facture/{id}', name: 'admin_export_facture')]
    public function export(Commande $commande, PdfService $pdfService): Response
    {
        $html = $this->renderView('pdf/facture.html.twig', [
            'commande' => $commande,
        ]);

        $pdf = $pdfService->showPdfFile($html);

        return new Response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="facture_commande_' . $commande->getId() . '.pdf"',
        ]);
    }
}
