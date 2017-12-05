<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace JakubBoucek\Aws;

use Nette,
	Aws\Sdk as Aws,
	Nette\Mail\IMailer,
	Nette\Mail\Message;


/**
 * Sends emails via AWS SES serivce
 */
class SesMailer implements IMailer
{
	use Nette\SmartObject;

	private $ses;

	public function __construct( Aws $aws )
	{
		$this->ses = $aws->createSes();
	}


	/**
	 * Sends email.
	 * @return void
	 * @throws SendException
	 */
	public function send(Message $mail)
	{
		$tmp = clone $mail;

		$from = $this->getCleanMail( $tmp->getFrom() );

		$destinations = [];
		foreach( ['To', 'Cc', 'Bcc'] as $key ) {
			$header = $tmp->getHeader( $key );
			if(is_array($header)) {
				foreach($header as $mail => $name ) {
					$destinations[] = $mail;
				}
			}
		}

		$message = $tmp->generateMessage();
		$sesArgs = [
			'Source' => $from,
			'Destinations' => $destinations,
			'RawMessage' => [
				'Data' => $message
			]
		];
		$result = $this->ses->sendRawEmail($sesArgs);
	}

	private function getCleanMail( $composedMail ) {
		if( is_array($composedMail) ) {
			return key($composedMail);
		}
		return $composedMail;
	}

}