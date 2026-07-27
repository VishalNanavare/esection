<?php

namespace App\Services;

use Dompdf\Dompdf;
use Dompdf\Options;

class PdfGenerationService
{
    public function renderPdfResponse(string $viewPath, array $data, string $filename = 'document.pdf')
    {
        // Every letter template prints `$date` in its header. Default it here
        // so a caller that forgets cannot 500 the whole document: PdfController
        // passed 'dispatch_date' instead of 'date', which made the verification
        // dispatch letter -- the main output of the app -- throw
        // "Undefined variable $date" on every request.
        $data['date'] ??= date('d/m/Y');

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
