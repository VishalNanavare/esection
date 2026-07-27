<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class StreamSeeder extends Seeder
{
    public function run()
    {
        $fields = $this->db->getFieldNames('stream_details');
        $col = in_array('stream_name', $fields) ? 'stream_name' : (in_array('stream', $fields) ? 'stream' : $fields[1]);

        $streams = [
            'FYBA', 'SYBA', 'TYBA',
            'FYBCOM', 'SYBCOM', 'TYBCOM',
            'MA Part 1', 'MA Part 2',
            'MCOM Part 1', 'MCOM Part 2',
            'MSC IT Part 1', 'MSC IT Part 2',
            'MCA'
        ];

        foreach ($streams as $s) {
            $existing = $this->db->table('stream_details')->where($col, $s)->get()->getRow();
            if (!$existing) {
                $this->db->table('stream_details')->insert([$col => $s]);
            }
        }
    }
}
