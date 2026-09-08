<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class VerifyWebhookSignature
{
    /**
     * Verify inbound payment-provider webhook callbacks.
     *
     * Defence in depth:
     *   1. If WEBHOOK_SECRET is configured, the caller must present an
     *      HMAC-SHA256 signature of the RAW request body in the
     *      `X-Webhook-Signature` header (constant-time comparison).
     *   2. If MPESA_CALLER_IPS is configured (comma-separated allow-list),
     *      Safaricom/M-pesa callback IP addresses are enforced, because
     *      Daraja does not sign callback bodies.
     *
     * When neither is configured the middleware logs a warning and continues,
     * so local development and test suites keep working out of the box while
     * production deployments can lock callbacks down with either (or both).
     */
    public function handle(Request $request, Closure $next): Response
    {
        $secret = trim((string) config('services.webhook.secret', ''));
        $callerIps = $this->allowedCallerIps();

        if ($secret !== '') {
            $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $secret);
            $provided = trim((string) $request->header('X-Webhook-Signature', ''));

            if (! hash_equals($expected, $provided)) {
                Log::warning('Webhook signature mismatch', [
                    'ip' => $request->ip(),
                    'path' => $request->path(),
                ]);

                return response()->json(['error' => 'Invalid webhook signature.'], 403);
            }
        }

        if ($callerIps !== [] && ! in_array($request->ip(), $callerIps, true)) {
            Log::warning('Webhook caller IP not in allow-list', [
                'ip' => $request->ip(),
                'path' => $request->path(),
            ]);

            return response()->json(['error' => 'Forbidden.'], 403);
        }

        if ($secret === '' && $callerIps === []) {
            Log::warning('Webhook received without configured secret or caller allow-list', [
                'ip' => $request->ip(),
                'path' => $request->path(),
            ]);
        }

        return $next($request);
    }

    private function allowedCallerIps(): array
    {
        $raw = (string) config('services.webhook.mpesa_caller_ips', '');

        return array_values(array_filter(array_map(
            'trim',
            explode(',', $raw),
        ), fn (string $ip) => $ip !== ''));
    }
}
