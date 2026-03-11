<?php

namespace Mollie\Payment\Refund\Controller;

use Exception;
use Joomla\CMS\Language\Text;
use Mollie\Infrastructure\Logger\Logger;
use Mollie\Infrastructure\ServiceRegister;
use Mollie\Payment\Infrastructure\DTO\Response;
use Mollie\Payment\Refund\RefundService;

class RefundController
{
    private RefundService $refundService;

    public function __construct()
    {
        /** @var RefundService $refundService */
        $refundService = ServiceRegister::getService(RefundService::class);
        $this->refundService = $refundService;
    }

    /**
     * Refund a payment
     *
     * @param string $orderId
     * @param float $refundAmount
     *
     * @return Response
     */
    public function refundPayment(string $orderId, float $refundAmount): Response
    {
        try {
            $refund = $this->refundService->refundPayment($orderId, $refundAmount);

            $refundedValue = $refund->getAmount()->getAmountValue();
            $refundedCurrency = $refund->getAmount()->getCurrency();

            return Response::success([
                'amount' => $refundedValue,
                'currency' => $refundedCurrency,
                'message' => sprintf(
                    Text::_('PLG_VMPAYMENT_MOLLIE_REFUND_SUCCESS'),
                    $refundedValue,
                    $refundedCurrency
                )
            ]);
        } catch (Exception $e) {
            Logger::logError('Payment refund failed', 'Refund', [
                'orderId' => $orderId,
                'amount' => $refundAmount,
                'error' => $e->getMessage()
            ]);

            return Response::error(Text::_('PLG_VMPAYMENT_MOLLIE_REFUND_ERROR'));
        }
    }
}
