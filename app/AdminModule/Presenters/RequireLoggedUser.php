<?php
declare(strict_types=1);

namespace App\AdminModule\Presenters;

use Nette\Application\UI\Presenter;

trait RequireLoggedUser
{
    public function injectRequireLoggedUser(): void
    {
        /** @var Presenter $presenter */
        $presenter = $this;

        $presenter->onStartup[] = static function () use ($presenter): void {
            if (!$presenter->getUser()->isLoggedIn()) {
                $presenter->redirect('Sign:in', ['backlink' => $presenter->storeRequest()]);
            }
        };
    }
}
