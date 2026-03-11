<?php

namespace Mollie\Payment\Infrastructure\Exception;

class MollieNotConnectedException extends \Exception
{
    public function __construct(string $message = "Not connected to Mollie", int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}