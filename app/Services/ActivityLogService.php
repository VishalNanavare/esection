<?php

namespace App\Services;

use App\Models\ActivityLogModel;

class ActivityLogService
{
    protected ActivityLogModel $activityLogModel;

    public function __construct()
    {
        $this->activityLogModel = new ActivityLogModel();
    }

    /**
     * Record one audit-trail entry. Never lets a logging failure block the
     * write it describes -- matches Universities::store()'s catch-and-log
     * style, just applied here instead of in a controller.
     */
    public function record(string $action, ?string $entityType = null, ?int $entityId = null, string $description = ''): void
    {
        try {
            $this->activityLogModel->insert([
                'user_id'     => session()->get('id'),
                'username'    => session()->get('username'),
                'action'      => $action,
                'entity_type' => $entityType,
                'entity_id'   => $entityId,
                'description' => $description,
                'created_at'  => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            log_message('error', '[ActivityLogService::record] {message}', ['message' => (string) $e]);
        }
    }

    public function recent(int $limit = 100): array
    {
        return $this->activityLogModel->getRecent($limit);
    }
}
