<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Remembers how the admission export's free-text values map onto this app's
 * own master data, so an operator answers each question once rather than once
 * per file.
 *
 * The export names things the way the admission system does, and those names
 * do not resolve against this database:
 *
 *   - CertifyingBoardName: only 1 of the 7 distinct values in the sample data
 *     matches college_details.Name exactly. Two are absent from the directory
 *     entirely, one is ambiguous across 2 rows, and one is a literal "-".
 *   - CourseName: 0 of 6 match courses.name. The export uses compound values
 *     like "B.Com-2017 Pattern-T.Y.B.Com-T.Y.B.Com" where this app uses
 *     "MCOM Part I".
 *
 * So the importer asks the operator to map each distinct value once, and
 * stores the answer here. The next file only asks about values never seen
 * before.
 *
 * Deliberately generic (source_type + source_value -> target_value) rather
 * than two tables: the two mappings differ only in what they point at, and a
 * third kind (states, qualifications) would need no schema change.
 *
 * target_value stores the resolved key as a STRING rather than a foreign key:
 * for a board it is college_details.id, for a course it is the course name
 * that goes into student_details.admission_taken_in -- which is free text in
 * that table, not a relation. A real FK would only fit half the cases.
 */
class CreateImportMappingsTable extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('import_mappings')) {
            return;
        }

        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            // 'board' | 'course'
            'source_type' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
            ],
            // The raw value as it appears in the spreadsheet. 200 matches
            // e_student_data.CertifyingBoardName, the widest thing mapped.
            'source_value' => [
                'type'       => 'VARCHAR',
                'constraint' => 200,
            ],
            // college_details.id for a board; the course name for a course.
            'target_value' => [
                'type'       => 'VARCHAR',
                'constraint' => 200,
            ],
            // Username, matching the convention used by student_reminders,
            // backup_history and e_student_data.imported_by.
            'created_by' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'null'       => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);

        // One answer per source value. UNIQUE is safe here (unlike on
        // e_student_data.ApplicationID) because the table starts empty, and it
        // is what makes "remember this mapping" an upsert rather than a
        // growing pile of duplicates.
        $this->forge->addUniqueKey(['source_type', 'source_value']);

        $this->forge->createTable('import_mappings');
    }

    public function down()
    {
        $this->forge->dropTable('import_mappings', true);
    }
}
