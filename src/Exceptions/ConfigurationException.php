<?php

declare(strict_types=1);

namespace TexHub\AlifPay\Exceptions;

/**
 * Thrown when the SDK is misconfigured (missing terminal id/password, etc.).
 */
class ConfigurationException extends AlifPayException
{
}
