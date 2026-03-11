<?php

namespace Mollie\Payment\Payment;

use Mollie\BusinessLogic\Http\DTO\Payment;
use Mollie\BusinessLogic\Payments\PaymentService;
use Mollie\Infrastructure\Configuration\Configuration;
use Mollie\Infrastructure\Logger\Logger;
use Mollie\Payment\Interface\VirtueMartRepositoryInterface;

class PaymentResponseService
{
    private const SUCCESS_STATUSES = ['paid', 'authorized', 'open', 'pending'];
    private const FAILED_STATUSES = ['failed', 'canceled', 'expired'];

    public function __construct(
        private readonly Configuration $configService,
        private readonly PaymentService $paymentService,
        private readonly VirtueMartRepositoryInterface $vmRepository
    ) {}

    /**
     * Get payment from Mollie by order ID
     *
     * @param string $orderId
     *
     * @return Payment|null
     */
    public function getPayment(string $orderId): ?Payment
    {
        try {
            return $this->paymentService->getPayment($orderId);
        } catch (\Exception $e) {
            Logger::logError('Failed to get payment', 'PaymentResponse', [
                'orderId' => $orderId,
                'error' => $e->getMessage()
            ]);

            return null;
        }
    }

    /**
     * Update VirtueMart order status based on Mollie payment status
     *
     * @param int $orderId
     * @param Payment $payment
     *
     * @return bool
     */
    public function updateOrderStatus(int $orderId, Payment $payment): bool
    {
        $currentStatus = $this->vmRepository->getOrder($orderId)->order_status;
        $mollieStatus = $payment->getStatus();
        $virtuemartStatus = $this->configService->getOrderStatusMapping()[$mollieStatus];

        if ($virtuemartStatus !== '' && $virtuemartStatus !== $currentStatus) {
            return $this->vmRepository->updateOrderStatus($orderId, $virtuemartStatus);
        }

        return false;
    }

    /**
     * @param string $status
     *
     * @return bool
     */
    public function isSuccessStatus(string $status): bool
    {
        return in_array($status, self::SUCCESS_STATUSES);
    }

    /**
     * @param string $status
     *
     * @return bool
     */
    public function isFailedStatus(string $status): bool
    {
        return in_array($status, self::FAILED_STATUSES);
    }

    /**
     * @param int $orderId
     *
     * @return string|null
     */
    public function getOrderNumber(int $orderId): ?string
    {
        return $this->vmRepository->getOrderNumber($orderId);
    }
}
