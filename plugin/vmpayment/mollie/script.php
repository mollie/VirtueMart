<?php
/**
 * Installation script for Mollie VirtueMart Payment Plugin
 */

defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Factory;
use Joomla\CMS\Installer\InstallerScript;
use Joomla\Database\DatabaseInterface;

class plgVmPaymentMollieInstallerScript extends InstallerScript
{
    /**
     * @param string $type
     * @param object $parent
     *
     * @return bool
     */
    public function postflight($type, $parent)
    {
        if ($type === 'install' || $type === 'update' || $type === 'discover_install') {
            $this->fixVirtueMartPaymentMethodReferences();
        }

        return true;
    }

    /**
     * Fix orphaned VirtueMart payment methods after plugin reinstall
     * Updates payment_jplugin_id to current plugin extension_id
     *
     * @return void
     * @throws Exception
     */
    protected function fixVirtueMartPaymentMethodReferences(): void
    {
        try {
            $db = Factory::getContainer()->get(DatabaseInterface::class);

            $query = $db->createQuery()
                ->select('extension_id')
                ->from($db->quoteName('#__extensions'))
                ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
                ->where($db->quoteName('element') . ' = ' . $db->quote('mollie'))
                ->where($db->quoteName('folder') . ' = ' . $db->quote('vmpayment'));

            $db->setQuery($query);
            $currentPluginId = $db->loadResult();

            if (!$currentPluginId) {
                return;
            }

            $tables = $db->getTableList();
            $vmPaymentTable = $db->getPrefix() . 'virtuemart_paymentmethods';

            if (!in_array($vmPaymentTable, $tables)) {
                return;
            }

            $query = $db->createQuery()
                ->update($db->quoteName('#__virtuemart_paymentmethods'))
                ->set($db->quoteName('payment_jplugin_id') . ' = ' . (int)$currentPluginId)
                ->where($db->quoteName('payment_element') . ' = ' . $db->quote('mollie'));

            $db->setQuery($query);
            $db->execute();

            $updatedCount = $db->getAffectedRows();

            if ($updatedCount > 0) {
                Factory::getApplication()->enqueueMessage(
                    sprintf('Mollie: Updated %d payment method(s) to current plugin version.', $updatedCount),
                    'message'
                );
            }

        } catch (Exception $e) {
            Factory::getApplication()->enqueueMessage(
                'Mollie: Could not auto-update payment method references: ' . $e->getMessage(),
                'warning'
            );
        }
    }
}
