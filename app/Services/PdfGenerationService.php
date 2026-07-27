<?php

namespace App\Services;

use Dompdf\Dompdf;
use Dompdf\Options;

class PdfGenerationService
{
    public function renderPdfResponse(string $viewPath, array $data, string $filename = 'document.pdf')
    {
        $html = view($viewPath, $data);

        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'Helvetica');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $response = service('response');
        return $response->setHeader('Content-Type', 'application/pdf')
                         ->setHeader('Content-Disposition', 'inline; filename="' . $filename . '"')
                         ->setBody($dompdf->output());
    }
}
