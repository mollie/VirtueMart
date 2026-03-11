<?php

namespace Mollie\Payment\Interface;

interface VirtueMartRepositoryInterface
{
    /**
     * @param int $countryId
     *
     * @return string|null
     */
    public function getCountryIsoCode(int $countryId): ?string;

    /**
     * @param int $orderId
     * @param string $status
     *
     * @return bool
     */
    public function updateOrderStatus(int $orderId, string $status): bool;

    /**
     * Get order number by order ID
     *
     * @param int $orderId
     *
     * @return string|null
     */
    public function getOrderNumber(int $orderId): ?string;

    /**
     * Get currency code from currency ID
     *
     * @param int $currencyId
     *
     * @return string
     */
    public function getCurrencyCode(int $currencyId): string;

    /**
     * Get order items for an order
     *
     * @param int $orderId
     *
     * @return array
     */
    public function getOrderItems(int $orderId): array;

    /**
     * Get order data (shipping, payment, totals, etc.)
     *
     * @param int $orderId
     *
     * @return object|null
     */
    public function getOrder(int $orderId): ?object;
}
