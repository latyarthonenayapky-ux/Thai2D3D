<?php

namespace App\Services;

use Generator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;

class DatabaseBackupService
{
    public const HEADER = 'WYA_BACKUP_V1';

    private const BATCH = 200;

    public function directory(): string
    {
        $path = storage_path('app/backups');
        File::ensureDirectoryExists($path);

        return $path;
    }

    public function driver(): string
    {
        return (string) config('database.connections.'.config('database.default').'.driver');
    }

    /** @return Collection<int, array{name: string, size: int, created_at: int, engine: string}> */
    public function all(): Collection
    {
        return collect(glob($this->directory().'/wya-backup-*.sql'))
            ->map(fn (string $path): array => [
                'name' => basename($path),
                'size' => (int) filesize($path),
                'created_at' => (int) filemtime($path),
                'engine' => $this->engineOf($path),
            ])
            ->sortByDesc('created_at')
            ->values();
    }

    public function create(): string
    {
        $name = 'wya-backup-'.date('Ymd-His').'-'.substr(md5(uniqid((string) mt_rand(), true)), 0, 6).'.sql';
        $path = $this->directory().'/'.$name;
        $handle = fopen($path, 'wb');

        if ($handle === false) {
            throw new RuntimeException('The backup folder is not writable on this server.');
        }

        try {
            $this->writePreamble($handle);
            foreach ($this->tableDefinitions() as $object) {
                $object['rows']
                    ? $this->writeTable($handle, $object['name'], $object['ddl'])
                    : $this->writeLine($handle, $object['ddl']);
            }
            $this->writeLine($handle, '-- restore guards re-enabled');
            $this->writeLine($handle, $this->driver() === 'sqlite' ? 'PRAGMA foreign_keys = ON;' : 'SET FOREIGN_KEY_CHECKS = 1;');
        } finally {
            fclose($handle);
        }

        return $name;
    }

    public function resolve(string $name): string
    {
        if (! preg_match('/^wya-backup-[0-9]{8}-[0-9]{6}-[a-f0-9]{6}\.sql$/', $name)) {
            throw new RuntimeException('Unknown backup file.');
        }

        $path = $this->directory().'/'.$name;

        if (! is_file($path)) {
            throw new RuntimeException('That backup file is no longer on disk.');
        }

        return $path;
    }

    public function delete(string $name): void
    {
        File::delete($this->resolve($name));
    }

