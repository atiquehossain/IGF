<?php

namespace App\Console\Commands;

use App\Models\Admin;
use App\Services\BanglaTranslationCatalogImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use JsonException;
use RuntimeException;
use Throwable;

final class ImportBanglaTranslations extends Command
{
    private const DEFAULT_CATALOG = 'resources/translations/bangla.catalog.json';

    protected $signature = 'translations:bangla
        {--catalog= : Repository-relative JSON catalog path}
        {--export-template : Export current rows without changing the database}
        {--apply : Apply the validated catalog to the Translation Center}
        {--admin= : Active owner/super-admin ID used for change attribution}
        {--required-only : Limit validation and import to required public rows}
        {--overwrite : Replace existing non-empty Bangla translations}
        {--batch=100 : Rows passed to TranslationCenterService per save (maximum 100)}
        {--force : Replace an existing catalog during template export}';

    protected $description = 'Export, validate, or safely import the reviewed English-to-Bangla translation catalog';

    public function __construct(private BanglaTranslationCatalogImporter $importer)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            if ($this->option('export-template') && $this->option('apply')) {
                throw new RuntimeException('--export-template and --apply cannot be used together.');
            }

            $path = $this->catalogPath(
                (string) ($this->option('catalog') ?: self::DEFAULT_CATALOG),
                (bool) $this->option('export-template')
            );
            if ($this->option('export-template')) {
                return $this->exportTemplate($path);
            }

            $catalog = $this->readCatalog($path);
            $requiredOnly = (bool) $this->option('required-only');
            $overwrite = (bool) $this->option('overwrite');
            $batchSize = $this->batchSize();

            if (!$this->option('apply')) {
                $report = $this->importer->inspect($catalog, $requiredOnly, $overwrite);
                $this->displayReport($report);
                $this->info('Catalog validation passed. No database changes were made.');

                return self::SUCCESS;
            }

            $admin = $this->ownerAdmin();
            $report = $this->importer->apply(
                $catalog,
                (int) $admin->getKey(),
                $requiredOnly,
                $overwrite,
                $batchSize
            );
            $this->displayReport($report);
            $this->info("Saved {$report['saved']} Bangla translations. Bangla remains disabled until the separate completeness gate is passed.");

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    private function exportTemplate(string $path): int
    {
        if (File::exists($path) && !$this->option('force')) {
            throw new RuntimeException('The catalog already exists. Use --force to replace it.');
        }

        File::ensureDirectoryExists(dirname($path));
        File::put(
            $path,
            json_encode(
                $this->importer->catalog(),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
            ).PHP_EOL
        );
        $this->info('Bangla review catalog written to '.$this->displayPath($path).'. No database changes were made.');

        return self::SUCCESS;
    }

