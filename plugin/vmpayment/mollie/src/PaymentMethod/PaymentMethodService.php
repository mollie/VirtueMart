<?php

namespace Mollie\Payment\PaymentMethod;

use Mollie\BusinessLogic\Configuration;
use Mollie\BusinessLogic\Http\DTO\Amount;
use Mollie\BusinessLogic\PaymentMethod\Model\PaymentMethodConfig;
use Mollie\BusinessLogic\PaymentMethod\PaymentMethodService as CorePaymentMethodService;
use Mollie\Infrastructure\Logger\Logger;
use Mollie\Infrastructure\ServiceRegister;
use Mollie\Payment\Infrastructure\DTO\SelectedMethodData;
use Mollie\Payment\Interface\VirtueMartRepositoryInterface;

class PaymentMethodService
{
    public function __construct(
        private readonly CorePaymentMethodService $corePaymentMethodService,
        private readonly VirtueMartRepositoryInterface $virtueMartRepository
    ) {}

    /**
     * @param string $profileId
     * @param string $currency
     *
     * @return array
     */
    public function getEnabledPaymentMethods(string $profileId, string $currency): array
    {
        $amount = new Amount();
        $amount->setCurrency($currency);
        $amount->setAmountValue(100);

        try {
            $enabledPaymentMethods = $this->corePaymentMethodService->getEnabledPaymentMethodConfigurations(
                profileId : $profileId,
                amount: $amount,
                apiMethod: PaymentMethodConfig::API_METHOD_PAYMENT
            );

            return array_filter(
                $enabledPaymentMethods,
                fn ($method) => SupportedPaymentMethods::isSupported($method->getMollieId())
            );
        } catch (\Exception $e) {
            Logger::logError('Failed to get enabled payment methods', 'PaymentMethod', [
                'profileId' => $profileId,
                'currency' => $currency,
                'error' => $e->getMessage()
            ]);

            return [];
        }
    }

    /**
     * Determine which payment method should be selected
     *
     * @param array $methods
     * @param string $currentSelection
     *
     * @return SelectedMethodData
     */
    public function findSelectedMethodAndDefaultLogo(array $methods, string $currentSelection): SelectedMethodData
    {
        foreach ($methods as $method) {
            if ($method->getMollieId() === $currentSelection) {
                return new SelectedMethodData(
                    method: $currentSelection,
                    defaultLogo: $method->getOriginalAPIConfig()->getImage()->getSize2x()
                );
            }
        }

        return !empty($methods)
            ? new SelectedMethodData(
                method: $methods[0]->getMollieId(),
                defaultLogo: $methods[0]->getOriginalAPIConfig()->getImage()->getSize2x()
            )
            : new SelectedMethodData(method: '', defaultLogo: '');
    }

    /**
     * Get currency code by currency ID
     *
     * @param int $currencyId
     *
     * @return string
     */
    public function getCurrencyCodeById(int $currencyId): string
    {
        return $this->virtueMartRepository->getCurrencyCode($currencyId);
    }

    /**
     * Get country ISO code from VirtueMart country ID
     *
     * @param int $countryId
     *
     * @return string|null
     */
    public function getCountryIsoCode(int $countryId): ?string
    {
        return $this->virtueMartRepository->getCountryIsoCode($countryId);
    }

    /**
     * Get payment methods available for a specific country from Mollie API
     *
     * @param string $countryCode
     *
     * @return PaymentMethodConfig[]
     */
    public function getPaymentMethodsByCountry(string $countryCode, string $orderTotal, string $currencyCode): array
    {
        try {
            $configService = ServiceRegister::getService(Configuration::CLASS_NAME);
            $profile = $configService->getWebsiteProfile();

            if (!$profile) {
                return [];
            }

            $profileId = $profile->getId();

            $amount = new Amount();
            $amount->setCurrency($currencyCode);
            $amount->setAmountValue($orderTotal);

            $methods = $this->corePaymentMethodService->getEnabledPaymentMethodConfigurations(
                $profileId,
                $countryCode,
                $amount,
                PaymentMethodConfig::API_METHOD_PAYMENT
            );

            $methodIds = [];
            foreach ($methods as $method) {
                $methodIds[] = $method->getMollieId();
            }

            return $methodIds;

        } catch (\Exception $e) {
            Logger::logError('Failed to get payment methods by country', 'PaymentMethod', [
                'countryCode' => $countryCode,
                'error' => $e->getMessage()
            ]);

            return [];
        }
    }
}
