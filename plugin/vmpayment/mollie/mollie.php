<?php

use Joomla\CMS\Language\Text;
use Mollie\Infrastructure\Configuration\Configuration;
use Mollie\Infrastructure\Logger\Logger;
use Mollie\Infrastructure\ServiceRegister;
use Mollie\Payment\Bootstrap;
use Mollie\Payment\Configuration\Mapping\PaymentMethodDisplayName;
use Mollie\Payment\Configuration\ConfigurationService;
use Mollie\Payment\Payment\Mapping\MollieStatusMapping;
use Mollie\Payment\PaymentMethod\Controller\PaymentMethodEditController;
use Mollie\Payment\PaymentMethod\Controller\PaymentMethodAjaxController;
use Mollie\Payment\Payment\Controller\PaymentResponseController;
use Mollie\Payment\Configuration\PaymentMethodConfigService;
use Mollie\Payment\Payment\Controller\PaymentController;
use Mollie\Payment\Capture\Controller\CaptureController;
use Mollie\Payment\Refund\Controller\RefundController;
use Mollie\Payment\Cancel\Controller\CancelController;
use Mollie\Payment\WebHook\Controller\WebHookController;
use Mollie\Payment\Infrastructure\DTO\Response;
use Mollie\Payment\Adapter\VirtueMartOrderService;
use Joomla\CMS\Factory;
use Joomla\Input\Input;
use Joomla\CMS\Response\JsonResponse;
use Joomla\CMS\Uri\Uri;

defined('_JEXEC') or die('Restricted access');

require_once __DIR__ . '/include_mollie.php';

Bootstrap::init();

if (!class_exists('vmPSPlugin')) {
    require(JPATH_VM_PLUGINS . DIRECTORY_SEPARATOR . 'vmpsplugin.php');
}

class plgVmPaymentMollie extends vmPSPlugin
{
    private const FINANCIAL_FIELDS = [
        'product_quantity',
        'product_final_price',
        'product_item_price',
        'product_subtotal_with_tax',
        'product_subtotal_without_tax',
        'product_tax',
        'product_basePriceWithTax',
        'product_discountedPriceWithoutTax',
        'product_priceWithoutTax',
        'product_subtotal_discount',
    ];

    private bool $suppressStatusChangeWarning = false;

    public function __construct(&$subject, $config)
    {
        parent::__construct($subject, $config);

        $this->_loggable = true;
        $this->tableFields = array_keys($this->getTableSQLFields());
        $this->_tablepkey = 'id';
        $this->_tableId = 'id';

        $varsToPush = array(
            'mollie_payment_method' => array('', 'char'),
            'transaction_description' => array('{orderNumber}', 'char'),
            'mollie_components_enabled' => array('0', 'int'),
        );

        $this->addVarsToPushCore($varsToPush, 1);
        $this->setConfigParameterable($this->_configTableFieldName, $varsToPush);
    }

    /**
     * @param $element
     * @param $id
     * @param $table
     *
     * @return bool
     */
    public function plgVmSetOnTablePluginParamsPayment($element, $id, &$table)
    {
        if ($element !== 'mollie') {
            return false;
        }

        $this->savePaymentMethodConfig();

        return $this->setOnTablePluginParams('mollie', $id, $table);
    }

    /**
     * @return mixed
     */
    public function getVmPluginCreateTableSQL()
    {
        return $this->createTableSQL('Mollie Payments Table');
    }

    /**
     * @return string[]
     */
    public function getTableSQLFields()
    {
        return array(
            'id' => 'int(11) UNSIGNED NOT NULL AUTO_INCREMENT',
            'virtuemart_order_id' => 'int(11) UNSIGNED',
            'virtuemart_paymentmethod_id' => 'mediumint(11) UNSIGNED',
            'payment_name' => 'varchar(255)',
            'payment_order_total' => 'decimal(15,5) NOT NULL DEFAULT 0.00000',
            'payment_currency' => 'char(3)',
            'mollie_transaction_id' => 'char(64)',
            'mollie_gateway' => 'char(64)'
        );
    }

    /**
     * @param $data
     *
     * @return mixed
     */
    public function plgVmDeclarePluginParamsPaymentVM3(&$data) {
        return $this->declarePluginParams('payment', $data);
    }

