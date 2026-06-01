<?php

declare(strict_types=1);

namespace TexHub\AlifPay;

use TexHub\AlifPay\Exceptions\InvalidSignatureException;

/**
 * HMAC SHA256 token generator used for authorizing requests and verifying
 * callbacks, following the Alif Acquiring scheme:
 *
 *     token = HMAC_SHA256( dataToSign, HMAC_SHA256(password, terminalId) )
 *
 * In other words the terminal password is first hashed with the terminal id,
 * and the result is used as the secret to sign the request payload.
 *
 * @see https://github.com/alifcapital/sdk/blob/master/integration-examples/php/TokenGenerator.php
 */
final class Signature
{
    public function __construct(
        private readonly string $terminalId,
        private readonly string $terminalPassword,
    ) {
    }

    /**
     * Generate the authorization token for the given signing string.
     */
    public function generate(string $dataToSign): string
    {
        $secret = hash_hmac('sha256', $this->terminalPassword, $this->terminalId);

        return hash_hmac('sha256', $dataToSign, $secret);
    }

    /**
     * Constant-time comparison of an expected token against a provided one.
     */
    public function verify(string $dataToSign, string $providedToken): bool
    {
        return hash_equals($this->generate($dataToSign), $providedToken);
    }

    /**
     * Verify a token, throwing on mismatch.
     *
     * @throws InvalidSignatureException
     */
    public function assertValid(string $dataToSign, string $providedToken): void
    {
        if (! $this->verify($dataToSign, $providedToken)) {
            throw new InvalidSignatureException('Alif callback signature verification failed.');
        }
    }

    /**
     * Concatenate the given parts into a signing string. `null` parts are skipped.
     */
    public static function concat(int|float|string|null ...$parts): string
    {
        return implode('', array_map(
            static fn ($part) => $part === null ? '' : (string) $part,
            $parts,
        ));
    }
}
