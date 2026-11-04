<?php

namespace App\Http\Controllers\Concerns;

use Inertia\Inertia;
use Inertia\Response;

/**
 * Public site pages share the landing look and its copy (nav, footer).
 */
trait RendersSitePages
{
    /**
     * @param  array<string, mixed>  $props
     * @param  list<string>  $copyGroups  extra lang/{locale}/*.php groups the page needs
     */
    protected function sitePage(string $component, array $props = [], array $copyGroups = []): Response
    {
        $copy = ['landing' => trans('landing')];

        foreach ($copyGroups as $group) {
            $copy[$group] = trans($group);
        }

        return Inertia::render($component, [...$props, 'copy' => $copy]);
    }
}
