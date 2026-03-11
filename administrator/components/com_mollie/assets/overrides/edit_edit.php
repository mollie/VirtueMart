<?php

defined('_JEXEC') or die('Restricted access');

use Mollie\Payment\PaymentMethod\Controller\PaymentMethodEditController;
use Mollie\Payment\Configuration\Mapping\PaymentMethodDisplayName;
use Joomla\CMS\Factory;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri as JUri;

if ($this->payment->payment_element == 'mollie') {
    $controller = new PaymentMethodEditController();
    $mollieMethod = $this->payment->mollie_payment_method ?? '';
    $currencyId = $this->payment->currency_id ?? null;
    $response = $controller->prepareEditData($mollieMethod, $currencyId);
    $data = $response->data;

    $ajaxUrl = JUri::root(true) . '/administrator/index.php?option=com_virtuemart&view=plugin&vmtype=vmpayment&name=mollie&task=getMethodsByCurrency';
    $ajaxUrl .= '&' . Session::getFormToken() . '=1';

    $jsPath = '/administrator/components/com_mollie/assets/js/payment-method-edit.js';
    $jsUrl = JUri::root(true) . $jsPath;

    $logoUploadJsPath = '/administrator/components/com_mollie/assets/js/logo-upload.js';
    $logoUploadJsUrl = JUri::root(true) . $logoUploadJsPath;

    $doc = Factory::getDocument();
    $doc->addScript($jsUrl . '?v=' . filemtime(JPATH_ROOT . $jsPath));
    $doc->addScript($logoUploadJsUrl . '?v=' . filemtime(JPATH_ROOT . $logoUploadJsPath));
    $doc->addScriptDeclaration('window.mollieAjaxUrl = "' . $ajaxUrl . '";');
    $doc->addScriptDeclaration('window.mollieCaptureRestrictions = ' . json_encode($data['captureRestrictions'] ?? ['manualOnly' => [], 'automaticOnly' => []]) . ';');
}

?>

<style>
    .admintable td {
        padding: 5px;
    }
</style>

