<?php

namespace App\AdminModule\Presenters;

use App\Model,
	Nette,
	Nette\Http\Request,
	Nette\Http\Response,
	Nette\Application\BadRequestException;

class OldAdminPresenter extends Nette\Application\UI\Presenter
{

	public function renderOldAdmin() {
		$this->flashMessage('Na webu je nasazena nová administrace. Zapamatujte si adresu www.ikofein.cz/admin, heslo najdete v e-mailu.', 'danger');
		$this->redirect('Sign:in');
	}


}
