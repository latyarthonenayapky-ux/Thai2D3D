<?php

namespace App\Services;

use App\Support\BusinessDataSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Plays a BusinessDataExport bundle into this database.
 *
 * Nothing is imported blindly: the walk over BusinessDataSchema::tables() stops
 * on unknown foreign ids, re-keys every row to the target Admin, and refuses the
 * whole run if the bundle tries to claim a row that already belongs to another
 * business on this server. A dry run takes the identical path and rolls the
 * transaction back, so the preview shown to the Admin is the import itself.
 */
class BusinessDataImport
{
    /** @var array<string, array<int, int>> source id => new id, per table map */
    private array $maps = [];

    /** @var array<string, array<int, bool>> source ids that already existed here */
    private array $existing = [];

    /** @var array<string, array<int, string>> target columns, per table */
    private array $columns = [];

    public function read(string $path): array
    {
        if (! is_readable($path)) {
            throw new RuntimeException('The bundle could not be read.');
        }

        $bundle = json_decode((string) file_get_contents($path), true);

        return $this->validate($bundle);
    }

    /**
     * @param  array<mixed>  $bundle
     * @return array<mixed>
     */
    public function validate(array $bundle): array
    {
        if (($bundle['format'] ?? null) !== BusinessDataSchema::FORMAT) {
            throw new RuntimeException('This is not a WYA business data bundle (missing '.BusinessDataSchema::FORMAT.').');
        }

        if (! isset($bundle['tables']) || ! is_array($bundle['tables'])) {
            throw new RuntimeException('The bundle has no tables.');
        }

        $allowed = array_keys(BusinessDataSchema::tables());
        $unknown = array_values(array_diff(array_keys($bundle['tables']), $allowed));

        if ($unknown !== []) {
            // Unknown tables are ignored rather than trusted, but the Admin is
            // told, because they usually mean the two builds are out of step.
            $bundle['ignored'] = $unknown;
        }

        $bundle['tables'] = array_intersect_key($bundle['tables'], array_flip($allowed));

        return $bundle;
    }

    /**
     * @return array<string, mixed>
     */
    public function run(array $bundle, int $targetAdminId, bool $dryRun = false): array
    {
        $bundle = $this->validate($bundle);

        $sourceAdminId = (int) ($bundle['business']['id'] ?? 0);

        $this->maps = $sourceAdminId > 0 ? ['users' => [$sourceAdminId => $targetAdminId]] : [];
        $this->existing = [];
        $this->columns = [];

        $report = [
            'dry_run' => $dryRun,
            'applied' => false,
            'business' => $bundle['business'] ?? null,
            'source_engine' => $bundle['engine'] ?? 'unknown',
            'created_at' => $bundle['created_at'] ?? null,
            'ignored' => $bundle['ignored'] ?? [],
            'tables' => [],
            'conflicts' => [],
            'totals' => ['new' => 0, 'matched' => 0, 'skipped' => 0, 'orphan' => 0],
        ];

        try {
            DB::transaction(function () use ($bundle, $targetAdminId, $dryRun, &$report): void {
                foreach (BusinessDataSchema::tables() as $name => $spec) {
                    $this->importTable($name, $spec, $bundle['tables'][$name] ?? [], $targetAdminId, $report);
                }

                if ($report['conflicts'] !== []) {
                    throw new RuntimeException('business_import_conflict');
                }

                $report['applied'] = ! $dryRun;

                if ($dryRun) {
                    // The walk above really inserted rows; rolling it back is what
                    // makes the preview and the import one and the same code path.
                    throw new RuntimeException('business_import_dry_run');
                }
            }, 3);
        } catch (RuntimeException $exception) {
            if (! in_array($exception->getMessage(), ['business_import_dry_run', 'business_import_conflict'], true)) {
                throw $exception;
            }
        }

        $report['totals']['rows'] = array_sum(array_column($report['tables'], 'new'));

        return $report;
    }

