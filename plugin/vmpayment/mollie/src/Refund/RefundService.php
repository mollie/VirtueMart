<?php

namespace Mollie\Payment\Refund;

use Mollie\BusinessLogic\Http\DTO\Refunds\Refund;
use Mollie\BusinessLogic\Http\Exceptions\UnprocessableEntityRequestException;
use Mollie\BusinessLogic\OrderReference\Exceptions\ReferenceNotFoundException;
use Mollie\BusinessLogic\Payments\PaymentService;
use Mollie\BusinessLogic\Refunds\RefundService as CoreRefundService;
use Mollie\Infrastructure\Http\Exceptions\HttpAuthenticationException;
use Mollie\Infrastructure\Http\Exceptions\HttpCommunicationException;
use Mollie\Infrastructure\Http\Exceptions\HttpRequestException;

class RefundService
{
    public function __construct(
        private readonly CoreRefundService $coreRefundService,
        private readonly PaymentService $paymentService)
    {}

    /**
     * Refund a payment
     *
     * @param string $shopReference
     * @param float $refundAmount
     *
     * @return Refund
     *
     * @throws UnprocessableEntityRequestException
     * @throws ReferenceNotFoundException
     * @throws HttpAuthenticationException
     * @throws HttpCommunicationException
     * @throws HttpRequestException
     */
    public function refundPayment(string $shopReference, float $refundAmount): Refund
    {
        $payment = $this->paymentService->getPayment($shopReference);

        if ($payment->getStatus() !== 'paid') {
            throw new UnprocessableEntityRequestException('status', "Payment cannot be refunded. Status: {$payment->getStatus()}");
        }

        $paidAmount = (float) $payment->getAmount()->getAmountValue();
        $currency = $payment->getAmount()->getCurrency();

        if ($refundAmount <= 0) {
            throw new UnprocessableEntityRequestException('amount', 'Refund amount must be greater than zero');
        }

        $refundedAmount = 0;
        if ($payment->getAmountRefunded()) {
            $refundedAmount = (float) $payment->getAmountRefunded()->getAmountValue();
        }

        $availableAmount = $paidAmount - $refundedAmount;

        if ($refundAmount > $availableAmount) {
            throw new UnprocessableEntityRequestException('amount', "Refund amount ({$refundAmount}) exceeds available amount ({$availableAmount})");
        }

        $formattedAmount = number_format($refundAmount, 2, '.', '');

        $refund = Refund::fromArray([
            'amount' => [
                'value' => $formattedAmount,
                'currency' => $currency,
            ]
        ]);

        return $this->coreRefundService->refundPayment($shopReference, $refund);
    }
}
