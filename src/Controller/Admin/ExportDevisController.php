<?php
namespace App\Controller\Admin;

use App\Entity\Devis;
use App\Service\PdfService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ExportDevisController extends AbstractController
{
    #[Route('/admin/export-devis/{id}', name: 'admin_export_devis')]
    public function export(Devis $devis, PdfService $pdfService): Response
    {
        // Calcul du total au moment de l'export si besoin
        $total = 0;
        foreach ($devis->getLignes() as $ligne) {
            $total += $ligne->getPrixTotal();
        }

        $html = $this->renderView('pdf/devis.html.twig', [
            'devis' => $devis,
            'total' => $total, // Passe le total au template
        ]);

        $pdf = $pdfService->showPdfFile($html);

        return new Response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="devis_' . $devis->getId() . '.pdf"',
        ]);
    }
}
