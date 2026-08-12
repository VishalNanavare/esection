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
            return $this->respondToPost(false, $e->getMessage(), base_url('settings/institute'));
        } catch (\Throwable $e) {
            log_message('error', '[SettingsInstitute::store] {message}', ['message' => (string) $e]);

            // 500, not 422: this one is a fault we have logged a trace for, not
            // something the operator can correct by editing the form.
            return $this->respondToPost(
                false,
                'Institute details could not be saved. The issue has been logged.',
                base_url('settings/institute'),
                [],
                500
            );
        }

        return $this->respondToPost(
            true,
            'Institute details updated successfully.',
            base_url('settings/institute'),
            ['data' => $this->instituteDetailsService->getAll()]
        );
    }
}
