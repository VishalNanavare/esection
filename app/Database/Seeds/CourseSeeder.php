<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Seeds courses from the union of distinct values already found in
 * stream_details.Division and student_details.admission_taken_in, so
 * nothing currently searchable/selectable across the 4 pages that share the
 * api/streams endpoint goes missing once that endpoint's data source
 * switches to this table (Settings Phase 10). Idempotent.
 *
 * Excludes "Select Admission Taken In" -- a literal placeholder string that
 * got saved as real data by a legacy form bug (6 rows), not a real course.
 */
class CourseSeeder extends Seeder
{
    private const JUNK_VALUES = ['Select Admission Taken In'];

    public function run()
    {
        $fromStreams = $this->db->table('stream_details')
            ->select('Division AS name')
            ->distinct()
            ->where('Division IS NOT NULL')
            ->where('Division !=', '')
            ->get()
            ->getResultArray();

        $fromStudents = $this->db->table('student_details')
            ->select('admission_taken_in AS name')
            ->distinct()
            ->where('admission_taken_in IS NOT NULL')
            ->where('admission_taken_in !=', '')
            ->get()
            ->getResultArray();

        $names = [];
        foreach (array_merge($fromStreams, $fromStudents) as $row) {
            $name = trim((string) $row['name']);
            if ($name === '' || in_array($name, self::JUNK_VALUES, true)) {
                continue;
            }
            $names[$name] = true; // dedupe across both sources
        }

        foreach (array_keys($names) as $name) {
            $existing = $this->db->table('courses')->where('name', $name)->get()->getRow();
            if ($existing) {
                continue;
            }

            $this->db->table('courses')->insert([
                'name'       => $name,
                'is_active'  => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }
}
