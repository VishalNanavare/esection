<?php

namespace App\Services;

use App\Models\ConfirmationModel;
use App\Models\StudentModel;
use App\Models\StreamModel;

class ConfirmationService
{
    protected ConfirmationModel $confirmationModel;
    protected StudentModel $studentModel;
    protected StreamModel $streamModel;

    public function __construct()
    {
        helper('esection');
        $this->confirmationModel = new ConfirmationModel();
        $this->studentModel      = new StudentModel();
        $this->streamModel       = new StreamModel();
    }

    public function getStreamMetrics(): array
    {
        $streams         = $this->streamModel->getAllStreams();
        $totalCounts     = $this->studentModel->getGroupedStudentCounts();
        $confirmedCounts = $this->studentModel->getGroupedConfirmedCounts();

        $metrics = [];
        foreach ($streams as $s) {
            $streamName = $s['Division'] ?? $s['full_name'] ?? '';
            if (empty($streamName)) continue;

            $total     = 0;
            $confirmed = 0;

            // Match exact or fuzzy stream name
            foreach ($totalCounts as $stName => $cnt) {
                if (stripos($stName, $streamName) !== false || stripos($streamName, $stName) !== false) {
                    $total += $cnt;
                }
            }

            foreach ($confirmedCounts as $stName => $cnt) {
                if (stripos($stName, $streamName) !== false || stripos($streamName, $stName) !== false) {
                    $confirmed += $cnt;
                }
            }

            $pending = max(0, $total - $confirmed);

            $metrics[] = [
                'stream'    => $streamName,
                'total'     => $total,
                'confirmed' => $confirmed,
                'pending'   => $pending
            ];
        }

        return $metrics;
    }

    public function searchStreamsForSelect2(?string $term): array
    {
        return $this->streamModel->searchStreamsForSelect2($term);
    }

    public function storeConfirmation(array $postData, string $username): array
    {
        $studentIds = $postData['student_ids'] ?? [];
        if (empty($studentIds) || !is_array($studentIds)) {
            throw new \InvalidArgumentException('No student records selected for DD confirmation.');
        }

        $inserted = $this->confirmationModel->storeConfirmationBatch(
            $studentIds,
            $postData['dd_no'] ?? '',
            $postData['bank_name'] ?? '',
            $postData['dd_date'] ?? date('Y-m-d'),
            $postData['dd_amount'] ?? '0',
            $postData['remark'] ?? 'Payment Confirmed',
            $username
        );

        return ['count' => $inserted];
    }

    public function getTotalConfirmedCount(): int
    {
        return $this->confirmationModel->getTotalConfirmedCount();
    }
}