    public function engineOf(string $path): string
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            return 'unknown';
        }

        $first = (string) fgets($handle);
        fclose($handle);

        return preg_match('/engine=([a-z0-9_]+)/', $first, $match) ? $match[1] : 'unknown';
    }

    public function acceptsUploadedFile(string $path): bool
    {
        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            return false;
        }

        $first = (string) fgets($handle);
        fclose($handle);

        return str_contains($first, self::HEADER);
    }

    /**
     * Apply a previously produced dump onto the live connection. A safety
     * backup is always written first because DDL cannot be rolled back.
     */
    public function restore(string $path): string
    {
        $safety = $this->create();

        if (! $this->acceptsUploadedFile($path)) {
            throw new RuntimeException('That file is not a WYA backup created by this application.');
        }

        $engine = $this->engineOf($path);

        if ($engine !== $this->driver()) {
            throw new RuntimeException("This backup was taken from a {$engine} database and cannot be restored onto {$this->driver()}.");
        }

        $connection = DB::connection();
        $connection->statement($engine === 'sqlite' ? 'PRAGMA foreign_keys = OFF;' : 'SET FOREIGN_KEY_CHECKS = 0;');
        $connection->statement($engine === 'sqlite' ? 'PRAGMA defer_foreign_keys = ON;' : 'SET UNIQUE_CHECKS = 0;');

        foreach ($this->statements($path) as $statement) {
            $connection->unprepared($statement);
        }

        $connection->statement($engine === 'sqlite' ? 'PRAGMA foreign_keys = ON;' : 'SET FOREIGN_KEY_CHECKS = 1;');

        if ($engine === 'sqlite') {
            $connection->unprepared('VACUUM;');
        } else {
            $connection->unprepared('ANALYZE;');
        }

        return $safety;
    }

    /** @return Collection<int, array{name: string, ddl: string, rows: bool}> */
    private function tableDefinitions(): Collection
    {
        if ($this->driver() === 'sqlite') {
            $rows = DB::select("SELECT name, sql FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' AND sql IS NOT NULL ORDER BY name");
            $extra = DB::select("SELECT name, sql, type FROM sqlite_master WHERE type IN ('index', 'view') AND sql IS NOT NULL ORDER BY name");
        } else {
            $names = array_map(
                fn (object $row): string => (string) array_values((array) $row)[0],
                DB::select('SHOW FULL TABLES WHERE Table_type = \'BASE TABLE\'')
            );
            $rows = collect($names)->map(function (string $name): array {
                $create = (array) DB::selectOne('SHOW CREATE TABLE '.$this->wrap($name));

                return ['name' => $name, 'sql' => (string) ($create[1] ?? '').';'];
            })->all();
            $extra = [];
        }

        return collect($rows)
            ->map(fn (object|array $row): array => [
                'name' => (string) ((array) $row)['name'],
                'ddl' => $this->terminate((string) ((array) $row)['sql']),
                'rows' => true,
            ])
            ->concat(collect($extra)->map(fn (object|array $row): array => [
                'name' => (string) ((array) $row)['name'],
                'ddl' => $this->terminate((string) ((array) $row)['sql']),
                'rows' => false,
            ]))
            ->values();
    }

    private function terminate(string $ddl): string
    {
        $ddl = trim($ddl);

        return str_ends_with($ddl, ';') ? $ddl : $ddl.';';
    }

    /** @param resource $handle */
    private function writePreamble($handle): void
    {
        $this->writeLine($handle, '-- '.self::HEADER.' engine='.$this->driver().' created='.date('c'));
        $this->writeLine($handle, '-- generated by '.config('app.name').'; restore from Setting > Data Backup');
        $this->writeLine($handle, $this->driver() === 'sqlite' ? 'PRAGMA foreign_keys = OFF;' : 'SET FOREIGN_KEY_CHECKS = 0;');

        if ($this->driver() !== 'sqlite') {
            $this->writeLine($handle, 'SET sql_mode = \'\';');
            $this->writeLine($handle, 'SET NAMES utf8mb4;');
        }
    }

    /**
     * @param  resource  $handle
     */
    private function writeTable($handle, string $table, string $ddl): void
    {
        $wrapped = $this->wrap($table);
        $this->writeLine($handle, '-- table '.$table);
        $this->writeLine($handle, 'DROP TABLE IF EXISTS '.$wrapped.';');
        $this->writeLine($handle, $ddl);

        $columns = $this->driver() === 'sqlite'
            ? array_map(
                fn (array $row): string => (string) ($row['name'] ?? ''),
                array_map(get_object_vars(...), DB::select('PRAGMA table_info('.$wrapped.')'))
            )
            : array_map(fn (object $row): string => (string) $row->Field, DB::select('SHOW COLUMNS FROM '.$wrapped));

        $columns = array_values(array_filter($columns));

        if ($columns === []) {
            return;
        }

        $select = 'SELECT '.$this->joinColumns($columns).' FROM '.$wrapped;
        $offset = 0;

        while (true) {
            $rows = DB::select($select.' LIMIT '.self::BATCH.' OFFSET '.$offset);

            if ($rows === []) {
                break;
            }

            $values = [];

            foreach ($rows as $row) {
                $row = (array) $row;
                $cells = array_map(
                    fn (string $column): string => $this->quote($row[$column] ?? null),
                    $columns
                );
                $values[] = '('.implode(', ', $cells).')';
            }

            $this->writeLine($handle, 'INSERT INTO '.$wrapped.' ('.$this->joinColumns($columns).') VALUES '.implode(', ', $values).';');
            $offset += self::BATCH;
        }
    }

    private function quote(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return DB::connection()->getPdo()->quote((string) $value);
    }

    private function wrap(string $name): string
    {
        return $this->driver() === 'sqlite' ? '"'.$name.'"' : '`'.$name.'`';
    }

    /** @param list<string> $columns */
    private function joinColumns(array $columns): string
    {
        return implode(', ', array_map(fn (string $column): string => $this->wrap($column), $columns));
    }

    /** @param resource $handle */
    private function writeLine($handle, string $line): void
    {
        fwrite($handle, $line."\n");
    }

    /** @return Generator<int, string> */
    private function statements(string $path): Generator
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new RuntimeException('The uploaded backup could not be read.');
        }

        $buffer = '';
        $quote = null;
        $skipLine = false;

        try {
            while (($line = fgets($handle)) !== false) {
                $trimmed = ltrim($line);

                if ($quote === null && ($trimmed === '' || str_starts_with($trimmed, '--'))) {
                    continue;
                }

                for ($index = 0; $index < strlen($line); $index++) {
                    $character = $line[$index];

                    if ($skipLine) {
                        $skipLine = $character !== "\n";

                        continue;
                    }

                    if ($quote !== null) {
                        $buffer .= $character;

                        if ($this->driver() !== 'sqlite' && $character === '\\' && $index + 1 < strlen($line)) {
                            $index++;
                            $buffer .= $line[$index];

                            continue;
                        }

                        if ($character === $quote) {
                            $quote = null;
                        }

                        continue;
                    }

                    if ($character === "'" || $character === '"' || $character === '`') {
                        $quote = $character;
                        $buffer .= $character;

                        continue;
                    }

                    if ($character === '-' && ($line[$index + 1] ?? '') === '-') {
                        $skipLine = true;

                        continue;
                    }

                    if ($character === ';') {
                        $statement = trim($buffer);

                        if ($statement !== '') {
                            yield $statement;
                        }

                        $buffer = '';

                        continue;
                    }

                    $buffer .= $character;
                }
            }

            if (trim($buffer) !== '') {
                yield trim($buffer);
            }
        } finally {
            fclose($handle);
        }
    }
}
