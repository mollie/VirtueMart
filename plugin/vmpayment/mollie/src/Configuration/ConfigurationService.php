<?php

namespace Mollie\Payment\Configuration;

use Joomla\CMS\Factory;
use Mollie\BusinessLogic\Authorization\Interfaces\EncryptionService;
use Mollie\BusinessLogic\Configuration;
use Mollie\Infrastructure\Logger\Logger;
use Mollie\Infrastructure\ServiceRegister;
use Mollie\Payment\Configuration\Mapping\OrderStatusMappingDefaults;

class ConfigurationService extends Configuration
{
    const CLASS_NAME = __CLASS__;
    const VERSION = '1.0.1';
    const VERSION_CHECK_URL = 'https://raw.githubusercontent.com/mollie/virtuemart/master/composer.json';
    const PLUGIN_DOWNLOAD_URL = 'https://github.com/mollie/virtuemart/releases';
    const MIN_LOG_LEVEL = Logger::INFO;
    const LOG_LEVEL_DISABLED = -1;

    protected static $instance;

    /**
     * @inheritDoc
     */
    public function getIntegrationName(): string
    {
        return 'VirtueMart';
    }

    /**
     * @inheritDoc
     */
    public function getCurrentSystemId(): string
    {
        $config = Factory::getConfig();
        $sitename = $config->get('sitename', '');
        $secret = $config->get('secret', '');

        return md5($sitename . $secret);
    }

    /**
     * @inheritDoc
     */
    public function getCurrentSystemName(): string
    {
        $config = Factory::getConfig();

        return $config->get('sitename', 'VirtueMart Site');
    }

    /**
     * @inheritDoc
     */
    public function getIntegrationVersion(): string
    {
        if (class_exists('VmConfig')) {
            return \VmConfig::getInstalledVersion();
        }

        if (defined('vmVersion')) {
            return (string) constant('vmVersion');
        }

        return '4.0.0';
    }

    /**
     * @inheritDoc
     */
    public function getExtensionName(): string
    {
        return 'MollieVirtueMart';
    }

    /**
     * @inheritDoc
     */
    public function getExtensionVersion(): string
    {
        return self::VERSION;
    }

    /**
     * @inheritDoc
     */
    public function getExtensionVersionCheckUrl(): string
    {
        return self::VERSION_CHECK_URL;
    }

    /**
     * @inheritDoc
     */
    public function getExtensionDownloadUrl($latestVersion = null): string
    {
        return $latestVersion ? self::PLUGIN_DOWNLOAD_URL . "/tag/v$latestVersion" : self::PLUGIN_DOWNLOAD_URL;
    }

    /**
     * @inheritDoc
     */
    protected function isSystemSpecific($name): bool
    {
        return false;
    }

    /**
     * @return string|null
     */
    public function getLiveApiKey(): ?string
    {
        $encryptedKey = $this->getConfigValue('liveApiKey');

        if (!$encryptedKey) {
            return null;
        }

        try {
            return $this->getEncryptionService()->decrypt($encryptedKey);
        } catch (\Exception $e) {
            Logger::logError('Failed to decrypt live API key: ' . $e->getMessage(), 'Configuration');

            return null;
        }
    }

    /**
     * @return string|null
     */
    public function getTestApiKey(): ?string
    {
        $encryptedKey = $this->getConfigValue('testApiKey');

        if (!$encryptedKey) {
            return null;
        }

        try {
            return $this->getEncryptionService()->decrypt($encryptedKey);
        } catch (\Exception $e) {
            Logger::logError('Failed to decrypt test API key: ' . $e->getMessage(), 'Configuration');

            return null;
        }
    }

    /**
     * @param string $apiKey
     *
     * @return void
     */
    public function setLiveApiKey(string $apiKey): void
    {
        try {
            $encrypted = $this->getEncryptionService()->encrypt($apiKey);
            $this->saveConfigValue('liveApiKey', $encrypted);
        } catch (\Exception $e) {
            Logger::logError('Failed to encrypt live API key: ' . $e->getMessage(), 'Configuration');
        }
    }

    /**
     * @param string $apiKey
     *
     * @return void
     */
    public function setTestApiKey(string $apiKey): void
    {
        try {
            $encrypted = $this->getEncryptionService()->encrypt($apiKey);
            $this->saveConfigValue('testApiKey', $encrypted);
        } catch (\Exception $e) {
            Logger::logError('Failed to encrypt test API key: ' . $e->getMessage(), 'Configuration');
        }
    }

    /**
     * Returns authorization token.
     *
     * @return string|null Authorization token if found; otherwise, NULL.
     */
    public function getAuthorizationToken(): ?string
    {
        $encryptedToken = $this->getConfigValue('authToken');

        if (!$encryptedToken) {
            return null;
        }

        try {
            return $this->getEncryptionService()->decrypt($encryptedToken);
        } catch (\Exception $e) {
            Logger::logError('Failed to decrypt authorization token: ' . $e->getMessage(), 'Configuration');

            return null;
        }
    }

    /**
     * Sets authorization token.
     *
     * @param string $authToken Authorization token.
     */
    public function setAuthorizationToken($authToken): void
    {
        try {
            $encrypted = $this->getEncryptionService()->encrypt($authToken);
            $this->saveConfigValue('authToken', $encrypted);
        } catch (\Exception $e) {
            Logger::logError('Failed to encrypt authorization token: ' . $e->getMessage(), 'Configuration');
        }
    }

    /**
     * @param array $orderStatusMapping
     *
     * @return void
     */
    public function setOrderStatusMapping(array $orderStatusMapping): void
    {
        $this->saveConfigValue('orderStatusMapping', $orderStatusMapping);
    }

    /**
     * @return array
     */
    public function getOrderStatusMapping(): array
    {
        return $this->getConfigValue('orderStatusMapping', OrderStatusMappingDefaults::getDefaultMapping());
    }

    /**
     * @return array
     */
    public function getCustomLogos(): array
    {
        return $this->getConfigValue('payment_logos', []);
    }

    /**
     * Get minimum log level
     *
     * @return int Logger level constant
     */
    public function getMinLogLevel(): int
    {
        return $this->getConfigValue('minLogLevel', self::LOG_LEVEL_DISABLED);
    }

    /**
     * Set minimum log level from UI
     *
     * @param string $logLevel One of: 'disabled', 'errors', 'everything'
     *
     * @return void
     */
    public function setMinLogLevel(string $logLevel): void
    {
        $logLevelInt = match ($logLevel) {
            'errors' => Logger::ERROR,
            'everything' => Logger::DEBUG,
            default => self::LOG_LEVEL_DISABLED,
        };

        $this->saveMinLogLevel($logLevelInt);
    }

    /**
     * @return EncryptionService
     */
    private function getEncryptionService(): EncryptionService
    {
        /** @var EncryptionService $encryptionService */
        $encryptionService = ServiceRegister::getService(EncryptionService::class);

        return $encryptionService;
    }
}
