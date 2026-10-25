<?php

declare(strict_types=1);

namespace App\AdminModule\Presenters;

use App\Model;
use Nette;

class DashboardPresenter extends Nette\Application\UI\Presenter
{
    use RequireLoggedUser;
    public function __construct(
        private readonly Model\Post $postModel,
    ) {
        parent::__construct();
    }


    public function renderDefault(): void
    {
        $posts = $this->postModel->getPosts();
        $this->getTemplate()->posts = $posts;
    }
}
