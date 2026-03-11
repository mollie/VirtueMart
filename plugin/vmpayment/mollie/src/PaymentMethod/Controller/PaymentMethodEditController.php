<?php

namespace Mollie\Payment\PaymentMethod\Controller;

use Exception;
use Joomla\CMS\Factory;
use Mollie\Infrastructure\Configuration\Configuration;
use Mollie\Infrastructure\Logger\Logger;
use Mollie\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException;
use Mollie\Infrastructure\ServiceRegister;
use Mollie\Payment\Configuration\ConfigurationService;
use Mollie\Payment\PaymentMethod\PaymentMethodService;
use Mollie\Payment\Configuration\PaymentMethodConfigService;
use Mollie\Payment\Infrastructure\DTO\Response;
use Mollie\Payment\Infrastructure\Exception\MollieNotConnectedException;
use Mollie\Payment\PaymentMethod\PaymentMethodCaptureRestrictions;

class PaymentMethodEditController
{
    private Configuration $configService;
    private PaymentMethodService $paymentMethodService;
    private PaymentMethodConfigService $paymentMethodConfigService;

    public function __construct()
    {
        /** @var ConfigurationService $configService */
        $configService = ServiceRegister::getService(Configuration::CLASS_NAME);
        $this->configService = $configService;

        /** @var PaymentMethodService $paymentMethodService */
        $paymentMethodService = ServiceRegister::getService(PaymentMethodService::class);
        $this->paymentMethodService = $paymentMethodService;

        /** @var PaymentMethodConfigService $paymentMethodConfigService */
        $paymentMethodConfigService = ServiceRegister::getService(PaymentMethodConfigService::class);
        $this->paymentMethodConfigService = $paymentMethodConfigService;
    }

    /**
     * Prepare all data needed for rendering the payment method edit form
     *
     * @param string $mollieMethod
     * @param int|null $currencyId
     *
     * @return Response
     */
    public function prepareEditData(string $mollieMethod = '', ?int $currencyId = null): Response
    {
        try {
            $paymentMethodsData = $this->getPaymentMethodsData($mollieMethod, $currencyId);
            $logoUrl = $this->getLogoUrl($mollieMethod, $paymentMethodsData['defaultLogo']);
            $transactionDescription = $this->getTransactionDescription($mollieMethod);
            $captureMode = $this->getCaptureMode($mollieMethod);

            return Response::success([
                'methods' => $paymentMethodsData['methods'],
                'selectedMethod' => $paymentMethodsData['selectedMethod'],
                'logoUrl' => $logoUrl,
                'transactionDescription' => $transactionDescription,
                'captureMode' => $captureMode,
                'captureRestrictions' => [
                    'manualOnly' => PaymentMethodCaptureRestrictions::MANUAL_ONLY,
                    'automaticOnly' => PaymentMethodCaptureRestrictions::AUTOMATIC_ONLY,
                ],
            ]);
        } catch (Exception $e) {
            Logger::logWarning('Payment method edit failed - not connected to Mollie', 'PaymentMethodEdit');

            Factory::getApplication()->enqueueMessage(
                \vmText::_('PLG_VMPAYMENT_MOLLIE_NOT_CONNECTED'),
                'warning'
            );

            return new Response(
                type: 'warning',
                data: [
                    'methods' => [],
                    'selectedMethod' => '',
                    'logoUrl' => '',
                    'transactionDescription' => '{orderNumber}',
                ],
                error: 'Not connected to Mollie'
            );
        }
    }

    /**
     * Get payment methods data with enabled methods and current selection
     *
     * @param string $mollieMethod
     * @param int|null $currencyId
     *
     * @return array{methods: array, selectedMethod: string, defaultLogo: string}
     *
     * @throws MollieNotConnectedException
     */
    private function getPaymentMethodsData(string $mollieMethod, ?int $currencyId): array
    {
        $profileId = $this->configService->getWebsiteProfile()?->getId();
        if (!$profileId) {
            throw new MollieNotConnectedException();
        }

        $currency = $currencyId
            ? $this->paymentMethodService->getCurrencyCodeById($currencyId)
            : 'EUR';

        $enabledMethods = $this->paymentMethodService->getEnabledPaymentMethods(
            $profileId,
            $currency
        );

        $selectedMethodData = $this->paymentMethodService->findSelectedMethodAndDefaultLogo(
            $enabledMethods,
            $mollieMethod
        );

        return [
            'methods' => $enabledMethods,
            'selectedMethod' => $selectedMethodData->method,
            'defaultLogo' => $selectedMethodData->defaultLogo
        ];
    }

    /**
     * Get logo URL for the current payment method
     *
     * @param string $mollieMethod
     * @param string $defaultLogo
     *
     * @return string
     */
    public function getLogoUrl(string $mollieMethod, string $defaultLogo = ''): string
    {
        return $this->paymentMethodConfigService->getPaymentMethodLogo($mollieMethod, $defaultLogo);
    }

    /**
     * @param string $mollieMethod
     *
     * @return string
     *
     * @throws MollieNotConnectedException
     * @throws RepositoryNotRegisteredException
     */
    private function getTransactionDescription(string $mollieMethod): string
    {
        if (!empty($mollieMethod)) {
            $profileId = $this->configService->getWebsiteProfile()?->getId();
            if (!$profileId) {
                throw new MollieNotConnectedException();
            }

            $config = $this->paymentMethodConfigService->getPaymentMethodConfig($profileId, $mollieMethod);

            if ($config && $config->getTransactionDescription()) {
                return $config->getTransactionDescription();
            }
        }

        return '{orderNumber}';
    }

    /**
     * @param string $mollieMethod
     *
     * @return string
     */
    private function getCaptureMode(string $mollieMethod): string
    {
        if (empty($mollieMethod)) {
            return PaymentMethodConfigService::CAPTURE_MODE_AUTOMATIC;
        }

        return $this->paymentMethodConfigService->getCaptureMode($mollieMethod);
    }
}
