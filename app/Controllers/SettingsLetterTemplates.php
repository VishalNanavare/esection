<?php

namespace App\Controllers;

use App\Services\LetterTemplateService;
use App\Services\PdfGenerationService;

class SettingsLetterTemplates extends BaseController
{
    protected LetterTemplateService $letterTemplateService;
    protected PdfGenerationService $pdfService;

    public function __construct()
    {
        $this->letterTemplateService = new LetterTemplateService();
        $this->pdfService             = new PdfGenerationService();
    }

    public function index()
    {
        $templates = [];
        foreach ($this->letterTemplateService->getSlugs() as $slug) {
            $templates[$slug] = [
                'label'  => $this->letterTemplateService->getLabel($slug),
                'tokens' => $this->letterTemplateService->getTokens($slug),
                'fields' => $this->letterTemplateService->getFields($slug),
            ];
        }

        $data = [
            'title'            => 'Settings — Letter Templates',
            'templates'        => $templates,
            'footerDepartment' => $this->letterTemplateService->getFooterDepartment(),
        ];

        return view('settings/letter_templates', $data);
    }

    public function store($slug)
    {
        try {
            $this->letterTemplateService->save($slug, $this->request->getPost());
        } catch (\InvalidArgumentException $e) {
            return $this->respondToPost(false, $e->getMessage(), base_url('settings/letter-templates'));
        } catch (\Throwable $e) {
            log_message('error', '[SettingsLetterTemplates::store] {message}', ['message' => (string) $e]);

            return $this->respondToPost(
                false,
                'The template could not be saved. The issue has been logged.',
                base_url('settings/letter-templates'),
                [],
                500
            );
        }

        return $this->respondToPost(
            true,
            'Letter template updated successfully.',
            base_url('settings/letter-templates'),
            ['data' => $this->letterTemplateService->getFields($slug)]
        );
    }

    public function storeFooter()
    {
        try {
            $this->letterTemplateService->saveFooterDepartment((string) ($this->request->getPost('footer_department') ?? ''));
        } catch (\Throwable $e) {
            log_message('error', '[SettingsLetterTemplates::storeFooter] {message}', ['message' => (string) $e]);

            return $this->respondToPost(
                false,
                'The footer could not be saved. The issue has been logged.',
                base_url('settings/letter-templates'),
                [],
                500
            );
        }

        return $this->respondToPost(
            true,
            'Footer updated successfully.',
            base_url('settings/letter-templates'),
            ['data' => ['footer_department' => $this->letterTemplateService->getFooterDepartment()]]
        );
    }

    /**
     * Renders a REAL PDF from the SUBMITTED (not-yet-saved) field values
     * plus dummy sample data -- never persists anything. Uses the exact
     * same pdf/{slug}_letter.php template and PdfGenerationService as real
     * generation, so the preview can never drift from real output.
     */
    public function preview($slug)
    {
        try {
            $fields = [
                'subject' => (string) ($this->request->getPost('subject') ?? ''),
                'body'    => (string) ($this->request->getPost('body') ?? ''),
                'closing' => (string) ($this->request->getPost('closing') ?? ''),
            ];

            $rendered = $this->letterTemplateService->render($slug, $fields, $this->sampleTokenValues($slug));
            $viewName = $this->templateViewName($slug);
        } catch (\InvalidArgumentException $e) {
            return $this->response->setStatusCode(422)->setBody(esc($e->getMessage()));
        }

        $data = array_merge($rendered, $this->sampleTemplateData($slug), ['date' => date('d/m/Y')]);

        return $this->pdfService->renderPdfResponse('pdf/' . $viewName, $data, 'preview_' . $slug . '.pdf');
    }

    /**
     * @throws \InvalidArgumentException for an unknown slug
     */
    private function templateViewName(string $slug): string
    {
        return match ($slug) {
            'dispatch'                 => 'dispatch_letter',
            'regularization'           => 'regularization_letter',
            'university_reminder'      => 'university_reminder_letter',
            'student_reminder'         => 'student_reminder_letter',
            'dispatch_accounts'        => 'dispatch_accounts_letter',
            'confirmation_eligibility' => 'confirmation_eligibility_letter',
            default                    => throw new \InvalidArgumentException('Unknown letter template.'),
        };
    }