    /**
     * @param $type
     * @param $name
     * @param $render
     *
     * @return void
     */
    public function plgVmOnSelfCallBE($type, $name, &$render)
    {
        if ($name !== 'mollie') {
            return;
        }

        $app = Factory::getApplication();
        $input = $app->input;

        $task = $input->getCmd('task');

        $method = match ($task) {
            'getMethodsByCurrency' => fn() => $this->ajaxGetMethodsByCurrency($input),
            'getCountryCode'       => fn() => $this->ajaxGetCountryCode($input),
            'getMethodsByCountry'  => fn() => $this->ajaxGetMethodsByCountry($input),
            'capturePayment'       => fn() => $this->ajaxCapturePayment(),
            'refundPayment'        => fn() => $this->ajaxRefundPayment(),
            default                => fn() => null,
        };

        $method();
    }

    /**
     * @return void
     */
    public function plgVmOnPaymentNotification(): void
    {
        $this->suppressStatusChangeWarning = true;

        $input = Factory::getApplication()->input;

        $webhookData = $input->post->getArray();

        $controller = new WebHookController();
        $response = $controller->handle($webhookData);

        if ($response->isSuccess()) {
            http_response_code(200);
        } else {
            http_response_code(503);
        }

        exit;
    }

    /**
     * Display Mollie payment method in checkout
     *
     * @param VirtueMartCart $cart
     * @param $selected
     * @param $htmlIn
     *
     * @return bool
     */
    public function plgVmDisplayListFEPayment(VirtueMartCart $cart, $selected, &$htmlIn)
    {
        $input = Factory::getApplication()->input;
        $wa = Factory::getApplication()->getDocument()->getWebAssetManager();

        $postSelected = $input->getInt('virtuemart_paymentmethod_id', 0);

        if ($postSelected > 0) {
            $selected = $postSelected;
        }

        $countryId = 0;
        if (!empty($cart->BT['virtuemart_country_id'])) {
            $countryId = (int)$cart->BT['virtuemart_country_id'];
        }

        $mollieConfig = $this->getMollieConfiguration();

        $currencyId = $cart->pricesCurrency;
        $orderTotal = round($cart->cartPrices['billTotal'], 2) ?? 0;

        $wa->registerAndUseScript(
            'mollie.sdk',
            'https://js.mollie.com/v1/mollie.js',
            [],
            ['defer' => true]
        );

        $wa->addInlineScript(
            'window.mollieCountryId = ' . $countryId . ';' . "\n" .
            'window.mollieConfig = ' . json_encode($mollieConfig) . ';' . "\n" .
            'window.mollieCartData = ' . json_encode([
                'currencyId' => $currencyId,
                'orderTotal' => $orderTotal,
            ]) . ';',
            [],
            ['name' => 'mollie-country-data']
        );

        vmJsApi::addJScript('mollie-checkout', Uri::root() . 'plugins/vmpayment/mollie/assets/js/mollie-checkout.js');

        $wa->registerAndUseStyle(
            'mollie.components',
            Uri::root() . 'plugins/vmpayment/mollie/assets/css/mollie-components.css'
        );

        return $this->displayListFE($cart, $selected, $htmlIn);
    }

    /**
     * Render payment method name with logo
     *
     * @param $method
     *
     * @return string
     */
    protected function renderPluginName($method)
    {
        $mollieMethodId = $method->mollie_payment_method ?? '';
        $logoUrl = '';

        if (!empty($mollieMethodId)) {
            $controller = new PaymentMethodEditController();
            $logoUrl = $controller->getLogoUrl($mollieMethodId);
        }

        $logoHtml = '';
        if (!empty($logoUrl)) {
            $logoHtml = '<img src="' . htmlspecialchars($logoUrl) . '" style="height:25px; margin-left:4px; margin-right:6px;" alt="' . htmlspecialchars($method->payment_name) . '">';
        }

        $componentsEnabled = ($mollieMethodId === 'creditcard' && !empty($method->mollie_components_enabled)) ? '1' : '0';

        $nameHtml = $logoHtml . '<span class="vmpayment" data-mollie-method="' . htmlspecialchars($mollieMethodId) . '" data-components-enabled="' . $componentsEnabled . '">'
            . htmlspecialchars($method->payment_name)
            . '</span>';

        if ($mollieMethodId === 'creditcard' && $componentsEnabled === '1') {
            $nameHtml .= $this->renderCreditCardFields();
        }

        return $nameHtml;
    }

