<?php

declare(strict_types=1);

namespace TexHub\AlifPay\Exceptions;

use TexHub\AlifPay\Enums\ResponseCode;

/**
 * Thrown when the API returns a non-success result.
 *
 * The gateway always answers with HTTP 200 and signals the outcome via the
 * `code` field, but transport-level HTTP errors are surfaced here too via the
 * {@see ApiException::$httpStatus} property.
 */
class ApiException extends AlifPayException
{
    /**
     * @param int                  $httpStatus  HTTP status code of the response.
     * @param int                  $apiCode     The API `code` field.
     * @param string               $apiMessage  Human-readable message from the API.
     * @param array<string, mixed> $payload     The full decoded response body.
     */
    public function __construct(
        public readonly int $httpStatus,
        public readonly int $apiCode,
        public readonly string $apiMessage,
        public readonly array $payload = [],
    ) {
        parent::__construct(
            sprintf('Alif API error (HTTP %d, code %d): %s', $httpStatus, $apiCode, $apiMessage),
            $apiCode,
        );
    }

    public function responseCode(): ?ResponseCode
    {
        return ResponseCode::tryFromInt($this->apiCode);
    }

    /** Whether the request may be safely retried later. */
    public function isRetryable(): bool
    {
        return $this->responseCode()?->isRetryable() ?? false;
    }

    /** Whether the failure was caused by a duplicate order id. */
    public function isDuplicate(): bool
    {
        return $this->apiCode === ResponseCode::Duplicate->value;
    }
}
