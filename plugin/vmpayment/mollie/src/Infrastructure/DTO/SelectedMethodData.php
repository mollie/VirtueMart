<?php

namespace Mollie\Payment\Infrastructure\DTO;

class SelectedMethodData
{
    public function __construct(
        public readonly string $method,
        public readonly string $defaultLogo
    ) {}
}
