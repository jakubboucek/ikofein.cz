<?php

declare(strict_types=1);

namespace App\AdminModule\Presenters;

use App\Model;
use Nette;

class DashboardPresenter extends Nette\Application\UI\Presenter
{
    use RequireLoggedUser;

    /**
     * @var Model\Post
     */
    private $postModel;


    /**
     * DashboardPresenter constructor.
     * @param Model\Post $postModel
     */
    public function __construct(Model\Post $postModel)
    {
        parent::__construct();
        $this->postModel = $postModel;
    }




    public function renderDefault(): void
    {
        $posts = $this->postModel->getPosts();
        $this->template->posts = $posts;
    }
}
