<?php

namespace App\Services\Github;

use RuntimeException;

/**
 * Raised when a GitHub write is attempted while BUILD_GITHUB_TOKEN is empty.
 * Controllers catch and translate to a 503 with a message the UI already
 * knows how to render as "GitHub not configured".
 */
class NotConfiguredException extends RuntimeException
{
}
