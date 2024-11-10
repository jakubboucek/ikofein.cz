<?php

declare(strict_types=1);

namespace App\Presenters;

use Nette;
use Nette\Application\Response;
use Nette\Application\Request;
use Nette\Application\Responses;
use Override;
use Tracy\ILogger;

class ErrorPresenter implements Nette\Application\IPresenter
{
    use Nette\SmartObject;

    private ILogger $logger;


    public function __construct(ILogger $logger)
    {
        $this->logger = $logger;
    }


    #[Override]
    public function run(Request $request): Response
    {
        $exception = $request->getParameter('exception');

        if ($exception instanceof Nette\Application\BadRequestException) {
            [$module, , $sep] = Nette\Application\Helpers::splitName($request->getPresenterName());
            return new Responses\ForwardResponse($request->setPresenterName($module . $sep . 'Error4xx'));
        }

        $this->logger->log($exception, ILogger::EXCEPTION);
        return new Responses\CallbackResponse(function (): void {
            require __DIR__ . '/templates/Error/500.phtml';
        });
    }
}
