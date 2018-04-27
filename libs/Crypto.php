<?php

namespace App;

use Defuse\Crypto\Crypto as CryptoDefuse;
use Nette\Utils\Json;

class Crypto
{
    /**
     * @var
     */
    private $key;


    /**
     * Crypto constructor.
     * @param $key
     */
    public function __construct($key)
    {
        $this->key = $key;
    }


    /**
     * @param $plaintext
     * @param bool $raw_binary
     * @return string
     * @throws \Defuse\Crypto\Exception\EnvironmentIsBrokenException
     */
    public function encrypt($plaintext, $raw_binary = false)
    {
        return CryptoDefuse::encryptWithPassword($plaintext, $this->key, $raw_binary);
    }


    /**
     * @param $ciphertext
     * @param bool $raw_binary
     * @return string
     * @throws \Defuse\Crypto\Exception\EnvironmentIsBrokenException
     * @throws \Defuse\Crypto\Exception\WrongKeyOrModifiedCiphertextException
     */
    public function decrypt($ciphertext, $raw_binary = false)
    {
        return CryptoDefuse::decryptWithPassword($ciphertext, $this->key, $raw_binary);
    }


    /**
     * @param $plainArray
     * @return string
     * @throws \Defuse\Crypto\Exception\EnvironmentIsBrokenException
     * @throws \Nette\Utils\JsonException
     */
    public function encryptArray($plainArray)
    {
        $json = Json::encode($plainArray);
        $cipher = self::encrypt($json, true);
        return base64_encode($cipher);
    }


    /**
     * @param $ciphertext
     * @return mixed
     * @throws \Defuse\Crypto\Exception\EnvironmentIsBrokenException
     * @throws \Defuse\Crypto\Exception\WrongKeyOrModifiedCiphertextException
     * @throws \Nette\Utils\JsonException
     */
    public function decryptArray($ciphertext)
    {
        $cipher = base64_decode($ciphertext);
        $json = self::decrypt($cipher, true);
        return Json::decode($json, Json::FORCE_ARRAY);
    }
}
