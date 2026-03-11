<?php

namespace Mollie\Payment\Adapter;

defined('_JEXEC') or die;

use Mollie\Payment\Interface\VirtueMartOrderStatusRepositoryInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;

class VirtueMartOrderStatusService
{
    public function __construct(
        private readonly VirtueMartOrderStatusRepositoryInterface $statusRepository
    ) {}

    /**
     * Returns all VirtueMart status options
     *
     * @return array
     */
    public function getStatusOptions(): array
    {
        $lang = Factory::getApplication()->getLanguage();

        $lang->load(
            'com_virtuemart_orders',
            JPATH_SITE . '/components/com_virtuemart',
            $lang->getTag(),
            true
        );

        $statuses = $this->statusRepository->getAllStatuses();

        foreach ($statuses as $status) {
            $status->translated_name = Text::_($status->order_status_name);
        }

        return $statuses;
    }
}
