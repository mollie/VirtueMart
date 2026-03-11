<?php
defined('_JEXEC') or die();

/**
 * Mollie Payment Response Template
 *
 * @var array $viewData Payment response data
 */
?>
<div class="post_payment_payment_name" style="width: 100%; margin-bottom: 10px;">
	<span class="post_payment_payment_name_title"><?php echo vmText::_('VMPAYMENT_STANDARD_PAYMENT_INFO'); ?> </span>
	<?php echo $viewData['payment_name']; ?>
</div>

<div class="post_payment_order_number" style="width: 100%; margin-bottom: 10px;">
	<span class="post_payment_order_number_title"><?php echo vmText::_('COM_VIRTUEMART_ORDER_NUMBER'); ?> </span>
	<?php echo $viewData['order_number']; ?>
</div>

<div class="post_payment_order_total" style="width: 100%; margin-bottom: 15px;">
	<span class="post_payment_order_total_title"><?php echo vmText::_('COM_VIRTUEMART_ORDER_PRINT_TOTAL'); ?> </span>
	<?php echo $viewData['displayTotalInPaymentCurrency']; ?>
</div>
<?php
if($viewData['orderlink']){
?>
<a class="vm-button-correct btn btn-primary" href="<?php echo JRoute::_($viewData['orderlink'], false)?>"><?php echo vmText::_('COM_VIRTUEMART_ORDER_VIEW_ORDER'); ?></a>
<?php
}
?>