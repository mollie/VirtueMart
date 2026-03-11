<?php

namespace Mollie\BusinessLogic\Authorization\Interfaces;

interface EncryptionService
{
    /**
     * @param string $message
     *
     * @return string
     */
    public function encrypt(string $message): string;

    /**
     * @param string $encryptedMessage
     *
     * @return string
     */
    public function decrypt(string $encryptedMessage): string;
}
