<?php

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

/** @var \Mollie\Component\Mollie\Administrator\View\Configuration\HtmlView $this */
?>

<form action="<?php echo Route::_('index.php?option=com_mollie&view=configuration'); ?>" method="post" name="adminForm" id="adminForm" class="form-horizontal">

    <div class="mollie-banner">
        <img src="<?php echo htmlspecialchars(Uri::root(true) . '/administrator/components/com_mollie/assets/images/mollie_banner.png'); ?>"
             alt="Mollie" />
    </div>

    <?php if (!$this->isJoomlaVersionSupported): ?>
        <div class="alert alert-warning">
            <span class="icon-warning" aria-hidden="true"></span>
            <span class="visually-hidden"><?php echo Text::_('WARNING'); ?></span>
            <?php echo Text::sprintf('COM_MOLLIE_JOOMLA_VERSION_REQUIRED', '5.3'); ?>
        </div>
    <?php elseif (!$this->isVirtueMartInstalled): ?>
        <div class="alert alert-warning">
            <span class="icon-warning" aria-hidden="true"></span>
            <span class="visually-hidden"><?php echo Text::_('WARNING'); ?></span>
            <?php echo Text::sprintf('COM_MOLLIE_VIRTUEMART_VERSION_REQUIRED', '4.0'); ?>
        </div>
    <?php else: ?>

        <div class="mollie-config-container">
            <!-- Version -->
            <div class="control-group">
                <div class="control-label">
                    <label><?php echo Text::_('COM_MOLLIE_VERSION'); ?></label>
                </div>
                <div class="controls">
                    <span class="mollie-version"><?php echo htmlspecialchars($this->config->version); ?></span>
                </div>
            </div>

            <!-- Retrieve Credentials Link -->
            <div class="control-group">
                <div class="control-label">
                    <!-- No label -->
                </div>
                <div class="controls">
                    <a href="https://my.mollie.com/dashboard/developers/api-keys" target="_blank" class="mollie-credentials-link">
                        <?php echo Text::_('COM_MOLLIE_RETRIEVE_CREDENTIALS_LINK'); ?>
                    </a>
                </div>
            </div>

            <!-- Environment Dropdown -->
            <div class="control-group">
                <div class="control-label">
                    <label for="mollie-environment"><?php echo Text::_('COM_MOLLIE_ENVIRONMENT'); ?></label>
                </div>
                <div class="controls">
                    <select id="mollie-environment" name="mollie_environment" class="form-select">
                        <option value="live" <?php echo $this->config->environment === 'live' ? 'selected' : ''; ?>>
                            <?php echo Text::_('COM_MOLLIE_LIVE'); ?>
                        </option>
                        <option value="test" <?php echo $this->config->environment === 'test' ? 'selected' : ''; ?>>
                            <?php echo Text::_('COM_MOLLIE_TEST'); ?>
                        </option>
                    </select>
                </div>
            </div>

            <!-- Live API Key -->
            <div class="control-group">
                <div class="control-label">
                    <label for="mollie-live-api-key"><?php echo Text::_('COM_MOLLIE_LIVE_API_KEY'); ?></label>
                </div>
                <div class="controls">
                    <input
                            type="password"
                            id="mollie-live-api-key"
                            name="mollie_live_api_key"
                            class="form-control"
                            value="<?php echo htmlspecialchars($this->config->liveApiKey); ?>"
                            placeholder="live_..."
                    />
                </div>
            </div>

            <!-- Test API Key -->
            <div class="control-group">
                <div class="control-label">
                    <label for="mollie-test-api-key"><?php echo Text::_('COM_MOLLIE_TEST_API_KEY'); ?></label>
                </div>
                <div class="controls">
                    <input
                            type="password"
                            id="mollie-test-api-key"
                            name="mollie_test_api_key"
                            class="form-control"
                            value="<?php echo htmlspecialchars($this->config->testApiKey); ?>"
                            placeholder="test_..."
                    />
                </div>
            </div>

            <!-- Profile ID -->
            <div class="control-group">
                <div class="control-label">
                    <label><?php echo Text::_('COM_MOLLIE_PROFILE_ID'); ?></label>
                </div>
                <div class="controls">
                    <?php if ($this->isConnected && !empty($this->config->profileId)): ?>
                        <span class="mollie-profile-id"><?php echo htmlspecialchars($this->config->profileId); ?></span>
                    <?php else: ?>
                        <span class="mollie-profile-placeholder"><?php echo Text::_('COM_MOLLIE_PROFILE_ID_PLACEHOLDER'); ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Status -->
            <div class="control-group">
                <div class="control-label">
                    <label><?php echo Text::_('COM_MOLLIE_STATUS'); ?></label>
                </div>
                <div class="controls">
                <span class="mollie-status mollie-status-<?php echo $this->isConnected ? 'connected' : 'disconnected'; ?>">
                    <?php echo $this->isConnected ? Text::_('COM_MOLLIE_CONNECTED') : Text::_('COM_MOLLIE_NOT_CONNECTED'); ?>
                </span>
                </div>
            </div>
        </div>

        <!-- Order Status Mapping Section (hidden until connected) -->
        <div class="mollie-config-section" id="order-mapping-section" style="<?php echo $this->isConnected ? '' : 'display:none;'; ?>">
            <h3 class="mollie-section-title"><?php echo Text::_('COM_MOLLIE_ORDER_STATUS_MAPPING_TITLE'); ?></h3>

            <?php
            $mollieStatuses = [
                'open'       => 'Open',
                'canceled'   => 'Canceled',
                'pending'    => 'Pending',
                'authorized' => 'Authorized',
                'expired'    => 'Expired',
                'failed'     => 'Failed',
                'paid'       => 'Paid',
                'refunded'   => 'Refunded'
            ];
            ?>

            <?php foreach ($mollieStatuses as $key => $label): ?>
                <div class="control-group">
                    <div class="control-label">
                        <label for="mapping-<?php echo $key; ?>"><?php echo $label; ?></label>
                    </div>
                    <div class="controls">
                        <select id="mapping-<?php echo $key; ?>" name="status_mapping[<?php echo $key; ?>]" class="form-select">
                            <option value=""><?php echo Text::_('COM_MOLLIE_NONE'); ?></option>
                            <?php foreach ($this->virtueMartStatuses as $vmStatus): ?>
                                <?php
                                $selected = ($this->config->status_mapping[$key] === $vmStatus->order_status_code) ? 'selected' : '';
                                ?>
                                <option value="<?php echo htmlspecialchars($vmStatus->order_status_code); ?>" <?php echo $selected; ?>>
                                    <?php echo htmlspecialchars($vmStatus->translated_name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Logging Section (hidden until connected) -->
        <div class="mollie-config-section" id="logging-section" style="<?php echo $this->isConnected ? '' : 'display:none;'; ?>">
            <h3 class="mollie-section-title"><?php echo Text::_('COM_MOLLIE_LOGGING_TITLE'); ?></h3>

            <div class="control-group">
                <div class="control-label">
                    <label for="log-level"><?php echo Text::_('COM_MOLLIE_LOG_LEVEL'); ?></label>
                </div>
                <div class="controls">
                    <select id="log-level" name="log_level" class="form-select">
                        <option value="disabled" <?php echo ($this->config->minLogLevel ?? 'disabled') === 'disabled' ? 'selected' : ''; ?>>
                            <?php echo Text::_('COM_MOLLIE_LOG_LEVEL_DISABLED'); ?>
                        </option>
                        <option value="errors" <?php echo ($this->config->minLogLevel ?? 'disabled') === 'errors' ? 'selected' : ''; ?>>
                            <?php echo Text::_('COM_MOLLIE_LOG_LEVEL_ERRORS'); ?>
                        </option>
                        <option value="everything" <?php echo ($this->config->minLogLevel ?? 'disabled') === 'everything' ? 'selected' : ''; ?>>
                            <?php echo Text::_('COM_MOLLIE_LOG_LEVEL_EVERYTHING'); ?>
                        </option>
                    </select>
                </div>
            </div>
        </div>

    <?php endif; ?>

    <input type="hidden" name="task" value="" />
    <?php echo HTMLHelper::_('form.token'); ?>
</form>
