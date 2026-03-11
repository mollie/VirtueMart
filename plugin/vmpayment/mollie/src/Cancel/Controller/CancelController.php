<?php

namespace Mollie\Payment\Cancel\Controller;

use Exception;
use Joomla\CMS\Language\Text;
use Mollie\BusinessLogic\OrderReference\Exceptions\ReferenceNotFoundException;
use Mollie\Infrastructure\Logger\Logger;
use Mollie\Infrastructure\ServiceRegister;
use Mollie\Payment\Cancel\CancelService;
use Mollie\Payment\Infrastructure\DTO\Response;

class CancelController
{
    private CancelService $cancelService;

    public function __construct()
    {
        /** @var CancelService $cancelService */
        $cancelService = ServiceRegister::getService(CancelService::class);
        $this->cancelService = $cancelService;
    }

    /**
     * @param string $orderId
     *
     * @return Response
     */
    public function cancelPayment(string $orderId): Response
    {
        try {
            $payment = $this->cancelService->cancelPayment($orderId);

            return Response::success([
                'payment_id' => $payment->getId(),
                'status' => $payment->getStatus(),
                'message' => Text::_('PLG_VMPAYMENT_MOLLIE_CANCEL_SUCCESS')
            ]);
        } catch (ReferenceNotFoundException $e) {
            Logger::logInfo('No payment to cancel for order ' . $orderId, 'Cancel');

            return Response::success([
                'message' => 'No payment to cancel'
            ]);
        } catch (Exception $e) {
            Logger::logError('Payment cancellation failed', 'Cancel', [
                'orderId' => $orderId,
                'error' => $e->getMessage()
            ]);

            return Response::error(sprintf(
                Text::_('PLG_VMPAYMENT_MOLLIE_CANCEL_ERROR'),
                $e->getMessage()
            ));
        }
    }
}
