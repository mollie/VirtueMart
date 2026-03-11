<?php

defined('_JEXEC') or die('Restricted access');
?>

<div class="mollie-component-wrapper hidden">
    <div class="form-group form-group--cardHolder">
        <label for="mollie-card-holder">Cardholder name</label>
        <div id="mollie-card-holder" class="mollie-field"></div>
        <div id="mollie-card-holder-error" class="error-message"></div>
    </div>

    <div class="form-group form-group--cardNumber">
        <label for="mollie-card-number">Card number</label>
        <div id="mollie-card-number" class="mollie-field"></div>
        <div id="mollie-card-number-error" class="error-message"></div>
    </div>

    <div class="form-row">
        <div class="form-group form-group--expiryDate form-col">
            <label for="mollie-expiry-date">MM/YY</label>
            <div id="mollie-expiry-date" class="mollie-field"></div>
            <div id="mollie-expiry-date-error" class="error-message"></div>
        </div>

        <div class="form-group form-group--verificationCode form-col">
            <label for="mollie-verification-code">CVV</label>
            <div id="mollie-verification-code" class="mollie-field"></div>
            <div id="mollie-verification-code-error" class="error-message"></div>
        </div>
    </div>
</div>
