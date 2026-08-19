<?php

namespace Tests\Unit;

use App\Services\SettingService;
use CodeIgniter\Test\CIUnitTestCase;
use ReflectionClass;

/**
 * Turning off Settings > Feature Toggles > Excel Import must hide the whole
 * feature, not just refuse the upload at the end of it.
 *
 * Before this, feature_import_enabled was checked in exactly one place --
 * StudentImportService::assertAcceptableUpload(). The sidebar link, the button
 * on New Form, the import screen itself and the blank-template download all
 * ignored it, so with the feature "off" an operator still saw the menu item,
 * opened a working-looking page, chose a workbook, waited for it to upload, and
 * only then was told the feature was switched off.
 *
 * feature_enabled() reads through SettingService's per-request memo, so these
 * tests seed that memo rather than needing a settings table.
 *
 * @internal
 */
final class ImportFeatureToggleTest extends CIUnitTestCase
{
    private function setImportEnabled(bool $enabled): void
    {
        helper('esection');

        $property = (new ReflectionClass(SettingService::class))->getProperty('cache');
        $property->setValue(null, ['feature_import_enabled' => $enabled ? '1' : '0']);
    }

    protected function tearDown(): void
    {
        (new ReflectionClass(SettingService::class))->getProperty('cache')->setValue(null, []);

        parent::tearDown();
    }

    public function testHelperReflectsTheToggle(): void
    {
        $this->setImportEnabled(false);
        $this->assertFalse(feature_enabled('feature_import_enabled'));

        $this->setImportEnabled(true);
        $this->assertTrue(feature_enabled('feature_import_enabled'));
    }

    /**
     * The five places that must consult the toggle. Asserted against source
     * because four of them are a view conditional or an early return, and the
     * failure they guard against is a future edit quietly dropping one -- which
     * would restore exactly the half-hidden state this fixed.
     *
     * @dataProvider guardedEntryPoints
     */
    public function testEntryPointConsultsTheToggle(string $file, string $needle, string $why): void
    {
        $source = (string) file_get_contents(ROOTPATH . $file);

        $this->assertStringContainsString($needle, $source, $why);
    }

    public static function guardedEntryPoints(): array
    {
        return [
            'sidebar link' => [
                'app/Views/layouts/app.php',
                "feature_enabled('feature_import_enabled') && can('students.import')",
                'The sidebar would advertise a feature that is switched off.',
            ],
            'new form button' => [
                'app/Views/students/new_form.php',
                "feature_enabled('feature_import_enabled') && can('students.import')",
                'The New Form screen would still offer "Import from Excel".',
            ],
            'import screen' => [
                'app/Controllers/Students.php',
                "if (! feature_enabled('feature_import_enabled'))",
                'students/import would render a working-looking upload page.',
            ],
            'upload validator' => [
                'app/Services/StudentImportService.php',
                "if (! feature_enabled('feature_import_enabled'))",
                'preview and commit would accept a workbook.',
            ],
        ];
    }

    /**
     * importForm() and importTemplate() must BOTH carry the guard -- the blank
     * template is a plain GET anyone can bookmark, with no upload for the
     * service to refuse.
     */
    public function testBothControllerEntryPointsAreGuarded(): void
    {
        $source = (string) file_get_contents(ROOTPATH . 'app/Controllers/Students.php');

        $this->assertSame(
            2,
            substr_count($source, "if (! feature_enabled('feature_import_enabled'))"),
            'Expected the guard on both importForm() and importTemplate().'
        );
    }

    /**
     * The permission check must survive alongside the toggle. They answer
     * different questions -- "is this feature on for anyone" versus "may THIS
     * user use it" -- and replacing one with the other would hand the import to
     * every logged-in account.
     */
    public function testPermissionCheckIsNotReplacedByTheToggle(): void
    {
        foreach (['app/Views/layouts/app.php', 'app/Views/students/new_form.php'] as $file) {
            $this->assertStringContainsString(
                "can('students.import')",
                (string) file_get_contents(ROOTPATH . $file),
                $file . ' lost its permission check.'
            );
        }
    }
}
