<?php

namespace Mollie\Payment\Capture\Controller;

use Exception;
use Joomla\CMS\Language\Text;
use Mollie\Infrastructure\Logger\Logger;
use Mollie\Infrastructure\ServiceRegister;
use Mollie\Payment\Capture\CaptureService;
use Mollie\Payment\Infrastructure\DTO\Response;

class CaptureController
{
    private CaptureService $captureService;

    public function __construct()
    {
        /** @var CaptureService $captureService */
        $captureService = ServiceRegister::getService(CaptureService::class);
        $this->captureService = $captureService;
    }

    /**
     * Capture a payment
     *
     * @param string $orderId
     * @param float $captureAmount
     *
     * @return Response
     */
    public function capturePayment(string $orderId, float $captureAmount): Response
    {
        try {
            $capture = $this->captureService->capturePayment($orderId, $captureAmount);

            $capturedAmount = $capture->getAmount()->getValueAmount();
            $capturedCurrency = $capture->getAmount()->getCurrency();

            return Response::success([
                'amount' => $capturedAmount,
                'currency' => $capturedCurrency,
                'message' => sprintf(
                    Text::_('PLG_VMPAYMENT_MOLLIE_CAPTURE_SUCCESS'),
                    $capturedAmount,
                    $capturedCurrency
                )
            ]);
        } catch (Exception $e) {
            Logger::logError('Payment capture failed', 'Capture', [
                'orderId' => $orderId,
                'amount' => $captureAmount,
                'error' => $e->getMessage()
            ]);

            return Response::error(Text::_('PLG_VMPAYMENT_MOLLIE_CAPTURE_ERROR'));
        }
    }
}
