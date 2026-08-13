<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use Config\Permissions;

/**
 * Replaces the 6 page-level access keys with 32 per-action permissions.
 *
 * Before this, a page grant was indivisible: `students_new` carried view,
 * create, edit, Excel import, export, both dispatch PDFs AND hard delete
 * across 16 routes, with no way to grant read-only access to anything.
 *
 * The rule for this migration is that NOBODY LOSES ANYTHING. Every existing
 * grant is expanded into the full action set of its module before the old key
 * is removed, so each of the six staff accounts wakes up able to do exactly
 * what it could do yesterday -- no more, no less. Tightening individuals is a
 * deliberate act afterwards, not a side effect of upgrading.
 *
 * Ordering matters: expand first, delete the legacy rows last. If this is
 * interrupted between the two, the user holds BOTH the old and the new keys
 * and is over-granted rather than locked out -- the safe direction to fail.
 */
class GranularAccessRights extends Migration
{
    private const OLD_KEYS = [
        'students_new',
        'universities',
        'confirmations',
        'regularization',
        'reminders_university',
        'reminders_student',
    ];

    public function up()
    {
        $this->addModuleColumns();
        $this->seedPermissionRows();
        $this->expandExistingGrants();
        $this->removeLegacyRows();
    }

    public function down()
    {
        $this->collapseGrantsToLegacy();
        $this->restoreLegacyPageRows();
        $this->dropModuleColumns();
    }

    // -----------------------------------------------------------------
    // up()
    // -----------------------------------------------------------------

    /** `module` groups the cards in the UI; `module_label` is the badge text. */
    private function addModuleColumns(): void
    {
        $fields = $this->db->getFieldNames('access_pages');

        if (! in_array('module', $fields, true)) {
            $this->forge->addColumn('access_pages', [
                'module' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 40,
                    'null'       => true,
                    'after'      => 'page_label',
                ],
            ]);
        }

