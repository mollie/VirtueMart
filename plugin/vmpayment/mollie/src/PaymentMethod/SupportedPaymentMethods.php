<?php

namespace Mollie\Payment\PaymentMethod;

class SupportedPaymentMethods
{
    public const SUPPORTED = [
        'applepay',
        'bacs',
        'bancomatpay',
        'bancontact',
        'banktransfer',
        'belfius',
        'blik',
        'creditcard',
        'directdebit',
        'eps',
        'ideal',
        'klarna',
        'mybank',
        'paypal',
        'przelewy24',
        'sofort',
        'twint',
    ];

    /**
     * Check if a payment method is supported
     *
     * @param string $methodId
     *
     * @return bool
     */
    public static function isSupported(string $methodId): bool
    {
        return in_array($methodId, self::SUPPORTED);
    }
}
