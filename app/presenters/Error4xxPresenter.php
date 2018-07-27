<?php

declare(strict_types=1);

namespace App\Presenters;

use App\Model\WebDir;
use Nette;

class Error4xxPresenter extends Nette\Application\UI\Presenter
{
    /**
     * @var WebDir
     */
    private $wwwDir;


    public function __construct(WebDir $wwwDir)
    {
        parent::__construct();
        $this->wwwDir = $wwwDir;
    }


    /**
     * @throws Nette\Application\BadRequestException
     */
    public function startup(): void
    {
        parent::startup();
        if (!$this->getRequest()->isMethod(Nette\Application\Request::FORWARD)) {
            $this->error();
        }
    }


    /**
     * @param Nette\Application\BadRequestException $exception
     */
    public function renderDefault(Nette\Application\BadRequestException $exception): void
    {
        // load template 403.latte or 404.latte or ... 4xx.latte
        $file = __DIR__ . "/templates/Error/{$exception->getCode()}.latte";

        $template = $this->getTemplate();
        $template->setFile(is_file($file) ? $file : __DIR__ . '/templates/Error/4xx.latte');

        $template->wwwDir = $this->wwwDir->getPath();
        $template->dataLayer = [['errorCode' => $exception->getCode()]];
    }

}
