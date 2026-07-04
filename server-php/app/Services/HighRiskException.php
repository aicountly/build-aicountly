<?php

namespace App\Services;

use RuntimeException;

/**
 * Thrown by SafetyGuardService when a code-mutation action is attempted
 * without meeting Part E of the Build spec. Controllers catch this and
 * return a 403 with the message as-is.
 */
class HighRiskException extends RuntimeException
{
}
