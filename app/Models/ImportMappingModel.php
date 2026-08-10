<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Remembered answers to "which university is this certifying board?" and
 * "which stream is this course?", so the importer asks once per value rather
 * than once per file.
 *
 * See 2026-08-10-000002_CreateImportMappingsTable for why the table is generic
 * and why target_value is a string rather than a foreign key.
 */
class ImportMappingModel extends Model
{
    public const TYPE_BOARD  = 'board';
    public const TYPE_COURSE = 'course';

    protected $table            = 'import_mappings';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $allowedFields    = [
        'source_type', 'source_value', 'target_value', 'created_by', 'created_at', 'updated_at',
    ];

    /**
     * Fresh builder per call -- the idiom the other Models use. Model::builder()
     * returns a SHARED builder that accumulates where-clauses across calls
     * within one request; $db->table() returns a new one every time.
     */
    private function table(): \CodeIgniter\Database\BaseBuilder
    {
        return $this->db->table($this->table);
    }

    /**
     * Every remembered mapping of one kind, as source_value => target_value.
     *
     * Returned as a lookup map rather than rows because that is how the
     * importer uses it: one query, then an in-memory hit test per distinct
     * value in the file.
     *
     * @return array<string, string>
     */
    public function getMap(string $sourceType): array
    {
        $rows = $this->table()
                     ->select('source_value, target_value')
                     ->where('source_type', $sourceType)
                     ->get()->getResultArray();

        $map = [];
        foreach ($rows as $row) {
            $map[(string) $row['source_value']] = (string) $row['target_value'];
        }

        return $map;
    }

    /**
     * Remember one mapping, replacing any previous answer for that value.
     *
     * An operator correcting a mapping they got wrong last time must not be
     * blocked by the unique key, and must not end up with two answers -- so
     * this updates in place rather than inserting.
     */
    public function remember(string $sourceType, string $sourceValue, string $targetValue, ?string $username): void
    {
        $sourceValue = trim($sourceValue);
        $targetValue = trim($targetValue);

        if ($sourceValue === '' || $targetValue === '') {
            return;
        }

        $now = date('Y-m-d H:i:s');

        $existing = $this->table()
                         ->where('source_type', $sourceType)
                         ->where('source_value', $sourceValue)
                         ->get()->getRowArray();

        if ($existing) {
            // Only touch the row if the answer actually changed, so updated_at
            // stays meaningful as "when this mapping was last corrected".
            if ((string) $existing['target_value'] === $targetValue) {
                return;
            }

            $this->table()
                 ->where('id', $existing['id'])
                 ->update(['target_value' => $targetValue, 'updated_at' => $now]);

            return;
        }

        $this->table()->insert([
            'source_type'  => $sourceType,
            'source_value' => $sourceValue,
            'target_value' => $targetValue,
            'created_by'   => $username,
            'created_at'   => $now,
            'updated_at'   => $now,
        ]);
    }
}
