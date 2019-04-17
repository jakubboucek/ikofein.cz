<?php

declare(strict_types=1);

namespace App\AdminModule\Presenters;

use App\Crypto;
use App\CryptoException;
use App\Forms\BootstrapizeForm;
use App\Model\UserManager;
use App\Model\UserNotFoundException;
use DateTime;
use JakubBoucek\Aws\SesMailer;
use Latte;
use Nette;
use Nette\Application\UI;
use Nette\Forms\Controls\TextInput;
use Nette\Mail;
use Nette\Security\Identity;
use Nette\Utils\ArrayHash;
use Nette\Utils\Random;

class SignPresenter extends Nette\Application\UI\Presenter
{
    /**
     * @var string|null
     * @persistent
     */
    public $backlink;

    /**
     * @var UserManager
     */
    private $userManager;
    /**
     * @var Crypto
     */
    private $crypto;
    /**
     * @var SesMailer
     */
    private $mailer;


    /**
     * SignPresenter constructor.
     * @param UserManager $userManager
     * @param Crypto $crypto
     * @param SesMailer $mailer
     */
    public function __construct(UserManager $userManager, Crypto $crypto, SesMailer $mailer)
    {
        parent::__construct();
        $this->userManager = $userManager;
        $this->crypto = $crypto;
        $this->mailer = $mailer;
    }


    /**
     * @throws Nette\Application\AbortException
     * @throws Nette\InvalidStateException
     */
    public function renderIn(): void
    {
        if ($this->user->isLoggedIn()) {
            $this->restoreRequest($this->backlink);
            $this->redirect('Dashboard:');
        }

        // Force start session before send content (need to protection)
        $this->getSession()->start();
    }


    /**
     *
     */
    public function renderReset(): void
    {
        if ($this->user->isLoggedIn()) {
            /** @var Identity $identity */
            $identity = $this->user->getIdentity();
            $email = $identity->email;

            /** @var UI\Form $resetForm */
            $resetForm = $this['resetForm'];
            /** @var TextInput $emailFiels */
            $emailFiels = $resetForm['email'];
            $emailFiels->setDefaultValue($email);
        }
    }


    /**
     * @param string $token
     * @throws Nette\Application\AbortException
     */
    public function renderChangePassword($token): void
    {
        try {
            $this->getUserActiveRowFromToken($token)->toArray();

            /** @var UI\Form $passwordForm */
            $passwordForm = $this['setPasswordForm'];
            /** @var Nette\Forms\Controls\HiddenField $tokenFiled */
            $tokenFiled = $passwordForm['token'];
            $tokenFiled->setDefaultValue($token);
        } catch (SignResetPasswordTokenException $e) {
            $this->flashMessage($e->getMessage(), 'danger');
            $this->redirect('Dashboard:');
        }
    }


    /**
     * @throws Nette\Application\AbortException
     */
    public function actionOut(): void
    {
        $this->user->logout(true);
        $this->redirect(':Static:');
    }


    /**
     * @return UI\Form
     */
    public function createComponentSignInForm(): UI\Form
    {
        $form = new UI\Form;
        $form->addEmail('email', 'E-mail:')
            ->setRequired('E-mail musí být vyplněn')
            ->setHtmlAttribute('autofocus')
            ->setHtmlAttribute('autocomplete', 'username');

        $form->addPassword('password', 'Heslo:')
            ->setRequired('heslo musí být vyplněno')
            ->setHtmlAttribute('autocomplete', 'current-password');

        $form->addCheckbox('remember', 'Zůstat přihlášen');

        $form->addSubmit('send', 'Přihlásit');

        $form->addProtection('Z důvodu ochrany prosím odešlete ještě jednou');

        $form->onSuccess[] = [$this, 'signInFormSuccess'];
        BootstrapizeForm::bootstrapize($form);
        return $form;
    }


    /**
     * @param UI\Form $form
     * @param ArrayHash $values
     * @throws Nette\Application\AbortException
     */
    public function signInFormSuccess(UI\Form $form, ArrayHash $values): void
    {
        try {
            $this->user->setExpiration($values->remember ? '14 days' : '20 minutes');
            $this->user->login($values['email'], $values['password']);
        } catch (Nette\Security\AuthenticationException $e) {
            $form->addError('The username or password you entered is incorrect.');
            return;
        }

        if ($this->backlink !== null) {
            $this->restoreRequest($this->backlink);
        }
        $this->redirect('Dashboard:');
    }


    /**
     * @return UI\Form
     */
    public function createComponentResetForm(): UI\Form
    {
        $form = new UI\Form;
        $form->addEmail('email', 'E-mail:')
            ->setRequired('E-mail musí být vyplněn')
            ->setHtmlAttribute('autofocus')
            ->setHtmlAttribute('autocomplete', 'email');

        $form->addSubmit('send', 'Zahájit reset hesla');

        $form->addProtection('Z důvodu ochrany prosím odešlete ještě jednou');

        $form->onSuccess[] = [$this, 'resetFormSuccess'];
        BootstrapizeForm::bootstrapize($form);
        return $form;
    }


