<?php

declare(strict_types=1);

namespace TexHub\AlifPay\Provider;

use TexHub\AlifPay\Enums\ProviderCode;

/**
 * The JSON document a provider endpoint answers with.
 *
 * A missing `code` is a fatal error on Alif's side, so the code is always present here. Alif reads
 * the body, not the HTTP status, but {@see httpStatus()} gives a sensible one to send along.
 *
 * @see https://alifcapital.github.io/providers
 */
final class ProviderResponse
{
    /**
     * @param array<string, mixed> $extra
     */
    private function __construct(
        public readonly ProviderCode $code,
        public readonly string $id,
        public readonly ?string $responseId = null,
        public readonly ?string $infoForClient = null,
        public readonly ?string $amount = null,
        public readonly array $extra = [],
    ) {
    }

    /**
     * Answer to `check`: the subscriber exists and the payment can be taken.
     *
     * `$amount` pre-fills what the customer owes; `$infoForClient` is shown to them at the till
     * and is capped at 1000 characters by Alif.
     */
    public static function accountFound(
        string $id,
        int|float|string|null $amount = null,
        ?string $infoForClient = null,
    ): self {
        return new self(
            code: ProviderCode::AccountFound,
            id: $id,
            infoForClient: self::truncateInfo($infoForClient),
            amount: $amount === null ? null : number_format((float) $amount, 2, '.', ''),
        );
    }

    /**
     * Answer to `pay`: the balance was credited. `$responseId` is the operation number on the
     * provider's own side and is what reconciliation is done against.
     */
    public static function paid(string $id, string $responseId): self
    {
        return new self(code: ProviderCode::Success, id: $id, responseId: $responseId);
    }

    /**
     * Answer to a repeated `pay` for an id already credited.
     */
    public static function duplicate(string $id, string $responseId): self
    {
        return new self(code: ProviderCode::DuplicateSuccess, id: $id, responseId: $responseId);
    }

    /**
     * Answer to `status`: what became of a payment.
     */
    public static function status(string $id, ProviderCode $code, ?string $responseId = null): self
    {
        return new self(code: $code, id: $id, responseId: $responseId);
    }

    /**
     * Any other outcome, success or failure.
     */
    public static function code(ProviderCode $code, string $id = '', ?string $responseId = null): self
    {
        return new self(code: $code, id: $id, responseId: $responseId);
    }

    /**
     * Attach fields Alif expects for a specific agreement (bank fees, split payments, …).
     *
     * @param array<string, mixed> $extra
     */
    public function with(array $extra): self
    {
        return new self(
            code: $this->code,
            id: $this->id,
            responseId: $this->responseId,
            infoForClient: $this->infoForClient,
            amount: $this->amount,
            extra: [...$this->extra, ...$extra],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $body = ['code' => $this->code->value];

        if ($this->id !== '') {
            $body['id'] = $this->id;
        }

        if ($this->responseId !== null) {
            $body['response_id'] = $this->responseId;
        }

        if ($this->amount !== null) {
            $body['amount'] = $this->amount;
        }

        if ($this->infoForClient !== null) {
            $body['info_for_client'] = $this->infoForClient;
        }

        return [...$body, ...$this->extra];
    }

    public function toJson(): string
    {
        return (string) json_encode($this->toArray(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * A transport status to send with the body. Alif decides on the body regardless.
     */
    public function httpStatus(): int
    {
        return match ($this->code) {
            ProviderCode::Unauthorized => 401,
            ProviderCode::InternalError => 500,
            ProviderCode::Unavailable => 503,
            default => 200,
        };
    }

    private static function truncateInfo(?string $info): ?string
    {
        if ($info === null) {
            return null;
        }

        return mb_strlen($info) > 1000 ? mb_substr($info, 0, 1000) : $info;
    }
}
