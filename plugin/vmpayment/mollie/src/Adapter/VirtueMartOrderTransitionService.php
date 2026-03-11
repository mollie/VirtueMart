<?php

namespace Mollie\Payment\Adapter;

use Joomla\CMS\Language\Text;
use Mollie\BusinessLogic\Integration\Interfaces\OrderTransitionService;
use Mollie\Infrastructure\Configuration\Configuration;
use Mollie\Infrastructure\Logger\Logger;
use Mollie\Payment\Payment\Mapping\MollieStatusMapping;

use VmModel;

class VirtueMartOrderTransitionService implements OrderTransitionService
{
    public function __construct(
        private readonly Configuration $configuration
    ) {}

    /**
     * @inheritDoc
     */
    public function payOrder($orderId, array $metadata): void
    {
        $this->updateOrderStatus($orderId, MollieStatusMapping::MOLLIE_PAID);
    }

    /**
     * @inheritDoc
     */
    public function expireOrder($orderId, array $metadata): void
    {
        $this->updateOrderStatus($orderId, MollieStatusMapping::MOLLIE_EXPIRED);
    }

    /**
     * @inheritDoc
     */
    public function cancelOrder($orderId, array $metadata): void
    {
        $this->updateOrderStatus($orderId, MollieStatusMapping::MOLLIE_CANCELED);
    }

    /**
     * @inheritDoc
     */
    public function failOrder($orderId, array $metadata): void
    {
        $this->updateOrderStatus($orderId, MollieStatusMapping::MOLLIE_FAILED);
    }

    /**
     * @inheritDoc
     */
    public function completeOrder($orderId, array $metadata): void
    {
        $this->updateOrderStatus($orderId, MollieStatusMapping::MOLLIE_PAID);
    }

    /**
     * @inheritDoc
     */
    public function authorizeOrder($orderId, array $metadata): void
    {
        $this->updateOrderStatus($orderId, MollieStatusMapping::MOLLIE_AUTHORIZED);
    }

    /**
     * @inheritDoc
     */
    public function refundOrder($orderId, array $metadata): void
    {
        $this->updateOrderStatus($orderId, MollieStatusMapping::MOLLIE_REFUNDED);
    }

    /**
     * Update VirtueMart order status
     *
     * @param string $orderId
     * @param string $mollieStatus
     *
     * @return void
     */
    private function updateOrderStatus(string $orderId, string $mollieStatus): void
    {
        $virtueMartStatus = $this->configuration->getOrderStatusMapping()[$mollieStatus];

        if (empty($virtueMartStatus)) {
            return;
        }

        $orderModel = VmModel::getModel('orders');
        $order = $orderModel->getOrder($orderId);

        if (!$order || empty($order['details']['BT'])) {
            Logger::logWarning("Order {$orderId} not found, cannot update status");

            return;
        }

        $currentStatus = $order['details']['BT']->order_status;

        if ($currentStatus === $virtueMartStatus) {
            Logger::logDebug("Order {$orderId} already has status {$virtueMartStatus}, skipping update");

            return;
        }

        $orderChanges = [
            'order_status' => $virtueMartStatus,
            'comments' => Text::_('PLG_VMPAYMENT_MOLLIE_PAYMENT_STATUS_UPDATED')
        ];

        $orderModel->updateStatusForOneOrder($orderId, $orderChanges, true);

        Logger::logDebug("Order status for order {$orderId} updated from {$currentStatus} to {$virtueMartStatus}");
    }
}
