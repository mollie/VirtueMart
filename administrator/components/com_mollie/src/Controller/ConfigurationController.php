<?php

namespace Mollie\Component\Mollie\Administrator\Controller;

defined('_JEXEC') or die;

use Exception;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\FormController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Mollie\Infrastructure\Logger\Logger;
use Mollie\Infrastructure\ServiceRegister;
use Mollie\Payment\Authorization\AuthorizationService;
use Mollie\Payment\Configuration\ConfigurationService;

class ConfigurationController extends FormController
{
    /**
     * @param   string|null  $key
     * @param   string|null  $urlVar
     *
     * @return  bool
     */
    public function save($key = null, $urlVar = null): bool
    {
        Session::checkToken() or jexit(Text::_('JINVALID_TOKEN'));

        $configService = ConfigurationService::getInstance();
        /* @var AuthorizationService $authService */
        $authService = ServiceRegister::getService(AuthorizationService::class);

        $app = Factory::getApplication();
        $input = $app->input;

        $liveApiKey = $input->post->get('mollie_live_api_key', '', 'string');
        $testApiKey = $input->post->get('mollie_test_api_key', '', 'string');
        $environment = $input->post->get('mollie_environment', 'test', 'string');

        try {
            $isConnected = (bool)$configService->getWebsiteProfile();
            $result = $authService->validateAndConnect($liveApiKey, $testApiKey, $environment === 'test');
            if (!$result['success']) {
                $app->enqueueMessage($result['message'], 'error');
                $this->setRedirect(Route::_('index.php?option=com_mollie&view=configuration', false));

                return false;
            }

            $orderStatusMapping = $input->post->get('status_mapping', [], 'array');
            $configService->setOrderStatusMapping($orderStatusMapping);

            $logLevel = $input->post->get('log_level', 'disabled', 'string');
            $configService->setMinLogLevel($logLevel);

            $app->enqueueMessage(Text::_(
                $isConnected ? 'COM_MOLLIE_CONFIGURATION_SAVED' : 'COM_MOLLIE_CONNECTED_MESSAGE'),
                'success');
        } catch (Exception $e) {
            $app->enqueueMessage(
                Text::sprintf('COM_MOLLIE_ERROR_SAVE_FAILED', $e->getMessage()),
                'error'
            );
            Logger::logError($e->getMessage(), 'Configuration Controller');
            $this->setRedirect(Route::_('index.php?option=com_mollie&view=configuration', false));

            return false;
        }

        $this->setRedirect(Route::_('index.php?option=com_mollie&view=configuration', false));

        return true;
    }

    /**
     * @param   string|null  $key
     *
     * @return  bool
     */
    public function cancel($key = null): bool
    {
        $this->setRedirect(Route::_('index.php?option=com_mollie&view=configuration', false));

        return true;
    }
}
