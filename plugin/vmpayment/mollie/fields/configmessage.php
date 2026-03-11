<?php
/**
 * Custom field to display configuration message
 *
 * @package    Mollie
 * @subpackage Fields
 */

defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Form\FormField;
use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;

class JFormFieldConfigmessage extends FormField
{
    /**
     * @var string
     */
    protected $type = 'Configmessage';

    /**
     * @return string
     */
    protected function getInput()
    {
        $document = Factory::getApplication()->getDocument();
        $cssPath = Uri::root(true) . '/plugins/vmpayment/mollie/assets/css/configmessage.css';
        $document->addStyleSheet($cssPath);

        $configUrl = 'index.php?option=com_mollie&view=configuration';

        $link = '<a href="' . htmlspecialchars($configUrl) . '">'
              . vmText::_('PLG_VMPAYMENT_MOLLIE_CONFIG_LINK_TEXT')
              . '</a>';

        $message = sprintf(
            vmText::_('PLG_VMPAYMENT_MOLLIE_CONFIG_MESSAGE'),
            $link
        );

        return '<span class="mollie-config-message">' . $message . '</span>';
    }

    /**
     * @return string
     */
    protected function getLabel()
    {
        return '';
    }
}
