<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\BusinessDataImport;
use App\Support\BusinessDataSchema;
use Illuminate\Console\Command;
use RuntimeException;

class ImportBusinessData extends Command
{
    protected $signature = 'thai2d3d:import-business
        {bundle : The JSON bundle written by thai2d3d:export-business}
        {--admin= : The Admin user id or e-mail that receives the data}
        {--dry-run : Report what would change and write nothing}';

    protected $description = 'Import a business exported from another machine (desktop to VPS)';

    public function handle(BusinessDataImport $import): int
    {
        try {
            $admin = $this->admin();
            $bundle = $import->read($this->argument('bundle'));
        } catch (RuntimeException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $report = $import->run($bundle, (int) $admin->id, $dryRun);

        $this->components->info(sprintf(
            'Bundle from %s <%s>, taken %s on %s.',
            $report['business']['name'] ?? '?',
            $report['business']['email'] ?? '?',
            $report['created_at'] ?? '?',
            $report['source_engine'] ?? '?'
        ));

        $this->table(
            ['table', 'in bundle', 'new', 'already here', 'skipped', 'no owner'],
            collect($report['tables'])
                ->reject(static fn (array $counts): bool => $counts['source'] === 0)
                ->map(static fn (array $counts, string $table): array => [
                    $counts['label'].' ('.$table.')',
                    $counts['source'],
                    $counts['new'],
                    $counts['matched'],
                    $counts['skipped'],
                    $counts['orphan'],
                ])
                ->values()
                ->all()
        );

        foreach ($report['ignored'] as $table) {
            $this->components->warn("Ignored {$table}: this build does not know that table.");
        }

        if ($report['conflicts'] !== []) {
            $this->components->error('Nothing was imported. These rows already belong to someone else here:');

            foreach ($report['conflicts'] as $conflict) {
                $this->line("  {$conflict['table']}: {$conflict['key']} - {$conflict['reason']}");
            }

            $this->line('Rename them on the old machine, or import into a different Admin account.');

            return self::FAILURE;
        }

        $totals = $report['totals'];

        if ($dryRun) {
            $this->components->info("Dry run: {$totals['new']} rows would be written, {$totals['matched']} already exist.");
            $this->line('Run the same command without --dry-run to import.');

            return self::SUCCESS;
        }

        $this->components->info("Imported {$totals['new']} rows for {$admin->name}; {$totals['matched']} already existed.");
        $this->line('Re-issue the Agent mobile logins under Settings if the old machine had them.');

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
}
