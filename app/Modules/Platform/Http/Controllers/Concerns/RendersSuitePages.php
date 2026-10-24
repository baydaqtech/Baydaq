<?php

namespace App\Modules\Platform\Http\Controllers\Concerns;

use Inertia\Inertia;
use Inertia\Response;

/**
 * Suite pages get the suite copy once per language, plus any module copy the controller names.
 */
trait RendersSuitePages
{
    /**
     * @param  array<string, mixed>  $props
     * @param  list<string>  $copyGroups  extra lang files to send, e.g. ['hr']
     */
    protected function suitePage(string $component, array $props = [], array $copyGroups = []): Response
    {
        $locale = app()->getLocale();
        $groups = ['suite', ...$copyGroups];

        return Inertia::render($component, [
            ...$props,
            'copy' => Inertia::once(fn (): array => array_combine($groups, array_map(fn (string $group): array => trans($group), $groups)))
                ->as('copy.'.implode('-', $groups).'.'.$locale),
        ]);
    }

    protected function toast(string $message, string $type = 'success'): void
    {
        Inertia::flash('toast', ['type' => $type, 'message' => $message]);
    }
}
