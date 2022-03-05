<?php

declare(strict_types=1);

namespace App;

use Defuse\Crypto\Crypto as CryptoDefuse;
use Nette\Utils\Json;
use Nette\Utils\JsonException;

class Crypto
{
    private string $key;


    public function __construct(string $key)
    {
        $this->key = $key;
    }


    /**
     * @throws CryptoException
     */
    public function encrypt(string $plaintext, bool $raw_binary = false): string
    {
        try {
            return CryptoDefuse::encryptWithPassword($plaintext, $this->key, $raw_binary);
        } catch (\Defuse\Crypto\Exception\CryptoException $e) {
            throw new CryptoException(
                'Unable to encrypt - encryption failed',
                $e->getCode(),
                $e
            );
        }
    }


    /**
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
