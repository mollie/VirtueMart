<?php

use Joomla\CMS\Factory;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Filesystem\File;
use Joomla\CMS\Filesystem\Folder;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Installer\InstallerScript;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;

defined('_JEXEC') or die;

class Com_MollieInstallerScript extends InstallerScript
{
    protected $minimumJoomla = '5.0';
    protected $minimumPhp = '8.1';

    /**
     * @param string $type
     * @param InstallerAdapter $parent
     *
     * @return bool
     */
    public function postflight(string $type, InstallerAdapter $parent): bool
    {
        if ($type === 'install' || $type === 'update') {
            $this->installMollieOrderStatus();
            $this->manageTemplateOverride(true);
        }

        return true;
    }

    /**
     * @param InstallerAdapter $parent
     *
     * @return bool
     */
    public function uninstall(InstallerAdapter $parent): bool
    {
        $this->removeMollieOrderStatus();
        $this->manageTemplateOverride(false);

        return true;
    }

    /**
     * Handles the installation or removal of the VirtueMart template override
     *
     * @param bool $install
     *
     * @return void
     */
    protected function manageTemplateOverride(bool $install): void
    {
        try {
            $db = Factory::getContainer()->get(DatabaseInterface::class);

            $query = $db->getQuery(true)
                ->select($db->quoteName('template'))
                ->from($db->quoteName('#__template_styles'))
                ->where($db->quoteName('client_id') . ' = 1')
                ->where($db->quoteName('home') . ' = 1');
            $db->setQuery($query);
            $adminTemplate = $db->loadResult();

            if (!$adminTemplate) return;

            $source = JPATH_ADMINISTRATOR . '/components/com_mollie/assets/overrides/edit_edit.php';
            $destFolder = JPATH_ADMINISTRATOR . '/templates/' . $adminTemplate . '/html/com_virtuemart/paymentmethod/';
            $destFile = $destFolder . 'edit_edit.php';

            if ($install) {
                if (File::exists($source)) {
                    if (!Folder::exists($destFolder)) {
                        Folder::create($destFolder);
                    }
                    File::copy($source, $destFile);
                }
            } else {
                if (File::exists($destFile)) {
                    File::delete($destFile);
                }
            }
        } catch (Exception $e) {
            Factory::getApplication()->enqueueMessage('Mollie Override Error: ' . $e->getMessage(), 'notice');
        }
    }

    /**
     * @return void
     */
    protected function installMollieOrderStatus(): void
    {
        try {
            $db = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
            $tables = $db->getTableList();
            $prefix = $db->getPrefix();
            $vmOrderStatesTable = $prefix . 'virtuemart_orderstates';

            if (!in_array($vmOrderStatesTable, $tables)) {
                Factory::getApplication()->enqueueMessage(Text::_('VirtueMart orderstates table not found.'),
                    'warning');

                return;
            }

            $query = $db->getQuery(true);
            $query->select('COUNT(*)')
                ->from($db->quoteName('#__virtuemart_orderstates'))
                ->where($db->quoteName('order_status_code') . ' = ' . $db->quote('AM'));
            $db->setQuery($query);

            if ((int) $db->loadResult() > 0) return;

            $query = $db->getQuery(true);
            $query->select('MAX(' . $db->quoteName('ordering') . ')')
                ->from($db->quoteName('#__virtuemart_orderstates'));
            $db->setQuery($query);
            $maxOrdering = (int) $db->loadResult();

            $query = $db->getQuery(true);
            $query->insert($db->quoteName('#__virtuemart_orderstates'))
                ->columns([
                    $db->quoteName('order_status_code'),
                    $db->quoteName('order_status_name'),
                    $db->quoteName('order_status_description'),
                    $db->quoteName('ordering'),
                    $db->quoteName('published')
                ])
                ->values(implode(',', [
                    $db->quote('AM'),
                    $db->quote('Authorized (Mollie)'),
                    $db->quote('Payment has been authorized by Mollie and is awaiting capture'),
                    (int) ($maxOrdering + 1),
                    1
                ]));

            $db->setQuery($query);
            $db->execute();

        } catch (Exception $e) {
            Factory::getApplication()->enqueueMessage('Error installing Mollie order status: ' . $e->getMessage(), 'error');
        }
    }

    /**
     * @return void
     */
    protected function removeMollieOrderStatus(): void
    {
        try {
            $db = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
            $query = $db->getQuery(true);
            $query->delete($db->quoteName('#__virtuemart_orderstates'))
                ->where($db->quoteName('order_status_code') . ' = ' . $db->quote('AM'));
            $db->setQuery($query);
            $db->execute();
        } catch (Exception $e) {
            Log::add($e->getMessage(), Log::ERROR, 'com_mollie');
        }
    }
}
