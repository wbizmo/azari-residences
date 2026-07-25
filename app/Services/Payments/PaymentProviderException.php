<?php

namespace App\Services\Payments;

use RuntimeException;
use Throwable;

class PaymentProviderException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $provider,
        public readonly ?int $statusCode = null,
        public readonly array $safeContext = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $statusCode ?? 0, $previous);
    }
}
