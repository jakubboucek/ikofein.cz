<?php

declare(strict_types=1);

namespace App\Presenters\Admin\Dashboard;

use App\Presenters\Admin\RequireLoggedUser;
use App\Model;
use Nette;
use Nette\Bridges\ApplicationLatte\DefaultTemplate;

/** @property-read DefaultTemplate $template */
class DashboardPresenter extends Nette\Application\UI\Presenter
{
    use RequireLoggedUser;


    public function __construct(
        private readonly Model\PostManager $postManager,
    ) {
        parent::__construct();
    }


    public function renderDefault(): void
    {
        $posts = $this->postManager->getPosts();
        $this->template->posts = $posts;
    }
}
