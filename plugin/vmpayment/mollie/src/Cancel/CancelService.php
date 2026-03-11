<?php

namespace Mollie\Payment\Cancel;

use Joomla\CMS\Language\Text;
use Mollie\BusinessLogic\BaseService;
use Mollie\BusinessLogic\Http\DTO\Payment;
use Mollie\BusinessLogic\Http\Exceptions\UnprocessableEntityRequestException;
use Mollie\BusinessLogic\OrderReference\Exceptions\ReferenceNotFoundException;
use Mollie\BusinessLogic\OrderReference\Model\OrderReference;
use Mollie\BusinessLogic\OrderReference\OrderReferenceService;
use Mollie\BusinessLogic\Payments\PaymentService;
use Mollie\Infrastructure\Http\Exceptions\HttpAuthenticationException;
use Mollie\Infrastructure\Http\Exceptions\HttpCommunicationException;
use Mollie\Infrastructure\Http\Exceptions\HttpRequestException;

class CancelService extends BaseService
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
     * @param string $shopReference
     *
     * @return Payment
     *
     * @throws UnprocessableEntityRequestException
     * @throws ReferenceNotFoundException
     * @throws HttpAuthenticationException
     * @throws HttpCommunicationException
     * @throws HttpRequestException
     */
    public function cancelPayment(string $shopReference): Payment
    {
        $payment = $this->paymentService->getPayment($shopReference);

        $status = $payment->getStatus();
        if (!in_array($status, ['open', 'authorized'])) {
            throw new UnprocessableEntityRequestException('status',
                sprintf(Text::_("PLG_VMPAYMENT_MOLLIE_CANCEL_STATUS"), $status));
        }

        $orderReference = $this->getOrderReference($shopReference);
        if (!$orderReference) {
            throw new ReferenceNotFoundException("Order reference not found for shop reference: {$shopReference}");
        }

        return $this->getProxy()->cancelPayment($orderReference->getMollieReference());
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