    /** Dummy per-record values for the {token} substitutions, keyed the same as real generation. */
    private function sampleTokenValues(string $slug): array
    {
        return match ($slug) {
            'dispatch' => [
                'course'        => 'B.Com',
                'academic_year' => '2025-2026',
            ],
            'regularization' => [
                'student_name'        => 'Jane Sample Doe',
                'eligibility_case_no' => 'SAMPLE-0001',
                'passing_course'      => 'B.Com',
            ],
            'university_reminder' => [
                'reminder_type' => '1st Reminder',
                'course'        => 'B.Com',
                'academic_year' => '2025-2026',
            ],
            'student_reminder' => [
                'course_name' => 'B.Com',
                'missing_doc' => 'Original Marksheet',
            ],
            'dispatch_accounts' => [
                'academic_year' => '2025-2026',
            ],
            'confirmation_eligibility' => [
                'academic_year'         => '2025-2026',
                'course'                => 'B.Com',
                'student_count_phrase'  => '( 1 ) student',
            ],
            default => [],
        };
    }

    /** Everything else each template view expects, filled with dummy sample data. Nothing here is persisted. */
    private function sampleTemplateData(string $slug): array
    {
        $sampleStudent = [
            'id'                                     => 0,
            'student_name'                           => 'Jane Sample Doe',
            'student_nee_name'                        => '',
            'eligibility_case_no'                     => 'SAMPLE-0001',
            'to_name'                                 => 'The Controller of Examinations',
            'clg_add'                                 => "Sample University\nSample City - 000000",
            'admission_taken_in'                      => 'B.Com',
            'admission_taken_year'                    => '2025-2026',
            'in_favour_of'                            => 'Sample Finance Officer',
            'verification_of_marksheet_done_by_you'   => 'Marksheet Verification',
            'notes'                                    => [
                ['note_text' => '1st Reminder', 'note_date' => date('Y-m-d')],
            ],
        ];

        return match ($slug) {
            'dispatch' => [
                'array_space' => 'SAMPLE-BATCH',
                'first_row'   => $sampleStudent,
                'students'    => [$sampleStudent],
            ],
            'regularization' => [
                'student_name'          => 'Jane Sample Doe',
                'eligibility_case_no'   => 'SAMPLE-0001',
                'admission_letter_for'  => 'UNI/SAMPLE/00456',
                'admission_letter_date' => '2025-06-15',
                'university_name'       => "Sample University\nSample City - 000000",
            ],
            'university_reminder' => [
                'reminder_type' => '1st Reminder',
                'first_row'     => $sampleStudent,
                'students'      => [$sampleStudent],
            ],
            'student_reminder' => [
                'student_name'        => 'Jane Sample Doe',
                'eligibility_case_no' => 'SAMPLE-0001',
                'course_name'         => 'B.Com',
                'missing_doc'         => 'Original Marksheet',
            ],
            'dispatch_accounts' => [
                'array_space' => 'SAMPLE-BATCH',
                'first_row'   => array_merge($sampleStudent, ['fees' => '500']),
                'students'    => [$sampleStudent],
            ],
            'confirmation_eligibility' => [
                'arraySpace' => 'SAMPLE-BATCH',
                'records' => [array_merge($sampleStudent, [
                    'case_no'          => 'SAMPLE-0001',
                    'mig_TC'           => 'Yes',
                    's_marks'          => 'Yes',
                    'p_degree'         => 'Yes',
                    'letter_no_date'   => 'SAMPLE/2025/001, 15/06/2025',
                    'remark'           => 'Sample remark',
                    'conf_from'        => '',
                    'conf_from_select' => '',
                    'etc_data'         => '',
                ])],
            ],
            default => [],
        };
    }

}
