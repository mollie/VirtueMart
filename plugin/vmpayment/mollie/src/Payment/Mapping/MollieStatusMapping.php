<?php

namespace Mollie\Payment\Payment\Mapping;

final class MollieStatusMapping
{
    public const MOLLIE_OPEN       = 'open';
    public const MOLLIE_CANCELED   = 'canceled';
    public const MOLLIE_PENDING    = 'pending';
    public const MOLLIE_AUTHORIZED = 'authorized';
    public const MOLLIE_EXPIRED    = 'expired';
    public const MOLLIE_FAILED     = 'failed';
    public const MOLLIE_PAID       = 'paid';
    public const MOLLIE_REFUNDED   = 'refunded';

    private const STATUS_MAP = [
        self::MOLLIE_OPEN       => 'P',
        self::MOLLIE_CANCELED   => 'X',
        self::MOLLIE_PENDING    => 'P',
        self::MOLLIE_AUTHORIZED => 'AM',
        self::MOLLIE_EXPIRED    => 'X',
        self::MOLLIE_FAILED     => 'D',
        self::MOLLIE_PAID       => 'C',
        self::MOLLIE_REFUNDED   => 'R',
    ];

    /**
     * @param string $mollieStatus
     *
     * @return string
     */
    public static function toVirtueMartStatus(string $mollieStatus): string
    {
        return self::STATUS_MAP[$mollieStatus] ?? 'P';
    }
}
