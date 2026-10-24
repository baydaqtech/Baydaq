<?php

namespace App\Http\Controllers\Concerns;

use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin pages get the admin copy (lang/{locale}/admin.php) and can flash a toast.
 */
trait RendersAdminPages
{
    /**
     * @param  array<string, mixed>  $props
     */
    protected function adminPage(string $component, array $props = []): Response
    {
        return Inertia::render($component, ['copy' => ['admin' => trans('admin')], ...$props]);
    }

    /**
     * Show a toast on the next page.
     */
    protected function toast(string $message, string $type = 'success'): void
    {
        Inertia::flash('toast', ['type' => $type, 'message' => $message]);
    }
}
