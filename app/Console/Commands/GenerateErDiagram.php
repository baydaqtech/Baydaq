<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Writes docs/ER.md from the live database schema: one Mermaid ER diagram per module, with every
 * column, primary and foreign keys, and relationships. Real foreign keys are solid lines; links that
 * exist only by convention (company_id, branch_id, ids to another module) are dotted.
 *
 * Run after every migration: php artisan docs:er. A test fails while the file is out of date.
 */
class GenerateErDiagram extends Command
{
    protected $signature = 'docs:er {--check : Exit non-zero if docs/ER.md is out of date instead of writing it}';

    protected $description = 'Regenerate the ER diagram (docs/ER.md) from the database schema';

    /** Table prefix => module heading. Tables without a prefix belong to the core app. */
    private const MODULES = [
        'platform__' => 'Platform (kernel: tenants, structure, access, packages, customers)',
        'hr__' => 'HR',
        'pos__' => 'Point of sale',
        'inv__' => 'Invoicing (ZATCA)',
        'res__' => 'Reservations',
        'sub__' => 'Subscriptions (memberships)',
        'ast__' => 'Assets and inventory',
        'finance__' => 'Finance (ledger, purchasing)',
        'menu__' => 'Menus',
        '' => 'Core app (users, clients, portal, permissions)',
    ];

    /** Framework bookkeeping tables left out of the diagram. */
    private const SKIP = ['migrations', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'sessions', 'password_reset_tokens'];

    /** Conventional links without a database foreign key: column => table. */
    private const CONVENTIONS = [
        'company_id' => 'platform__companies',
        'branch_id' => 'platform__branches',
        'home_branch_id' => 'platform__branches',
        'organization_id' => 'platform__organizations',
        'platform_customer_id' => 'platform__customers',
    ];

    public function handle(): int
    {
        $markdown = $this->render();
        $path = base_path('docs/ER.md');

        if ($this->option('check')) {
            $current = is_file($path) ? file_get_contents($path) : '';

            if ($current !== $markdown) {
                $this->error('docs/ER.md is out of date: run php artisan docs:er');

                return self::FAILURE;
            }

            $this->info('docs/ER.md is up to date.');

            return self::SUCCESS;
        }

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        file_put_contents($path, $markdown);
        $this->info('Wrote docs/ER.md');

        return self::SUCCESS;
    }

    public function render(): string
    {
        $tables = collect(Schema::getTableListing(schema: Schema::getConnection()->getDatabaseName(), schemaQualified: false))
            ->reject(fn (string $table): bool => in_array($table, self::SKIP, true))
            ->sort()->values();
        $foreign = $tables->mapWithKeys(fn (string $table): array => [$table => Schema::getForeignKeys($table)]);

        $out = [
            '# Baydaq ER diagram',
            '',
            '> Generated from the database schema by `php artisan docs:er`. Do not edit by hand: change a',
            '> migration, run `php artisan migrate` and `php artisan docs:er`. The test',
            '> `tests/Feature/Docs/ErDiagramTest.php` fails while this file is out of date.',
            '>',
            '> Solid lines are database foreign keys. Dotted lines are links by convention (ids that point',
            '> across modules, which the modular design keeps without a foreign key). `client_id` (the tenant)',
            '> is on almost every table and is not drawn.',
            '',
            '## Tables per module',
            '',
        ];

        foreach (self::MODULES as $prefix => $title) {
            $count = $this->tablesOf($tables, $prefix)->count();

            if ($count > 0) {
                $out[] = "- {$title}: {$count} tables";
            }
        }

        foreach (self::MODULES as $prefix => $title) {
            $own = $this->tablesOf($tables, $prefix);

            if ($own->isEmpty()) {
                continue;
            }

            $out[] = '';
            $out[] = "## {$title}";
            $out[] = '';
            $out[] = '```mermaid';
            $out[] = 'erDiagram';

            $links = [];
            $outside = [];

            foreach ($own as $table) {
                $out[] = '    '.$this->entity($table).' {';
                $keys = collect($foreign[$table])->flatMap(fn (array $key): array => $key['columns'])->all();

                foreach (Schema::getColumns($table) as $column) {
                    $type = preg_replace('/[^a-z0-9_]/', '_', strtolower(strtok($column['type_name'], '(')));
                    $marks = array_filter([
                        $column['name'] === 'id' ? 'PK' : null,
                        in_array($column['name'], $keys, true) ? 'FK' : null,
                    ]);
                    $out[] = "        {$type} {$column['name']}".($marks ? ' '.implode(',', $marks) : '');
                }

                $out[] = '    }';

                foreach ($foreign[$table] as $key) {
                    $target = $key['foreign_table'];

                    if ($target === $table && $key['columns'] === ['id']) {
                        continue;
                    }

                    if ($target === 'clients' && $key['columns'] === ['client_id']) {
                        continue;
                    }

                    $links[] = '    '.$this->entity($target).' ||--o{ '.$this->entity($table).' : "'.implode(',', $key['columns']).'"';
                    $outside[] = $target;
                }

                foreach (Schema::getColumnListing($table) as $column) {
                    $target = self::CONVENTIONS[$column] ?? null;

                    if ($target === null || $target === $table || in_array($column, $keys, true) || ! $tables->contains($target)) {
                        continue;
                    }

                    $links[] = '    '.$this->entity($target).' ||..o{ '.$this->entity($table).' : "'.$column.'"';
                    $outside[] = $target;
                }
            }

            // Tables of other modules that this module points at, shown by name only.
            foreach (array_unique(array_diff($outside, $own->all())) as $stub) {
                $out[] = '    '.$this->entity($stub).' { }';
            }

            sort($links);
            array_push($out, ...array_values(array_unique($links)));
            $out[] = '```';
        }

        return implode("\n", $out)."\n";
    }

    /**
     * @param  Collection<int, string>  $tables
     * @return Collection<int, string>
     */
    private function tablesOf(Collection $tables, string $prefix): Collection
    {
        return $tables->filter(function (string $table) use ($prefix): bool {
            if ($prefix !== '') {
                return str_starts_with($table, $prefix);
            }

            foreach (array_keys(self::MODULES) as $other) {
                if ($other !== '' && str_starts_with($table, $other)) {
                    return false;
                }
            }

            return true;
        })->values();
    }

    private function entity(string $table): string
    {
        return str_replace('__', '_', $table);
    }
}