        if (! in_array('module_label', $fields, true)) {
            $this->forge->addColumn('access_pages', [
                'module_label' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 100,
                    'null'       => true,
                    'after'      => 'module',
                ],
            ]);
        }
    }

    /**
     * Insert-or-update each of the 32 catalog rows.
     *
     * Written as upsert-by-key rather than "truncate and re-insert" so that
     * re-running it can never orphan a grant: user_page_access holds a VARCHAR
     * copy of page_key with no foreign key, so deleting a row here would
     * silently disconnect every grant referencing it.
     */
    private function seedPermissionRows(): void
    {
        $now = date('Y-m-d H:i:s');

        foreach (Permissions::seedRows() as $row) {
            $exists = $this->db->table('access_pages')
                ->where('page_key', $row['page_key'])
                ->countAllResults() > 0;

            if ($exists) {
                $this->db->table('access_pages')
                    ->where('page_key', $row['page_key'])
                    ->update([
                        'page_label'   => $row['page_label'],
                        'module'       => $row['module'],
                        'module_label' => $row['module_label'],
                        'sort_order'   => $row['sort_order'],
                        'updated_at'   => $now,
                    ]);

                continue;
            }

            $this->db->table('access_pages')->insert($row + [
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * Every holder of an old page key gains that module's whole action set.
     *
     * Deliberately reads the CURRENT grants for each user and inserts only the
     * keys they are missing, rather than blind-inserting: user_page_access has
     * UNIQUE(user_id, page_key), so a blind insertBatch would abort the whole
     * migration on a re-run.
     */
    private function expandExistingGrants(): void
    {
        $legacyGrants = $this->db->table('user_page_access')
            ->select('user_id, page_key, granted_by')
            ->whereIn('page_key', self::OLD_KEYS)
            ->get()
            ->getResultArray();

        if ($legacyGrants === []) {
            return;
        }

        $now     = date('Y-m-d H:i:s');
        $pending = [];

        foreach ($legacyGrants as $grant) {
            $module = Permissions::LEGACY_KEY_TO_MODULE[$grant['page_key']] ?? null;

            if ($module === null) {
                continue;
            }

            foreach (Permissions::MODULES[$module]['actions'] as $action) {
                $pending[(int) $grant['user_id']][$module . '.' . $action] = $grant['granted_by'];
            }
        }

        foreach ($pending as $userId => $keys) {
            $existing = array_column(
                $this->db->table('user_page_access')
                    ->select('page_key')
                    ->where('user_id', $userId)
                    ->get()
                    ->getResultArray(),
                'page_key'
            );

            $rows = [];

            foreach ($keys as $key => $grantedBy) {
                if (in_array($key, $existing, true)) {
                    continue;
                }

                $rows[] = [
                    'user_id'    => $userId,
                    'page_key'   => $key,
                    'granted_by' => $grantedBy,
                    'granted_at' => $now,
                ];
            }

            if ($rows !== []) {
                $this->db->table('user_page_access')->insertBatch($rows);
            }
        }
    }

    /** Last, so an interruption over-grants rather than locking anyone out. */
    private function removeLegacyRows(): void
    {
        $this->db->table('user_page_access')->whereIn('page_key', self::OLD_KEYS)->delete();
        $this->db->table('access_pages')->whereIn('page_key', self::OLD_KEYS)->delete();
    }

    // -----------------------------------------------------------------
    // down()
    // -----------------------------------------------------------------

    /**
     * Collapse back: holding ANY action of a module restores the old page key.
     *
     * That is the correct inverse -- under the old scheme the page key WAS the
     * whole action set, so anyone holding even one action must get the page
     * back or they would lose access that up() had preserved.
     */
    private function collapseGrantsToLegacy(): void
    {
        $moduleToLegacy = array_flip(Permissions::LEGACY_KEY_TO_MODULE);

        $granular = $this->db->table('user_page_access')
            ->select('user_id, page_key, granted_by')
            ->whereIn('page_key', Permissions::allKeys())
            ->get()
            ->getResultArray();

        $now     = date('Y-m-d H:i:s');
        $restore = [];

        foreach ($granular as $grant) {
            $module = Permissions::moduleOf($grant['page_key']);

            if ($module === null || ! isset($moduleToLegacy[$module])) {
                continue;
            }

            $restore[(int) $grant['user_id']][$moduleToLegacy[$module]] = $grant['granted_by'];
        }

        foreach ($restore as $userId => $keys) {
            foreach ($keys as $legacyKey => $grantedBy) {
                $exists = $this->db->table('user_page_access')
                    ->where('user_id', $userId)
                    ->where('page_key', $legacyKey)
                    ->countAllResults() > 0;

                if ($exists) {
                    continue;
                }

                $this->db->table('user_page_access')->insert([
                    'user_id'    => $userId,
                    'page_key'   => $legacyKey,
                    'granted_by' => $grantedBy,
                    'granted_at' => $now,
                ]);
            }
        }

        $this->db->table('user_page_access')->whereIn('page_key', Permissions::allKeys())->delete();
    }

    private function restoreLegacyPageRows(): void
    {
        $now   = date('Y-m-d H:i:s');
        $order = 0;

        $labels = [
            'students_new'         => 'Students - New Entry',
            'universities'         => 'Universities',
            'confirmations'        => 'Confirmations',
            'regularization'       => 'Regularization',
            'reminders_university' => 'Reminders - University',
            'reminders_student'    => 'Reminders - Student',
        ];

        foreach ($labels as $key => $label) {
            $order++;

            if ($this->db->table('access_pages')->where('page_key', $key)->countAllResults() > 0) {
                continue;
            }

            $this->db->table('access_pages')->insert([
                'page_key'   => $key,
                'page_label' => $label,
                'sort_order' => $order,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $this->db->table('access_pages')->whereIn('page_key', Permissions::allKeys())->delete();
    }

    private function dropModuleColumns(): void
    {
        $fields = $this->db->getFieldNames('access_pages');

        foreach (['module', 'module_label'] as $column) {
            if (in_array($column, $fields, true)) {
                $this->forge->dropColumn('access_pages', $column);
            }
        }
    }
}
