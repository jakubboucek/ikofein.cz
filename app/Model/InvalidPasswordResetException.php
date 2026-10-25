<?php

declare(strict_types=1);

namespace App\Model;

final class InvalidPasswordResetException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('Odkaz je neplatný nebo vypršel, případně bylo překročeno omezení počtu pokusů.'
            . ' Vyžádejte si nový odkaz nebo to zkuste později.');
    }
}
