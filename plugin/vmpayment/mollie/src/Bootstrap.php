<?php

namespace Mollie\Payment;

use Mollie\BusinessLogic\Authorization\ApiKey\ApiKeyAuthService;
use Mollie\BusinessLogic\Authorization\Interfaces\AuthorizationService as CoreAuthorizationService;
use Mollie\BusinessLogic\Authorization\Interfaces\EncryptionService as EncryptionServiceInterface;
use Mollie\BusinessLogic\BootstrapComponent as BaseBootstrap;
use Mollie\BusinessLogic\Http\ApiKey\ProxyDataProvider;
use Mollie\BusinessLogic\OrderReference\Model\OrderReference;
use Mollie\BusinessLogic\OrderReference\OrderReferenceService;
use Mollie\BusinessLogic\PaymentMethod\Model\PaymentMethodConfig;
use Mollie\Infrastructure\Configuration\Configuration;
use Mollie\Infrastructure\Configuration\ConfigEntity;
use Mollie\Infrastructure\Http\CurlHttpClient;
use Mollie\Infrastructure\Http\HttpClient;
use Mollie\Infrastructure\Logger\Interfaces\ShopLoggerAdapter;
use Mollie\Infrastructure\ORM\Exceptions\RepositoryClassException;
use Mollie\Infrastructure\ORM\RepositoryRegistry;
use Mollie\Infrastructure\ServiceRegister;
use Mollie\Payment\Authorization\EncryptionService;
use Mollie\Payment\Interface\VirtueMartRepositoryInterface;
use Mollie\Payment\Interface\VirtueMartOrderStatusRepositoryInterface;
use Mollie\Payment\Adapter\JoomlaLoggerAdapter;
use Mollie\Payment\Repository\BaseRepository;
use Mollie\Payment\Repository\VirtueMartOrderStatusRepository;
use Mollie\Payment\Payment\PaymentResponseService;
use Mollie\Payment\Authorization\AuthorizationService;
use Mollie\Payment\Configuration\ConfigurationService;
use Mollie\Payment\Adapter\VirtueMartOrderStatusService;
use Mollie\Payment\Adapter\VirtueMartOrderService;
use Mollie\Payment\PaymentMethod\PaymentMethodService;
use Mollie\Payment\Configuration\PaymentMethodConfigService;
use Mollie\Payment\Payment\PaymentService;
use Mollie\Payment\PaymentMethod\VirtueMartPaymentMethodService;
use Mollie\BusinessLogic\PaymentMethod\PaymentMethodService as CorePaymentMethodService;
use Mollie\BusinessLogic\Payments\PaymentService as CorePaymentService;
use Mollie\Payment\Repository\VirtueMartRepository;
use Mollie\Payment\Repository\PaymentMethodConfigRepository;
use Mollie\Payment\Capture\CaptureService;
use Mollie\Payment\Refund\RefundService;
use Mollie\Payment\Cancel\CancelService;
use Mollie\Payment\Adapter\VirtueMartOrderTransitionService;
use Mollie\BusinessLogic\Refunds\RefundService as CoreRefundService;
use Mollie\BusinessLogic\Integration\Interfaces\OrderTransitionService;

class Bootstrap extends BaseBootstrap
{
    private static bool $isInitialized = false;

    /**
     * Initialize the Mollie integration
     *
     * @return void
     */
    public static function init(): void
    {
        if (self::$isInitialized) {
            return;
        }

        parent::init();
        self::$isInitialized = true;
    }

