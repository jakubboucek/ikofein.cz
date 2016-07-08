<?php

namespace App;

use Defuse\Crypto\Crypto as CryptoDefuse,
	Nette\Utils\Json;

class Crypto
{
	private $key;

	public function __construct( $key ) {
		$this->key = $key;
	}

	public function encrypt( $plaintext, $raw_binary = FALSE ) {
		return CryptoDefuse::encryptWithPassword( $plaintext, $this->key, $raw_binary );
	}

	public function decrypt( $ciphertext, $raw_binary = FALSE ) {
		return CryptoDefuse::decryptWithPassword( $ciphertext, $this->key, $raw_binary );
	}

	public function encryptArray( $plainArray ) {
		$json = Json::encode( $plainArray );
		$cipher = self::encrypt( $json, TRUE );
		return base64_encode( $cipher );
	}

	public function decryptArray( $ciphertext ) {
		$cipher = base64_decode( $ciphertext );
		$json = self::decrypt( $cipher, TRUE );
		return Json::decode( $json, Json::FORCE_ARRAY );
	}
}
