<?php

namespace Mollie\Payment\Payment\Controller;

use Mollie\Infrastructure\Logger\Logger;
use Mollie\Infrastructure\ServiceRegister;
use Mollie\Payment\Payment\PaymentResponseService;
use Mollie\Payment\Infrastructure\DTO\Response;
use Joomla\CMS\Language\Text;

class PaymentResponseController
{
    private PaymentResponseService $paymentResponseService;

    public function __construct()
    {
        /** @var PaymentResponseService $paymentResponseService */
        $paymentResponseService = ServiceRegister::getService(PaymentResponseService::class);
        $this->paymentResponseService = $paymentResponseService;
    }

    /**
     * Handle customer return from Mollie checkout
     *
     * @param string $orderId VirtueMart order ID
     * @return Response
     */
    public function handleReturn(string $orderId): Response
    {
        if (empty($orderId) || !is_numeric($orderId)) {
            Logger::logError("The order {$orderId} is not valid", 'Payment');

            return Response::error(Text::_('PLG_VMPAYMENT_MOLLIE_INVALID_ORDER'));
        }

        $payment = $this->paymentResponseService->getPayment($orderId);

        if (!$payment) {
            Logger::logError("The payment for order {$orderId} is not found", 'Payment');

            return Response::error(Text::_('PLG_VMPAYMENT_MOLLIE_PAYMENT_NOT_FOUND'));
        }

        $this->paymentResponseService->updateOrderStatus((int)$orderId, $payment);

        $orderNumber = $this->paymentResponseService->getOrderNumber((int)$orderId);

        $status = $payment->getStatus();

        $type = match(true) {
            $this->paymentResponseService->isSuccessStatus($status) => 'success',
            $this->paymentResponseService->isFailedStatus($status) => 'failed',
            default => 'unknown',
        };

        return new Response(
            type: $type,
            data: [
                'orderId' => (int)$orderId,
                'orderNumber' => $orderNumber,
                'paymentStatus' => $status,
            ]
        );
    }
}
