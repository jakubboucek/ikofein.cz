<?php

namespace App\AdminModule\Presenters;

use App\Model,
	Nette,
	Nette\Http\Request,
	Nette\Http\Response,
	Nette\Application\BadRequestException;

class DashboardPresenter extends Nette\Application\UI\Presenter
{
	private $postModel;

	public function __construct( Model\Post $postModel ) {
		$this->postModel = $postModel;
	}

	public function startup() {
		parent::startup();
		if( ! $this->user->isLoggedIn() ) {
			$this->redirect('Sign:in');
		}
	}

	public function renderDefault() {
		$posts = $this->postModel->getPosts();
		$this->template->posts = $posts;
	}


}
