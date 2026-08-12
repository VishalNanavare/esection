<?php

namespace App\Controllers;

use App\Services\FeatureToggleService;

class SettingsFeatures extends BaseController
{
    protected FeatureToggleService $featureToggleService;

    public function __construct()
    {
        $this->featureToggleService = new FeatureToggleService();
    }

    public function index()
    {
        $data = [
            'title' => 'Settings — Feature Toggles',
            'flags' => $this->featureToggleService->getAll(),
        ];

        return view('settings/features', $data);
    }

    public function store()
    {
        $this->featureToggleService->save($this->request->getPost());

        return $this->respondToPost(true, 'Feature toggles updated.', base_url('settings/features'));
    }
}
