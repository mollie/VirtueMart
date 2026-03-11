<?php

namespace Mollie\Component\Mollie\Administrator\View\Configuration;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Version;
use Mollie\Infrastructure\Logger\Logger;
use Mollie\Infrastructure\ServiceRegister;
use Mollie\Payment\Configuration\ConfigurationService;
use Mollie\Payment\Adapter\VirtueMartOrderStatusService;
use Mollie\Payment\Configuration\Mapping\OrderStatusMappingDefaults;
use stdClass;
use vmVersion;

class HtmlView extends BaseHtmlView
{
    const MINIMUM_VM_VERSION = 4.0;
    const MINIMUM_JOOMLA_VERSION = 5.3;

    protected object $config;
    protected bool $isConnected;
    protected array $virtueMartStatuses;
    protected bool $isVirtueMartInstalled;
    protected bool $isJoomlaVersionSupported;

    /**
     * @param   string|null  $tpl
     *
     * @return  void
     */
    public function display($tpl = null): void
    {
        $this->isVirtueMartInstalled = $this->checkVirtueMartVersion();
        $this->isJoomlaVersionSupported = $this->checkJoomlaVersion();
        $this->loadConfiguration();
        $this->addToolbar();
        $this->loadAssets();

        parent::display($tpl);
    }

    /**
     * @return  void
     */
    protected function loadConfiguration(): void
    {
        $configService = ConfigurationService::getInstance();

        $this->config = new stdClass();
        $this->config->version = $configService->getExtensionVersion();
        $this->config->liveApiKey = $configService->getLiveApiKey() ?: '';
        $this->config->testApiKey = $configService->getTestApiKey() ?: '';
        $this->config->environment = $configService->isTestMode() ? 'test' : 'live';

        $profile = $configService->getWebsiteProfile();
        $this->config->profileId = $profile ? $profile->getId() : '';

        $activeKey = $this->config->environment === 'test'
            ? $this->config->testApiKey
            : $this->config->liveApiKey;

        $this->isConnected = !empty($activeKey);

        $savedMapping = $configService->getOrderStatusMapping();
        $defaultOrderStatusMapping = OrderStatusMappingDefaults::getDefaultMapping();

        $this->config->status_mapping = array_merge($defaultOrderStatusMapping, $savedMapping);

        $logLevelInt = $configService->getMinLogLevel();
        $this->config->minLogLevel = match ($logLevelInt) {
            Logger::ERROR => 'errors',
            Logger::DEBUG => 'everything',
            default => 'disabled',
        };

        if ($this->isVirtueMartInstalled) {
            /* @var VirtueMartOrderStatusService $vmService */
            $vmService = ServiceRegister::getService(VirtueMartOrderStatusService::CLASS);
            $this->virtueMartStatuses = $vmService->getStatusOptions();
        } else {
            $this->virtueMartStatuses = [];
        }
    }

    /**
     * Add toolbar buttons.
     *
     * @return  void
     */
    protected function addToolbar(): void
    {
        ToolbarHelper::title(Text::_('COM_MOLLIE_CONFIGURATION'), 'cog');
        ToolbarHelper::apply('configuration.save');
        ToolbarHelper::cancel('configuration.cancel');
    }

    /**
     * Load CSS and JavaScript assets.
     *
     * @return  void
     */
    protected function loadAssets(): void
    {
        $wa = Factory::getApplication()->getDocument()->getWebAssetManager();

        $wa->registerAndUseStyle('com_mollie.configuration', 'media/com_mollie/css/configuration.css');
    }

    /**
     * @return  bool
     */
    protected function checkVirtueMartVersion(): bool
    {
        if (!ComponentHelper::isEnabled('com_virtuemart')) {
            return false;
        }

        $versionFile = JPATH_ADMINISTRATOR . '/components/com_virtuemart/version.php';

        if (!file_exists($versionFile)) {
            return false;
        }

        include_once $versionFile;

        if (!class_exists('vmVersion')) {
            return false;
        }

        return version_compare(vmVersion::$RELEASE, self::MINIMUM_VM_VERSION, '>=');
    }

    /**
     * @return bool
     */
    protected function checkJoomlaVersion(): bool
    {
        $version = new Version();

        return version_compare($version->getShortVersion(), self::MINIMUM_JOOMLA_VERSION, '>=');
    }
}
