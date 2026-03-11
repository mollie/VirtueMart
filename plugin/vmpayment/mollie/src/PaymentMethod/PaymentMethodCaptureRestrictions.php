<?php

namespace Mollie\Payment\PaymentMethod;

final class PaymentMethodCaptureRestrictions
{
    public const MANUAL_ONLY = [];
    public const AUTOMATIC_ONLY = [
        'ideal',
        'bancomatpay',
        'bancontact',
        'belfius',
        'blik',
        'eps',
        'mybank',
        'przelewy24',
        'banktransfer',
        'twint',
        'paypal',
        'directdebit',
        'sofort'
    ];
}
