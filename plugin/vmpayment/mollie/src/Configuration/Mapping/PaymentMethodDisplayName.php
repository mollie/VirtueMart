<?php

namespace Mollie\Payment\Configuration\Mapping;

defined('_JEXEC') or die;

final class PaymentMethodDisplayName
{
    public const IDEAL = 'iDEAL';
    public const CREDITCARD = 'Credit Card';
    public const PAYPAL = 'PayPal';
    public const BANCONTACT = 'Bancontact';
    public const SOFORT = 'SOFORT Banking';
    public const KLARNA = 'Klarna';
    public const APPLEPAY = 'Apple Pay';
    public const BELFIUS = 'Belfius';
    public const BANKTRANSFER = 'Bank Transfer';
    public const SEPADIRECTDEBIT = 'SEPA Direct Debit';
    public const EPS = 'EPS';
    public const PRZELEWY24 = 'Przelewy24';
    public const MYBANK = 'MyBank';
    public const TWINT = 'TWINT';
    public const BLIK = 'BLIK';
    public const BANCOMATPAY = 'Bancomat Pay';
    public const BACSDIRECTDEBIT = 'BACS Direct Debit';

    /**
     * Get display name for a payment method ID
     *
     * @param string $methodId
     *
     * @return string
     */
    public static function fromMethodId(string $methodId): string
    {
        return match (strtolower($methodId)) {
            'ideal' => self::IDEAL,
            'creditcard' => self::CREDITCARD,
            'paypal' => self::PAYPAL,
            'bancontact' => self::BANCONTACT,
            'sofort' => self::SOFORT,
            'klarna' => self::KLARNA,
            'applepay' => self::APPLEPAY,
            'belfius' => self::BELFIUS,
            'banktransfer' => self::BANKTRANSFER,
            'directdebit' => self::SEPADIRECTDEBIT,
            'eps' => self::EPS,
            'przelewy24' => self::PRZELEWY24,
            'mybank' => self::MYBANK,
            'twint' => self::TWINT,
            'blik' => self::BLIK,
            'bancomatpay' => self::BANCOMATPAY,
            'bacs' => self::BACSDIRECTDEBIT,
            default => ucfirst($methodId),
        };
    }
}
