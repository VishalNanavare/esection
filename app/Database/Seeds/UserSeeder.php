<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run()
    {
        $users = [
            [
                'username'      => 'admin',
                'email'         => 'admin@idol.mu.ac.in',
                'password_hash' => password_hash('a seeded default password', PASSWORD_BCRYPT),
                'role'          => 'admin',
                'full_name'     => 'System Administrator',
                'created_at'    => date('Y-m-d H:i:s'),
                'updated_at'    => date('Y-m-d H:i:s'),
            ],
            [
                'username'      => 'esection1',
                'email'         => 'esection1@idol.mu.ac.in',
                'password_hash' => password_hash('a seeded default password', PASSWORD_BCRYPT),
                'role'          => 'staff',
                'full_name'     => 'E-Section Staff Desk 1',
                'created_at'    => date('Y-m-d H:i:s'),
                'updated_at'    => date('Y-m-d H:i:s'),
            ],
            [
                'username'      => 'esection2',
                'email'         => 'esection2@idol.mu.ac.in',
                'password_hash' => password_hash('a seeded default password', PASSWORD_BCRYPT),
                'role'          => 'staff',
                'full_name'     => 'E-Section Staff Desk 2',
                'created_at'    => date('Y-m-d H:i:s'),
                'updated_at'    => date('Y-m-d H:i:s'),
            ],
            [
                'username'      => 'esection3',
                'email'         => 'esection3@idol.mu.ac.in',
                'password_hash' => password_hash('a seeded default password', PASSWORD_BCRYPT),
                'role'          => 'staff',
                'full_name'     => 'E-Section Staff Desk 3',
                'created_at'    => date('Y-m-d H:i:s'),
                'updated_at'    => date('Y-m-d H:i:s'),
            ],
            [
                'username'      => 'esection4',
                'email'         => 'esection4@idol.mu.ac.in',
                'password_hash' => password_hash('a seeded default password', PASSWORD_BCRYPT),
                'role'          => 'staff',
                'full_name'     => 'E-Section Staff Desk 4',
                'created_at'    => date('Y-m-d H:i:s'),
                'updated_at'    => date('Y-m-d H:i:s'),
            ],
            [
                'username'      => 'esection5',
                'email'         => 'esection5@idol.mu.ac.in',
                'password_hash' => password_hash('a seeded default password', PASSWORD_BCRYPT),
                'role'          => 'staff',
                'full_name'     => 'E-Section Staff Desk 5',
                'created_at'    => date('Y-m-d H:i:s'),
                'updated_at'    => date('Y-m-d H:i:s'),
            ],
            [
                'username'      => 'esection6',
                'email'         => 'esection6@idol.mu.ac.in',
                'password_hash' => password_hash('a seeded default password', PASSWORD_BCRYPT),
                'role'          => 'staff',
                'full_name'     => 'E-Section Staff Desk 6',
                'created_at'    => date('Y-m-d H:i:s'),
                'updated_at'    => date('Y-m-d H:i:s'),
            ],
        ];

        foreach ($users as $user) {
            $existing = $this->db->table('users')->where('username', $user['username'])->get()->getRow();
            if (!$existing) {
                $this->db->table('users')->insert($user);
            }
        }
    }
}
