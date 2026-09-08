<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * MpesaService \u2014 Safaricom Daraja API v1.0 Integration
 *
 * Implements:
 *   - OAuth2 token retrieval (auto-cached, refreshed before expiry)
 *   - STK Push (Lipa Na M-Pesa Online) \u2014 request payment from customer
 *   - STK Push Query \u2014 check payment status by checkout_request_id
 *   - B2C Payment \u2014 send money TO customer (doctor payouts)
 *
 * Required .env variables:
 *   MPESA_ENVIRONMENT=sandbox|live
 *   MPESA_CONSUMER_KEY / MPESA_CONSUMER_SECRET
 *   MPESA_SHORTCODE / MPESA_PASSKEY
 *   MPESA_CALLBACK_URL / MPESA_QUEUE_TIMEOUT_URL
 *   MPESA_B2C_SHORTCODE / MPESA_INITIATOR_NAME / MPESA_SECURITY_CREDENTIAL
 *   MPESA_B2C_RESULT_URL
 */
class MpesaService
{
    private const SANDBOX_BASE = 'https://sandbox.safaricom.co.ke';

    private const LIVE_BASE = 'https://api.safaricom.co.ke';

    private const EP_OAUTH = '/oauth/v1/generate?grant_type=client_credentials';

    private const EP_STK_PUSH = '/mpesa/stkpush/v1/processrequest';

    private const EP_STK_QUERY = '/mpesa/stkpush/v1/query';

    private const EP_B2C = '/mpesa/b2c/v1/paymentrequest';

    private const TOKEN_CACHE_KEY = 'mpesa_oauth_token';

    private ?string $baseUrl = null;

    private ?string $token = null;

    // STK Push
    public function stkPush(
        string $amount,
        string $phone,
        string $reference,
        string $description = 'Dr. Plaza Payment',
    ): array {
        $token = $this->getToken();
        $baseUrl = $this->baseUrl();

        $phone = $this->normalisePhone($phone);
        $timestamp = now()->format('YmdHis');
        $passkey = config('services.mpesa.passkey', '');
        $shortcode = (int) config('services.mpesa.shortcode', '174379');

        // Password = Shortcode + Passkey + Timestamp (base64 encoded)
        $password = base64_encode($shortcode.$passkey.$timestamp);

        $body = [
            'BusinessShortCode' => $shortcode,
            'Password' => $password,
            'Timestamp' => $timestamp,
            'TransactionType' => 'CustomerPayBillOnline',
            'Amount' => (int) $amount,
            'PartyA' => $phone,
            'PartyB' => $shortcode,
            'PhoneNumber' => $phone,
            'CallBackURL' => config('services.mpesa.callback_url', ''),
            'QueueTimeOutURL' => config('services.mpesa.queue_timeout_url', ''),
            'AccountReference' => substr($reference, 0, 100),
            'TransactionDesc' => substr($description, 0, 100),
        ];

        Log::info('MpesaService: STK Push request', [
            'phone' => $this->maskPhone($phone),
            'amount' => $amount,
            'ref' => $reference,
        ]);

        $response = Http::withToken($token)
            ->timeout(30)
            ->post($baseUrl.self::EP_STK_PUSH, $body);

        $data = $response->json();

        if ($response->failed() || isset($data['errorCode'])) {
            Log::error('MpesaService: STK Push failed', ['body' => $data]);
            throw new \RuntimeException(
                $data['errorMessage'] ?? $data['responseDescription'] ?? 'STK Push request failed'
            );
        }

        Log::info('MpesaService: STK Push initiated', [
            'checkout_request_id' => $data['CheckoutRequestID'] ?? null,
            'merchant_request_id' => $data['MerchantRequestID'] ?? null,
        ]);

        return [
            'checkout_request_id' => $data['CheckoutRequestID'] ?? '',
            'merchant_request_id' => $data['MerchantRequestID'] ?? '',
        ];
    }

    // STK Query
    public function stkQuery(string $checkoutRequestId): array
    {
        $token = $this->getToken();
        $baseUrl = $this->baseUrl();

        $timestamp = now()->format('YmdHis');
        $passkey = config('services.mpesa.passkey', '');
        $shortcode = (int) config('services.mpesa.shortcode', '174379');
        $password = base64_encode($shortcode.$passkey.$timestamp);

        $response = Http::withToken($token)
            ->timeout(30)
            ->post($baseUrl.self::EP_STK_QUERY, [
                'BusinessShortCode' => $shortcode,
                'Password' => $password,
                'Timestamp' => $timestamp,
                'CheckoutRequestID' => $checkoutRequestId,
            ]);

        $data = $response->json();

        if ($response->failed() || isset($data['errorCode'])) {
            Log::error('MpesaService: STK Query failed', ['body' => $data]);
            throw new \RuntimeException(
                $data['errorMessage'] ?? $data['responseDescription'] ?? 'STK Query failed'
            );
        }

        return [
            'result_code' => (int) ($data['ResultCode'] ?? -1),
            'result_desc' => $data['ResultDesc'] ?? 'Unknown',
            'amount' => $data['Amount'] ?? null,
            'mpesa_receipt' => $data['MpesaReceiptNumber'] ?? null,
            'transaction_id' => $data['TransactionID'] ?? null,
        ];
    }

