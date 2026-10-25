<?php
declare(strict_types=1);

namespace App\AdminModule\Presenters;

trait RequireLoggedUser
{
    public function injectRequireLoggedUser(): void
    {
        $presenter = $this;

        $presenter->onStartup[] = static function () use ($presenter): void {
            if (!$presenter->getUser()->isLoggedIn()) {
                $presenter->redirect('Sign:in', ['backlink' => $presenter->storeRequest()]);
            }
        };
    }
}
