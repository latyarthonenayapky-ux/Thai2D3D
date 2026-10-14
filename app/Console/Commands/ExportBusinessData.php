<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\BusinessDataExport;
use Illuminate\Console\Command;
use RuntimeException;

class ExportBusinessData extends Command
{
    protected $signature = 'thai2d3d:export-business
        {--admin= : The Admin user id or e-mail whose business is exported}
        {--out= : Where to write the bundle, default storage/app/exports}';

    protected $description = 'Write one business out as a JSON bundle that can be imported on another machine';

    public function handle(BusinessDataExport $export): int
    {
        try {
            $admin = $this->admin();
            $path = $this->option('out') ?: $this->defaultPath($export);
        } catch (RuntimeException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $directory = dirname($path);

        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $bundle = $export->build((int) $admin->id);
        $rows = array_map(static fn (array $rows): int => count($rows), $bundle['tables']);
        $rows = array_filter($rows);

        file_put_contents($path, json_encode($bundle, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $this->components->info('Exported '.basename($path));
        $this->table(
            ['table', 'rows'],
            collect($rows)->map(static fn (int $count, string $table): array => [$table, $count])->values()->all()
        );
        $this->line('Import it on the VPS with: php artisan thai2d3d:import-business '.basename($path).' --dry-run');

        return self::SUCCESS;
    }

    private function admin(): User
    {
        $term = (string) ($this->option('admin') ?? '');

        if ($term === '') {
            $admins = User::query()->whereIn('role', ['admin', 'owner'])->orderBy('id')->limit(2)->get();

            if ($admins->count() !== 1) {
                throw new RuntimeException('There is more than one Admin on this machine. Pass --admin=<id or e-mail>.');
            }

            return $admins->first();
        }

        $admin = User::query()
            ->whereIn('role', ['admin', 'owner'])
            ->where(is_numeric($term) ? ['id' => (int) $term] : ['email' => $term])
            ->first();

        if (! $admin) {
            throw new RuntimeException("No Admin matches {$term}.");
        }

        return $admin;
    }

    private function defaultPath(BusinessDataExport $export): string
    {
        return storage_path('app/exports/'.$export->filename());
    }
}
