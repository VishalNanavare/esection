<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * The full admission record, exactly as the IDOL admission system exports it.
 *
 * This is the wide, lossless half of the importer: all 24 export columns land
 * here, whether or not the candidate is ready to be dispatched. The narrow
 * dispatch record -- the 12 fields a verification letter actually needs --
 * goes to student_details via StudentVerificationService.
 *
 * See 2026-08-10-000001_FormaliseEStudentData for why ApplicationID is indexed
 * but not UNIQUE, and why uniqueness is enforced here in PHP instead.
 */
class EStudentDataModel extends Model
{
    protected $table            = 'e_student_data';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';

    /** The 24 export columns, plus the 4 tracking columns this app adds. */
    protected $allowedFields = [
        'AdmissionYear', 'ApplicationID', 'FirstName', 'MiddleName', 'LastName',
        'NameOnMarkSheet', 'Gender', 'CorrespondenceAddress', 'PermanentAddress',
        'PrimaryMobileNo', 'AlternateMobileNo', 'EmailID', 'CourseName', 'LastQual',
        'QualificationName', 'CertifyingBodyType', 'CertifyingBoardName',
        'CertifyingStateName', 'AdmissionFeeAmount', 'AdmissionFeeTransactionDateTime',
        'ApplicationStatus', 'SCEligibilityStatus', 'EligibilityCaseNo',
        'EligibilityApprovalDate',
        'import_ref', 'imported_at', 'imported_by', 'dispatch_array_space',
    ];

    /** The 24 source columns alone, for building an insert from a parsed row. */
    public const SOURCE_COLUMNS = [
        'AdmissionYear', 'ApplicationID', 'FirstName', 'MiddleName', 'LastName',
        'NameOnMarkSheet', 'Gender', 'CorrespondenceAddress', 'PermanentAddress',
        'PrimaryMobileNo', 'AlternateMobileNo', 'EmailID', 'CourseName', 'LastQual',
        'QualificationName', 'CertifyingBodyType', 'CertifyingBoardName',
        'CertifyingStateName', 'AdmissionFeeAmount', 'AdmissionFeeTransactionDateTime',
        'ApplicationStatus', 'SCEligibilityStatus', 'EligibilityCaseNo',
        'EligibilityApprovalDate',
    ];

    private function table(): \CodeIgniter\Database\BaseBuilder
    {
        return $this->db->table($this->table);
    }

    /**
     * Existing rows for the given ApplicationIDs, as ApplicationID => id.
     *
     * One query for the whole file rather than one per row -- the importer
     * needs to know which of up to a few hundred candidates already exist
     * before it writes anything.
     *
     * Where the same ApplicationID appears more than once (the pre-existing
     * sample data contains four such pairs, and no UNIQUE index prevents it),
     * the LOWEST id wins, so repeated imports converge on one row rather than
     * ping-ponging between duplicates.
     *
     * @param  string[] $applicationIds
     * @return array<string, int>
     */
    public function findIdsByApplicationIds(array $applicationIds): array
    {
        $applicationIds = array_values(array_filter(array_map('strval', $applicationIds), static fn (string $v): bool => $v !== ''));

        if ($applicationIds === []) {
            return [];
        }

        $rows = $this->table()
                     ->select('id, ApplicationID')
                     ->whereIn('ApplicationID', $applicationIds)
                     ->orderBy('id', 'ASC')
                     ->get()->getResultArray();

        $map = [];
        foreach ($rows as $row) {
            $key = (string) $row['ApplicationID'];

            if (! isset($map[$key])) {
                $map[$key] = (int) $row['id'];
            }
        }

        return $map;
    }

    /**
     * Insert or update one admission record, keyed on ApplicationID.
     *
     * Update rather than insert-always because consecutive admission exports
     * overlap: the same eligibility case reappears in a later file carrying an
     * updated EligibilityApprovalDate or status. Inserting both would grow a
     * duplicate per export and force every reader to work out which row is
     * current.
     *
     * @param  array<string, string> $row      the 24 source columns
     * @param  int|null              $existing id to update, or null to insert
     * @return int the row id written
     */
    public function upsertRecord(array $row, ?int $existingId, string $importRef, ?string $username): int
    {
        $payload = [];
        foreach (self::SOURCE_COLUMNS as $column) {
            $payload[$column] = $row[$column] ?? null;
        }

        $payload['import_ref']  = $importRef;
        $payload['imported_at'] = date('Y-m-d H:i:s');
        $payload['imported_by'] = $username;

        if ($existingId !== null) {
            // dispatch_array_space is deliberately NOT touched: a candidate
            // already dispatched stays linked to the batch that dispatched
            // them, even when their admission record is refreshed.
            $this->table()->where('id', $existingId)->update($payload);

            return $existingId;
        }

        $this->table()->insert($payload);

        return (int) $this->db->insertID();
    }

    /**
     * Record which verification batch these candidates were dispatched in.
     *
     * @param int[] $ids
     */
    public function markDispatched(array $ids, string $arraySpace): void
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));

        if ($ids === []) {
            return;
        }

        $this->table()->whereIn('id', $ids)->update(['dispatch_array_space' => $arraySpace]);
    }
}
