<?php

namespace App\Services;

use App\Support\BusinessDataSchema;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Writes one business out as an engine independent JSON bundle.
 *
 * The desktop app stores its data in SQLite and the hosted service in MySQL, so
 * a normal SQL dump cannot cross that border. This export keeps plain rows and
 * leaves the re-keying to BusinessDataImport, which means an export taken on any
 * build can be played into any other build.
 */
class BusinessDataExport
{
    /**
     * Source ids that were written for each table, used to scope the next table.
     *
     * @var array<string, array<int, int>>
     */
    private array $ids = [];

    public function build(int $sourceAdminId): array
    {
        $admin = DB::table('users')->where('id', $sourceAdminId)->first();

        if (! $admin) {
            throw new RuntimeException("No user with id {$sourceAdminId}.");
        }

        $this->ids = [];
        $tables = [];

        foreach (BusinessDataSchema::tables() as $name => $spec) {
            $rows = $this->rows($name, $spec, $sourceAdminId);

            if ($spec['map'] !== null) {
                $this->ids[$spec['map']] = array_values(array_map(
                    static fn (array $row): int => (int) $row['id'],
                    $rows
                ));
            }

            $tables[$name] = $rows;
        }

        return [
            'format' => BusinessDataSchema::FORMAT,
            'created_at' => now()->toIso8601String(),
            'engine' => (string) config('database.default'),
            'laravel' => \Illuminate\Foundation\Application::VERSION,
            'php' => PHP_VERSION,
            'business' => [
                'id' => (int) $admin->id,
                'name' => $admin->name,
                'email' => $admin->email,
            ],
            'tables' => $tables,
        ];
    }

    public function toJson(int $sourceAdminId): string
    {
        return (string) json_encode(
            $this->build($sourceAdminId),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
    }

    public function filename(): string
    {
        return 'wya-business-'.now()->format('Ymd-His').'-'.substr(bin2hex(random_bytes(3)), 0, 6).'.json';
    }

    /**
     * @param  array{scope: array}  $spec
     * @return array<int, array<string, mixed>>
     */
    private function rows(string $table, array $spec, int $sourceAdminId): array
    {
        $scope = $spec['scope'];
        $query = DB::table($table);

        if (isset($scope['column'])) {
            $query->where($scope['column'], $sourceAdminId);

            foreach ($scope['where'] ?? [] as $column => $value) {
                $query->where($column, $value);
            }
        } elseif (isset($scope['parent'])) {
            [$map, $column] = $scope['parent'];
            $ids = $this->ids[$map] ?? [];

            if ($ids === []) {
                return [];
            }

            $query->whereIn($column, $ids);
        } else {
            $clauses = [];

            foreach ($scope['any_of'] as [$map, $column]) {
                if (($this->ids[$map] ?? []) !== []) {
                    $clauses[] = [$column, $this->ids[$map]];
                }
            }

            if ($clauses === []) {
                return [];
            }

            $query->where(function (\Illuminate\Database\Query\Builder $query) use ($clauses): void {
                foreach ($clauses as [$column, $ids]) {
                    $query->orWhereIn($column, $ids);
                }
            });
        }

        return $query->orderBy('id')
            ->get()
            ->map(static fn ($row): array => (array) $row)
            ->all();
    }
}
