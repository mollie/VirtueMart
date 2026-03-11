<?php

namespace Mollie\Payment\Repository;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use Mollie\Payment\Interface\VirtueMartOrderStatusRepositoryInterface;

class VirtueMartOrderStatusRepository implements VirtueMartOrderStatusRepositoryInterface
{
    /**
     * @inheritDoc
     */
    public function getAllStatuses(): array
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->createQuery();

        $query->select([
            $db->quoteName('order_status_code'),
            $db->quoteName('order_status_name')
        ])
            ->from($db->quoteName('#__virtuemart_orderstates'))
            ->order($db->quoteName('order_status_name') . ' ASC');

        $db->setQuery($query);

        return $db->loadObjectList() ?: [];
    }

    /**
     * @inheritDoc
     */
    public function getStatusByCode(string $code): ?\stdClass
    {
        if (empty($code)) {
            return null;
        }

        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->createQuery();

        $query->select('*')
            ->from($db->quoteName('#__virtuemart_orderstates'))
            ->where($db->quoteName('order_status_code') . ' = ' . $db->quote($code));

        $db->setQuery($query, 0, 1);

        return $db->loadObject();
    }
}
