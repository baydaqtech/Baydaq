<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\RendersAdminPages;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class SettingsController extends Controller
{
    use RendersAdminPages;

    public function edit(): Response
    {
        return $this->adminPage('Admin/Settings', ['issuer' => Setting::issuer()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate(
            collect(Setting::ISSUER_KEYS)
                ->mapWithKeys(fn (string $key): array => [$key => ['nullable', 'string', $key === 'invoice_note' ? 'max:500' : 'max:255']])
                ->all(),
        );

        Setting::store($validated);
        $this->toast(__('admin.common.saved'));

        return back();
    }
}
