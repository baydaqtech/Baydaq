<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Invoices\IssueEinvoice;
use App\Http\Controllers\Concerns\RendersAdminPages;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Modules\Platform\Models\Company;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class SettingsController extends Controller
{
    use RendersAdminPages;

    public function edit(): Response
    {
        return $this->adminPage('Admin/Settings', [
            'issuer' => Setting::issuer(),
            'einvoicing_company_id' => Setting::valuesFor([IssueEinvoice::SETTING])[IssueEinvoice::SETTING],
            'companies' => Company::withoutGlobalScopes()->with('client:id,name_ar,name_en')->orderBy('client_id')->orderBy('id')->get()
                ->map(fn (Company $company): array => ['value' => (string) $company->id, 'label' => $company->client?->localized('name').' · '.$company->localized('name')])->all(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate(
            collect(Setting::ISSUER_KEYS)
                ->mapWithKeys(fn (string $key): array => [$key => ['nullable', 'string', $key === 'invoice_note' ? 'max:500' : 'max:255']])
                ->put(IssueEinvoice::SETTING, ['nullable', 'integer', 'exists:platform__companies,id'])
                ->all(),
        );

        Setting::store($validated);
        $this->toast(__('admin.common.saved'));

        return back();
    }
}
