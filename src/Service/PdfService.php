<?php
// src/Service/PdfService.php
namespace App\Service;

use Dompdf\Dompdf;
use Dompdf\Options;

class PdfService
{
    public function showPdfFile(string $html): string
    {
        $options = new Options();
        $options->set('defaultFont', 'Arial');
        $options->set('isRemoteEnabled', true); // ⚠️ très important
        $options->set('isPhpEnabled', true);
        $options->setChroot($this->getProjectPublicDir());
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        return $dompdf->output();
    }

    private function getProjectPublicDir(): string
    {
        return realpath(__DIR__ . '/../../public');
    }
}