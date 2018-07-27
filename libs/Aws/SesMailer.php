<?php

declare(strict_types=1);

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace JakubBoucek\Aws;

use Aws\Sdk as Aws;
use Nette;
use Nette\Mail\IMailer;
use Nette\Mail\Message;

/**
 * Sends emails via AWS SES serivce
 */
class SesMailer implements IMailer
{
    use Nette\SmartObject;

    /**
     * @var \Aws\Ses\SesClient
     */
    private $ses;


    /**
     * SesMailer constructor.
     * @param Aws $aws
     */
    public function __construct(Aws $aws)
    {
        $this->ses = $aws->createSes();
    }


    /**
     * Sends email.
     * @param Message $message
     * @return void
     */
    public function send(Message $message): void
    {
        $tmp = clone $message;

        $from = $this->getCleanMail($tmp->getFrom());

        $destinations = [];
        foreach (['To', 'Cc', 'Bcc'] as $key) {
            $header = $tmp->getHeader($key);
            if (\is_array($header)) {
                foreach ($header as $mail => $name) {
                    $destinations[] = $mail;
                }
            }
        }

        $rawMessage = $tmp->generateMessage();
        $sesArgs = [
            'Source' => $from,
            'Destinations' => $destinations,
            'RawMessage' => [
                'Data' => $rawMessage
            ]
        ];

        $this->ses->sendRawEmail($sesArgs);
    }


    /**
     * @param string|array $composedMail
     * @return string
     */
    private function getCleanMail($composedMail): string
    {
        if (\is_array($composedMail)) {
            return (string)key($composedMail);
        }
        return $composedMail;
    }
}