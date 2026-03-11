<?php

namespace Mollie\Payment\Capture;

use Mollie\BusinessLogic\BaseService;
use Mollie\BusinessLogic\Http\DTO\Payments\Capture;
use Mollie\BusinessLogic\Http\Exceptions\UnprocessableEntityRequestException;
use Mollie\BusinessLogic\OrderReference\Exceptions\ReferenceNotFoundException;
use Mollie\BusinessLogic\OrderReference\Model\OrderReference;
use Mollie\BusinessLogic\OrderReference\OrderReferenceService;
use Mollie\BusinessLogic\Payments\PaymentService;
use Mollie\Infrastructure\Http\Exceptions\HttpAuthenticationException;
use Mollie\Infrastructure\Http\Exceptions\HttpCommunicationException;
use Mollie\Infrastructure\Http\Exceptions\HttpRequestException;

class CaptureService extends BaseService
{
    /**
     * Fully qualified name of this class.
     */
    const CLASS_NAME = __CLASS__;
    /**
     * Singleton instance of this class.
     *
     * @var static
     */
    protected static $instance;

    public function __construct(
        private readonly PaymentService $paymentService,
        private readonly OrderReferenceService $orderReferenceService
    )
    {
        parent::__construct();
    }

    /**
     * Capture a payment
     *
     * @param string $shopReference
     * @param float $captureAmount
     *
     * @return Capture
     *
     * @throws UnprocessableEntityRequestException
     * @throws ReferenceNotFoundException
     * @throws HttpAuthenticationException
     * @throws HttpCommunicationException
     * @throws HttpRequestException
     */
    public function capturePayment(string $shopReference, float $captureAmount): Capture
    {
        $payment = $this->paymentService->getPayment($shopReference);

        if (!$payment) {
            throw new ReferenceNotFoundException("Payment not found for order: {$shopReference}");
        }

        if ($payment->getStatus() !== 'authorized') {
            throw new UnprocessableEntityRequestException('status', "Payment cannot be captured. Status: {$payment->getStatus()}");
        }

        $authorizedAmount = (float) $payment->getAmount()->getAmountValue();
        $currency = $payment->getAmount()->getCurrency();

        if ($captureAmount <= 0) {
            throw new UnprocessableEntityRequestException('amount', 'Capture amount must be greater than zero');
        }

        if ($captureAmount > $authorizedAmount) {
            throw new UnprocessableEntityRequestException(
                'amount',
                "Capture amount {$captureAmount} {$currency} exceeds authorized amount {$authorizedAmount} {$currency}"
            );
        }

        $formattedAmount = number_format($captureAmount, 2, '.', '');

        $capture = Capture::fromArray([
            'amount' => [
                'value' => $formattedAmount,
                'currency' => $currency,
            ]
        ]);

        $orderReference = $this->getOrderReference($shopReference);
        if ($orderReference) {
            return $this->getProxy()->createCapture($capture, $orderReference->getMollieReference());
        }

        throw new ReferenceNotFoundException("Order reference not found for shop reference: {$shopReference}");
    }

    /**
     * @param $shopReference
     *
     * @return OrderReference|null
     */
    private function getOrderReference($shopReference): ?OrderReference
    {
        return $this->orderReferenceService->getByShopReference($shopReference);
    }
}
