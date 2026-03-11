<?php

namespace Mollie\Payment\Payment;

use Mollie\BusinessLogic\Http\DTO\Address;
use Mollie\BusinessLogic\Http\DTO\Amount;
use Mollie\BusinessLogic\Http\DTO\Payment;
use Mollie\BusinessLogic\Http\DTO\Orders\OrderLine;
use Mollie\BusinessLogic\Http\Exceptions\UnprocessableEntityRequestException;
use Mollie\BusinessLogic\OrderReference\Exceptions\ReferenceNotFoundException;
use Mollie\BusinessLogic\Payments\PaymentService as CorePaymentService;
use Mollie\BusinessLogic\PaymentMethod\DTO\DescriptionParameters;
use Mollie\Infrastructure\Configuration\Configuration;
use Mollie\Infrastructure\Http\Exceptions\HttpAuthenticationException;
use Mollie\Infrastructure\Http\Exceptions\HttpCommunicationException;
use Mollie\Infrastructure\Http\Exceptions\HttpRequestException;
use Mollie\Infrastructure\Logger\Logger;
use Mollie\Infrastructure\ServiceRegister;
use Mollie\Payment\Configuration\ConfigurationService;
use Mollie\Payment\Interface\VirtueMartRepositoryInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;
use RuntimeException;
use VirtueMartCart;

class PaymentService
{
    public function __construct(
        private readonly CorePaymentService $corePaymentService,
        private readonly VirtueMartRepositoryInterface $virtueMartRepository
    ) {}

