<?php

namespace Mollie\Payment\Authorization;

defined('_JEXEC') or die;

use Exception;
use Joomla\CMS\Language\Text;
use Mollie\BusinessLogic\Authorization\ApiKey\ApiKey;
use Mollie\BusinessLogic\Authorization\AuthorizationService as CoreAuthorizationService;
use Mollie\Payment\Configuration\ConfigurationService;

class AuthorizationService
{
    /**
     * @param ConfigurationService $configService
     * @param CoreAuthorizationService $authService
     */
    public function __construct(
        private readonly ConfigurationService $configService,
        private readonly CoreAuthorizationService $authService
    ) {}

    /**
     * @param string|null $liveApiKey
     * @param string|null $testApiKey
     * @param bool $isTestMode
     *
     * @return array
     *
     * @throws Exception
     */
    public function validateAndConnect(?string $liveApiKey = null, ?string $testApiKey = null, bool $isTestMode = true): array
    {
        try {
            $liveApiKeyObject = $liveApiKey ? new ApiKey($liveApiKey) : null;
            $testApiKeyObject = $testApiKey ? new ApiKey($testApiKey) : null;
        } catch (Exception $e) {
            throw new Exception(Text::_('COM_MOLLIE_ERROR_WRONG_KEY_FORMAT'));
        }

        if (!$this->validateKeysFormat($liveApiKeyObject, $testApiKeyObject)) {
            throw new Exception(Text::_('COM_MOLLIE_ERROR_WRONG_KEY_FORMAT'));
        }

        $activeKey = $isTestMode ? $testApiKeyObject : $liveApiKeyObject;
        $otherKey  = $isTestMode ? $liveApiKeyObject : $testApiKeyObject;

        if (!$activeKey) {
            throw new Exception(Text::_('COM_MOLLIE_ERROR_MISSING_KEY'));
        }

        if ($otherKey && !$this->authService->validateToken($otherKey)) {
            throw new Exception(Text::_('COM_MOLLIE_ERROR_INVALID_KEY'));
        }

        try {
            $this->authService->connect($activeKey);
        } catch (Exception $e) {
            throw new Exception(Text::_('COM_MOLLIE_ERROR_INVALID_KEY'));
        }

        $this->configService->setLiveApiKey(trim($liveApiKey));
        $this->configService->setTestApiKey(trim($testApiKey));

        return [
            'success' => true,
            'profile' => $this->configService->getWebsiteProfile()
        ];
    }

    /**
     * @param ApiKey|null $liveKey
     * @param ApiKey|null $testKey
     *
     * @return bool
     */
    private function validateKeysFormat(?ApiKey $liveKey, ?ApiKey $testKey): bool
    {
        $liveValid = !$liveKey || !$liveKey->isTest();
        $testValid = !$testKey || $testKey->isTest();

        return $liveValid && $testValid;
    }

}
