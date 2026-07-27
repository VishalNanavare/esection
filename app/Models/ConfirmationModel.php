<?php

namespace App\Models;

use CodeIgniter\Model;

class ConfirmationModel extends Model
{
    protected $table            = 'conf_stud_data';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $allowedFields    = [
        'student_id', 'stud_id', 'dd_no', 'bank_name', 'dd_date', 'dd_amount', 'remark', 'en_by', 'en_time'
    ];

    public function getTotalConfirmedCount(): int
    {
        return $this->countAllResults();
    }

    public function storeConfirmationBatch(array $studentIds, string $ddNo, string $bankName, string $ddDate, string $ddAmount, string $remark, string $username): int
    {
        helper('esection');
        $inserted = 0;
        foreach ($studentIds as $studId) {
            $data = [
                'student_id' => (int) $studId,
                'dd_no'      => sanitize_xss($ddNo),
                'bank_name'  => sanitize_xss($bankName),
                'dd_date'    => sanitize_xss($ddDate),
                'dd_amount'  => sanitize_xss($ddAmount),
                'remark'     => sanitize_xss($remark),
                'en_by'      => sanitize_xss($username),
                'en_time'    => time()
            ];

            $this->insert($data);
            $inserted++;
        }

        return $inserted;
    }
}
