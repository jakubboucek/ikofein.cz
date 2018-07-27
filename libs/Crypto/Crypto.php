<?php

declare(strict_types=1);

namespace App;

use Defuse\Crypto\Crypto as CryptoDefuse;
use Nette\Utils\Json;
use Nette\Utils\JsonException;

class Crypto
{
    /**
     * @var string
     */
    private $key;


    /**
     * Crypto constructor.
     * @param string $key
     */
    public function __construct($key)
    {
        $this->key = $key;
    }


    /**
     * @param string $plaintext
     * @param bool $raw_binary
     * @return string
     * @throws CryptoException
     */
    public function encrypt(string $plaintext, bool $raw_binary = false): string
    {
        try {
            return CryptoDefuse::encryptWithPassword($plaintext, $this->key, $raw_binary);
        } catch (\Defuse\Crypto\Exception\CryptoException $e) {
            throw new CryptoException(
                'Unable to encrypt - enryption failed',
                $e->getCode(),
                $e
            );
        }
    }


    /**
     * @param string $ciphertext
     * @param bool $raw_binary
     * @return string
     * @throws CryptoException
     */
    public function decrypt(string $ciphertext, bool $raw_binary = false): string
    {
        try {
            return CryptoDefuse::decryptWithPassword($ciphertext, $this->key, $raw_binary);
        } catch (\Defuse\Crypto\Exception\CryptoException $e) {
            throw new CryptoException(
                'Unable to decrypt cipher message',
                $e->getCode(),
                $e
            );
        }
    }


    /**
     * @param array $plainArray
     * @return string
     * @throws CryptoException
     */
    public function encryptArray(array $plainArray): string
    {
        try {
            $json = Json::encode($plainArray);
            $cipher = $this->encrypt($json, true);
            return base64_encode($cipher);
        } catch (JsonException $e) {
            throw new CryptoException(
                'Unable to encrypt - serialization data to JSON failed',
                $e->getCode(),
                $e
            );
        }
    }


    /**
     * @param string $ciphertext
     * @return array
     * @throws CryptoException
     */
    public function decryptArray(string $ciphertext): array
    {
        try {
            $cipher = $this->strictBase64Decode($ciphertext);
            $json = $this->decrypt($cipher, true);
            $decoded = Json::decode($json, Json::FORCE_ARRAY);

            if (!\is_array($decoded)) {
                $type = \gettype($decoded);
                throw new CryptoException("Unable to decrypt array, $type decoded instead");
            }

            /** @var array $decoded */
            return $decoded;
        } catch (CryptoException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new CryptoException(
                'Unable to decrypt cipher message',
                $e->getCode(),
                $e
            );
        }
    }


    /**
     * @param string $input
     * @return string
     * @throws CryptoException
     */
    private function strictBase64Decode(string $input): string
    {
        $output = base64_decode($input, true);

        if ($output === false) {
            throw new CryptoException('Invalid Base64 code');
        }

        return $output;
    }
}