    /**
     * Validate and correct payment method during checkout
     * This hook runs BEFORE order creation
     * Fixes the issue where all Mollie methods share payment_element='mollie'
     *
     * @param VirtueMartCart $cart
     *
     * @return bool|null
     */
    public function plgVmOnCheckoutCheckDataPayment(VirtueMartCart $cart)
    {
        $input = Factory::getApplication()->input;
        $selectedId = $input->getInt('virtuemart_paymentmethod_id', 0);

        if (!$selectedId) {
            return null;
        }

        if (!($method = $this->getVmPluginMethod($selectedId))) {
            return null;
        }

        if (!$this->selectedThisElement($method->payment_element)) {
            return null;
        }

        $cart->virtuemart_paymentmethod_id = $selectedId;

        return true;
    }

    /**
     * Handle order confirmation and create Mollie payment
     * Called by VirtueMart when customer confirms their order
     *
     * @param VirtueMartCart $cart
     * @param array $order
     *
     * @return bool|null True on success, null on error, false to skip
     */
    public function plgVmConfirmedOrder($cart, $order)
    {
        if (!isset($order['details']['BT'])) {
            return null;
        }

        $orderDetails = $order['details']['BT'];

        if (!($method = $this->getVmPluginMethod($orderDetails->virtuemart_paymentmethod_id))) {
            return null;
        }

        if (!$this->selectedThisElement($method->payment_element)) {
            return false;
        }

        $controller = new PaymentController();

        $response = $controller->createPayment($cart, $order, $method);

        if (!$response->isSuccess()) {
            $orderId = $order['details']['BT']->virtuemart_order_id;
            $orderModel = VmModel::getModel('orders');
            $orderModel->updateStatusForOneOrder($orderId, ['order_status' => 'X'], true);

            Factory::getApplication()->enqueueMessage(
                Text::_('PLG_VMPAYMENT_MOLLIE_PAYMENT_FAILED'),
                'error'
            );

            Factory::getApplication()->redirect(\JRoute::_('index.php?option=com_virtuemart&view=cart', false));

            return null;
        }

        Factory::getApplication()->redirect($response->data['checkoutUrl']);

        return true;
    }

    /**
     * @param $html
     *
     * @return void
     *
     * @throws Exception
     */
    public function plgVmOnPaymentResponseReceived(&$html)
    {
        $this->suppressStatusChangeWarning = true;

        $orderId = vRequest::getString('order_id', '');

        $controller = new PaymentResponseController();
        $response = $controller->handleReturn($orderId);

        match($response->type) {
            'success' => $this->handleSuccessResponse($response, $html),
            'failed' => $this->handleFailedResponse($response),
            'error' => $this->handleErrorResponse($response),
            default => $this->handleUnknownResponse(),
        };
    }

