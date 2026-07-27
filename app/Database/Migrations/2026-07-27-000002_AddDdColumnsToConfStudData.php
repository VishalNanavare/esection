<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Adds the Demand Draft columns that ConfirmationModel has always written to
 * but which never existed in the live `conf_stud_data` table.
 *
 * Background
 * ----------
 * `ConfirmationModel::$allowedFields` listed student_id, dd_no, bank_name,
 * dd_date, dd_amount and en_by. None of them are present in the production
 * schema, so every DD confirmation submit threw
 *   DatabaseException: Unknown column 'student_id' in 'field list'
 * which the controller caught and flashed to the UI as raw SQL. The feature
 * has therefore never persisted a single row -- the 4,208 rows in the table
 * are all legacy, the newest dated 2026-03-14.
 *
 * The legacy app stores the owning student's id in the `name` column: see
 * esection_basic/con_view.php, which posts `name<N>` = student_details.id,
 * and esection_basic/config/conf_ins.php, which inserts it. Verified against
 * live data: all 4,208 values are numeric, 4,188 join to student_details.id,
 * and on those the stream agrees 100%. `student_id` is backfilled from it so
 * the join has a properly typed, indexable key.
 *
 * This migration is purely additive and every column is nullable; no legacy
 * column is read or modified, so down() is lossless.
 *
 * NOTE: migration 000001 is already recorded in the `migrations` table and
 * will not re-run, which is why its addColumn branch cannot be relied upon.
 */
class AddDdColumnsToConfStudData extends Migration
{
    private const TABLE = 'conf_stud_data';

    /** @var array<string, array<string, mixed>> */
    private array $columns = [
        'student_id' => ['type' => 'INT',     'constraint' => 11,  'null' => true],
        'dd_no'      => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
        'bank_name'  => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
        'dd_date'    => ['type' => 'VARCHAR', 'constraint' => 50,  'null' => true],
        'dd_amount'  => ['type' => 'VARCHAR', 'constraint' => 50,  'null' => true],
        'en_by'      => ['type' => 'VARCHAR', 'constraint' => 50,  'null' => true],
    ];

    public function up()
    {
        if (! $this->db->tableExists(self::TABLE)) {
            return;
        }

        $toAdd = [];
        foreach ($this->columns as $name => $definition) {
            if (! $this->db->fieldExists($name, self::TABLE)) {
                $toAdd[$name] = $definition;
            }
        }

        if ($toAdd !== []) {
            $this->forge->addColumn(self::TABLE, $toAdd);
        }

        // Index the new join key. Guarded so a re-run is a no-op.
        if (! $this->indexExists('idx_conf_student_id')) {
            $this->db->query(
                'CREATE INDEX idx_conf_student_id ON ' . self::TABLE . ' (student_id)'
            );
        }

        // Backfill from the legacy `name` column, which holds student_details.id.
        $this->db->query(
            'UPDATE ' . self::TABLE . "
                SET student_id = CAST(name AS UNSIGNED)
              WHERE student_id IS NULL
                AND name REGEXP '^[0-9]+$'"
        );
    }

    public function down()
    {
        if (! $this->db->tableExists(self::TABLE)) {
            return;
        }

        if ($this->indexExists('idx_conf_student_id')) {
            $this->db->query('DROP INDEX idx_conf_student_id ON ' . self::TABLE);
        }

        $toDrop = array_filter(
            array_keys($this->columns),
            fn (string $name): bool => $this->db->fieldExists($name, self::TABLE)
        );

        if ($toDrop !== []) {
            $this->forge->dropColumn(self::TABLE, array_values($toDrop));
        }
    }

    private function indexExists(string $indexName): bool
    {
        foreach ($this->db->getIndexData(self::TABLE) as $index) {
            if ($index->name === $indexName) {
                return true;
            }
        }

        return false;
    }
}
