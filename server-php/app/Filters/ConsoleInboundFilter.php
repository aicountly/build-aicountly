<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Console → Build inbound authentication (approval callback, audit ack).
 *
 * Console sends:
 *   Authorization: Bearer <BUILD_CONSOLE_INBOUND_TOKEN>
 *   X-Source:      console.aicountly.org
 *
 * Kept separate from Flow inbound so tokens can be rotated independently.
 */
class ConsoleInboundFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        helper('response');

        if (strtoupper($request->getMethod()) === 'OPTIONS') {
            return service('response')->setStatusCode(204);
        }

        $expectedToken = (string) env('BUILD_CONSOLE_INBOUND_TOKEN', '');
        if ($expectedToken === '') {
            return build_json_error('Console inbound not configured.', ['env' => 'BUILD_CONSOLE_INBOUND_TOKEN'], 503);
        }

        $header = (string) $request->getHeaderLine('Authorization');
        if (! preg_match('/^Bearer\\s+(\\S+)$/i', $header, $m) || ! hash_equals($expectedToken, $m[1])) {
            return build_json_unauthorized('Invalid Console inbound token.');
        }

        $request->buildConsoleSource = (string) ($request->getHeaderLine('X-Source') ?: 'console.aicountly.org');
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