    /**
     * @param int $virtuemart_order_id
     * @param int $virtuemart_paymentmethod_id
     * @param array $orderDetails
     *
     * @return string|null
     */
    public function plgVmOnShowOrderBEPayment($virtuemart_order_id, $virtuemart_paymentmethod_id, $orderDetails = null)
    {
        if (!($method = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) {
            return null;
        }

        if (!$this->selectedThisElement($method->payment_element)) {
            return null;
        }

        $mollieMethodId = $method->mollie_payment_method ?? '';

        $logoUrl = '';
        if (!empty($mollieMethodId)) {
            $paymentMethodController = new PaymentMethodEditController();
            $logoUrl = $paymentMethodController->getLogoUrl($mollieMethodId);
        }

        $paymentMethodName = PaymentMethodDisplayName::fromMethodId($mollieMethodId);

        $paymentController = new PaymentController();
        $response = $paymentController->getPayment($virtuemart_order_id);

        if (!$response->isSuccess()) {
            Factory::getApplication()->enqueueMessage($response->error, 'warning');

            $viewData = [
                'payment_name' => $method->payment_name,
                'payment_method_name' => $paymentMethodName,
                'payment_method_logo' => $logoUrl,
                'order_id' => $virtuemart_order_id,
                'method' => $method,
            ];

            return $this->renderByLayout('order_payment_missing', $viewData);
        }

        $payment = $response->data['payment'];
        $isPaymentRefundable = $payment->getStatus() === 'paid'
            && $payment->getAmountRefunded()->getAmountValue() < $payment->getAmount()->getAmountValue();

        $viewData = [
            'payment_name' => $method->payment_name,
            'payment_method_name' => $paymentMethodName,
            'payment_method_logo' => $logoUrl,
            'mollie_transaction_id' => $payment->getId(),
            'payment_status' => $payment->getStatus(),
            'amount_paid' => $payment->getAmount()->getAmountValue(),
            'amount_refunded' => $payment->getAmountRefunded()->getAmountValue(),
            'currency' => $payment->getAmount()->getCurrency(),
            'order_id' => $virtuemart_order_id,
            'method' => $method,
            'show_capture_button' => $payment->getStatus() === 'authorized',
            'show_refund_button' => $isPaymentRefundable,
            'show_view_in_mollie_button' => true,
        ];

        return $this->renderByLayout('order_payment_details', $viewData);
    }

    /**
     * Handle order status update - cancel payment if order is cancelled
     *
     * @param object $data
     *
     * @return bool|null - false to prevent status change, true/null to allow
     */
    public function plgVmOnUpdateOrderPayment(object $data, string $old_order_status = null): ?bool
    {
        if (!isset($data->virtuemart_order_id) || !isset($data->order_status)) {
            return null;
        }

        if (!($method = $this->getVmPluginMethod($data->virtuemart_paymentmethod_id))) {
            return null;
        }

        if (!$this->selectedThisElement($method->payment_element)) {
            return null;
        }

        $app = Factory::getApplication();
        $newItemData = $app->input->get('item_id', [], 'array');

        if (!empty($newItemData)) {
            $canUpdate = $this->validateOrderLineChange($data, $newItemData);

            if (!$canUpdate) {
                return false;
            }
        }

        if (!$this->suppressStatusChangeWarning && $this->isOrderCancelled($data, $old_order_status)) {
            $response = $this->handleOrderCancel($data);

            if (!$response->isSuccess()) {
                $app->enqueueMessage($response->error, 'error');

                return false;
            }

            /** @var ConfigurationService $configurationService */
            $configurationService = ServiceRegister::getService(Configuration::CLASS_NAME);
            $orderStatusMapping = $configurationService->getOrderStatusMapping();
            $mappedStatus = $orderStatusMapping[MollieStatusMapping::MOLLIE_CANCELED] ?? null;

            if ($mappedStatus) {
                $data->order_status = $mappedStatus;
            }
        } elseif (!$this->suppressStatusChangeWarning
            && $old_order_status !== null
            && $old_order_status !== $data->order_status) {
            $app->enqueueMessage(
                Text::_('PLG_VMPAYMENT_MOLLIE_ORDER_STATUS_CHANGE_NOT_SYNCED'),
                'warning'
            );
        }

        return null;
    }

    /**
     * Prevents payment and shipping method changes for Mollie orders
     *
     * @param array $cart
     * @param array $order
     *
     * @return bool|null
     */
    public function plgVmUpdateOrderHead($cart, $order): ?bool
    {
        if (!isset($order['details']['BT']->virtuemart_paymentmethod_id)) {
            return null;
        }

        if (!($method = $this->getVmPluginMethod($order['details']['BT']->virtuemart_paymentmethod_id))) {
            return null;
        }

        if (!$this->selectedThisElement($method->payment_element)) {
            return null;
        }

        $app = Factory::getApplication();

        if (isset($cart->virtuemart_paymentmethod_id) &&
            $cart->virtuemart_paymentmethod_id != $order['details']['BT']->virtuemart_paymentmethod_id) {

            $app->enqueueMessage(
                Text::_('PLG_VMPAYMENT_MOLLIE_PAYMENT_METHOD_CHANGE_NOT_SUPPORTED'),
                'error'
            );

            return false;
        }

        if (isset($cart->virtuemart_shipmentmethod_id) &&
            isset($order['details']['BT']->virtuemart_shipmentmethod_id) &&
            $cart->virtuemart_shipmentmethod_id != $order['details']['BT']->virtuemart_shipmentmethod_id) {

            $app->enqueueMessage(
                Text::_('PLG_VMPAYMENT_MOLLIE_SHIPPING_METHOD_CHANGE_NOT_SUPPORTED'),
                'error'
            );

            return false;
        }

        return null;
    }

    /**
     * Render credit card component fields
     *
     * @return string
     */
    private function renderCreditCardFields(): string
    {
        $templatePath = __DIR__ . '/tmpl/creditcard_fields.php';

        if (!file_exists($templatePath)) {
            return '';
        }

        ob_start();
        include $templatePath;
        return ob_get_clean();
    }

    /**
     * @param Response $response
     * @param $html
     *
     * @return void
     */
    private function handleSuccessResponse(Response $response, &$html): void
    {
        $cart = VirtueMartCart::getCart();
        $cart->emptyCart();

        $html = $this->renderPaymentResponse($response);
    }

    /**
     * @param Response $response
     *
     * @return void
     *
     * @throws Exception
     */
    private function handleFailedResponse(Response $response): void
    {
        $app = Factory::getApplication();
        $app->enqueueMessage(Text::_('PLG_VMPAYMENT_MOLLIE_PAYMENT_FAILED'), 'error');
        $app->redirect(\JRoute::_('index.php?option=com_virtuemart&view=cart', false));
    }

    /**
     * @param Response $response
     *
     * @return void
     *
     * @throws Exception
     */
    private function handleErrorResponse(Response $response): void
    {
        $app = Factory::getApplication();
        $message = $response->error ?? Text::_('PLG_VMPAYMENT_MOLLIE_PAYMENT_NOT_FOUND');
        $app->enqueueMessage($message, 'error');
        $app->redirect(\JRoute::_('index.php?option=com_virtuemart&view=cart', false));
    }

    /**
     * @return void
     *
     * @throws Exception
     */
    private function handleUnknownResponse(): void
    {
        $app = Factory::getApplication();
        $app->enqueueMessage(Text::_('PLG_VMPAYMENT_MOLLIE_PAYMENT_NOT_FOUND'), 'error');
        $app->redirect(\JRoute::_('index.php?option=com_virtuemart&view=cart', false));
    }

    /**
     * @param Response $response
     *
     * @return string
     */
    private function renderPaymentResponse(Response $response): string
    {
        // Not loaded elsewhere when order status is not updated
        vmLanguage::loadJLang('com_virtuemart_orders', true);

        $modelOrder = VmModel::getModel('orders');
        $order = $modelOrder->getOrder($response->data['orderId']);

        if (!$order) {
            return '';
        }

        // Get payment method details
        $method = $this->getVmPluginMethod($order['details']['BT']->virtuemart_paymentmethod_id);

        if (!$method) {
            return '';
        }

        $orderlink = \JRoute::_('index.php?option=com_virtuemart&view=orders&layout=details&order_number=' .
            $response->data['orderNumber'] . '&order_pass=' . $order['details']['BT']->order_pass, false);

        $totalInPaymentCurrency = vmPSPlugin::getAmountInCurrency(
            $order['details']['BT']->order_total,
            $order['details']['BT']->payment_currency_id ?? $order['details']['BT']->user_currency_id
        );

        $viewData = array(
            'order_number' => $response->data['orderNumber'],
            'payment_name' => $method->payment_name,
            'payment_status' => $response->data['paymentStatus'],
            'payment_type' => $response->type,
            'displayTotalInPaymentCurrency' => $totalInPaymentCurrency['display'],
            'orderlink' => $orderlink,
            'method' => $method
        );

        return $this->renderByLayout('payment_response', $viewData);
    }


    /**
     * Handle AJAX request to get payment methods by currency
     *
     * @param Input $input
     *
     * @return void
     */
    private function ajaxGetMethodsByCurrency(Input $input): void
    {
        $app = Factory::getApplication();
        $currencyId = $input->getInt('currency_id');
        $currentSelection = $input->getString('current_selection', '');

        if (!$currencyId) {
            echo new JsonResponse(['error' => 'Currency ID required'], null, false, true);
            $app->close();
            return;
        }

        $currencyCode = $this->getCurrencyCode($currencyId);

        $controller = new PaymentMethodAjaxController();
        echo $controller->getPaymentMethodsResponse($currencyCode, $currentSelection);
        $app->close();
    }

    /**
     * Get currency code from VirtueMart currency ID
     *
     * @param int $currencyId
     *
     * @return string
     */
    private function getCurrencyCode(int $currencyId): string
    {
        $currencyModel = \VmModel::getModel('currency');
        $currency = $currencyModel->getCurrency($currencyId);

        return $currency->currency_code_3 ?? 'EUR';
    }

    /**
     * Handle AJAX request to get country ISO code
     *
     * @param Input $input
     *
     * @return void
     */
    private function ajaxGetCountryCode(Input $input): void
    {
        $app = Factory::getApplication();
        $countryId = $input->getInt('country_id');

        if (!$countryId) {
            echo new JsonResponse(['error' => 'Country ID required'], null, false, true);
            $app->close();
            return;
        }

        $controller = new PaymentMethodAjaxController();
        echo $controller->getCountryCodeResponse($countryId);
        $app->close();
    }

    /**
     * Handle AJAX request to get payment methods by country
     *
     * @param Input $input
     *
     * @return void
     */
    private function ajaxGetMethodsByCountry(Input $input): void
    {
        $app = Factory::getApplication();
        $countryCode = $input->getString('country_code');
        $currencyId = $input->getString('currency_id');
        $orderTotal = $input->getString('order_total');

        if (!$countryCode) {
            echo new JsonResponse(['error' => 'Country code required'], null, false, true);
            $app->close();
            return;
        }

        $currencyModel = VmModel::getModel('currency');
        $currency = $currencyModel->getCurrency($currencyId);
        $currencyCode = $currency->currency_code_3 ?? 'EUR';

        $controller = new PaymentMethodAjaxController();
        echo $controller->getPaymentMethodsByCountryResponse($countryCode, $orderTotal, $currencyCode);
        $app->close();
    }

    /**
     * Handle AJAX request to capture a payment
     *
     * @return void
     */
    private function ajaxCapturePayment(): void
    {
        $app = Factory::getApplication();
        $input = $app->input;

        $orderId = $input->getInt('order_id');
        $amount = $input->getString('amount', '');

        if (!$orderId) {
            echo new JsonResponse(['error' => 'Order ID required'], null, false, true);
            $app->close();

            return;
        }

        if ($amount && !$this->isValidDecimalAmount($amount)) {
            echo new JsonResponse(['error' => Text::_('PLG_VMPAYMENT_MOLLIE_AMOUNT_TOO_MANY_DECIMALS')], null, false, true);
            $app->close();

            return;
        }

        try {
            $controller = new CaptureController();
            $response = $controller->capturePayment($orderId, $amount ?: null);

            if ($response->isSuccess()) {
                echo new JsonResponse($response->data, null, false);
            } else {
                echo new JsonResponse(['error' => $response->error], null, true);
            }
        } catch (\Exception $e) {
            echo new JsonResponse(['error' => $e->getMessage()], null, true);
        }

        $app->close();
    }

    private function ajaxRefundPayment(): void
    {
        $app = Factory::getApplication();
        $input = $app->input;

        $orderId = $input->getInt('order_id');
        $amount = $input->getString('amount', '');

        if (!$orderId) {
            echo new JsonResponse(['error' => 'Order ID required'], null, false, true);
            $app->close();
            return;
        }

        if ($amount && !$this->isValidDecimalAmount($amount)) {
            echo new JsonResponse(['error' => Text::_('PLG_VMPAYMENT_MOLLIE_AMOUNT_TOO_MANY_DECIMALS')], null, false, true);
            $app->close();
            return;
        }

        try {
            $controller = new RefundController();
            $response = $controller->refundPayment($orderId, $amount ?: null);

            if ($response->isSuccess()) {
                echo new JsonResponse($response->data, null, false);
            } else {
                echo new JsonResponse(['error' => $response->error], null, true);
            }
        } catch (\Exception $e) {
            echo new JsonResponse(['error' => $e->getMessage()], null, true);
        }

        $app->close();
    }


    /**
     * Get Mollie configuration for credit card components
     *
     * @return array
     */
    private function getMollieConfiguration(): array
    {
        try {
            /** @var Configuration $configService */
            $configService = ServiceRegister::getService(Configuration::CLASS_NAME);

            $profile = $configService->getWebsiteProfile();
            $isTestMode = $configService->isTestMode();

            // Get Joomla language
            $lang = Factory::getApplication()->getLanguage();
            $locale = str_replace('-', '_', $lang->getTag()); // e.g., 'en_GB'

            return [
                'profileId' => $profile ? $profile->getId() : '',
                'testMode' => $isTestMode,
                'locale' => $locale
            ];
        } catch (\Exception $e) {
            return [
                'profileId' => '',
                'testMode' => false,
                'locale' => 'en_US'
            ];
        }
    }

    /**
     * @param $data
     *
     * @return bool
     */
    private function isOrderCancelled($data, ?string $old_order_status): bool
    {
        return $data->order_status === 'X' && $old_order_status !== 'X';
    }

    /**
     * @param $data
     *
     * @return Response
     */
    private function handleOrderCancel($data): Response
    {
        $controller = new CancelController();

        return $controller->cancelPayment($data->virtuemart_order_id);
    }

    /**
     * Validate order changes - compare fields that affect payment
     *
     * @param object $data
     * @param array $newItems
     *
     * @return bool
     */
    private function validateOrderLineChange(object $data, array $newItems): bool
    {
        $app = Factory::getApplication();

        if ($this->shippingOrPaymentChanged($data)) {
            $app->enqueueMessage(
                Text::_('PLG_VMPAYMENT_MOLLIE_ORDER_TOTAL_CHANGE_NOT_SUPPORTED'),
                'error'
            );

            return false;
        }

        $currentItems = $this->fetchCurrentOrderItems($data->virtuemart_order_id);

        $changes = $this->compareOrderItems($currentItems, $newItems);

        if ($changes['priceOrQuantityChanged']) {
            $app->enqueueMessage(
                Text::_('PLG_VMPAYMENT_MOLLIE_ORDER_TOTAL_CHANGE_NOT_SUPPORTED'),
                'error'
            );

            Logger::logWarning(
                "Price/quantity change was blocked for Mollie order {$data->virtuemart_order_id}",
                'Mollie',
                ['changes' => $changes['details']]
            );

            return false;
        }

        if ($changes['otherFieldsChanged']) {
            $app->enqueueMessage(
                Text::_('PLG_VMPAYMENT_MOLLIE_ORDER_LINE_CHANGE_WARNING'),
                'warning'
            );
        }

        return true;
    }

    /**
     * Check if order-level financial fields changed (shipping, payment, taxes, total)
     *
     * @param object $data
     *
     * @return bool
     */
    private function shippingOrPaymentChanged(object $data): bool
    {
        /** @var VirtueMartOrderService $orderService */
        $orderService = ServiceRegister::getService(VirtueMartOrderService::class);

        $currentOrder = $orderService->getOrder($data->virtuemart_order_id);

        if (!$currentOrder) {
            return false;
        }

        $input = Factory::getApplication()->input;

        $fieldsToCheck = [
            'order_shipment',
            'order_shipment_tax',
            'order_payment',
            'order_payment_tax',
            'order_tax',
            'order_billTaxAmount',
            'order_total',
        ];

        foreach ($fieldsToCheck as $field) {
            $newValue = $input->getFloat($field);

            if ($newValue === null) {
                continue;
            }

            $currentValue = (float)$currentOrder->$field;
            if (abs($currentValue - $newValue) > 0.01) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param int $orderId
     *
     * @return array
     */
    private function fetchCurrentOrderItems(int $orderId): array
    {
        /** @var VirtueMartOrderService $orderService */
        $orderService = ServiceRegister::getService(VirtueMartOrderService::class);

        return $orderService->getOrderItems($orderId);
    }

    /**
     * @param array $currentItems
     * @param array $newItems
     *
     * @return array
     */
    private function compareOrderItems(array $currentItems, array $newItems): array
    {
        $priceOrQuantityChanged = false;
        $otherFieldsChanged = false;
        $details = [];

        foreach ($newItems as $itemId => $newItem) {
            if (!isset($currentItems[$itemId])) {
                continue;
            }

            $current = $currentItems[$itemId];

            foreach ($newItem as $field => $newValue) {
                if (!isset($current->$field)) {
                    continue;
                }

                $currentValue = $current->$field;

                $changed = is_numeric($currentValue) && is_numeric($newValue)
                    ? abs((float)$currentValue - (float)$newValue) > 0.01
                    : $currentValue !== $newValue;

                if (!$changed) {
                    continue;
                }

                $details[$itemId][$field] = [
                    'old' => $currentValue,
                    'new' => $newValue
                ];

                in_array($field, self::FINANCIAL_FIELDS)
                    ? $priceOrQuantityChanged = true
                    : $otherFieldsChanged = true;
            }
        }

        return [
            'priceOrQuantityChanged' => $priceOrQuantityChanged,
            'otherFieldsChanged' => $otherFieldsChanged,
            'details' => $details
        ];
    }

    /**
     * Save PaymentMethodConfig entity with logo and transaction description
     *
     * @return void
     */
    private function savePaymentMethodConfig(): void
    {
        $input = Factory::getApplication()->input;
        $params = $input->get('params', [], 'array');

        $mollieMethodId = $params['mollie_payment_method'] ?? '';
        $transactionDescription = $params['transaction_description'] ?? '{orderNumber}';

        $currencyId = $input->getInt('currency_id');
        $currencyCode = $this->getCurrencyCode($currencyId);

        if (empty($mollieMethodId)) {
            return;
        }

        try {
            /** @var PaymentMethodConfigService $configService */
            $configService = ServiceRegister::getService(PaymentMethodConfigService::class);

            $customLogoUrl = $this->handleLogoUpload();

            $captureMode = $input->getString('capture_mode', PaymentMethodConfigService::CAPTURE_MODE_AUTOMATIC);

            $configService->savePaymentMethodConfig(
                $mollieMethodId,
                $currencyCode,
                $transactionDescription,
                $customLogoUrl,
                $captureMode
            );

            if ($customLogoUrl) {
                Factory::getApplication()->enqueueMessage('Payment method configuration saved with custom logo.', 'success');
            }
        } catch (\Exception $e) {
            Factory::getApplication()->enqueueMessage('Failed to save payment method configuration: ' . $e->getMessage(), 'error');
        }
    }

    /**
     * @return string|null
     */
    private function handleLogoUpload(): ?string
    {
        if (empty($_FILES['payment_method_logo']['name'])) {
            return null;
        }

        $file = $_FILES['payment_method_logo'];

        if (!$this->validateUploadedFile($file)) {
            return null;
        }

        $uploadDir = JPATH_ROOT . '/images/mollie/custom-logos';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $filename = time() . '_' . uniqid() . '.' . $ext;
        $uploadPath = $uploadDir . '/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
            Factory::getApplication()->enqueueMessage(Text::_('PLG_VMPAYMENT_MOLLIE_UPLOAD_FAILED'), 'error');
            return null;
        }

        return Uri::root(true) . '/images/mollie/custom-logos/' . $filename;
    }

    /**
     * Validate uploaded logo file for security
     *
     * @param array $file The $_FILES array element
     * @return bool
     */
    private function validateUploadedFile(array $file): bool
    {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            Factory::getApplication()->enqueueMessage(Text::_('PLG_VMPAYMENT_MOLLIE_UPLOAD_ERROR'), 'error');
            return false;
        }

        $maxFileSize = 2 * 1024 * 1024;
        if ($file['size'] > $maxFileSize) {
            Factory::getApplication()->enqueueMessage(Text::_('PLG_VMPAYMENT_MOLLIE_UPLOAD_SIZE_EXCEEDED'), 'error');
            return false;
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'svg'];
        if (!in_array($ext, $allowedExtensions)) {
            Factory::getApplication()->enqueueMessage(Text::_('PLG_VMPAYMENT_MOLLIE_UPLOAD_INVALID_TYPE'), 'error');
            return false;
        }


        $actualMimeType = mime_content_type($file['tmp_name']);
        $allowedMimeTypes = [
            'image/jpeg' => ['jpg', 'jpeg'],
            'image/png' => ['png'],
            'image/gif' => ['gif'],
            'image/svg+xml' => ['svg'],
            'text/xml' => ['svg'],
            'text/plain' => ['svg']
        ];

        if (!isset($allowedMimeTypes[$actualMimeType])) {
            Factory::getApplication()->enqueueMessage(Text::_('PLG_VMPAYMENT_MOLLIE_UPLOAD_INVALID_CONTENT'), 'error');
            return false;
        }

        if (!in_array($ext, $allowedMimeTypes[$actualMimeType])) {
            Factory::getApplication()->enqueueMessage(Text::_('PLG_VMPAYMENT_MOLLIE_UPLOAD_EXTENSION_MISMATCH'), 'error');
            return false;
        }

        return true;
    }

    /**
     * Validate that amount has maximum 2 decimal places
     *
     * @param string $amount
     *
     * @return bool
     */
    private function isValidDecimalAmount(string $amount): bool
    {
        if (!is_numeric($amount)) {
            return false;
        }

        $parts = explode('.', $amount);
        if (count($parts) > 2) {
            return false;
        }

        if (count($parts) === 2 && strlen($parts[1]) > 2) {
            return false;
        }

        return true;
    }
}