    /**
     * @param  array<string, mixed>  $spec
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, mixed>  $report
     */
    private function importTable(string $table, array $spec, array $rows, int $targetAdminId, array &$report): void
    {
        $counts = ['label' => $spec['label'], 'source' => count($rows), 'new' => 0, 'matched' => 0, 'skipped' => 0, 'orphan' => 0];

        if ($rows === []) {
            $report['tables'][$table] = $counts;

            return;
        }

        $this->columns[$table] ??= Schema::getColumnListing($table);

        foreach ($rows as $row) {
            $data = $this->prepare($table, $spec, $row, $targetAdminId, $counts);

            if ($data === null) {
                continue;
            }

            $found = $this->findExisting($table, $spec, $data);

            if ($found !== null) {
                if ($this->conflicts($table, $spec, $data, $found, $targetAdminId, $report)) {
                    $counts['skipped']++;

                    continue;
                }

                if ($spec['map'] !== null) {
                    $this->maps[$spec['map']][(int) $row['id']] = (int) $found->id;
                    $this->existing[$spec['map']][(int) $row['id']] = true;
                }

                $counts['matched']++;

                continue;
            }

            if ($this->isDuplicateOfExistingParent($spec, $row)) {
                // Recorded as already existing so this row's own children are
                // skipped for the same reason instead of reported as orphans.
                if ($spec['map'] !== null) {
                    $this->existing[$spec['map']][(int) $row['id']] = true;
                }

                $counts['skipped']++;

                continue;
            }

            $newId = DB::table($table)->insertGetId($data);

            if ($spec['map'] !== null) {
                $this->maps[$spec['map']][(int) $row['id']] = (int) $newId;
            }

            $counts['new']++;
        }

        $report['tables'][$table] = $counts;

        foreach (['new', 'matched', 'skipped', 'orphan'] as $key) {
            $report['totals'][$key] += $counts[$key];
        }
    }

    /**
     * @param  array<string, mixed>  $spec
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>|null
     */
    private function prepare(string $table, array $spec, array $row, int $targetAdminId, array &$counts): ?array
    {
        $data = array_intersect_key($row, array_flip($this->columns[$table]));
        unset($data['id']);

        foreach ($spec['blank'] as $column) {
            if (array_key_exists($column, $data)) {
                $data[$column] = null;
            }
        }

        foreach ($spec['remap'] as $column => $map) {
            if (! array_key_exists($column, $data) || $data[$column] === null) {
                continue;
            }

            $value = (int) $data[$column];

            if (! isset($this->maps[$map][$value])) {
                // Its owner was not imported, so this row has nowhere to hang.
                $counts['orphan']++;

                return null;
            }

            $data[$column] = $this->maps[$map][$value];
        }

        if ($spec['owner'] !== null && ($data[$spec['owner']] ?? null) !== $targetAdminId) {
            $counts['orphan']++;

            return null;
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $spec
     * @param  array<string, mixed>  $data
     */
    private function findExisting(string $table, array $spec, array $data): ?object
    {
        if ($spec['match'] === []) {
            return null;
        }

        $key = [];

        foreach ($spec['match'] as $column) {
            // A row that was never tagged with an id of its own (the web Sale
            // Entry does not send client_uuid) cannot be recognised by key.
            if (! array_key_exists($column, $data) || $data[$column] === null || $data[$column] === '') {
                return null;
            }

            $key[$column] = $data[$column];
        }

        /** @var ?object $found */
        $found = DB::table($table)->where($key)->first(['id', ...$this->lookupColumns($spec)]);

        return $found;
    }

    /**
     * @param  array<string, mixed>  $spec
     * @return array<int, string>
     */
    private function lookupColumns(array $spec): array
    {
        $columns = $spec['compare'] ?? [];

        if ($spec['owner'] !== null && ! in_array($spec['owner'], $columns, true)) {
            $columns[] = $spec['owner'];
        }

        return $columns;
    }

    /**
     * @param  array<string, mixed>  $spec
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $report
     */
    private function conflicts(string $table, array $spec, array $data, object $found, int $targetAdminId, array &$report): bool
    {
        $reason = null;

        if ($spec['owner'] !== null && (int) ($found->{$spec['owner']} ?? 0) !== $targetAdminId) {
            $reason = __('app.move.conflict_foreign');
        }

        foreach (($spec['compare'] ?? []) as $column) {
            if (array_key_exists($column, $data) && (string) $found->{$column} !== (string) $data[$column]) {
                $reason = __('app.move.conflict_parent', ['column' => str_replace('_', ' ', $column)]);
            }
        }

        if ($reason === null) {
            return false;
        }

        $report['conflicts'][] = [
            'table' => $spec['label'],
            'key' => implode(' / ', array_map(static fn (string $column): string => (string) ($data[$column] ?? '?'), $spec['match'])),
            'reason' => $reason,
        ];

        return true;
    }

    /**
     * A row with no usable key is only new when its parent was created by this
     * same run; otherwise the parent already carries it here.
     *
     * @param  array<string, mixed>  $spec
     * @param  array<string, mixed>  $row
     */
    private function isDuplicateOfExistingParent(array $spec, array $row): bool
    {
        if ($spec['parents'] === []) {
            return false;
        }

        $referenced = false;

        foreach ($spec['remap'] as $column => $map) {
            if (! in_array($map, $spec['parents'], true) || ($row[$column] ?? null) === null) {
                continue;
            }

            $referenced = true;

            if (! isset($this->existing[$map][(int) $row[$column]])) {
                return false;
            }
        }

        return $referenced;
    }
}