    /**
     * Create payment on Mollie API
     *
     * @param VirtueMartCart $cart
     * @param array $order
     * @param object $method
     * @param string|null $cardToken
     *
     * @return array
     *
     * @throws RuntimeException
     */
    public function createPayment(VirtueMartCart $cart, array $order, object $method, ?string $cardToken = null): array
    {
        $orderDetails = $order['details']['BT'];
        $orderId = $orderDetails->virtuemart_order_id;
        $orderNumber = $orderDetails->order_number;

        try {
            $paymentDTO = $this->buildPaymentDTO($cart, $order, $method, $orderNumber, $cardToken);

            $createdPayment = $this->corePaymentService->createPayment((string)$orderId, $paymentDTO);

            $checkoutLink = $createdPayment->getLink('checkout');
            $checkoutUrl = $checkoutLink?->getHref();
            $paymentId = $createdPayment->getId();

            if (!$checkoutUrl) {
                throw new \Exception('No checkout URL in payment response');
            }

            return [
                'success' => true,
                'checkoutUrl' => $checkoutUrl,
                'paymentId' => $paymentId,
                'error' => null
            ];

        } catch (\Exception $e) {
            Logger::logError("There was an error while creating a payment for order {$orderId}",
                'Payment',
                [
                    'orderId' => $orderId,
                    'orderNumber' => $orderNumber,
                    'error' => $e->getMessage()
                ]);

            return [
                'success' => false,
                'checkoutUrl' => null,
                'paymentId' => null,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * @param string $orderId
     *
     * @return Payment
     *
     * @throws HttpAuthenticationException
     * @throws HttpCommunicationException
     * @throws HttpRequestException
     * @throws ReferenceNotFoundException
     * @throws UnprocessableEntityRequestException
     */
    public function getPayment(string $orderId): Payment
    {
        return $this->corePaymentService->getPayment($orderId);
    }

    /**
     * @param object $method
     *
     * @return string|null
     */
    public function getCardToken(object $method): ?string
    {
        if (!$this->requiresCardToken($method)) {
            return null;
        }

        $input = Factory::getApplication()->input;
        $cardToken = $input->getString('mollieCardToken', '');

        return !empty($cardToken) ? $cardToken : null;
    }

    /**
     * Check if this payment method requires a card token
     *
     * @param object $method
     *
     * @return bool
     */
    public function requiresCardToken(object $method): bool
    {
        return $method->mollie_payment_method === 'creditcard'
            && !empty($method->mollie_components_enabled);
    }

    /**
     * @param VirtueMartCart $cart
     * @param object $orderDetails
     * @param object $method
     * @param string $orderNumber
     * @param string|null $cardToken
     *
     * @return Payment
     *
     * @throws RuntimeException
     */
    private function buildPaymentDTO(
        VirtueMartCart $cart,
        array $order,
        object $method,
        string $orderNumber,
        ?string $cardToken
    ): Payment {
        $orderDetails = $order['details']['BT'];
        $payment = new Payment();

        $profileId = $this->getProfileId();
        if ($profileId) {
            $payment->setProfileId($profileId);
        }

        $amount = $this->buildAmount($cart);
        $payment->setAmount($amount);

        $description = $this->buildDescription($method, $orderNumber, $orderDetails, $cart);
        $payment->setDescription($description);

        $redirectUrl = $this->getRedirectUrl($orderDetails->virtuemart_order_id);
        $payment->setRedirectUrl($redirectUrl);

        $webhookUrl = $this->getWebhookUrl();
        $payment->setWebhookUrl($webhookUrl);

        $locale = $this->getLocale();
        $payment->setLocale($locale);

        $lines = $this->buildLines($cart, $amount->getCurrency());
        $payment->setLines($lines);

        if (!empty($method->mollie_payment_method)) {
            $payment->setMethods([$method->mollie_payment_method]);
        }

        $metadata = [
            'order_id' => $orderDetails->virtuemart_order_id,
            'order_number' => $orderNumber,
        ];
        $payment->setMetadata($metadata);

        if ($cardToken) {
            $payment->setCardToken($cardToken);
        }

        $billingAddress = $this->buildBillingAddress($orderDetails);
        if ($billingAddress) {
            $payment->setBillingAddress($billingAddress);
        }

        $shippingAddress = $this->buildShippingAddress($order);
        if ($shippingAddress) {
            $payment->setShippingAddress($shippingAddress);
        }

        return $payment;
    }

    /**
     * Build Amount DTO from cart data
     *
     * @param VirtueMartCart $cart
     *
     * @return Amount
     *
     * @throws RuntimeException
     */
    private function buildAmount(VirtueMartCart $cart): Amount
    {
        $total = $cart->cartPrices['billTotal'] ?? 0;

        $currencyId = $cart->pricesCurrency ?? null;

        $currencyModel = \VmModel::getModel('currency');
        $currencyData = $currencyModel->getCurrency($currencyId);
        $currency = $currencyData?->currency_code_3 ?? null;
        if (!$currency) {
            throw new RuntimeException("No currency found!");
        }

        $formattedAmount = number_format((float)$total, 2, '.', '');

        $amount = new Amount();
        $amount->setCurrency($currency);
        $amount->setAmountValue($formattedAmount);

        return $amount;
    }

    /**
     * Build payment description from template
     *
     * @param object $method
     * @param string $orderNumber
     * @param object $orderDetails
     * @param VirtueMartCart $cart
     *
     * @return string
     */
    private function buildDescription(object $method, string $orderNumber, object $orderDetails, VirtueMartCart $cart): string
    {
        $template = $method->transaction_description ?? 'Order {orderNumber}';

        $config = Factory::getApplication()->getConfig();
        $storeName = $config->get('sitename', 'Shop');

        $cartNumber = $cart->virtuemart_cart_id ?? '';

        $descriptionParams = new DescriptionParameters(
            $orderNumber,
            $storeName,
            $orderDetails->first_name ?? '',
            $orderDetails->last_name ?? '',
            $orderDetails->company ?? '',
            $cartNumber
        );

        $description = strtr($template, $descriptionParams->toArray());

        return substr($description, 0, 255);
    }

    /**
     * Build billing address DTO from order details
     *
     * @param object $orderDetails
     *
     * @return Address|null
     */
    private function buildBillingAddress(object $orderDetails): ?Address
    {
        $address = new Address();

        $address->setGivenName($orderDetails->first_name ?? '');
        $address->setFamilyName($orderDetails->last_name ?? '');

        $address->setEmail($orderDetails->email ?? '');

        $street = trim(
            ($orderDetails->address_1 ?? '') . ' ' .
            ($orderDetails->address_2 ?? '')
        );
        if ($street) {
            $address->setStreetAndNumber($street);
        }

        if (!empty($orderDetails->city)) {
            $address->setCity($orderDetails->city);
        }

        if (!empty($orderDetails->zip)) {
            $address->setPostalCode($orderDetails->zip);
        }

        $countryCode = !empty($orderDetails->virtuemart_country_id)
            ? $this->virtueMartRepository->getCountryIsoCode((int)$orderDetails->virtuemart_country_id)
            : null;

        if ($countryCode) {
            $address->setCountry($countryCode);
        }

        return $address;
    }

    /**
     * Build shipping address from VirtueMart order data
     *
     * @param array $order VirtueMart order data
     *
     * @return Address|null
     */
    private function buildShippingAddress(array $order): ?Address
    {
        $shippingDetails = $order['details']['ST'] ?? null;

        if (!$shippingDetails) {
            return null;
        }

        $address = new Address();

        $address->setGivenName($shippingDetails->first_name ?? '');
        $address->setFamilyName($shippingDetails->last_name ?? '');

        $street = trim(
            ($shippingDetails->address_1 ?? '') . ' ' .
            ($shippingDetails->address_2 ?? '')
        );
        if ($street) {
            $address->setStreetAndNumber($street);
        }

        if (!empty($shippingDetails->city)) {
            $address->setCity($shippingDetails->city);
        }

        if (!empty($shippingDetails->zip)) {
            $address->setPostalCode($shippingDetails->zip);
        }

        $countryCode = !empty($shippingDetails->virtuemart_country_id)
            ? $this->virtueMartRepository->getCountryIsoCode((int)$shippingDetails->virtuemart_country_id)
            : null;

        if ($countryCode) {
            $address->setCountry($countryCode);
        }

        if (!empty($shippingDetails->company)) {
            $address->setOrganizationName($shippingDetails->company);
        }

        return $address;
    }

    /**
     * @param int $orderId
     *
     * @return string
     */
    private function getRedirectUrl(int $orderId): string
    {
        return Route::_(
            'index.php?option=com_virtuemart&view=pluginresponse&task=pluginresponsereceived&pm=mollie&order_id=' . $orderId,
            false,
            true,
            -1
        );
    }

    /**
     * @return string
     */
    private function getWebhookUrl(): string
    {
        return Route::_(
            'index.php?option=com_virtuemart&view=pluginresponse&task=pluginnotification&tmpl=component&pm=mollie',
            false,
            true,
            -1
        );
    }

    /**
     * Get locale for Mollie
     *
     * @return string
     */
    private function getLocale(): string
    {
        $joomlaLang = Factory::getApplication()->getLanguage()->getTag();

        $locale = str_replace('-', '_', $joomlaLang);

        $supported = [
            'en_US', 'en_GB', 'nl_NL', 'nl_BE', 'fr_FR', 'fr_BE',
            'de_DE', 'de_AT', 'de_CH', 'es_ES', 'ca_ES', 'pt_PT',
            'it_IT', 'nb_NO', 'sv_SE', 'fi_FI', 'da_DK', 'is_IS',
            'hu_HU', 'pl_PL', 'lv_LV', 'lt_LT'
        ];

        return in_array($locale, $supported) ? $locale : 'en_GB';
    }

    /**
     * Get Mollie profile ID from configuration
     *
     * @return string|null
     */
    private function getProfileId(): ?string
    {
        /** @var ConfigurationService $configService */
        $configService = ServiceRegister::getService(Configuration::CLASS_NAME);
        $profile = $configService->getWebsiteProfile();

        return $profile?->getId();
    }

    /**
     * @param VirtueMartCart $cart
     *
     * @return array
     *
     * @throws RuntimeException
     */
    private function buildLines(VirtueMartCart $cart, string $currency): array
    {
        $lines = [];
        $lines = array_merge($lines, $this->buildProductLines($cart, $currency));

        $discountLine = $this->buildDiscountLine($cart, $currency);
        if ($discountLine) {
            $lines[] = $discountLine;
        }

        $shippingLine = $this->buildShippingLine($cart, $currency);
        if ($shippingLine) {
            $lines[] = $shippingLine;
        }

        return $lines;
    }

    /**
     * @param VirtueMartCart $cart
     * @param string $currency
     *
     * @return array
     */
    private function buildProductLines(VirtueMartCart $cart, string $currency): array
    {
        $lines = [];

        $cartProductTax = $cart->cartPrices['cartTax'] ?? 0;
        $billDiscountAmount = $cart->cartPrices['billDiscountAmount'] ?? 0;
        $totalProductSubtotal = 0;
        $isTaxPerBill = $cartProductTax > 0;

        $productLevelDiscountSum = 0;
        foreach ($cart->products as $product) {
            $productLevelDiscountSum += $product->prices['subtotal_discount'] ?? 0;
        }

        $billLevelOnlyDiscount = $billDiscountAmount - $productLevelDiscountSum;
        $hasBillDiscount = abs($billLevelOnlyDiscount) > 0.01;

        if ($isTaxPerBill || $hasBillDiscount) {
            foreach ($cart->products as $product) {
                $totalProductSubtotal += $product->prices['subtotal'] ?? 0;
            }
        }

        foreach ($cart->products as $product) {
            $line = new OrderLine();

            $line->setName($product->product_name);
            $line->setQuantity((int)$product->quantity);
            $line->setSku($product->product_sku);
            $line->setType('physical');

            $subtotalWithoutTax = $product->prices['subtotal'] ?? 0;
            $taxAmount = $product->prices['subtotal_tax_amount'] ?? 0;

            $subtotalWithTax = $product->prices['subtotal_with_tax'] ?? $subtotalWithoutTax;

            if ($hasBillDiscount && $totalProductSubtotal > 0) {
                $discountShare = ($subtotalWithoutTax / $totalProductSubtotal) * $billLevelOnlyDiscount;
                $subtotalWithTax += $discountShare;
            }

            if ($isTaxPerBill && $totalProductSubtotal > 0) {
                $taxAmount = ($subtotalWithoutTax / $totalProductSubtotal) * $cartProductTax;
                $subtotalWithTax += $taxAmount;
            }

            $unitPriceWithTax = $product->prices['salesPrice'] ?? 0;
            if (($isTaxPerBill || $hasBillDiscount) && $product->quantity > 0) {
                $unitPriceWithTax = $subtotalWithTax / $product->quantity;
            }

            $unitAmount = new Amount();
            $unitAmount->setCurrency($currency);
            $unitAmount->setAmountValue(number_format((float)$unitPriceWithTax, 2, '.', ''));
            $line->setUnitPrice($unitAmount);

            $totalAmount = new Amount();
            $totalAmount->setCurrency($currency);
            $totalAmount->setAmountValue(number_format((float)$subtotalWithTax, 2, '.', ''));
            $line->setTotalAmount($totalAmount);

            $vatRate = '0.00';
            $finalVatAmount = 0;

            if ($subtotalWithoutTax > 0 && $taxAmount > 0) {
                $calculatedRate = ($taxAmount / $subtotalWithoutTax) * 100;
                $vatRate = number_format((float)$calculatedRate, 2, '.', '');
                $finalVatAmount = $subtotalWithTax * ($calculatedRate / (100 + $calculatedRate));
            }

            $line->setVatRate($vatRate);

            $vatAmount = new Amount();
            $vatAmount->setCurrency($currency);
            $vatAmount->setAmountValue(number_format((float)$finalVatAmount, 2, '.', ''));
            $line->setVatAmount($vatAmount);

            $lines[] = $line;
        }

        return $lines;
    }

    /**
     * @param VirtueMartCart $cart
     * @param string $currency
     *
     * @return OrderLine|null
     */
    private function buildDiscountLine(VirtueMartCart $cart, string $currency): ?OrderLine
    {
        $discountAmount = $cart->cartPrices['salesPriceCoupon'] ?? 0;

        if ($discountAmount >= 0) {
            return null;
        }

        $discountLine = new OrderLine();
        $discountLine->setName('Discount');
        $discountLine->setQuantity(1);
        $discountLine->setType('discount');

        $unitAmount = new Amount();
        $unitAmount->setCurrency($currency);
        $unitAmount->setAmountValue(number_format((float)$discountAmount, 2, '.', ''));
        $discountLine->setUnitPrice($unitAmount);

        $totalAmount = new Amount();
        $totalAmount->setCurrency($currency);
        $totalAmount->setAmountValue(number_format((float)$discountAmount, 2, '.', ''));
        $discountLine->setTotalAmount($totalAmount);

        $couponTax = $cart->cartPrices['couponTax'] ?? 0;

        $discountLine->setVatRate('0.00');

        $vatAmount = new Amount();
        $vatAmount->setCurrency($currency);
        $vatAmount->setAmountValue(number_format((float)$couponTax, 2, '.', ''));
        $discountLine->setVatAmount($vatAmount);

        return $discountLine;
    }

    /**
     * @param VirtueMartCart $cart
     * @param string $currency
     *
     * @return OrderLine|null
     */
    private function buildShippingLine(VirtueMartCart $cart, string $currency): ?OrderLine
    {
        $shippingCost = $cart->cartPrices['salesPriceShipment'] ?? 0;

        if ($shippingCost <= 0) {
            return null;
        }

        $shippingLine = new OrderLine();
        $shippingLine->setName('Shipping');
        $shippingLine->setQuantity(1);
        $shippingLine->setType('shipping_fee');

        $shippingTotal = $shippingCost;
        $shippingTax = $cart->cartPrices['shipmentTax'] ?? 0;
        $shippingWithoutTax = $cart->cartPrices['shipmentValue'] ?? ($shippingTotal - $shippingTax);

        $unitAmount = new Amount();
        $unitAmount->setCurrency($currency);
        $unitAmount->setAmountValue(number_format((float)$shippingTotal, 2, '.', ''));
        $shippingLine->setUnitPrice($unitAmount);

        $totalAmount = new Amount();
        $totalAmount->setCurrency($currency);
        $totalAmount->setAmountValue(number_format((float)$shippingTotal, 2, '.', ''));
        $shippingLine->setTotalAmount($totalAmount);

        $vatRate = '0.00';
        if ($shippingWithoutTax > 0 && $shippingTax >= 0) {
            $calculatedRate = ($shippingTax / $shippingWithoutTax) * 100;
            $vatRate = number_format((float)$calculatedRate, 2, '.', '');
        }
        $shippingLine->setVatRate($vatRate);

        $vatAmount = new Amount();
        $vatAmount->setCurrency($currency);
        $vatAmount->setAmountValue(number_format((float)$shippingTax, 2, '.', ''));
        $shippingLine->setVatAmount($vatAmount);

        return $shippingLine;
    }
}
