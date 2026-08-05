<?php

namespace App\Controllers;

use App\Services\ActivityLogService;

class Settings extends BaseController
{
    protected ActivityLogService $activityLogService;

    public function __construct()
    {
        $this->activityLogService = new ActivityLogService();
    }

    public function index()
    {
        return view('settings/index', ['title' => 'Settings']);
    }

    public function activityLog()
    {
        $data = [
            'title'   => 'Settings — Activity Log',
            'entries' => $this->activityLogService->recent(100),
        ];

        return view('settings/activity_log', $data);
    }
}
