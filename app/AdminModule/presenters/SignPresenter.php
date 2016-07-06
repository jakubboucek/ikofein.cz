<?php

namespace App\AdminModule\Presenters;

use Nette,
	Nette\Http\Request,
	Nette\Http\Response,
	Nette\Application\BadRequestException,
	Nette\Application\UI,
	App\Forms\BootstrapizeForm;

class SignPresenter extends Nette\Application\UI\Presenter
{

	public function renderIn() {
		if( $this->user->isLoggedIn() ) {
			$this->redirect('Dashboard:');
		}
	}


	public function actionOut() {
		$this->user->logout(TRUE);
		$this->redirect(':Static:');
	}


	public function createComponentSignInForm() {
		$form = new UI\Form;
		$form->addText('email', 'E-mail:')
			->setType('email')
			->setRequired('E-mail musí být vyplněn')
			->setAttribute('autofocus');

		$form->addPassword('password', 'Heslo:')
			->setRequired('heslo musí být vyplněno');

		$form->addCheckbox('remember', 'Zůstat přihlášen');

		$form->addSubmit('send', 'Přihlásit');

		$form->onSuccess[] = [$this, 'signInFormSuccess'];
		BootstrapizeForm::bootstrapize( $form );
		return $form;
	}

	public function signInFormSuccess (UI\Form $form, $values) {
		try {
			$this->user->setExpiration($values->remember ? '14 days' : '20 minutes');
			$this->user->login($values['email'], $values['password']);
		} catch (Nette\Security\AuthenticationException $e) {
			$form->addError('The username or password you entered is incorrect.');
			return;
		}
		$this->redirect('Dashboard:');
	}
}
