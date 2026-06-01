<?php

declare(strict_types=1);

namespace TexHub\AlifPay\Enums;

/**
 * Tokenization callback result codes (root `code` field of the callback body).
 *
 * @see https://docs.acquiring.alif.tj/tokenization
 */
enum TokenizationStatus: int
{
    case Success = 1;
    case Duplicate = 2;
    case Rejected = 9;

    public function isSuccessful(): bool
    {
        return $this === self::Success;
    }

    public function reasonCode(): string
    {
        return match ($this) {
            self::Success => 'success',
            self::Duplicate => 'duplicate',
            self::Rejected => 'rejected',
        };
    }
}
