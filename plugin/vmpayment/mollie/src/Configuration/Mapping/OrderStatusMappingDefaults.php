<?php

namespace Mollie\Payment\Configuration\Mapping;

use Mollie\Payment\Payment\Mapping\MollieStatusMapping;

defined('_JEXEC') or die;

final class OrderStatusMappingDefaults
{
    /**
     * @return string[]
     */
    public static function getDefaultMapping(): array
    {
        return [
            MollieStatusMapping::MOLLIE_OPEN       => '',
            MollieStatusMapping::MOLLIE_CANCELED   => 'X',
            MollieStatusMapping::MOLLIE_PENDING    => 'P',
            MollieStatusMapping::MOLLIE_AUTHORIZED => 'AM',
            MollieStatusMapping::MOLLIE_EXPIRED    => 'X',
            MollieStatusMapping::MOLLIE_FAILED     => 'D',
            MollieStatusMapping::MOLLIE_PAID       => 'C',
            MollieStatusMapping::MOLLIE_REFUNDED   => 'R',
        ];
    }
}
