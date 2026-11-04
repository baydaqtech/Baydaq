<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Modules\Platform\Notifications\SuiteAlert;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The notification centre: the latest alerts in the reader's language, marking them read, and
 * which kinds also arrive by email.
 */
class NotificationController extends Controller
{
    /**
     * @return list<array{id: string, kind: string, title: string, body: string, url: ?string, read: bool, at: string}>
     */
    public static function feed(User $user, int $limit = 15): array
    {
        return $user->notifications()->limit($limit)->get()
            ->map(function (DatabaseNotification $notification): array {
                $data = $notification->data;
                $kind = (string) ($data['kind'] ?? 'other');
                $params = array_filter($data, fn ($value): bool => is_scalar($value));

                return [
                    'id' => $notification->id,
                    'kind' => $kind,
                    'title' => __(SuiteAlert::text($kind, 'title'), $params),
                    'body' => __(SuiteAlert::text($kind, 'body'), $params),
                    'url' => $data['url'] ?? null,
                    'read' => $notification->read_at !== null,
                    'at' => $notification->created_at->toIso8601String(),
                ];
            })->values()->all();
    }

    /** One alert (given an id) or all of them marked read. */
    public function read(Request $request): RedirectResponse
    {
        $id = $request->validate(['id' => ['nullable', 'string', 'max:64']])['id'] ?? null;

        $request->user()->unreadNotifications()->when($id, fn ($query, string $id) => $query->whereKey($id))->update(['read_at' => now()]);

        return back();
    }

    public function settings(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Auth/NotificationSettings', [
            'kinds' => array_map(fn (string $kind): array => [
                'kind' => $kind,
                'label' => __(SuiteAlert::text($kind, 'setting')),
                'mail' => in_array('mail', $user->notificationChannels($kind), true),
            ], trans('notifications.kinds')),
            'home' => $user->homeUrl(),
            'copy' => ['account' => trans('account')],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $kinds = trans('notifications.kinds');
        $data = $request->validate(['mail' => ['array'], 'mail.*' => ['boolean']]);

        $request->user()->forceFill([
            'notification_settings' => collect($kinds)->mapWithKeys(fn (string $kind): array => [$kind => ['mail' => (bool) ($data['mail'][$kind] ?? false)]])->all(),
        ])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('account.notifications.saved')]);

        return back();
    }
}
