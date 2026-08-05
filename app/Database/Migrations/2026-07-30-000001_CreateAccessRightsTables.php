<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Foundation for Settings > Access Rights: a fixed catalog of the 6
 * operational feature pages, plus a grant table saying which staff users can
 * reach which of them. This system only ever governs `staff` accounts --
 * `role === 'admin'` bypasses every check unconditionally and deliberately
 * never appears in user_page_access at all.
 *
 * Seeded here (not a separate Seeder) because AccessFilter needs these rows
 * to exist the moment this migration runs. Initial grants give every
 * CURRENT staff account every page -- "everyone can access everything,
 * tighten later" is the explicit rollout philosophy, not a placeholder.
 */
class CreateAccessRightsTables extends Migration
{
    private const PAGES = [
        ['page_key' => 'students_new',         'page_label' => 'Students - New Entry',   'sort_order' => 1],
        ['page_key' => 'universities',         'page_label' => 'Universities',           'sort_order' => 2],
        ['page_key' => 'confirmations',        'page_label' => 'Confirmations',          'sort_order' => 3],
        ['page_key' => 'regularization',       'page_label' => 'Regularization',         'sort_order' => 4],
        ['page_key' => 'reminders_university', 'page_label' => 'Reminders - University', 'sort_order' => 5],
        ['page_key' => 'reminders_student',    'page_label' => 'Reminders - Student',    'sort_order' => 6],
    ];

    public function up()
    {
        if (! $this->db->tableExists('access_pages')) {
            $this->forge->addField([
                'id' => [
                    'type'           => 'INT',
                    'constraint'     => 11,
                    'unsigned'       => true,
                    'auto_increment' => true,
                ],
                'page_key' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 50,
                    'unique'     => true,
                ],
                'page_label' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 100,
                ],
                'sort_order' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'default'    => 0,
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
            $this->forge->createTable('access_pages');
        }

        if (! $this->db->tableExists('user_page_access')) {
            $this->forge->addField([
                'id' => [
                    'type'           => 'INT',
                    'constraint'     => 11,
                    'unsigned'       => true,
                    'auto_increment' => true,
                ],
                'user_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                ],
                'page_key' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 50,
                ],
                'granted_by' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'null'       => true,
                ],
                'granted_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey(['user_id', 'page_key']);
            $this->forge->createTable('user_page_access');
        }

        $this->seedPages();
        $this->seedInitialGrants();
    }

    private function seedPages(): void
    {
        foreach (self::PAGES as $page) {
            $exists = $this->db->table('access_pages')->where('page_key', $page['page_key'])->get()->getRow();

            if (! $exists) {
                $this->db->table('access_pages')->insert(array_merge($page, [
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]));
            }
        }
    }

    private function seedInitialGrants(): void
    {
        $staffIds = array_column(
            $this->db->table('users')->select('id')->where('role', 'staff')->get()->getResultArray(),
            'id'
        );

        foreach ($staffIds as $userId) {
            foreach (self::PAGES as $page) {
                $exists = $this->db->table('user_page_access')
                    ->where('user_id', $userId)
                    ->where('page_key', $page['page_key'])
                    ->get()->getRow();

                if (! $exists) {
                    $this->db->table('user_page_access')->insert([
                        'user_id'    => $userId,
                        'page_key'   => $page['page_key'],
                        'granted_by' => null,
                        'granted_at' => date('Y-m-d H:i:s'),
                    ]);
                }
            }
        }
    }

    public function down()
    {
        $this->forge->dropTable('user_page_access', true);
        $this->forge->dropTable('access_pages', true);
    }
}