<div class="col50">
    <fieldset>
        <legend><?php echo vmText::_('COM_VIRTUEMART_PAYMENTMETHOD'); ?></legend>
        <table class="admintable">
            <?php echo VmHTML::row('input','COM_VIRTUEMART_PAYMENTMETHOD_FORM_NAME','payment_name',$this->payment->payment_name,'class="required"').$this->origLang; ?>
            <?php echo VmHTML::row('input','COM_VIRTUEMART_SLUG','slug',$this->payment->slug).$this->origLang; ?>
            <?php echo VmHTML::row('booleanlist','COM_VIRTUEMART_PUBLISHED','published',$this->payment->published); ?>
            <?php echo VmHTML::row('textarea','COM_VIRTUEMART_PAYMENT_FORM_DESCRIPTION','payment_desc',$this->payment->payment_desc).$this->origLang; ?>
            <?php echo VmHTML::row('raw','COM_VIRTUEMART_PAYMENT_CLASS_NAME', $this->vmPPaymentList );

            if ($this->payment->payment_element == 'mollie') :
                $hasMethods = count($data['methods'] ?? []) > 0;
                $hideStyle = $hasMethods ? '' : ' style="display: none;"';

                $mollieDropdown = '<select name="params[mollie_payment_method]" id="mollie_payment_method" class="inputbox">';
                if ($hasMethods) {
                    foreach ($data['methods'] as $method) {
                        $methodId = $method->getMollieId();
                        $methodName = PaymentMethodDisplayName::fromMethodId($methodId);
                        $selected = ($methodId === $data['selectedMethod']) ? ' selected="selected"' : '';
                        $mollieDropdown .= '<option value="' . htmlspecialchars($methodId) . '"' . $selected . '>' . htmlspecialchars($methodName) . '</option>';
                    }
                }
                $mollieDropdown .= '</select>';

                echo '<tr' . $hideStyle . '><td class="key"><label>' . vmText::_('PLG_VMPAYMENT_MOLLIE_PAYMENT_METHOD') . '</label></td><td>' . $mollieDropdown . '</td></tr>';

                $logoHtml = '<div style="display: flex; align-items: center; gap: 15px;">';
                $logoHtml .= '<div>';
                $logoHtml .= '<input type="file" name="payment_method_logo" id="payment_method_logo" accept="image/*" style="display: none;" />';
                $logoHtml .= '<label for="payment_method_logo" class="btn btn-primary" style="cursor: pointer; margin: 0;">Select a file</label>';
                $logoHtml .= '<span id="logo_filename" style="margin-left: 10px; font-style: italic; color: #666;"></span>';
                $logoHtml .= '</div>';

                $logoPreviewStyle = !empty($data['logoUrl']) ? '' : 'display: none;';
                $logoSrc = !empty($data['logoUrl']) ? htmlspecialchars($data['logoUrl']) : '';
                $logoHtml .= '<div id="mollie_logo_preview" style="' . $logoPreviewStyle . ' align-items: center; gap: 10px;">';
                $logoHtml .= '<img id="mollie_logo_img" src="' . $logoSrc . '" alt="Payment Method Logo" style="max-width: 150px; max-height: 50px; border: 1px solid #ddd; padding: 5px; background: #fff;" />';
                $logoHtml .= '</div>';
                $logoHtml .= '</div>';

                echo '<tr' . $hideStyle . '><td class="key"><label>' . vmText::_('PLG_VMPAYMENT_MOLLIE_PAYMENT_LOGO') . '</label></td><td>' . $logoHtml . '</td></tr>';

                // Mollie Components field (only for creditcard)
                $isCreditCard = ($data['selectedMethod'] ?? '') === 'creditcard';
                $componentsHideStyle = $isCreditCard ? '' : ' style="display: none;"';
                $componentsEnabled = $this->payment->mollie_components_enabled ?? 0;

                $componentsHtml = '<fieldset class="radio">';
                $componentsHtml .= '<input type="radio" name="params[mollie_components_enabled]" id="components_no" value="0"' . (!$componentsEnabled ? ' checked="checked"' : '') . ' />';
                $componentsHtml .= '<label for="components_no">' . vmText::_('JNO') . '</label>';
                $componentsHtml .= '<input type="radio" name="params[mollie_components_enabled]" id="components_yes" value="1"' . ($componentsEnabled ? ' checked="checked"' : '') . ' />';
                $componentsHtml .= '<label for="components_yes">' . vmText::_('JYES') . '</label>';
                $componentsHtml .= '</fieldset>';

                echo '<tr id="mollie_components_row"' . $componentsHideStyle . '><td class="key"><label>' . vmText::_('PLG_VMPAYMENT_MOLLIE_COMPONENTS') . '</label></td><td>' . $componentsHtml . '</td></tr>';

                $transactionDescValue = htmlspecialchars($data['transactionDescription'] ?? '{orderNumber}');
                echo '<tr' . $hideStyle . '><td class="key"><label>' . vmText::_('PLG_VMPAYMENT_MOLLIE_TRANSACTION_DESC') . '</label></td><td><textarea name="params[transaction_description]" class="inputbox" rows="3" cols="30">' . $transactionDescValue . '</textarea></td></tr>';

                $captureModeHtml = '<select name="capture_mode" id="capture_mode" class="inputbox">';
                $captureModeHtml .= '<option value="automatic"' . (($data['captureMode'] ?? 'automatic') === 'automatic' ? ' selected="selected"' : '') . '>' . vmText::_('PLG_VMPAYMENT_MOLLIE_CAPTURE_MODE_AUTOMATIC') . '</option>';
                $captureModeHtml .= '<option value="manual"' . (($data['captureMode'] ?? 'automatic') === 'manual' ? ' selected="selected"' : '') . '>' . vmText::_('PLG_VMPAYMENT_MOLLIE_CAPTURE_MODE_MANUAL') . '</option>';
                $captureModeHtml .= '</select>';

                echo '<tr id="capture_mode_row"' . $hideStyle . '><td class="key"><label for="capture_mode">' . vmText::_('PLG_VMPAYMENT_MOLLIE_CAPTURE_MODE') . '</label></td><td>' . $captureModeHtml . '</td></tr>';

            endif;

            if($this->checkConditionsCore){
                echo VmHTML::row('input', 'COM_VM_METHD_MIN_AMOUNT', 'min_amount', $this->payment->min_amount);
                echo VmHTML::row('input', 'COM_VM_METHD_MAX_AMOUNT', 'max_amount', $this->payment->max_amount);
            }

            echo VmHTML::row('raw', 'COM_VIRTUEMART_SHIPPING_FORM_SHOPPER_GROUP', $this->shopperGroupList);


            if($this->checkConditionsCore){

                $raw = '<select class="inputbox multiple" id="categories" name="categories[]" multiple="multiple" size="10">
                '.  ShopFunctions::categoryListTree($this->payment->categories) .'
            </select>';
                echo VmHTML::row('raw', 'COM_VM_CATEGORIES',$raw);

                $raw = '<select class="inputbox multiple" id="blocking_categories" name="blocking_categories[]" multiple="multiple" size="10">
                '.  ShopFunctions::categoryListTree($this->payment->blocking_categories) .'
            </select>';

                echo VmHTML::row('raw', 'COM_VM_CATEGORIES_BLOCKING',$raw);

                echo VmHtml::row('raw', 'COM_VM_COUNTRIES',ShopFunctionsF::renderCountryList($this->payment->countries,True, [], '', 0, 'countries', 'countries'));
                echo VmHtml::row('raw', 'COM_VM_COUNTRIES_BLOCKING',ShopFunctionsF::renderCountryList($this->payment->blocking_countries,True, [], '', 0, 'blocking_countries', 'blocking_countries'));
                echo VmHtml::row('raw', 'COM_VM_SHIPMENTS',$this->shipmentList);
                echo VmHtml::row('checkbox', 'COM_VM_ENABLE_BY_COUPON', 'byCoupon', $this->payment->byCoupon);
                echo VmHtml::row('input', 'COM_VM_ENABLE_BY_COUPON_BY_CODE', 'couponCode', $this->payment->couponCode);
                echo VmHtml::row('checkbox', 'COM_VM_PROGRESSIVE', 'progressive', $this->payment->progressive);
            }

            echo VmHTML::row('input','COM_VIRTUEMART_LIST_ORDER','ordering',$this->payment->ordering,'class="inputbox"','',4,4); ?>
            <?php echo VmHTML::row('raw', 'COM_VIRTUEMART_CURRENCY', $this->currencyList); ?>
            <?php
            if ($this->showVendors()) {
                echo VmHTML::row('raw', 'COM_VIRTUEMART_VENDOR', $this->vendorList);
            }
            if($this->showVendors ){
                echo VmHTML::row('checkbox','COM_VIRTUEMART_SHARED', 'shared', $this->payment->shared );
            }
            ?>
        </table>
    </fieldset>
</div>
