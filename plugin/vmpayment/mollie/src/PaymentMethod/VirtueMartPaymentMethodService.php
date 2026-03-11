<?php

namespace Mollie\Payment\PaymentMethod;

use Mollie\BusinessLogic\Http\Exceptions\UnprocessableEntityRequestException;
use Mollie\BusinessLogic\PaymentMethod\Model\PaymentMethodConfig;
use Mollie\BusinessLogic\PaymentMethod\PaymentMethodService as CorePaymentMethodService;
use Mollie\Infrastructure\Http\Exceptions\HttpAuthenticationException;
use Mollie\Infrastructure\Http\Exceptions\HttpCommunicationException;
use Mollie\Infrastructure\Http\Exceptions\HttpRequestException;

class VirtueMartPaymentMethodService extends CorePaymentMethodService
{
    protected static $instance;

    /**
     * Get payment configuration by Mollie ID
     *
     * @param string $profileId
     * @param string $paymentMethodId
     *
     * @return PaymentMethodConfig|null
     *
     * @throws HttpAuthenticationException
     * @throws HttpCommunicationException
     * @throws HttpRequestException
     * @throws UnprocessableEntityRequestException
     */
    public function getPaymentConfigurationById($profileId, $paymentMethodId): PaymentMethodConfig|null
    {
        $configurations = $this->getAllPaymentMethodConfigurations($profileId);

        $mollieMethodId = str_replace('mollie_', '', $paymentMethodId);

        foreach ($configurations as $configuration) {
            if ($configuration->getMollieId() === $mollieMethodId) {
                return $configuration;
            }
        }

        return null;
    }
}
