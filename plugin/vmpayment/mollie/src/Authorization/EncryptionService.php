<?php

namespace Mollie\Payment\Authorization;

use Joomla\CMS\Factory;
use Mollie\BusinessLogic\Authorization\Interfaces\EncryptionService as EncryptionServiceInterface;

class EncryptionService implements EncryptionServiceInterface
{
    /**
     * @param string $message
     *
     * @return string
     *
     * @throws \Exception
     */
    public function encrypt(string $message): string
    {
        if (!extension_loaded('sodium')) {
            throw new \Exception('Encryption failed');
        }

        $key = $this->getEncryptionKey();
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        $encrypted = sodium_crypto_secretbox($message, $nonce, $key);

        return base64_encode($nonce . $encrypted);
    }

    /**
     * @param string $encryptedMessage
     *
     * @return string
     *
     * @throws \Exception
     */
    public function decrypt(string $encryptedMessage): string
    {
        if (!extension_loaded('sodium')) {
            throw new \Exception('Decryption failed');
        }

        $decoded = base64_decode($encryptedMessage, true);

        if (!$decoded) {
            throw new \Exception('Invalid encrypted message format');
        }

        $key = $this->getEncryptionKey();
        $nonce = mb_substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES, '8bit');
        $ciphertext = mb_substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES, null, '8bit');

        $decrypted = sodium_crypto_secretbox_open($ciphertext, $nonce, $key);

        if (!$decrypted) {
            throw new \Exception('Decryption failed');
        }

        return $decrypted;
    }

    /**
     * @return string
     *
     * @throws \Exception
     */
    private function getEncryptionKey(): string
    {
        $config = Factory::getApplication()->getConfig();
        $secret = $config->get('secret');

        if (empty($secret)) {
            throw new \Exception('Joomla secret key not configured');
        }

        return hash('sha256', $secret . 'mollie_encryption', true);
    }
}