    // B2C
    public function b2c(
        string $amount,
        string $phone,
        string $remarks = 'Dr. Plaza Payout',
        string $occasion = '',
        string $commandId = 'BusinessPayment',
    ): array {
        $token = $this->getToken();
        $baseUrl = $this->baseUrl();

        $phone = $this->normalisePhone($phone);
        $b2cShortcode = (int) config('services.mpesa.b2c_shortcode',
            config('services.mpesa.shortcode', '174379'));

        $body = [
            'InitiatorName' => config('services.mpesa.initiator_name', ''),
            'SecurityCredential' => config('services.mpesa.security_credential', ''),
            'CommandID' => $commandId,
            'Amount' => (int) $amount,
            'PartyA' => $b2cShortcode,
            'PartyB' => $phone,
            'Remarks' => substr($remarks, 0, 100),
            'QueueTimeOutURL' => config('services.mpesa.b2c_result_url', ''),
            'ResultURL' => config('services.mpesa.b2c_result_url', ''),
            'Occasion' => substr($occasion, 0, 100),
        ];

        Log::info('MpesaService: B2C request', [
            'phone' => $this->maskPhone($phone),
            'amount' => $amount,
        ]);

        $response = Http::withToken($token)
            ->timeout(30)
            ->post($baseUrl.self::EP_B2C, $body);

        $data = $response->json();

        if ($response->failed() || isset($data['errorCode'])) {
            Log::error('MpesaService: B2C failed', ['body' => $data]);
            throw new \RuntimeException(
                $data['errorMessage'] ?? $data['responseDescription'] ?? 'B2C payment failed'
            );
        }

        return [
            'conversation_id' => $data['ConversationID'] ?? '',
            'originator_conversation_id' => $data['OriginatorConversationID'] ?? '',
        ];
    }

    // Token
    public function getToken(): string
    {
        if ($this->token) {
            return $this->token;
        }

        $cacheKey = self::TOKEN_CACHE_KEY;
        if (Cache::has($cacheKey)) {
            $this->token = Cache::get($cacheKey);

            return $this->token;
        }

        $baseUrl = $this->baseUrl();
        $consumerKey = config('services.mpesa.consumer_key', '');
        $consumerSecret = config('services.mpesa.consumer_secret', '');

        if (empty($consumerKey) || empty($consumerSecret)) {
            throw new \RuntimeException(
                'M-Pesa credentials not configured. Set MPESA_CONSUMER_KEY and MPESA_CONSUMER_SECRET in .env'
            );
        }

        $response = Http::withBasicAuth($consumerKey, $consumerSecret)
            ->timeout(15)
            ->get($baseUrl.self::EP_OAUTH);

        $data = $response->json();

        if ($response->failed() || isset($data['error']) || ! isset($data['access_token'])) {
            Log::error('MpesaService: OAuth failed', ['body' => $data]);
            throw new \RuntimeException(
                $data['error_description'] ?? $data['error'] ?? 'Failed to obtain M-Pesa OAuth token'
            );
        }

        $this->token = $data['access_token'];

        // Refresh 60s before expiry
        $ttl = max(((int) ($data['expires_in'] ?? 3600)) - 60, 300);
        Cache::put($cacheKey, $this->token, $ttl);

        Log::debug('MpesaService: OAuth token cached', ['ttl' => $ttl]);

        return $this->token;
    }

    public function invalidateToken(): void
    {
        $this->token = null;
        Cache::forget(self::TOKEN_CACHE_KEY);
    }

    private function baseUrl(): string
    {
        if ($this->baseUrl) {
            return $this->baseUrl;
        }
        $env = config('services.mpesa.environment', 'sandbox');
        $this->baseUrl = ($env === 'live') ? self::LIVE_BASE : self::SANDBOX_BASE;

        return $this->baseUrl;
    }

    public function normalisePhone(string $phone): string
    {
        $phone = preg_replace("/\D/", '', $phone);

        if (str_starts_with($phone, '0')) {
            $phone = '254'.substr($phone, 1);
        } elseif (str_starts_with($phone, '7') || str_starts_with($phone, '1')) {
            $phone = '254'.$phone;
        } elseif (! str_starts_with($phone, '254')) {
            $phone = '254'.$phone;
        }

        $phone = ltrim($phone, '+');

        if (strlen($phone) !== 12) {
            throw new \InvalidArgumentException(
                "Invalid Kenyan phone number: {$phone}. Expected: 254xxxxxxxxx."
            );
        }

        return $phone;
    }

    private function maskPhone(string $phone): string
    {
        if (strlen($phone) < 6) {
            return '***';
        }

        return substr($phone, 0, 3).'****'.substr($phone, -3);
    }
}
