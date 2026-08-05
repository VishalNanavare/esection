<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Seeds academic_years from the DISTINCT admission_taken_year values already
 * live in student_details, rather than a fixed list -- the 5 values
 * hardcoded in the old students/new_form.php dropdown were incomplete and
 * partly inaccurate relative to what students' records actually contain
 * (e.g. "JAN-DEC 2025-2025" was never a real value; the real ones are
 * "JAN-DEC 2020-2020" through "2023-2023"). Idempotent: safe to re-run.
 *
 * Deliberately does not mark any row is_current -- guessing the
 * institution's actual current academic year from today's date risks being
 * wrong; the admin sets it explicitly via Settings > Academic Years.
 */
class AcademicYearSeeder extends Seeder
{
    public function run()
    {
        $years = $this->db->table('student_details')
            ->select('admission_taken_year')
            ->distinct()
            ->where('admission_taken_year IS NOT NULL')
            ->where('admission_taken_year !=', '')
            ->orderBy('admission_taken_year', 'ASC')
            ->get()
            ->getResultArray();

        foreach ($years as $row) {
            $label = trim((string) $row['admission_taken_year']);
            if ($label === '') {
                continue;
            }

            $existing = $this->db->table('academic_years')->where('year_label', $label)->get()->getRow();
            if ($existing) {
                continue;
            }

            $this->db->table('academic_years')->insert([
                'year_label' => $label,
                'is_current' => 0,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }
}
