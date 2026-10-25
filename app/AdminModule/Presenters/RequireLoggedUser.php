<?php
declare(strict_types=1);

namespace App\AdminModule\Presenters;

trait RequireLoggedUser
{
    public function injectRequireLoggedUser(\App\Model\UserManager $userManager): void
    {
        $presenter = $this;

        $presenter->onStartup[] = static function () use ($presenter, $userManager): void {
            $identity = $presenter->getUser()->getIdentity();
            if ($presenter->getUser()->isLoggedIn() && (!$identity || !$userManager->isIdentityCurrent($identity))) {
                $presenter->getUser()->logout(true);
            }
            if (!$presenter->getUser()->isLoggedIn()) {
                $presenter->redirect('Sign:in', ['backlink' => $presenter->storeRequest()]);
            }
        };
    }
}
