<?php

namespace App\Exceptions;

use Exception;

/**
 * Thrown by App\Services\Sharer when a share request can't be fulfilled
 * (unknown recipient, empty body, rate limit hit, unauthenticated). The
 * message is already translated and safe to render to the user.
 */
class ShareFailedException extends Exception {}
