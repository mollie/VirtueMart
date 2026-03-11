<?php

namespace Mollie\Payment\Payment\Controller;

use Exception;
use Mollie\Infrastructure\Logger\Logger;
use Mollie\Infrastructure\ServiceRegister;
use Mollie\Payment\Payment\PaymentService;
use Mollie\Payment\Infrastructure\DTO\Response;
use Joomla\CMS\Language\Text;
use RuntimeException;
use VirtueMartCart;

class PaymentController
{
    private PaymentService $paymentService;

    public function __construct()
    {
        /** @var PaymentService $paymentService */
        $paymentService = ServiceRegister::getService(PaymentService::class);
        $this->paymentService = $paymentService;
    }

    /**
     * Handle order confirmation and create Mollie payment
     *
     * @param VirtueMartCart $cart
     * @param array $order
     * @param object $method
     *
     * @return Response
     */
    public function createPayment(VirtueMartCart $cart, array $order, object $method): Response
    {
        $cardToken = $this->paymentService->getCardToken($method);

        if ($this->paymentService->requiresCardToken($method) && empty($cardToken)) {
            return Response::error(Text::_('PLG_VMPAYMENT_MOLLIE_CARD_TOKEN_MISSING'));
        }

        try {
            $result = $this->paymentService->createPayment($cart, $order, $method, $cardToken);

            if (!$result['success']) {
                return Response::error($result['error']);
            }

            return Response::success([
                'checkoutUrl' => $result['checkoutUrl'],
                'paymentId' => $result['paymentId']
            ]);
        } catch (RuntimeException $e) {
            Logger::logError('Payment creation failed: ' . $e->getMessage(), 'Payment');

            return Response::error('Error creating payment');
        }
    }

    /**
     * @param string $orderId
     *
     * @return Response
     */
    public function getPayment(string $orderId): Response
    {
        try {
            $payment = $this->paymentService->getPayment($orderId);

            return Response::success(['payment' => $payment]);
        } catch (Exception $e) {
            Logger::logError("There was an error getting the payment for {$orderId}", 'Payment',
            [
                'orderId' => $orderId,
                'error' => $e->getMessage()
            ]);

            return Response::error(Text::_('PLG_VMPAYMENT_MOLLIE_GET_PAYMENT_ERROR'));
        }
    }
}

