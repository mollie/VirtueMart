<?php

namespace Mollie\Payment\Interface;

use stdClass;

interface VirtueMartOrderStatusRepositoryInterface
{
    /**
     * @return array
     */
    public function getAllStatuses(): array;

    /**
     * @param string $code
     *
     * @return stdClass|null
     */
    public function getStatusByCode(string $code): ?stdClass;
}
