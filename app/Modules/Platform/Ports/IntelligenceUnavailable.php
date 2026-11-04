<?php

namespace App\Modules\Platform\Ports;

use RuntimeException;

/**
 * AI is not configured, the provider failed, or it declined the request. The message is safe to
 * show to the user.
 */
class IntelligenceUnavailable extends RuntimeException {}
