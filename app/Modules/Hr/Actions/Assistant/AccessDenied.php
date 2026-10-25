<?php

namespace App\Modules\Hr\Actions\Assistant;

use RuntimeException;

/**
 * An assistant tool was asked for data the asker's role does not reach. The tool reports it to
 * the model instead of failing the answer.
 */
class AccessDenied extends RuntimeException {}
