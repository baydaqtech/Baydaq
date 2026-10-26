<?php

namespace App\Modules\Platform\Adapters;

use App\Modules\Platform\Data\SentMessage;
use App\Modules\Platform\Models\Message;
use App\Modules\Platform\Ports\Messaging;
use App\Modules\Platform\Tenancy\CurrentTenant;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Sends through Baydaq's WhatsApp service when configured (driver "whatsapp"); otherwise only
 * records the message (driver "log"), which is what local, tests and demos use.
 */
class WhatsAppMessaging implements Messaging
{
    public function __construct(private CurrentTenant $tenant) {}

    public function send(?string $phone, string $template, array $params, string $locale = 'ar', ?string $context = null): ?SentMessage
    {
        $to = self::normalize($phone);

        if ($to === null) {
            return null;
        }

        $body = __("messages.{$template}", array_map('strval', $params), $locale);
        $message = new Message;
        $message->forceFill([
            'client_id' => $this->tenant->id(), 'channel' => 'whatsapp', 'to' => $to, 'template' => $template, 'params' => $params,
            'body' => $body, 'locale' => $locale, 'context' => $context, 'status' => 'logged',
        ]);

        if (config('baydaq.messaging.driver') === 'whatsapp' && config('baydaq.messaging.whatsapp.url')) {
            try {
                $response = Http::withToken((string) config('baydaq.messaging.whatsapp.token'))->acceptJson()->timeout(10)
                    ->post(rtrim((string) config('baydaq.messaging.whatsapp.url'), '/').'/messages', ['to' => $to, 'template' => $template, 'locale' => $locale, 'params' => $params, 'body' => $body]);
                $message->forceFill($response->successful() ? ['status' => 'sent', 'provider_id' => $response->json('id')] : ['status' => 'failed', 'error' => mb_substr($response->body(), 0, 250)]);
            } catch (Throwable $e) {
                $message->forceFill(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 250)]);
            }
        }

        $message->save();

        return new SentMessage($message->id, $message->status, $body);
    }

    /** Saudi mobile numbers to international form: 05xxxxxxxx / 5xxxxxxxx / 9665xxxxxxxx → 9665xxxxxxxx. */
    public static function normalize(?string $phone): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $phone);

        return match (true) {
            $digits === '' => null,
            str_starts_with($digits, '00') => substr($digits, 2),
            str_starts_with($digits, '05') && strlen($digits) === 10 => '966'.substr($digits, 1),
            str_starts_with($digits, '5') && strlen($digits) === 9 => '966'.$digits,
            default => $digits,
        };
    }
}
