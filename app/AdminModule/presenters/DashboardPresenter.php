<?php

namespace App\AdminModule\Presenters;

use App\Model;
use Nette;

class DashboardPresenter extends Nette\Application\UI\Presenter
{
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


    /**
     * @throws Nette\Application\AbortException
     */
    public function startup(): void
    {
        parent::startup();
        if (!$this->user->isLoggedIn()) {
            $this->redirect('Sign:in');
        }
    }


    public function renderDefault(): void
    {
        $posts = $this->postModel->getPosts();
        $this->template->posts = $posts;
    }


}
