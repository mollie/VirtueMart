<?php

namespace Mollie\Payment\Repository;

use Joomla\CMS\Language\Text;
use Mollie\Payment\Interface\VirtueMartRepositoryInterface;
use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use VmModel;

/**
 * Repository for VirtueMart database operations
 */
class VirtueMartRepository implements VirtueMartRepositoryInterface
{
    /**
     * @inheritDoc
     */
    public function getCountryIsoCode(int $countryId): ?string
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->createQuery()
            ->select($db->quoteName('country_2_code'))
            ->from($db->quoteName('#__virtuemart_countries'))
            ->where($db->quoteName('virtuemart_country_id') . ' = ' . (int)$countryId);

        $db->setQuery($query);

        return $db->loadResult() ?: null;
    }

    /**
     * @inheritDoc
     */
    public function updateOrderStatus(int $orderId, string $status): bool
    {
        $modelOrder = VmModel::getModel('orders');

        $order = [];
        $order['order_status'] = $status;
        $order['virtuemart_order_id'] = $orderId;
        $order['customer_notified'] = 1;
        $order['comments'] = Text::_('PLG_VMPAYMENT_MOLLIE_PAYMENT_STATUS_UPDATED');

        return (bool)$modelOrder->updateStatusForOneOrder($orderId, $order, true);
    }

    /**
     * @inheritDoc
     */
    public function getOrderNumber(int $orderId): ?string
    {
        $modelOrder = VmModel::getModel('orders');
        $order = $modelOrder->getOrder($orderId);

        return $order['details']['BT']->order_number ?? null;
    }

    /**
     * @inheritDoc
     */
    public function getCurrencyCode(int $currencyId): string
    {
        $currencyModel = VmModel::getModel('currency');
        $currency = $currencyModel->getCurrency($currencyId);

        if ($currency && !empty($currency->currency_code_3)) {
            return $currency->currency_code_3;
        }

        return 'EUR';
    }

    /**
     * @inheritDoc
     */
    public function getOrderItems(int $orderId): array
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->createQuery()
            ->select('*')
            ->from($db->quoteName('#__virtuemart_order_items'))
            ->where($db->quoteName('virtuemart_order_id') . ' = ' . (int)$orderId);

        $db->setQuery($query);
        $items = $db->loadObjectList('virtuemart_order_item_id');

        return $items ?: [];
    }

    /**
     * @inheritDoc
     */
    public function getOrder(int $orderId): ?object
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->createQuery()
            ->select('*')
            ->from($db->quoteName('#__virtuemart_orders'))
            ->where($db->quoteName('virtuemart_order_id') . ' = ' . (int)$orderId);

        $db->setQuery($query);

        return $db->loadObject() ?: null;
    }
}
