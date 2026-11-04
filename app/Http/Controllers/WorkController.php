<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RendersSitePages;
use Inertia\Response;

/**
 * Our work: the projects on the landing page, and a page for each one that has no website of
 * its own (config/work.php, copy in lang/{locale}/work.php).
 */
class WorkController extends Controller
{
    use RendersSitePages;

    public function show(string $slug): Response
    {
        $pages = self::pages();
        $index = array_search($slug, $pages, true);

        abort_if($index === false, 404);

        $screens = fn (string $kind): array => array_map(fn (string $file, int $at): array => [
            'src' => "/images/work/{$slug}/{$file}",
            'caption' => trans("work.projects.{$slug}.{$kind}")[$at] ?? '',
        ], config("work.projects.{$slug}.{$kind}"), array_keys(config("work.projects.{$slug}.{$kind}")));

        // The pages run in a loop, so there is always a project to go on to; with two, one link is enough.
        $total = count($pages);

        return $this->sitePage('WorkProject', [
            'slug' => $slug,
            'web' => $screens('web'),
            'mobile' => $screens('mobile'),
            'position' => ['index' => $index + 1, 'total' => $total],
            'previous' => $total > 2 ? $pages[($index - 1 + $total) % $total] : null,
            'next' => $total > 1 ? $pages[($index + 1) % $total] : null,
        ], ['work']);
    }

    /**
     * Each project of the landing page and where its logo leads: its own website, or its page here.
     *
     * @return list<array{slug: string, href: string, external: bool}>
     */
    public static function tiles(): array
    {
        return collect(config('work.projects'))->map(fn (array $project, string $slug): array => [
            'slug' => $slug,
            'href' => $project['url'] ?? route('work.show', $slug, false),
            'external' => isset($project['url']),
        ])->values()->all();
    }

    /**
     * The projects with a page on this site, in order.
     *
     * @return list<string>
     */
    private static function pages(): array
    {
        return array_keys(array_filter(config('work.projects'), fn (array $project): bool => ! isset($project['url'])));
    }
}
