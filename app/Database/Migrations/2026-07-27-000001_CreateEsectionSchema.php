<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateEsectionSchema extends Migration
{
    public function up()
    {
        // 1. Create Users Table
        if (!$this->db->tableExists('users')) {
            $this->forge->addField([
                'id' => [
                    'type'           => 'INT',
                    'constraint'     => 11,
                    'unsigned'       => true,
                    'auto_increment' => true,
                ],
                'username' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 50,
                    'unique'     => true,
                ],
                'email' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 100,
                    'null'       => true,
                ],
                'password_hash' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 255,
                ],
                'role' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 20,
                    'default'    => 'staff',
                ],
                'full_name' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 100,
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
            $this->forge->createTable('users');
        }

        // 2. Create College Details Table if missing
        if (!$this->db->tableExists('college_details')) {
            $this->forge->addField([
                'id' => [
                    'type'           => 'INT',
                    'constraint'     => 11,
                    'auto_increment' => true,
                ],
                'Name' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 255,
                    'null'       => true,
                ],
                'States' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 150,
                    'null'       => true,
                ],
                'Address' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
                'email_id' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 100,
                    'null'       => true,
                ],
                'mobile_no' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 50,
                    'null'       => true,
                ],
                'fees' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 20,
                    'null'       => true,
                ],
                'head_name' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 150,
                    'null'       => true,
                ],
                'in_favour_of' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 255,
                    'null'       => true,
                ],
                'sel_data' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 5,
                    'default'    => '1',
                ],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->createTable('college_details');
        }

        // 3. Create Student Details Table if missing
        if (!$this->db->tableExists('student_details')) {
            $this->forge->addField([
                'id' => [
                    'type'           => 'INT',
                    'constraint'     => 11,
                    'auto_increment' => true,
                ],
                'array_space' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 50,
                    'null'       => true,
                ],
                'to_name' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 150,
                    'null'       => true,
                ],
                'clg_add' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
                'admission_taken_year' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 50,
                    'null'       => true,
                ],
                'student_name' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 150,
                    'null'       => true,
                ],
                'student_nee_name' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 150,
                    'null'       => true,
                ],
                'eligibility_case_no' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 100,
                    'null'       => true,
                ],
                'admission_taken_in' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 100,
                    'null'       => true,
                ],
                'verification_of_marksheet_done_by_you' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 150,
                    'null'       => true,
                ],
                'in_favour_of' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 255,
                    'null'       => true,
                ],
                'en_time' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 50,
                    'null'       => true,
                ],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->createTable('student_details');
        }

        // 4. Create or Ensure Conf Stud Data Table
        if (!$this->db->tableExists('conf_stud_data')) {
            $this->forge->addField([
                'id' => [
                    'type'           => 'INT',
                    'constraint'     => 11,
                    'auto_increment' => true,
                ],
                'student_id' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 50,
                    'null'       => true,
                ],
                'stream' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 100,
                    'null'       => true,
                ],
                'dd_no' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 100,
                    'null'       => true,
                ],
                'bank_name' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 150,
                    'null'       => true,
                ],
                'dd_date' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 50,
                    'null'       => true,
                ],
                'dd_amount' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 50,
                    'null'       => true,
                ],
                'remark' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 255,
                    'null'       => true,
                ],
                'en_by' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 50,
                    'null'       => true,
                ],
                'en_time' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 50,
                    'null'       => true,
                ],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->createTable('conf_stud_data');
        } else {
            // Ensure student_id and dd_amount columns exist on existing table
            if (!$this->db->fieldExists('student_id', 'conf_stud_data')) {
                $this->forge->addColumn('conf_stud_data', [
                    'student_id' => [
                        'type'       => 'VARCHAR',
                        'constraint' => 50,
                        'null'       => true,
                    ]
                ]);
            }
            if (!$this->db->fieldExists('dd_amount', 'conf_stud_data')) {
                $this->forge->addColumn('conf_stud_data', [
                    'dd_amount' => [
                        'type'       => 'VARCHAR',
                        'constraint' => 50,
                        'null'       => true,
                    ]
                ]);
            }
            if (!$this->db->fieldExists('dd_no', 'conf_stud_data')) {
                $this->forge->addColumn('conf_stud_data', [
                    'dd_no' => [
                        'type'       => 'VARCHAR',
                        'constraint' => 100,
                        'null'       => true,
                    ]
                ]);
            }
            if (!$this->db->fieldExists('bank_name', 'conf_stud_data')) {
                $this->forge->addColumn('conf_stud_data', [
                    'bank_name' => [
                        'type'       => 'VARCHAR',
                        'constraint' => 150,
                        'null'       => true,
                    ]
                ]);
            }
            if (!$this->db->fieldExists('dd_date', 'conf_stud_data')) {
                $this->forge->addColumn('conf_stud_data', [
                    'dd_date' => [
                        'type'       => 'VARCHAR',
                        'constraint' => 50,
                        'null'       => true,
                    ]
                ]);
            }
        }

        // 5. Create Stream Details Table
        if (!$this->db->tableExists('stream_details')) {
            $this->forge->addField([
                'id' => [
                    'type'           => 'INT',
                    'constraint'     => 11,
                    'auto_increment' => true,
                ],
                'stream_name' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 100,
                ],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->createTable('stream_details');
        }
    }

    public function down()
    {
        $this->forge->dropTable('users', true);
        $this->forge->dropTable('stream_details', true);
    }
}
