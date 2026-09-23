<?php

declare(strict_types=1);

namespace TexHub\AlifPay\Provider;

use TexHub\AlifPay\Exceptions\AlifPayException;
use TexHub\AlifPay\Exceptions\ConfigurationException;
use TexHub\AlifPay\Exceptions\InvalidSignatureException;

/**
 * The provider side of the protocol: the endpoint Alif calls with `check`, `pay` and `status`.
 *
 * This is the opposite direction from acquiring — here the request arrives and the answer is a
 * {@see ProviderResponse}. Authorization is HTTP Basic with credentials agreed with Alif; the
 * comparison is constant-time, and the endpoint must be rejected outright when it fails.
 *
 * ```php
 * $server = new ProviderServer('login', 'secret');
 *
 * if (! $server->authorize($_SERVER['HTTP_AUTHORIZATION'] ?? null)) {
 *     $server->send(ProviderResponse::code(ProviderCode::Unauthorized));
 *     exit;
 * }
 *
 * $request = $server->request(file_get_contents('php://input'));
 * ```
 *
 * @see https://alifcapital.github.io/providers
 */
final class ProviderServer
{
    public function __construct(
        private readonly string $login,
        private readonly string $password,
    ) {
        if (trim($this->login) === '' || trim($this->password) === '') {
            throw new ConfigurationException('Alif provider endpoint needs a login and a password.');
        }
    }

    /**
     * Check an incoming `Authorization: Basic …` header against the agreed credentials.
     *
     * The raw header value is expected, with or without the `Basic ` prefix.
     */
    public function authorize(?string $authorizationHeader): bool
    {
        if ($authorizationHeader === null) {
            return false;
        }

        $value = trim($authorizationHeader);

        if (stripos($value, 'basic ') === 0) {
            $value = substr($value, 6);
        }

        $decoded = base64_decode(trim($value), true);

        if ($decoded === false) {
            return false;
        }

        return hash_equals($this->login . ':' . $this->password, $decoded);
    }

    /**
     * Authorize or throw.
     *
     * @throws InvalidSignatureException
     */
    public function assertAuthorized(?string $authorizationHeader): void
    {
        if (! $this->authorize($authorizationHeader)) {
            throw new InvalidSignatureException('Alif provider request failed Basic authorization.');
        }
    }

    /**
     * Parse an incoming request body.
     *
     * @param string|array<string, mixed> $body
     *
     * @throws AlifPayException
     */
    public function request(string|array $body): ProviderRequest
    {
        if (is_string($body)) {
            $decoded = json_decode($body, true);

            if (! is_array($decoded)) {
                throw new AlifPayException('Alif provider request body is not valid JSON.');
            }

            $body = $decoded;
        }

        return ProviderRequest::fromArray($body);
    }

    /**
     * The header value this endpoint expects, for wiring up a test client.
     */
    public function expectedAuthorizationHeader(): string
    {
        return 'Basic ' . base64_encode($this->login . ':' . $this->password);
    }

    /**
     * Emit a response as JSON with its status code, for plain-PHP endpoints. Frameworks should
     * build their own response from {@see ProviderResponse::toArray()} instead.
     */
    public function send(ProviderResponse $response): void
    {
        if (! headers_sent()) {
            http_response_code($response->httpStatus());
            header('Content-Type: application/json; charset=utf-8');
        }

        echo $response->toJson();
    }
}
