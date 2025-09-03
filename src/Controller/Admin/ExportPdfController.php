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
        // --- DÉBUT DE LA MODIFICATION ---
        
        // 1. Construire le chemin complet vers l'image en utilisant le paramètre
        $imagePath = $this->getParameter('public_directory') . '/utils/logo/forever-removebg-preview.png';
        
        // 2. Vérifier si le fichier existe avant de continuer
        if (file_exists($imagePath)) {
            // Lire le fichier et l'encoder en Base64
            $imageData = base64_encode(file_get_contents($imagePath));
            $imageSrc = 'data:image/png;base64,' . $imageData;
        } else {
            // Fournir une valeur par défaut si l'image n'est pas trouvée
            $imageSrc = null; 
        }
        
        // --- FIN DE LA MODIFICATION ---

        // On passe maintenant la variable 'imageSrc' au template
        $html = $this->renderView('pdf/facture.html.twig', [
            'commande' => $commande,
            'imageSrc' => $imageSrc, // <-- AJOUTÉ ICI
        ]);

        // Le reste de la méthode ne change pas
        $pdf = $pdfService->showPdfFile($html);

        return new Response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="facture_commande_' . $commande->getId() . '.pdf"',
        ]);
    }
}