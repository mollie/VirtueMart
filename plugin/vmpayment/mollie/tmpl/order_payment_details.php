<?php
defined('_JEXEC') or die();

use Joomla\CMS\Uri\Uri;

$cssUrl = Uri::root(true) . '/plugins/vmpayment/mollie/assets/css/order-payment-details.css';
$jsUrl = Uri::root(true) . '/plugins/vmpayment/mollie/assets/js/order-payment-details.js';

vmJsApi::addJScript('mollie-order-payment-details', $jsUrl);

 /**
 * @var array $viewData Payment response data
 */
?>

<link rel="stylesheet" href="<?php echo $cssUrl; ?>" type="text/css" />

<div class="mollie-payment-details" data-order-id="<?php echo htmlspecialchars($viewData['order_id']); ?>">
    <h4><?php echo vmText::_('PLG_VMPAYMENT_MOLLIE_PAYMENT_DETAILS_HEADING'); ?></h4>

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

        <tr>
            <td class="label"><?php echo vmText::_('PLG_VMPAYMENT_MOLLIE_AMOUNT_PAID'); ?>:</td>
            <td class="value">
                <?php echo htmlspecialchars($viewData['amount_paid'] . ' ' . $viewData['currency']); ?>
            </td>
        </tr>

        <tr>
            <td class="label"><?php echo vmText::_('PLG_VMPAYMENT_MOLLIE_AMOUNT_REFUNDED'); ?>:</td>
            <td class="value">
                <?php echo htmlspecialchars($viewData['amount_refunded']  . ' ' . $viewData['currency']); ?>
            </td>
        </tr>

        <tr>
            <td class="label"><?php echo vmText::_('PLG_VMPAYMENT_MOLLIE_PAYMENT_STATUS'); ?>:</td>
            <td class="value">
                <?php echo htmlspecialchars(ucfirst(
                    $viewData['amount_paid'] !== 0 && $viewData['amount_paid'] === $viewData['amount_refunded']
                        ? 'Refunded'
                        : $viewData['payment_status']));
                ?>
            </td>
        </tr>
    </table>

    <div id="mollie-capture-amount-row" style="display: none; padding: 15px 0; border-top: 1px solid #ddd; margin-top: 15px;">
        <div style="display: flex; align-items: center; justify-content: space-between;">
            <div style="display: flex; align-items: center; gap: 10px;">
                <label for="mollie-capture-amount" style="font-weight: normal; margin: 0;">
                    <?php echo vmText::_('PLG_VMPAYMENT_MOLLIE_CAPTURE_AMOUNT_LABEL'); ?>:
                </label>
                <input type="number"
                       id="mollie-capture-amount"
                       class="inputbox"
                       style="width: 120px;"
                       step="0.01"
                       min="0.01"
                       value="<?php echo htmlspecialchars($viewData['amount_paid']); ?>"
                       data-max-amount="<?php echo htmlspecialchars($viewData['amount_paid']); ?>" />
                <span id="mollie-capture-currency"><?php echo htmlspecialchars($viewData['currency'] ?? 'EUR'); ?></span>
            </div>
            <a href="#"
               class="vm-button-correct btn btn-primary"
               id="mollie-capture-confirm-btn"
               data-processing-text="<?php echo htmlspecialchars(vmText::_('PLG_VMPAYMENT_MOLLIE_CAPTURE_PROCESSING')); ?>"
               onclick="return false;">
                <?php echo vmText::_('PLG_VMPAYMENT_MOLLIE_CAPTURE_PAYMENT'); ?>
            </a>
        </div>
    </div>

    <div id="mollie-refund-amount-row" style="display: none; padding: 15px 0; border-top: 1px solid #ddd; margin-top: 15px;">
        <div style="display: flex; align-items: center; justify-content: space-between;">
            <div style="display: flex; align-items: center; gap: 10px;">
                <label for="mollie-refund-amount" style="font-weight: normal; margin: 0;">
                    <?php echo vmText::_('PLG_VMPAYMENT_MOLLIE_REFUND_AMOUNT_LABEL'); ?>:
                </label>
                <?php
                $maxRefundAmount = $viewData['amount_paid'] - $viewData['amount_refunded'];
                ?>
                <input type="number"
                       id="mollie-refund-amount"
                       class="inputbox"
                       style="width: 120px;"
                       step="0.01"
                       min="0.01"
                       value="<?php echo htmlspecialchars($maxRefundAmount); ?>"
                       data-max-amount="<?php echo htmlspecialchars($maxRefundAmount); ?>" />
                <span id="mollie-refund-currency"><?php echo htmlspecialchars($viewData['currency'] ?? 'EUR'); ?></span>
            </div>
            <a href="#"
               class="vm-button-correct btn btn-primary"
               id="mollie-refund-confirm-btn"
               data-processing-text="<?php echo htmlspecialchars(vmText::_('PLG_VMPAYMENT_MOLLIE_REFUND_PROCESSING')); ?>"
               onclick="return false;">
                <?php echo vmText::_('PLG_VMPAYMENT_MOLLIE_REFUND_PAYMENT'); ?>
            </a>
        </div>
    </div>

    <div class="mollie-payment-actions">
        <?php if ($viewData['show_capture_button']): ?>
            <a href="#" class="vm-button-correct btn btn-primary" id="mollie-capture-btn" onclick="return false;">
                <?php echo vmText::_('PLG_VMPAYMENT_MOLLIE_CAPTURE_PAYMENT'); ?>
            </a>
        <?php endif; ?>

        <?php if ($viewData['show_refund_button']): ?>
            <a href="#" class="vm-button-correct btn btn-primary" id="mollie-refund-btn" onclick="return false;">
                <?php echo vmText::_('PLG_VMPAYMENT_MOLLIE_REFUND_PAYMENT'); ?>
            </a>
        <?php endif; ?>

        <?php if ($viewData['show_view_in_mollie_button'] && !empty($viewData['mollie_transaction_id'])): ?>
            <a href="https://www.mollie.com/dashboard/payments/<?php echo htmlspecialchars($viewData['mollie_transaction_id']); ?>"
               target="_blank"
               class="vm-button-correct btn btn-primary">
                <?php echo vmText::_('PLG_VMPAYMENT_MOLLIE_VIEW_IN_DASHBOARD'); ?>
            </a>
        <?php endif; ?>
    </div>
</div>
