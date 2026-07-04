<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Flow → Build inbound authentication for the handoff endpoints:
 *   POST /api/v1/tasks
 *   POST /api/v1/internal/flow/build-task
 *
 * Flow's BuildApiClient sends:
 *   Authorization: Bearer <FLOW_INBOUND_TOKEN>
 *   X-Source:      flow.aicountly.org
 *
 * Optional HMAC signature check when BUILD_FLOW_INBOUND_HMAC_SECRET is set:
 *   X-Signature:   sha256=<hex>   (over raw request body)
 */
class FlowInboundFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        helper('response');

        // Idempotent OPTIONS for browser previews (should never actually happen server-to-server).
        if (strtoupper($request->getMethod()) === 'OPTIONS') {
            return service('response')->setStatusCode(204);
        }

        $expectedToken = (string) env('BUILD_FLOW_INBOUND_TOKEN', '');
        if ($expectedToken === '') {
            return build_json_error('Flow inbound not configured.', ['env' => 'BUILD_FLOW_INBOUND_TOKEN'], 503);
        }

        $header = (string) $request->getHeaderLine('Authorization');
        if (! preg_match('/^Bearer\\s+(\\S+)$/i', $header, $m) || ! hash_equals($expectedToken, $m[1])) {
            return build_json_unauthorized('Invalid Flow inbound token.');
        }

        // Optional HMAC verification.
        $hmacSecret = (string) env('BUILD_FLOW_INBOUND_HMAC_SECRET', '');
        if ($hmacSecret !== '') {
            $sig  = (string) $request->getHeaderLine('X-Signature');
            $body = (string) $request->getBody();
            $expectedSig = 'sha256=' . hash_hmac('sha256', $body, $hmacSecret);
            if ($sig === '' || ! hash_equals($expectedSig, $sig)) {
                return build_json_unauthorized('Invalid Flow inbound signature.');
            }
        }

        // Stash source for downstream services (defaults to flow.aicountly.org).
        $request->buildFlowSource = (string) ($request->getHeaderLine('X-Source') ?: 'flow.aicountly.org');
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
