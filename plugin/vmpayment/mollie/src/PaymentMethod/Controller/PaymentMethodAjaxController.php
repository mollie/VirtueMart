<?php

namespace Mollie\Payment\PaymentMethod\Controller;

use Mollie\Infrastructure\Configuration\Configuration;
use Mollie\Infrastructure\Logger\Logger;
use Mollie\Infrastructure\ServiceRegister;
use Mollie\Payment\Configuration\ConfigurationService;
use Mollie\Payment\PaymentMethod\PaymentMethodService;
use Mollie\Payment\Configuration\PaymentMethodConfigService;
use Mollie\Payment\Configuration\Mapping\PaymentMethodDisplayName;
use Joomla\CMS\Response\JsonResponse;

class PaymentMethodAjaxController
{
    private Configuration $configService;
    private PaymentMethodService $paymentMethodService;
    private PaymentMethodConfigService $paymentMethodConfigService;

    public function __construct()
    {
        /** @var ConfigurationService $configService */
        $configService = ServiceRegister::getService(Configuration::class);
        $this->configService = $configService;

        /** @var PaymentMethodService $paymentMethodService */
        $paymentMethodService = ServiceRegister::getService(PaymentMethodService::class);
        $this->paymentMethodService = $paymentMethodService;

        /** @var PaymentMethodConfigService $paymentMethodConfigService */
        $paymentMethodConfigService = ServiceRegister::getService(PaymentMethodConfigService::class);
        $this->paymentMethodConfigService = $paymentMethodConfigService;
    }

    /**
     * @param string $currencyCode
     * @param string $currentSelection
     *
     * @return JsonResponse
     */
    public function getPaymentMethodsResponse(string $currencyCode, string $currentSelection = ''): JsonResponse
    {
        try {
            $data = $this->getPaymentMethodsData($currencyCode, $currentSelection);

            $methods = [];
            foreach ($data['methods'] as $method) {
                $methodId = $method->getMollieId();
                $defaultLogo = $method->getOriginalAPIConfig()->getImage()->getSize2x();

                $logo = $this->getLogoForMethod($methodId, $defaultLogo);

                $methods[] = [
                    'id' => $methodId,
                    'name' => PaymentMethodDisplayName::fromMethodId($methodId),
                    'logo' => $logo,
                    'hasCustomLogo' => $logo !== $defaultLogo,
                ];
            }

            return new JsonResponse([
                'methods' => $methods,
                'selectedMethod' => $data['selectedMethod'],
                'defaultLogo' => $data['defaultLogo'],
            ]);
        } catch (\Exception $e) {
            Logger::logError('Failed to get payment methods response', 'PaymentMethodAjax', [
                'currency' => $currencyCode,
                'error' => $e->getMessage()
            ]);

            return new JsonResponse(['error' => $e->getMessage()], null, false, true);
        }
    }

    /**
     * Get logo URL for the current payment method
     *
     * @param string $mollieMethod
     * @param string $defaultLogo
     *
     * @return string
     */
    private function getLogoForMethod(string $mollieMethod, string $defaultLogo): string
    {
        return $this->paymentMethodConfigService->getPaymentMethodLogo($mollieMethod, $defaultLogo);
    }

    /**
     * Get country ISO code from VirtueMart country ID
     *
     * @param int $countryId
     *
     * @return JsonResponse
     */
    public function getCountryCodeResponse(int $countryId): JsonResponse
    {
        try {
            $countryCode = $this->paymentMethodService->getCountryIsoCode($countryId);

            if (!$countryCode) {
                Logger::logWarning("Country code not found for country id {$countryId}", 'PaymentMethodAjax');

                return new JsonResponse(['error' => 'Country not found'], null, false, true);
            }

            return new JsonResponse(['countryCode' => $countryCode]);
        } catch (\Exception $e) {
            Logger::logError('Failed to get country code', 'PaymentMethodAjax', [
                'countryId' => $countryId,
                'error' => $e->getMessage()
            ]);

            return new JsonResponse(['error' => $e->getMessage()], null, false, true);
        }
    }

    /**
     * Get payment methods by country
     *
     * @param string $countryCode
     *
     * @return JsonResponse
     */
    public function getPaymentMethodsByCountryResponse(string $countryCode, string $orderTotal, string $currencyCode): JsonResponse
    {
        try {
            $methods = $this->paymentMethodService->getPaymentMethodsByCountry($countryCode, $orderTotal, $currencyCode);

            return new JsonResponse(['methods' => $methods]);

        } catch (\Exception $e) {
            Logger::logError('Failed to get payment methods by country', 'PaymentMethodAjax', [
                'countryCode' => $countryCode,
                'error' => $e->getMessage()
            ]);

            return new JsonResponse(['error' => $e->getMessage()], null, false, true);
        }
    }

    /**
     * @param string $currencyCode
     * @param string $currentSelection
     *
     * @return array{methods: array, selectedMethod: string, defaultLogo: string}
     */
    private function getPaymentMethodsData(string $currencyCode, string $currentSelection = ''): array
    {
        $profileId = $this->configService->getWebsiteProfile()->getId();

        try {
            $enabledMethods = $this->paymentMethodService->getEnabledPaymentMethods(
                $profileId,
                $currencyCode
            );

            $selectedMethodData = $this->paymentMethodService->findSelectedMethodAndDefaultLogo(
                $enabledMethods,
                $currentSelection
            );

            return [
                'methods' => $enabledMethods,
                'selectedMethod' => $selectedMethodData->method,
                'defaultLogo' => $selectedMethodData->defaultLogo
            ];
        } catch (\Exception $e) {
            Logger::logError('Failed to get payment methods', 'PaymentMethodAjax', [
                'profileId' => $profileId,
                'currency' => $currencyCode,
                'error' => $e->getMessage()
            ]);

            return [
                'methods' => [],
                'selectedMethod' => '',
                'defaultLogo' => ''
            ];
        }
    }
}
