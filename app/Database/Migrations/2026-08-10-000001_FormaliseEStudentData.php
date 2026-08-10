<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Make `e_student_data` a real, supported table.
 *
 * It arrived with the esection_basic backup and has been carrying 21 sample
 * rows ever since: no migration created it, nothing in app/ read or wrote it,
 * and it has no index beyond its PRIMARY key. It is the shape of the IDOL
 * admission system's Excel export, and the candidate importer now writes the
 * complete 24-column admission record into it.
 *
 * This migration does three things:
 *
 *  1. Creates the table if it is absent, so a fresh deployment matches a
 *     migrated one. The 24 columns and their widths are reproduced exactly as
 *     the legacy table declares them -- the export's own field sizes -- so an
 *     existing installation needs no data conversion.
 *  2. Adds the import-tracking columns. Without them a bad import cannot be
 *     identified afterwards, let alone undone.
 *  3. Indexes the three columns the importer actually looks rows up by. Every
 *     one of those is a full table scan today.
 *
 * ApplicationID is indexed NON-uniquely on purpose. It is the business key the
 * importer upserts on, but a UNIQUE index cannot be created here: the existing
 * sample data already contains four repeated ApplicationIDs (4198, 12636,
 * 13913, 21900 -- the same eligibility case re-exported with a later approval
 * date), so the index would fail to build. Uniqueness is therefore enforced in
 * the application, by EStudentDataModel::findByApplicationId() before write.
 */
class FormaliseEStudentData extends Migration
{
    private const TABLE = 'e_student_data';

    /**
     * The admission export's own 24 columns, in its own order and widths.
     * Only used when the table does not already exist.
     */
    private const SOURCE_COLUMNS = [
        'AdmissionYear'                   => 20,
        'ApplicationID'                   => 25,
        'FirstName'                       => 50,
        'MiddleName'                      => 50,
        'LastName'                        => 50,
        'NameOnMarkSheet'                 => 500,
        'Gender'                          => 10,
        'CorrespondenceAddress'           => 1000,
        'PermanentAddress'                => 1000,
        'PrimaryMobileNo'                 => 30,
        'AlternateMobileNo'               => 30,
        'EmailID'                         => 50,
        'CourseName'                      => 150,
        'LastQual'                        => 150,
        'QualificationName'               => 150,
        'CertifyingBodyType'              => 15,
        'CertifyingBoardName'             => 200,
        'CertifyingStateName'             => 50,
        'AdmissionFeeAmount'              => 15,
        'AdmissionFeeTransactionDateTime' => 100,
        'ApplicationStatus'               => 150,
        'SCEligibilityStatus'             => 15,
        'EligibilityCaseNo'               => 50,
        'EligibilityApprovalDate'         => 65,
    ];

    public function up()
    {
        if (! $this->db->tableExists(self::TABLE)) {
            $fields = [
                'id' => [
                    'type'           => 'INT',
                    'constraint'     => 11,
                    'unsigned'       => true,
                    'auto_increment' => true,
                ],
            ];

            foreach (self::SOURCE_COLUMNS as $name => $width) {
                $fields[$name] = [
                    'type'       => 'VARCHAR',
                    'constraint' => $width,
                    'null'       => true,
                ];
            }

            $this->forge->addField($fields);
            $this->forge->addKey('id', true);
            $this->forge->createTable(self::TABLE);
        }

        // --- Import tracking -------------------------------------------------
        // Nullable throughout: the 21 pre-existing rows predate the importer
        // and must not be invented a provenance they never had.
        $tracking = [
            // Which upload produced this row, so one import can be reviewed or
            // reversed as a unit.
            'import_ref' => [
                'type'       => 'VARCHAR',
                'constraint' => 40,
                'null'       => true,
            ],
            'imported_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            // Username, matching student_reminders.created_by and
            // backup_history.created_by -- survives a user rename or deletion,
            // which a user id would not.
            'imported_by' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'null'       => true,
            ],
            // The verification batch this candidate was dispatched in, once it
            // has been. Links back to student_details.array_space WITHOUT
            // altering student_details, and stays null for a row that is
            // recorded but not yet dispatched (e.g. eligibility still Pending).
            'dispatch_array_space' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'null'       => true,
            ],
        ];

        foreach ($tracking as $column => $definition) {
            if (! $this->db->fieldExists($column, self::TABLE)) {
                $this->forge->addColumn(self::TABLE, [$column => $definition]);
            }
        }

        // --- Indexes ---------------------------------------------------------
        $this->addIndexIfMissing('idx_esd_application_id', 'ApplicationID');
        $this->addIndexIfMissing('idx_esd_case_no', 'EligibilityCaseNo');
        $this->addIndexIfMissing('idx_esd_board', 'CertifyingBoardName');
        $this->addIndexIfMissing('idx_esd_import_ref', 'import_ref');
    }

    public function down()
    {
        // The table itself is NOT dropped: it predates this migration and holds
        // real data. Only what this migration added is removed.
        foreach (['idx_esd_application_id', 'idx_esd_case_no', 'idx_esd_board', 'idx_esd_import_ref'] as $index) {
            if ($this->indexExists($index)) {
                $this->db->query('DROP INDEX ' . $index . ' ON ' . self::TABLE);
            }
        }

        foreach (['import_ref', 'imported_at', 'imported_by', 'dispatch_array_space'] as $column) {
            if ($this->db->fieldExists($column, self::TABLE)) {
                $this->forge->dropColumn(self::TABLE, $column);
            }
        }
    }

    /**
     * CertifyingBoardName is varchar(200); an index prefix keeps the key short
     * enough to stay comfortably inside InnoDB's limit while still being
     * selective -- board names differ well within their first 100 characters.
     */
    private function addIndexIfMissing(string $indexName, string $column): void
    {
        if ($this->indexExists($indexName)) {
            return;
        }

        $length = $column === 'CertifyingBoardName' ? '(100)' : '';

        $this->db->query(
            'CREATE INDEX ' . $indexName . ' ON ' . self::TABLE . ' (' . $column . $length . ')'
        );
    }

    /** CI4's Forge has no "does this index exist" check. */
    private function indexExists(string $indexName): bool
    {
        if (! $this->db->tableExists(self::TABLE)) {
            return false;
        }

        return $this->db->query(
            'SHOW INDEX FROM ' . $this->db->protectIdentifiers(self::TABLE, true) . ' WHERE Key_name = ?',
            [$indexName]
        )->getResultArray() !== [];
    }
}
