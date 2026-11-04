<?php

namespace App\Modules\Subscriptions\Adapters;

use App\Modules\Platform\Ports\CustomerData;
use App\Modules\Subscriptions\Models\Member;

class MemberCustomerData implements CustomerData
{
    public function export(int $customerId): array
    {
        return ['members' => Member::query()->where('platform_customer_id', $customerId)->get()
            ->map(fn (Member $member): array => [
                'number' => $member->number, 'name' => $member->name, 'phone' => $member->phone, 'email' => $member->email,
                'gender' => $member->gender, 'birth_date' => $member->birth_date?->toDateString(), 'since' => $member->created_at->toDateString(),
            ])->all()];
    }

    /** The member number and membership history stay; the person behind them goes. */
    public function erase(int $customerId): int
    {
        $members = Member::query()->where('platform_customer_id', $customerId)->get();

        foreach ($members as $member) {
            $member->forceFill(['name' => '—', 'phone' => 'erased-'.$member->id, 'email' => null, 'gender' => null, 'birth_date' => null, 'note' => null, 'is_active' => false])->save();
        }

        return $members->count();
    }
}
