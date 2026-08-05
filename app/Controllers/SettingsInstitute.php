<?php

namespace App\Controllers;

use App\Services\InstituteDetailsService;

class SettingsInstitute extends BaseController
{
    protected InstituteDetailsService $instituteDetailsService;

    public function __construct()
    {
        $this->instituteDetailsService = new InstituteDetailsService();
    }

    public function index()
    {
        $data = [
            'title'            => 'Settings — Institute Details',
            'details'          => $this->instituteDetailsService->getAll(),
            'maxSizeKb'        => InstituteDetailsService::MAX_SIZE_KB,
            'logoWidth'        => InstituteDetailsService::LOGO_WIDTH,
            'logoHeight'       => InstituteDetailsService::LOGO_HEIGHT,
            'letterheadWidth'  => InstituteDetailsService::LETTERHEAD_WIDTH,
            'letterheadHeight' => InstituteDetailsService::LETTERHEAD_HEIGHT,
            'signatureSpaceMin' => InstituteDetailsService::MIN_SIGNATURE_SPACE_LINES,
            'signatureSpaceMax' => InstituteDetailsService::MAX_SIGNATURE_SPACE_LINES,
        ];

        return view('settings/institute', $data);
    }

    public function store()
    {
        try {
            $this->instituteDetailsService->save(
                $this->request->getPost(),
                [
                    'logo'       => $this->request->getFile('logo'),
                    'letterhead' => $this->request->getFile('letterhead'),
                ]
            );
        } catch (\InvalidArgumentException $e) {
            return $this->failResponse($e->getMessage(), 422);
        } catch (\Throwable $e) {
            log_message('error', '[SettingsInstitute::store] {message}', ['message' => (string) $e]);

            return $this->failResponse('Institute details could not be saved. The issue has been logged.', 500);
        }

        if ($this->request->isAJAX()) {
            return $this->response->setJSON([
                'status'  => 'success',
                'message' => 'Institute details updated successfully.',
                'data'    => $this->instituteDetailsService->getAll(),
            ]);
        }

        return redirect()->to(base_url('settings/institute'))->with('success', 'Institute details updated successfully.');
    }

    /**
     * Same failure, shaped for whichever caller sent the request -- a JSON
     * error for the AJAX submit, or the usual flash+redirect otherwise.
     */
    private function failResponse(string $message, int $statusCode)
    {
        if ($this->request->isAJAX()) {
            return $this->response->setStatusCode($statusCode)->setJSON(['status' => 'error', 'message' => $message]);
        }

        return redirect()->to(base_url('settings/institute'))->with('error', $message);
    }
}
