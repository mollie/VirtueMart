<?php
defined('_JEXEC') or die();

use Joomla\CMS\Uri\Uri;

$cssUrl = Uri::root(true) . '/plugins/vmpayment/mollie/assets/css/order-payment-details.css';

/**
 * @var array $viewData Error data
 */
?>

<link rel="stylesheet" href="<?php echo $cssUrl; ?>" type="text/css" />

<div class="mollie-payment-details" data-order-id="<?php echo htmlspecialchars($viewData['order_id']); ?>">
    <h4><?php echo vmText::_('PLG_VMPAYMENT_MOLLIE_PAYMENT_DETAILS_HEADING'); ?></h4>

    <div class="mollie-message mollie-message-warning">
        <p>
            <strong><?php echo vmText::_('PLG_VMPAYMENT_MOLLIE_WARNING'); ?>:</strong>
            <?php echo vmText::_('PLG_VMPAYMENT_MOLLIE_PAYMENT_MISSING_ON_PLATFORM'); ?>
        </p>
    </div>

    <table>
        <tr>
            <td class="label"><?php echo vmText::_('PLG_VMPAYMENT_MOLLIE_PAYMENT_NAME'); ?>:</td>
            <td class="value">
                <div class="mollie-payment-method-name">
                    <?php if (!empty($viewData['payment_method_logo'])): ?>
                        <img src="<?php echo htmlspecialchars($viewData['payment_method_logo']); ?>"
                             alt="<?php echo htmlspecialchars($viewData['payment_name']); ?>"
                             class="mollie-payment-method-logo">
                    <?php endif; ?>
                    <span><?php echo htmlspecialchars($viewData['payment_name']); ?></span>
                </div>
            </td>
        </tr>
    </table>
</div>