    private function readCatalog(string $path): array
    {
        if (!File::isFile($path)) {
            throw new RuntimeException(
                'Bangla catalog not found at '.$this->displayPath($path).'. Run translations:bangla --export-template first.'
            );
        }

        try {
            $catalog = json_decode(File::get($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Bangla catalog JSON is invalid: '.$exception->getMessage(), 0, $exception);
        }

        if (!is_array($catalog)) {
            throw new RuntimeException('Bangla catalog must contain a JSON object.');
        }

        return $catalog;
    }

    private function ownerAdmin(): Admin
    {
        $value = (string) $this->option('admin');
        if (preg_match('/^[1-9][0-9]*$/', $value) !== 1) {
            throw new RuntimeException('--admin must identify the active owner/super-admin applying these translations.');
        }

        $admin = Admin::query()->with('roleModel')->find((int) $value);
        if (!$admin || !(bool) $admin->status || !$admin->isOwner()) {
            throw new RuntimeException('The selected admin is not an active owner/super-admin.');
        }

        return $admin;
    }

    private function batchSize(): int
    {
        $value = (string) $this->option('batch');
        if (preg_match('/^[1-9][0-9]*$/', $value) !== 1) {
            throw new RuntimeException('--batch must be a whole number between 1 and 100.');
        }

        $batchSize = (int) $value;
        if ($batchSize > BanglaTranslationCatalogImporter::MAX_BATCH_SIZE) {
            throw new RuntimeException('--batch cannot exceed 100 rows.');
        }

        return $batchSize;
    }

    private function catalogPath(string $requested, bool $createParent = false): string
    {
        $requested = str_replace('\\', '/', trim($requested));
        if ($requested === ''
            || str_contains($requested, "\0")
            || str_starts_with($requested, '/')
            || preg_match('/^[A-Za-z]:\//', $requested) === 1
            || in_array('..', explode('/', $requested), true)) {
            throw new RuntimeException('The catalog path must be repository-relative and cannot contain parent traversal.');
        }

        $segments = array_values(array_filter(
            explode('/', $requested),
            static fn (string $segment): bool => $segment !== '' && $segment !== '.'
        ));
        $relative = implode('/', $segments);
        $catalogPrefix = 'resources/translations/';
        if (!str_starts_with($relative, $catalogPrefix)
            || strtolower((string) pathinfo($relative, PATHINFO_EXTENSION)) !== 'json') {
            throw new RuntimeException('Bangla catalogs must be JSON files inside resources/translations/.');
        }

        $resourcesRoot = realpath(resource_path());
        if ($resourcesRoot === false) {
            throw new RuntimeException('The resources directory is missing.');
        }

        $catalogRoot = resource_path('translations');
        $candidate = base_path(str_replace('/', DIRECTORY_SEPARATOR, $relative));
        $catalogRootReal = realpath($catalogRoot);
        if ($catalogRootReal !== false && !$this->pathIsWithin($catalogRootReal, $resourcesRoot)) {
            throw new RuntimeException('The translation catalog directory resolves outside resources/.');
        }

        $existingAncestor = dirname($candidate);
        while (!file_exists($existingAncestor)) {
            $parent = dirname($existingAncestor);
            if ($parent === $existingAncestor) {
                throw new RuntimeException('Unable to resolve the translation catalog directory.');
            }
            $existingAncestor = $parent;
        }
        $existingAncestorReal = realpath($existingAncestor);
        $allowedAncestor = $catalogRootReal !== false ? $catalogRootReal : $resourcesRoot;
        if ($existingAncestorReal === false || !$this->pathIsWithin($existingAncestorReal, $allowedAncestor)) {
            throw new RuntimeException('The translation catalog path resolves outside resources/translations/.');
        }

        if ($createParent) {
            File::ensureDirectoryExists(dirname($candidate));
            $catalogRootReal = realpath($catalogRoot);
            $parentReal = realpath(dirname($candidate));
            if ($catalogRootReal === false
                || $parentReal === false
                || !$this->pathIsWithin($catalogRootReal, $resourcesRoot)
                || !$this->pathIsWithin($parentReal, $catalogRootReal)) {
                throw new RuntimeException('The translation catalog path resolves outside resources/translations/.');
            }

            if (file_exists($candidate)) {
                $candidateReal = realpath($candidate);
                if ($candidateReal === false || !$this->pathIsWithin($candidateReal, $catalogRootReal)) {
                    throw new RuntimeException('The translation catalog path resolves outside resources/translations/.');
                }

                return $candidateReal;
            }

            return $parentReal.DIRECTORY_SEPARATOR.basename($candidate);
        }

        if (file_exists($candidate)) {
            $candidateReal = realpath($candidate);
            if ($catalogRootReal === false
                || $candidateReal === false
                || !$this->pathIsWithin($candidateReal, $catalogRootReal)) {
                throw new RuntimeException('The translation catalog path resolves outside resources/translations/.');
            }

            return $candidateReal;
        }

        return $candidate;
    }

    private function pathIsWithin(string $path, string $root): bool
    {
        $path = rtrim(str_replace('\\', '/', $path), '/');
        $root = rtrim(str_replace('\\', '/', $root), '/');
        if (DIRECTORY_SEPARATOR === '\\') {
            $path = strtolower($path);
            $root = strtolower($root);
        }

        return $path === $root || str_starts_with($path, $root.'/');
    }

    private function displayReport(array $report): void
    {
        $rows = [
            ['Current Translation Center rows', $report['total_rows']],
            ['Rows in this run', $report['selected_rows']],
            ['Required rows', $report['required_rows']],
            ['Optional rows', $report['optional_rows']],
            ['Catalog entries', $report['catalog_entries']],
            ['Already translated (kept)', $report['already_translated']],
            ['Unchanged', $report['unchanged']],
            ['Explicitly preserved source values', $report['preserved_source']],
            ['Planned updates', $report['planned']],
        ];
        if (array_key_exists('saved', $report)) {
            $rows[] = ['Saved updates', $report['saved']];
        }
        $this->table(['Check', 'Count'], $rows);
    }

    private function displayPath(string $path): string
    {
        $base = rtrim(base_path(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;

        return str_starts_with($path, $base)
            ? str_replace(DIRECTORY_SEPARATOR, '/', substr($path, strlen($base)))
            : $path;
    }
}
