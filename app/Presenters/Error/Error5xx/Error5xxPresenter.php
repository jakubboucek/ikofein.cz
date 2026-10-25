<?php

declare(strict_types=1);

namespace App\Presenters\Error\Error5xx;

use Nette;
use Nette\Application\Response;
use Nette\Application\Request;
use Nette\Application\Responses;
use Override;
use Tracy\ILogger;

class Error5xxPresenter implements Nette\Application\IPresenter
{
    use Nette\SmartObject;


    public function __construct(
        private readonly ILogger $logger,
    ) {
    }


    #[Override]
    public function run(Request $request): Response
    {
        $exception = $request->getParameter('exception');

        $this->logger->log($exception, ILogger::EXCEPTION);
        return new Responses\CallbackResponse(function (): void {
            require __DIR__ . '/500.phtml';
        });
    }
}