    /**
     * Initializes services and utilities
     *
     * @return void
     */
    protected static function initServices(): void
    {
        parent::initServices();

        ServiceRegister::registerService(
            VirtueMartOrderStatusRepositoryInterface::class,
            static function () {
                return new VirtueMartOrderStatusRepository();
            }
        );

        ServiceRegister::registerService(
            VirtueMartOrderStatusService::class,
            static function () {
                return new VirtueMartOrderStatusService(ServiceRegister::getService(VirtueMartOrderStatusRepositoryInterface::class));
            }
        );

        ServiceRegister::registerService(
            CoreAuthorizationService::CLASS_NAME,
            static function () {
                return ApiKeyAuthService::getInstance();
            }
        );

        ServiceRegister::registerService(
            Configuration::CLASS_NAME,
            static function () {
                return ConfigurationService::getInstance();
            }
        );

        ServiceRegister::registerService(
            AuthorizationService::CLASS,
            static function () {
                return new AuthorizationService(
                    ServiceRegister::getService(Configuration::class),
                    ServiceRegister::getService(CoreAuthorizationService::CLASS_NAME),
                );
            }
        );

        ServiceRegister::registerService(
            HttpClient::CLASS_NAME,
            static function () {
                return new CurlHttpClient();
            }
        );

        ServiceRegister::registerService(
            ProxyDataProvider::CLASS_NAME,
            static function () {
                return new ProxyDataProvider();
            }
        );

        ServiceRegister::registerService(
            ShopLoggerAdapter::CLASS_NAME,
            static function () {
                return new JoomlaLoggerAdapter();
            }
        );

        ServiceRegister::registerService(
            VirtueMartRepositoryInterface::class,
            static function () {
                return new VirtueMartRepository();
            }
        );

        ServiceRegister::registerService(
            VirtueMartOrderService::class,
            static function () {
                return new VirtueMartOrderService(
                    ServiceRegister::getService(VirtueMartRepositoryInterface::class)
                );
            }
        );

        ServiceRegister::registerService(
            CorePaymentMethodService::CLASS_NAME,
            static function () {
                return VirtueMartPaymentMethodService::getInstance();
            }
        );

        ServiceRegister::registerService(
            PaymentMethodService::class,
            static function () {
                return new PaymentMethodService(
                    ServiceRegister::getService(CorePaymentMethodService::CLASS_NAME),
                    ServiceRegister::getService(VirtueMartRepositoryInterface::class)
                );
            }
        );

        ServiceRegister::registerService(
            PaymentMethodConfigService::class,
            static function () {
                return new PaymentMethodConfigService(
                    ServiceRegister::getService(Configuration::CLASS_NAME),
                    ServiceRegister::getService(CorePaymentMethodService::CLASS_NAME)
                );
            }
        );

        ServiceRegister::registerService(
            PaymentService::class,
            static function () {
                return new PaymentService(
                    ServiceRegister::getService(CorePaymentService::CLASS_NAME),
                    ServiceRegister::getService(VirtueMartRepositoryInterface::class)
                );
            }
        );

        ServiceRegister::registerService(
            PaymentResponseService::class,
            static function () {
                return new PaymentResponseService(
                    ServiceRegister::getService(Configuration::CLASS_NAME),
                    ServiceRegister::getService(CorePaymentService::CLASS_NAME),
                    ServiceRegister::getService(VirtueMartRepositoryInterface::class),
                );
            }
        );

        ServiceRegister::registerService(
            CaptureService::class,
            static function () {
                return new CaptureService(
                    ServiceRegister::getService(CorePaymentService::CLASS_NAME),
                    ServiceRegister::getService(OrderReferenceService::CLASS_NAME)
                );
            }
        );

        ServiceRegister::registerService(
            RefundService::class,
            static function () {
                return new RefundService(
                    ServiceRegister::getService(CoreRefundService::CLASS_NAME),
                    ServiceRegister::getService(CorePaymentService::CLASS_NAME)
                );
            }
        );

        ServiceRegister::registerService(
            CancelService::class,
            static function () {
                return new CancelService(
                    ServiceRegister::getService(CorePaymentService::CLASS_NAME),
                    ServiceRegister::getService(OrderReferenceService::CLASS_NAME)
                );
            }
        );

        ServiceRegister::registerService(
            OrderTransitionService::CLASS_NAME,
            static function () {
                return new VirtueMartOrderTransitionService(
                    ServiceRegister::getService(Configuration::CLASS_NAME),
                );
            }
        );

        ServiceRegister::registerService(
            EncryptionServiceInterface::class,
            static function () {
                return new EncryptionService();
            }
        );
    }

    /**
     * Initializes entity repositories
     *
     * @return void
     *
     * @throws RepositoryClassException
     */
    protected static function initRepositories(): void
    {
        parent::initRepositories();

        RepositoryRegistry::registerRepository(
            ConfigEntity::getClassName(),
            BaseRepository::getClassName()
        );

        RepositoryRegistry::registerRepository(
            OrderReference::getClassName(),
            BaseRepository::getClassName()
        );

        RepositoryRegistry::registerRepository(
            PaymentMethodConfig::getClassName(),
            PaymentMethodConfigRepository::getClassName()
        );
    }
}
