<?php

namespace Toast\ThemeFonts\Tasks;

use SilverStripe\Core\Extensible;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DB;
use SilverStripe\PolyExecution\PolyOutput;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Toast\ThemeFonts\Extensions\SiteConfigExtension;
use Toast\ThemeFonts\Models\ThemeFontConfig;
use Toast\ThemeFonts\Models\ThemeFontFamily;

/**
 * Upgrades data from earlier versions of the module. Run after `sake db:build --flush`:
 *
 *   sake tasks:theme-fonts-migrate [--dry-run]
 *
 * - ThemeFontConfig.FontFamilyID (a duplicate of the ThemeFontFamily relation) is copied into
 *   ThemeFontFamilyID where that is empty. The column is renamed `_obsolete_FontFamilyID` by db:build.
 * - Uploaded font files that were never published are published so visitors can load them.
 * - Default font configs are created for every site (including each subsite).
 * - The generated font files are rebuilt for every site.
 *
 * The task is idempotent and safe to run more than once.
 */
class MigrateThemeFontsTask extends BuildTask
{
    use Extensible;

    protected static string $commandName = 'theme-fonts-migrate';

    protected string $title = 'Theme Fonts: migrate data';

    protected static string $description = 'Upgrades theme font data from earlier versions of the module and rebuilds the generated font files for every site';

    private static array $permissions_for_browser_execution = ['ADMIN'];

    /**
     * Columns that held the family ID before it was merged into the ThemeFontFamily relation
     */
    private static array $legacy_family_columns = ['FontFamilyID', '_obsolete_FontFamilyID'];

    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        $dryRun = (bool) $input->getOption('dry-run');

        if ($dryRun) {
            $output->writeln('Dry run, nothing will be written');
        }

        $this->migrateFamilyIDs($output, $dryRun);
        $this->extend('onAfterMigrateFamilyIDs', $output, $dryRun);

        if (!$dryRun) {
            $this->publishFontFiles($output);
            $this->rebuildSites($output);
        }

        $this->extend('onAfterMigrateThemeFonts', $output, $dryRun);

        return Command::SUCCESS;
    }

    public function getOptions(): array
    {
        return [
            new InputOption('dry-run', null, InputOption::VALUE_NONE, 'Report what would change without writing'),
        ];
    }

    protected function migrateFamilyIDs(PolyOutput $output, bool $dryRun): void
    {
        $table = ThemeFontConfig::getSchema()->tableName(ThemeFontConfig::class);
        $familyTable = ThemeFontFamily::getSchema()->tableName(ThemeFontFamily::class);
        $columns = array_keys(DB::get_schema()->fieldList($table));
        $legacyColumn = null;

        foreach ($this->config()->get('legacy_family_columns') as $candidate) {
            if (in_array($candidate, $columns, true)) {
                $legacyColumn = $candidate;
                break;
            }
        }

        if (!$legacyColumn) {
            $output->writeln('No legacy font family column found, skipping family ID migration');
            return;
        }

        $where = "(\"$table\".\"ThemeFontFamilyID\" IS NULL OR \"$table\".\"ThemeFontFamilyID\" = 0)"
            . " AND \"$table\".\"$legacyColumn\" > 0"
            . " AND \"$table\".\"$legacyColumn\" IN (SELECT \"ID\" FROM \"$familyTable\")";

        $count = (int) DB::query("SELECT COUNT(*) FROM \"$table\" WHERE $where")->value();

        if ($count && !$dryRun) {
            DB::query("UPDATE \"$table\" SET \"ThemeFontFamilyID\" = \"$legacyColumn\" WHERE $where");
        }

        $output->writeln(sprintf('%d font config(s) %s from "%s"', $count, $dryRun ? 'would be updated' : 'updated', $legacyColumn));
    }

    protected function publishFontFiles(PolyOutput $output): void
    {
        $published = 0;

        foreach (ThemeFontFamily::get() as $family) {
            foreach ($family->ThemeFontFiles() as $file) {
                if (!$file->isPublished()) {
                    $file->publishSingle();
                    $published++;
                }
            }
        }

        $output->writeln(sprintf('%d font file(s) published', $published));
    }

    protected function rebuildSites(PolyOutput $output): void
    {
        foreach (SiteConfigExtension::getAllSiteConfigs() as $siteConfig) {
            $siteConfig->requireDefaultThemeFontConfigs();
            $siteConfig->generateThemeFontFiles();
            $output->writeln(sprintf('Rebuilt font files for SiteConfig #%d (%s)', $siteConfig->ID, $siteConfig->getThemeFontsFileKey()));
        }
    }
}
