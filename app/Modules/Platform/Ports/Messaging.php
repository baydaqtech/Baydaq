<?php

namespace App\Modules\Platform\Ports;

use App\Modules\Platform\Data\SentMessage;

/**
 * WhatsApp messages to people (employees, customers, members) through Baydaq's own WhatsApp
 * service. Modules name a template and its parameters; the kernel renders the text in the
 * person's language, sends it and keeps a record. A missing phone is not an error.
 */
interface Messaging
{
    /**
     * @param  array<string, string|int|float>  $params
     */
    public function send(?string $phone, string $template, array $params, string $locale = 'ar', ?string $context = null): ?SentMessage;
}
