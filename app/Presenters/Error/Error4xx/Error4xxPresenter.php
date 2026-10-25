<?php

declare(strict_types=1);

namespace App\Presenters\Error\Error4xx;

use Nette;
use Nette\Application\BadRequestException;

class Error4xxPresenter extends Nette\Application\UI\Presenter
{
    #[\Override]
    public function startup(): void
    {
        parent::startup();
        if (!$this->getRequest()->isMethod(Nette\Application\Request::FORWARD)) {
            $this->error();
        }
    }


    public function renderDefault(BadRequestException $exception): void
    {
        // load template 403.latte or 404.latte or ... 4xx.latte
        $file = __DIR__ . "/{$exception->getCode()}.latte";

        $template = $this->getTemplate();
        $template->setFile(is_file($file) ? $file : __DIR__ . '/4xx.latte');

        $template->lang = 'en';
        $template->dataLayer = [['errorCode' => $exception->getCode()]];
    }
}
