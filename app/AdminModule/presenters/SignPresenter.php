<?php

namespace App\AdminModule\Presenters;

use Nette,
	Nette\Application\BadRequestException,
	Nette\Application\UI,
	Nette\Http\Request,
	Nette\Http\Response,
	Nette\Mail,
	Nette\Utils\Random,
	Latte,
	App\Crypto,
	App\Forms\BootstrapizeForm,
	App\Model\UserManager,
	Defuse\Crypto\Exception as CryptoException,
	JakubBoucek\Aws\SesMailer;

class SignPresenter extends Nette\Application\UI\Presenter
{
	private $userManager;
	private $crypto;
	private $mailer;

	public function __construct( UserManager $userManager, Crypto $crypto, SesMailer $mailer ) {
		$this->userManager = $userManager;
		$this->crypto = $crypto;
		$this->mailer = $mailer;
	}

	public function renderIn() {
		if( $this->user->isLoggedIn() ) {
			$this->redirect('Dashboard:');
		}

		// Force start session before send content (need to protection)
		$this->getSession()->start();
	}

	public function renderReset() {
		$email = '';
		if( $this->user->isLoggedIn() ) {
			$email = $this->user->getIdentity()->email;
			$this['resetForm']['email']->setDefaultValue($email);
		}
	}

	public function renderChangePassword( $token ) {
		try {
			$user = $this->getUserActiveRowFromToken($token)->toArray();
			$this['setPasswordForm']['token']->setDefaultValue($token);
		}
		catch(SignResetPasswordTokenException $e) {
			$this->flashMessage($e->getMessage(), 'danger');
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

		$form->addProtection('Z důvodu ochrany prosím odešlete ještě jednou');

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

	public function createComponentResetForm() {
		$form = new UI\Form;
		$form->addText('email', 'E-mail:')
			->setType('email')
			->setRequired('E-mail musí být vyplněn')
			->setAttribute('autofocus');

		$form->addSubmit('send', 'Zahájit reset hesla');

		$form->addProtection('Z důvodu ochrany prosím odešlete ještě jednou');

		$form->onSuccess[] = [$this, 'resetFormSuccess'];
		BootstrapizeForm::bootstrapize( $form );
		return $form;
	}

	public function resetFormSuccess (UI\Form $form, $values) {
		$email = $values['email'];
		$hash = $this->userManager->startReset($email);

		if($hash) {
			$key = strtoupper(substr($hash, 0, 6));
			$plainData = [
				'key' => $key,
				'hash' => $hash,
				'email' => $email,
				'expire' => (new \DateTime('+ 1 hour'))->format(\DateTime::ATOM),
			];
			$token = $this->crypto->encryptArray($plainData);

			$this->sendChangeNotification($email, $key, $token);
		}
		else {
			//fake hash
			$key = strtoupper(Random::generate(6));
		}

		$this->flashMessage("Reset hesla byl zahájen s označením: „${key}“. Pokud Vámi zadaný e-mail existuje v databázi,".
			" byla na něj právě odeslána zpráva s pokyny pro dokončení procesu. Všechny dříve odeslané žádosti o reset hesla".
			" jsou tímto okamžikem zneplatněny, funkční bude pouze e-mail s označením: „${key}“. Žádost je platná 1 hodinu.", 'success');
		$this->redirect('this');
	}

	public function createComponentSetPasswordForm() {
		$form = new UI\Form;
		$form->addHidden('token');

		$form->addPassword('password', 'Heslo:')
			->setRequired('Heslo musí být vyplněno')
			->setAttribute('autofocus');

		$form->addPassword('password2', 'Heslo znovu:')
			->setRequired('Heslo musí být vyplněno')
    		->addRule(UI\Form::EQUAL, 'Hesla se neshodují', $form['password']);

		$form->addSubmit('send', 'Nastavit nové heslo');

		$form->addProtection('Z důvodu ochrany prosím odešlete ještě jednou');

		$form->onSuccess[] = [$this, 'setPasswordFormSuccess'];
		BootstrapizeForm::bootstrapize( $form );
		return $form;
	}

	public function setPasswordFormSuccess (UI\Form $form, $values) {
		try {
			$user = $this->getUserActiveRowFromToken($values['token']);
		}
		catch(SignResetPasswordTokenException $e) {
			$form->addError($e->getMessage());
			return;
		}

		$email = $user->email;
		$password = $values['password'];
		$this->userManager->setPassword($email, $password);

		$this->flashMessage("Heslo bylo změněno", 'success');
		$this->redirect('Sign:in');
	}

	private function sendChangeNotification( $email, $key, $token ) {
		$templateFile = __DIR__ . '/templates/Sign/resetMail.latte';
		$latte = new Latte\Engine;
		$mail = new Mail\Message;

		$params = [
			'title' => "Reset hesla k webu ikofein.cz ($key)",
			'link' => $this->link('//changePassword', ['token'=>$token]),
			'key' => $key,
		];

		$mail->setFrom('no-reply@ikofein.cz', 'Kofein automat')
			->setSubject( $params['title'] )
			->addTo( $email )
			->setHtmlBody($latte->renderToString($templateFile, $params));
		$mailer = $this->mailer;
		$mailer->send($mail);
	}

	private function getUserActiveRowFromToken( $token ) {
		try {
			$plainData = $this->crypto->decryptArray($token);
		}
		catch(CryptoException\WrongKeyOrModifiedCiphertextException $e) {
			throw new SignResetPasswordTokenException("Odkaz na reset hesla je poškozený, zkuste jej poslat znovu.", 0, $e);
		}

		$expire = new \DateTime($plainData['expire']);
		$now = new \DateTime();
		if($expire < $now) {
			throw new SignResetPasswordTokenException("Odkaz na reset hesla již expiroval, zkuste jej poslat znovu.", 0);
		}

		$user = $this->userManager->getUserByEmail($plainData['email']);
		if(!$user) {
			throw new SignResetPasswordTokenException("Uživatel neexistuje, nebo byl smazán", 0);
		}

		if($user['reset_hash'] != $plainData['hash']) {
			throw new SignResetPasswordTokenException("Odkaz na reset hesla byl zněplatněn, zkuste jej poslat znovu.", 0);
		}

		return $user;
	}
}

class SignResetPasswordTokenException extends \Exception
{}