    /**
     * @param UI\Form $form
     * @throws CryptoException
     * @throws Nette\Application\AbortException
     * @throws Nette\InvalidArgumentException
     * @throws UI\InvalidLinkException
     */
    public function resetFormSuccess(UI\Form $form): void
    {
        $values = $form->values;
        $email = $values['email'];

        try {
            $hash = $this->userManager->startReset($email);
            $key = strtoupper(substr($hash, 0, 6));
            $plainData = [
                'key' => $key,
                'hash' => $hash,
                'email' => $email,
                'expire' => (new DateTime('+ 1 hour'))->format(DateTime::ATOM),
            ];
            $token = $this->crypto->encryptArray($plainData);
            $this->sendChangeNotification($email, $key, $token);
        } catch (UserNotFoundException $e) {
            // Not exists user - fake hash
            $key = strtoupper(Random::generate(6));
        }

        $this->flashMessage(
            "Reset hesla byl zahájen s označením: „${key}“. Pokud Vámi zadaný e-mail" .
            ' existuje v databázi, byla na něj právě odeslána zpráva s pokyny pro dokončení procesu. Všechny dříve' .
            ' odeslané žádosti o reset hesla jsou tímto okamžikem zneplatněny, funkční bude pouze e-mail s označením:' .
            " „${key}“. Žádost je platná 1 hodinu.",
            'success'
        );
        $this->redirect('this');
    }


    /**
     * @return UI\Form
     */
    public function createComponentSetPasswordForm(): UI\Form
    {
        $form = new UI\Form;
        $form->addHidden('token');

        $form->addPassword('password', 'Heslo:')
            ->setRequired('Heslo musí být vyplněno')
            ->setHtmlAttribute('autofocus')
            ->setHtmlAttribute('autocomplete', 'new-password');

        $form->addPassword('password2', 'Heslo znovu:')
            ->setRequired('Heslo musí být vyplněno')
            ->addRule(UI\Form::EQUAL, 'Hesla se neshodují', $form['password'])
            ->setHtmlAttribute('autocomplete', 'new-password');

        $form->addSubmit('send', 'Nastavit nové heslo');

        $form->addProtection('Z důvodu ochrany prosím odešlete ještě jednou');

        $form->onSuccess[] = [$this, 'setPasswordFormSuccess'];
        BootstrapizeForm::bootstrapize($form);
        return $form;
    }


    /**
     * @param UI\Form $form
     * @param ArrayHash $values
     * @throws Nette\Application\AbortException
     */
    public function setPasswordFormSuccess(UI\Form $form, ArrayHash $values): void
    {
        try {
            $user = $this->getUserActiveRowFromToken($values['token']);
        } catch (SignResetPasswordTokenException $e) {
            $form->addError($e->getMessage());
            return;
        }

        $email = $user->email;
        $password = $values['password'];
        $this->userManager->setPassword($email, $password);

        $this->flashMessage('Heslo bylo změněno', 'success');
        $this->redirect('Sign:in');
    }


    /**
     * @param string $email
     * @param string $key
     * @param string $token
     * @throws UI\InvalidLinkException
     */
    private function sendChangeNotification(string $email, string $key, string $token): void
    {
        $templateFile = __DIR__ . '/templates/Sign/resetMail.latte';
        $latte = new Latte\Engine;
        $mail = new Mail\Message;

        $params = [
            'title' => "Reset hesla k webu ikofein.cz ($key)",
            'link' => $this->link('//changePassword', ['token' => $token]),
            'key' => $key,
        ];

        $mail->setFrom('no-reply@ikofein.cz', 'Kofein automat')
            ->setSubject($params['title'])
            ->addTo($email)
            ->setHtmlBody($latte->renderToString($templateFile, $params));
        $mailer = $this->mailer;
        $mailer->send($mail);
    }


    /**
     * @param string $token
     * @return bool|mixed|Nette\Database\Table\IRow
     * @throws SignResetPasswordTokenException
     */
    private function getUserActiveRowFromToken(string $token)
    {
        try {
            $plainData = $this->crypto->decryptArray($token);
        } catch (CryptoException $e) {
            throw new SignResetPasswordTokenException(
                'Odkaz na reset hesla je poškozený, zkuste jej poslat znovu.',
                0,
                $e
            );
        }

        $expire = new DateTime($plainData['expire']);
        $now = new DateTime();
        if ($expire < $now) {
            throw new SignResetPasswordTokenException(
                'Odkaz na reset hesla již expiroval, zkuste jej poslat znovu.',
                0
            );
        }

        try {
            $user = $this->userManager->getUserByEmail($plainData['email']);
        } catch (UserNotFoundException $e) {
            throw new SignResetPasswordTokenException('Uživatel neexistuje, nebo byl smazán', 0);
        }

        if ($user['reset_hash'] !== $plainData['hash']) {
            throw new SignResetPasswordTokenException(
                'Odkaz na reset hesla byl zněplatněn, zkuste jej poslat znovu.',
                0
            );
        }

        return $user;
    }
}
