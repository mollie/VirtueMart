<?php

namespace Mollie\Payment\Configuration;

use Mollie\BusinessLogic\Configuration;
use Mollie\BusinessLogic\Http\DTO\Amount;
use Mollie\BusinessLogic\PaymentMethod\Model\PaymentMethodConfig;
use Mollie\BusinessLogic\PaymentMethod\PaymentMethodService as CorePaymentMethodService;
use Mollie\Infrastructure\Logger\Logger;
use Mollie\Infrastructure\ORM\Exceptions\RepositoryNotRegisteredException;
use Mollie\Infrastructure\ORM\RepositoryRegistry;
use Mollie\Payment\Repository\PaymentMethodConfigRepository;

class PaymentMethodConfigService
{
    public const CAPTURE_MODE_AUTOMATIC = 'automatic';

    public function __construct(
        private readonly Configuration $configService,
        private readonly CorePaymentMethodService $corePaymentMethodService
    ) {}

    /**
     * Save payment method configuration
     *
     * @param string $mollieMethodId
     * @param string $currencyCode
     * @param string|null $transactionDescription
     * @param string|null $customLogoUrl
     *
     * @return PaymentMethodConfig
     *
     * @throws RepositoryNotRegisteredException
     */
    public function savePaymentMethodConfig(
        string $mollieMethodId,
        string $currencyCode,
        ?string $transactionDescription = null,
        ?string $customLogoUrl = null,
        ?string $captureMode = null
    ): PaymentMethodConfig {
        $profileId = $this->configService->getWebsiteProfile()->getId();

        $config = $this->getPaymentMethodConfig($profileId, $mollieMethodId);

        if (!$config) {
            $config = $this->createNewConfig($profileId, $mollieMethodId, $currencyCode);
        }

        if ($transactionDescription !== null) {
            $config->setTransactionDescription($transactionDescription);
        }

        if ($customLogoUrl !== null) {
            $config->setImage($customLogoUrl);
        }

        $config->setCaptureOption($captureMode ?? self::CAPTURE_MODE_AUTOMATIC);

        $this->getRepository()->saveOrUpdate($config);

        return $config;
    }

    /**
     * Get payment method configuration
     *
     * @param string $profileId
     * @param string $mollieMethodId
     *
     * @return PaymentMethodConfig|null
     *
     * @throws RepositoryNotRegisteredException
     */
    public function getPaymentMethodConfig(string $profileId, string $mollieMethodId): ?PaymentMethodConfig
    {
        return $this->getRepository()->findByProfileAndMollieId($profileId, $mollieMethodId);
    }

    /**
     * Get PaymentMethodConfig repository
     *
     * @return PaymentMethodConfigRepository
     *
     * @throws RepositoryNotRegisteredException
     */
    private function getRepository(): PaymentMethodConfigRepository
    {
        /** @var PaymentMethodConfigRepository $repository */
        $repository = RepositoryRegistry::getRepository(PaymentMethodConfig::CLASS_NAME);

        return $repository;
    }

    /**
     * @param string $mollieMethod
     * @param string $defaultLogo
     *
     * @return string
     */
    public function getPaymentMethodLogo(string $mollieMethod, string $defaultLogo = ''): string
    {
        if (empty($mollieMethod)) {
            return $defaultLogo;
        }

        try {
            $profileId = $this->configService->getWebsiteProfile()?->getId();
            if (!$profileId) {
                throw new \Exception("Missing profileId");
            }
            $config = $this->getPaymentMethodConfig($profileId, $mollieMethod);

            if ($config && $config->getImage()) {
                return $config->getImage();
            }
        } catch (\Exception $e) {
            Logger::logWarning('Failed to get payment method logo', 'PaymentMethodConfig', [
                'method' => $mollieMethod,
                'error' => $e->getMessage()
            ]);

            return '';
        }

        return $defaultLogo;
    }

    /**
     * Create new payment method config with API data
     *
     * @param string $profileId
     * @param string $mollieMethodId
     * @param string $currencyCode
     *
     * @return PaymentMethodConfig
     */
    private function createNewConfig(string $profileId, string $mollieMethodId, string $currencyCode): PaymentMethodConfig
    {
        try {
            $amount = new Amount();
            $amount->setCurrency($currencyCode);
            $amount->setAmountValue(100);

            $enabledMethods = $this->corePaymentMethodService->getEnabledPaymentMethodConfigurations(
                $profileId,
                null,
                $amount,
                PaymentMethodConfig::API_METHOD_PAYMENT
            );

            foreach ($enabledMethods as $method) {
                if ($method->getMollieId() === $mollieMethodId) {
                    return $method;
                }
            }
        } catch (\Exception $e) {
            Logger::logError("There was an error creating the configuration",
                'Payment Method Configuration',
                [
                    'profileId' => $profileId,
                    'mollieMethodId' => $mollieMethodId,
                    'currencyCode' => $currencyCode,
                    'error' => $e->getMessage()
                ]
            );
        }

        $config = new PaymentMethodConfig();
        $config->setProfileId($profileId);

        return $config;
    }

    /**
     * @param string $mollieMethodId
     *
     * @return string
     */
    public function getCaptureMode(string $mollieMethodId): string
    {
        try {
            $profileId = $this->configService->getWebsiteProfile()?->getId();
            if (!$profileId) {
                throw new \Exception("Missing profileId");
            }

            $config = $this->getPaymentMethodConfig($profileId, $mollieMethodId);

            if ($config) {
                return $config->getCaptureOption() ?: self::CAPTURE_MODE_AUTOMATIC;
            }
        } catch (\Exception $e) {
            Logger::logWarning('Failed to get capture mode', 'PaymentMethodConfig', [
                'method' => $mollieMethodId,
                'error' => $e->getMessage()
            ]);
        }

        return self::CAPTURE_MODE_AUTOMATIC;
    }
}
