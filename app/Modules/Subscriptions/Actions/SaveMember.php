<?php

namespace App\Modules\Subscriptions\Actions;

use App\Modules\Platform\Ports\Customers;
use App\Modules\Platform\Tenancy\CurrentTenant;
use App\Modules\Subscriptions\Models\Member;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SaveMember
{
    public function __construct(private CurrentTenant $tenant, private Customers $customers) {}

    /**
     * Create or update a member. A new member gets the company's next number (M-00001) and a
     * card code for the door. Phones are unique per company, and each phone is linked to the
     * account-wide customer record (X2).
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(Member $member, array $data, ?int $companyId = null): Member
    {
        $companyId ??= $member->company_id;
        $phone = preg_replace('/\s+/', '', (string) ($data['phone'] ?? $member->phone));

        if (Member::query()->where('company_id', $companyId)->where('phone', $phone)->whereKeyNot($member->id ?? 0)->exists()) {
            throw ValidationException::withMessages(['phone' => __('subscriptions.errors.phone_taken')]);
        }

        $member->fill([...$data, 'phone' => $phone]);

        if (! $member->exists) {
            $next = (int) Member::query()->where('company_id', $companyId)->lockForUpdate()->count() + 1;
            $member->forceFill([
                'client_id' => $member->client_id ?: $this->tenant->client()->id,
                'company_id' => $companyId,
                'number' => 'M-'.str_pad((string) $next, 5, '0', STR_PAD_LEFT),
                'card_token' => Str::random(32),
            ]);
        }

        if ($phone !== '' && ($member->isDirty('phone') || $member->platform_customer_id === null)) {
            $member->forceFill(['platform_customer_id' => $this->customers->identify((string) $member->name, $phone, $member->email)?->id]);
        }

        $member->save();

        return $member;
    }
}
