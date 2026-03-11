<?php

namespace Mollie\Payment\Adapter;

use Mollie\Payment\Interface\VirtueMartRepositoryInterface;

class VirtueMartOrderService
{
    public function __construct(
        private readonly VirtueMartRepositoryInterface $virtueMartRepository
    ) {}

    /**
     * @param int $orderId
     *
     * @return array
     */
    public function getOrderItems(int $orderId): array
    {
        return $this->virtueMartRepository->getOrderItems($orderId);
    }

    /**
     * @param int $orderId
     *
     * @return object|null
     */
    public function getOrder(int $orderId): ?object
    {
        return $this->virtueMartRepository->getOrder($orderId);
    }
}
