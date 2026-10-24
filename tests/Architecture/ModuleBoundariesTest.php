<?php

namespace Tests\Architecture;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Finder;

/**
 * Modules talk only through ports. One module may reference another only through that
 * module's Ports, Data and Events namespaces, may never name another module's tables, and
 * only the Platform kernel may touch the core App\Models.
 */
class ModuleBoundariesTest extends TestCase
{
    private const MODULES_PATH = __DIR__.'/../../app/Modules';

    /** Namespaces a module may expose to others. */
    private const PUBLIC_SEGMENTS = ['Ports', 'Data', 'Events'];

    /**
     * The kernel exposes a little more: its enums, the value objects of the access engine,
     * the tenant concern and the controller concerns every suite page uses.
     */
    private const KERNEL_PUBLIC = ['Ports', 'Data', 'Events', 'Enums', 'Access', 'Tenancy', 'Models\\Concerns', 'Http\\Controllers\\Concerns'];

    /** Table prefixes per module, matched as 'prefix__ inside string literals. */
    private const TABLE_PREFIXES = [
        'Platform' => 'platform__',
        'Hr' => 'hr__',
        'Pos' => 'pos__',
        'Invoicing' => 'inv__',
        'Assets' => 'ast__',
        'Reservations' => 'res__',
        'Subscriptions' => 'sub__',
        'Menus' => 'menu__',
    ];

    #[Test]
    public function modules_reference_each_other_only_through_ports_data_and_events(): void
    {
        $violations = [];

        foreach ($this->moduleFiles() as $module => $file) {
            preg_match_all('/App\\\\Modules\\\\([A-Za-z]+)\\\\([A-Za-z]+(?:\\\\[A-Za-z]+)*)/', $file['contents'], $matches, PREG_SET_ORDER);

            foreach ($matches as [, $other, $path]) {
                if ($other === $module) {
                    continue;
                }

                $allowed = $other === 'Platform' ? self::KERNEL_PUBLIC : self::PUBLIC_SEGMENTS;
                $public = array_filter($allowed, fn (string $segment): bool => str_starts_with($path, $segment.'\\') || $path === $segment);

                if ($public === []) {
                    $violations[] = "{$file['path']} reaches into {$other}\\{$path}";
                }
            }
        }

        $this->assertSame([], array_unique($violations), implode("\n", $violations));
    }

    #[Test]
    public function modules_never_name_another_modules_tables(): void
    {
        $violations = [];

        foreach ($this->moduleFiles() as $module => $file) {
            foreach (self::TABLE_PREFIXES as $owner => $prefix) {
                // Foreign keys toward the kernel's tables are the one allowed cross-module reference.
                if ($owner === 'Platform' && str_contains($file['path'], '/Database/Migrations/')) {
                    continue;
                }

                if ($owner !== $module && preg_match('/[\'"]'.preg_quote($prefix, '/').'/', $file['contents'])) {
                    $violations[] = "{$file['path']} names a {$owner} table ({$prefix})";
                }
            }
        }

        $this->assertSame([], $violations, implode("\n", $violations));
    }

    #[Test]
    public function only_the_platform_kernel_uses_the_core_models(): void
    {
        $violations = [];

        foreach ($this->moduleFiles() as $module => $file) {
            if ($module !== 'Platform' && preg_match('/App\\\\Models\\\\(?!Concerns\\\\)[A-Za-z]+/', $file['contents'], $match)) {
                $violations[] = "{$file['path']} uses {$match[0]}";
            }
        }

        $this->assertSame([], $violations, implode("\n", $violations));
    }

    /**
     * @return \Generator<string, array{path: string, contents: string}>
     */
    private function moduleFiles(): \Generator
    {
        $finder = Finder::create()->files()->in(self::MODULES_PATH)->name('*.php');

        foreach ($finder as $file) {
            $relative = $file->getRelativePathname();
            $module = explode(DIRECTORY_SEPARATOR, $relative)[0];

            yield $module => ['path' => 'app/Modules/'.$relative, 'contents' => $file->getContents()];
        }
    }
}
