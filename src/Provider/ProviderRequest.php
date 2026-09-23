<?php

declare(strict_types=1);

namespace TexHub\AlifPay\Provider;

use TexHub\AlifPay\Enums\ProviderAction;

/**
 * A request Alif sent to the provider endpoint.
 *
 * `id` is the payment's identifier in Alif's system and is the idempotency key: a repeated `pay`
 * with an id already stored must return the stored outcome rather than credit twice.
 *
 * @see https://alifcapital.github.io/providers
 */
final class ProviderRequest
{
    /**
     * @param array<string, mixed> $info
     * @param array<string, mixed> $raw
     */
    public function __construct(
        public readonly ?ProviderAction $action,
        public readonly string $rawAction,
        public readonly string $id,
        public readonly string $account,
        public readonly ?string $amount,
        public readonly ?string $serviceId,
        public readonly ?string $time,
        public readonly bool $isCommercial,
        public readonly array $info,
        public readonly array $raw,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $rawAction = (string) ($data['action'] ?? '');
        $amount = $data['amount'] ?? null;
        $info = $data['info'] ?? [];

        return new self(
            action: ProviderAction::tryFrom($rawAction),
            rawAction: $rawAction,
            id: isset($data['id']) ? self::scalarToString($data['id']) : '',
            account: isset($data['account']) ? self::scalarToString($data['account']) : '',
            amount: ($amount === null || $amount === '') ? null : self::normalizeAmount($amount),
            serviceId: isset($data['srv_id']) ? self::scalarToString($data['srv_id']) : null,
            time: isset($data['time']) ? (string) $data['time'] : null,
            isCommercial: (bool) ($data['is_commercial'] ?? false),
            info: is_array($info) ? $info : [],
            raw: $data,
        );
    }

    /**
     * The amount as a float, for arithmetic. Null when the request carries none.
     */
    public function amountAsFloat(): ?float
    {
        return $this->amount === null ? null : (float) $this->amount;
    }

    public function is(ProviderAction $action): bool
    {
        return $this->action === $action;
    }

    /**
     * Read one of the extra `info` fields Alif was configured to forward.
     */
    public function info(string $key, mixed $default = null): mixed
    {
        return $this->info[$key] ?? $default;
    }

    private static function scalarToString(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    private static function normalizeAmount(mixed $amount): string
    {
        return is_numeric($amount) ? number_format((float) $amount, 2, '.', '') : (string) $amount;
    }
}
