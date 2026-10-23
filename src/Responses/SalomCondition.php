<?php

declare(strict_types=1);

namespace TexHub\AlifPay\Responses;

/**
 * One Salom instalment option a seller terminal offers. Its `id` goes into a split as
 * `condition_id`.
 *
 * @see https://docs.acquiring.alif.tj/marketplace
 */
final class SalomCondition
{
    public function __construct(
        public readonly int $id,
        public readonly string $displayName,
        public readonly float $clientCommission,
        public readonly int $durationMax,
        public readonly string $durationType,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (int) ($data['id'] ?? 0),
            displayName: (string) ($data['display_name'] ?? ''),
            clientCommission: (float) ($data['client_commission'] ?? 0),
            durationMax: (int) ($data['duration_max'] ?? 0),
            durationType: (string) ($data['duration_type'] ?? ''),
        );
    }
}
