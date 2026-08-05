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

        // Injected here, once, so every current AND future letter template
        // reads the configured institute identity (Settings > Institute
        // Details) automatically rather than each controller wiring it
        // separately. Each key stays null (not an empty string) when never
        // configured, so templates can `?? 'their own hardcoded default'`.
        $institute = (new InstituteDetailsService())->getAll();
        $data['instituteName']                ??= $institute['institute_name'] ?: null;
        $data['instituteUniversityTitle']      ??= $institute['institute_university_title'] ?: null;
        $data['instituteAddress']              ??= $institute['institute_address'] ?: null;
        $data['instituteSignatoryName']        ??= $institute['institute_signatory_name'] ?: null;
        $data['instituteSignatoryDesignation'] ??= $institute['institute_signatory_designation'] ?: null;
        $data['instituteLogoPath']             ??= $institute['institute_logo_path'] ?: null;
        $data['instituteLetterheadPath']       ??= $institute['institute_letterhead_path'] ?: null;
        $rawSignatureSpace = $institute['institute_signature_space_lines'] ?: null;
        $data['instituteSignatureSpaceLines']  ??= $rawSignatureSpace !== null ? (int) $rawSignatureSpace : null;

        // Same centralized-injection principle as the institute fields
        // above: every letter template can read the shared "department"
        // signature line from Settings > Letter Templates without each
        // controller wiring it separately.
        $data['footerDepartment'] ??= (new LetterTemplateService())->getFooterDepartment();

        $html = view($viewPath, $data);

        $options = new Options();
        // Deliberately FALSE. isRemoteEnabled lets Dompdf make outbound HTTP
        // requests for any <img src="http://..."> or CSS url() it encounters
        // while rendering -- the classic Dompdf SSRF vector. No letter
        // template needs it: every image is a local file under public/
        // (reached via the chroot below), and all six templates only ever
        // emit esc()'d text. Leaving it on bought nothing and would turn any
        // future unescaped field into a server-side request forgery.
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'Helvetica');
        // Dompdf's local file:// access is governed separately from
        // isRemoteEnabled by its own "chroot" allowlist, which defaults to
        // Dompdf's own package directory (vendor/dompdf/dompdf) -- not this
        // app. Without this, an institute logo under public/uploads/ is
        // silently dropped (no error, just never embedded) because it falls
        // outside that default chroot.
        $options->setChroot([FCPATH]);

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
