<?php

namespace Config;

/**
 * The one authoritative list of permissions.
 *
 * Everything derives from here: the migration seed, validation in
 * AccessRightsService, the grouped Access Rights UI, the sidebar, and the
 * page-key list guarding the shared /api/* Select2 endpoints.
 *
 * It exists because the previous page-level system kept its catalog in THREE
 * places that had to agree by hand -- the access_pages table (which drove
 * rendering), AccessRightsService::PAGE_DEFINITIONS (which drove validation,
 * defaults and the matrix's checked-state map), and a hardcoded filter string
 * in Config\Routes. The failure was silent and destructive: a key present in
 * the table but missing from the const rendered as a column that was UNCHECKED
 * even for users who held it, so the next save revoked it from everyone.
 *
 * Keys are `module.action`. The module half must match the `MODULES` array key
 * exactly, because AccessRightsService derives a permission's module by
 * splitting on the first dot.
 */
class Permissions
{
    /**
     * Every action a permission may express, with the label shown in the UI.
     *
     * Not every module has every action -- Universities has no delete route,
     * university reminders have no delete, and only Students can import. The
     * per-module lists below are the truth; this is only the label lookup.
     */
    public const ACTION_LABELS = [
        'view'   => 'View',
        'create' => 'Create',
        'edit'   => 'Edit',
        'delete' => 'Delete',
        'toggle' => 'Activate / Deactivate',
        'import' => 'Import',
        'export' => 'Export',
        'print'  => 'Print / PDF',
    ];

    /**
     * Module key => [label, group_label, actions].
     *
     * The action lists are derived from the actual routes, not invented. A
     * module only offers an action if a route exists that performs it, so
     * there is no permission here that cannot be exercised.
     *
     * `group_label` is the badge text on the Access Rights cards.
     */
    public const MODULES = [
        'students' => [
            'label'       => 'Students',
            'group_label' => 'Student Permissions',
            'sort_order'  => 1,
            // view also covers generateCaseNo / getCollegeInfo / getJson,
            // import covers the blank template download as well as the
            // preview+commit pair, and print covers both dispatch letters.
            'actions'     => ['view', 'create', 'edit', 'delete', 'import', 'export', 'print'],
        ],
        'universities' => [
            'label'       => 'Universities',
            'group_label' => 'University Permissions',
            'sort_order'  => 2,
            // No delete route exists by design; deactivation is the soft path.
            'actions'     => ['view', 'create', 'edit', 'toggle', 'export'],
        ],
        'confirmations' => [
            'label'       => 'Confirmations',
            'group_label' => 'Confirmation Permissions',
            'sort_order'  => 3,
            // export covers both the pending list and the history export.
            'actions'     => ['view', 'create', 'delete', 'export', 'print'],
        ],
        'regularization' => [
            'label'       => 'Regularization',
            'group_label' => 'Regularization Permissions',
            'sort_order'  => 4,
            // generateLetter both creates and prints; it is gated on create,
            // and print governs reprinting an existing letter from history.
            'actions'     => ['view', 'create', 'edit', 'delete', 'export', 'print'],
        ],
        'reminders_university' => [
            'label'       => 'Reminders - University',
            'group_label' => 'University Reminder Permissions',
            'sort_order'  => 5,
            // No delete route for university reminder batches.
            'actions'     => ['view', 'create', 'export', 'print'],
        ],
        'reminders_student' => [
            'label'       => 'Reminders - Candidate',
            'group_label' => 'Candidate Reminder Permissions',
            'sort_order'  => 6,
            'actions'     => ['view', 'create', 'delete', 'export', 'print'],
        ],
    ];

    /**
     * The old page-level keys, and which module each became.
     *
     * Kept so the migration can expand existing grants in place and reverse
     * itself. Do not use for anything else -- these keys are gone after the
     * migration runs.
     */
    public const LEGACY_KEY_TO_MODULE = [
        'students_new'         => 'students',
        'universities'         => 'universities',
        'confirmations'        => 'confirmations',
        'regularization'       => 'regularization',
        'reminders_university' => 'reminders_university',
        'reminders_student'    => 'reminders_student',
    ];

    /** Every permission key, flat: ['students.view', 'students.create', ...]. */
    public static function allKeys(): array
    {
        $keys = [];

        foreach (self::MODULES as $module => $definition) {
            foreach ($definition['actions'] as $action) {
                $keys[] = $module . '.' . $action;
            }
        }

        return $keys;
    }

    /** Every module's `view` key -- what the shared /api/* endpoints require. */
    public static function viewKeys(): array
    {
        return array_map(
            static fn (string $module): string => $module . '.view',
            array_keys(self::MODULES)
        );
    }

    public static function isValidKey(string $key): bool
    {
        return in_array($key, self::allKeys(), true);
    }

    /** The module half of a permission key, or null if the key is unknown. */
    public static function moduleOf(string $key): ?string
    {
        $module = strstr($key, '.', true);

        return $module !== false && isset(self::MODULES[$module]) ? $module : null;
    }

    /**
     * Rows for seeding `access_pages`, in display order.
     *
     * @return list<array{page_key:string,page_label:string,module:string,module_label:string,sort_order:int}>
     */
    public static function seedRows(): array
    {
        $rows  = [];
        $order = 0;

        foreach (self::MODULES as $module => $definition) {
            foreach ($definition['actions'] as $action) {
                $rows[] = [
                    'page_key'     => $module . '.' . $action,
                    'page_label'   => $definition['label'] . ' - ' . self::ACTION_LABELS[$action],
                    'module'       => $module,
                    'module_label' => $definition['group_label'],
                    'sort_order'   => ++$order,
                ];
            }
        }

        return $rows;
    }
}
