<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Raised when a facility tries an action its subscription does not allow.
 *
 * Extends RuntimeException so existing generic catches still behave; downstream
 * controllers must catch this BEFORE their broader RuntimeException handler to
 * surface the structured 422/403 response.
 *
 * Business codes:
 *   DOCTOR_LIMIT_REACHED / STAFF_LIMIT_REACHED / LOCATION_LIMIT_REACHED =
 *       422 with context (used / max / required / action)
 *   BOOKINGS_LIMIT_REACHED = 422 (only when config books in block mode)
 *   FEATURE_NOT_ENABLED / SUBSCRIPTION_EXPIRED / SUBSCRIPTION_SUSPENDED =
 *       403 style entitlement failures
 */
class EntitlementException extends RuntimeException
{
    protected array $context;

    protected int $httpStatus;

    public function __construct(string $code, string $message, int $httpStatus = 422, array $context = [], ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
        $this->code = $code;
        $this->httpStatus = $httpStatus;
        $this->context = $context;
    }

    public function businessCode(): string
    {
        return $this->code;
    }

    public function context(): array
    {
        return $this->context;
    }

    public function httpStatus(): int
    {
        return $this->httpStatus;
    }

    public static function limit(string $metric, array $ctx): self
    {
        $code = strtoupper($metric).'_LIMIT_REACHED';

        return new self($code, $ctx['message'], 422, $ctx);
    }

    public static function featureDisabled(string $key): self
    {
        return new self('FEATURE_NOT_ENABLED', "The '{$key}' feature is not enabled for this facility.", 403, ['feature' => $key]);
    }

    public static function subscriptionRestricted(string $message, array $context = []): self
    {
        $code = ($context['status'] ?? '') === 'suspended' ? 'SUBSCRIPTION_SUSPENDED' : 'SUBSCRIPTION_EXPIRED';

        return new self($code, $message, 403, $context);
    }
}
