<?php

declare(strict_types=1);

namespace TexHub\AlifPay\Exceptions;

/**
 * Thrown when a callback/webhook signature (token) fails verification.
 */
class InvalidSignatureException extends AlifPayException
{
}
