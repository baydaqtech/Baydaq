<?php

namespace App\Modules\Reservations\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Hr\Ports\StaffDirectory;
use App\Modules\Platform\Data\BranchRef;
use App\Modules\Platform\Enums\FeatureAction;
use App\Modules\Platform\Http\Controllers\Concerns\ReachesBranches;
use App\Modules\Platform\Http\Controllers\Concerns\RendersSuitePages;
use App\Modules\Platform\Ports\OrgDirectory;
use App\Modules\Reservations\Enums\BookingStatus;
use App\Modules\Reservations\Enums\ReservationsFeature;
use App\Modules\Reservations\Models\Booking;
use Illuminate\Http\Request;
use Inertia\Response;

class BookingController extends Controller
{
    use ReachesBranches, RendersSuitePages;

    public function index(Request $request, OrgDirectory $directory, StaffDirectory $staff): Response
    {
        $filters = ['search' => $request->query('search'), 'status' => $request->query('status'), 'from' => $request->query('from'), 'to' => $request->query('to')];
        $branches = $this->branchesFor(ReservationsFeature::Bookings, FeatureAction::View);
        $names = $branches->mapWithKeys(fn (BranchRef $branch): array => [$branch->id => $branch->name]);
        $timezones = $branches->mapWithKeys(fn (BranchRef $branch): array => [$branch->id => $directory->company($branch->companyId)?->timezone ?? 'Asia/Riyadh']);

        $bookings = Booking::query()->whereIn('branch_id', $branches->pluck('id'))->with(['service', 'resource'])
            ->when($filters['status'], fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['from'], fn ($query, string $from) => $query->whereDate('starts_at', '>=', $from))
            ->when($filters['to'], fn ($query, string $to) => $query->whereDate('starts_at', '<=', $to))
            ->when($filters['search'], fn ($query, string $search) => $query->where(fn ($query) => $query->where('reference', $search)->orWhere('customer_name', 'like', "%{$search}%")->orWhere('customer_phone', 'like', "%{$search}%")))
            ->latest('starts_at')->paginate(30)->withQueryString();

        return $this->suitePage('Reservations/Bookings', [
            'bookings' => $bookings->through(fn (Booking $booking): array => [
                ...CalendarController::bookingArray($booking, $timezones[$booking->branch_id], fn (?int $id): ?string => $id ? $staff->employee($id)?->name : null),
                'branch' => $names[$booking->branch_id],
            ]),
            'filters' => $filters,
            'statuses' => BookingStatus::options(),
        ], ['reservations']);
    }
}
