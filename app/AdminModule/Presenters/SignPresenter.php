<?php

declare(strict_types=1);

namespace App\AdminModule\Presenters;

use App\Forms\BootstrapizeForm;
use App\Model\Jwt\Jwt;
use App\Model\Jwt\JwtException;
use App\Model\UserManager;
use App\Model\UserNotFoundException;
use JakubBoucek\Aws\SesMailer;
use Latte;
use Nette;
use Nette\Application\UI;
use Nette\Database\Table\ActiveRow;
use Nette\Forms\Controls\TextInput;
use Nette\Mail;
use Nette\Security\Identity;
use Nette\Utils\ArrayHash;

class SignPresenter extends Nette\Application\UI\Presenter
{
    private const RESET_PASSWORD_AUDIENCE = 'reset-password';

    /** @persistent */
    public ?string $backlink = null;
    private UserManager $userManager;
    private Jwt $jwt;
    private SesMailer $mailer;


    public function __construct(UserManager $userManager, Jwt $jwt, SesMailer $mailer)
    {
        parent::__construct();
        $this->userManager = $userManager;
        $this->jwt = $jwt;
        $this->mailer = $mailer;
    }


    /**
     * @throws Nette\Application\AbortException
     * @throws Nette\InvalidStateException
     */
    public function renderIn(): void
    {
        if ($this->getUser()->isLoggedIn()) {
            if ($this->backlink) {
                $this->restoreRequest($this->backlink);
            }
            $this->redirect('Dashboard:');
        }

        // Force start session before send content (need to protection)
        $this->getSession()->start();
    }


    public function renderReset(): void
    {
        if ($this->getUser()->isLoggedIn()) {
            /** @var Identity $identity */
            $identity = $this->getUser()->getIdentity();
            $email = $identity->email;

            /** @var UI\Form $resetForm */
            $resetForm = $this['resetForm'];
            /** @var TextInput $emailFiels */
            $emailFiels = $resetForm['email'];
            $emailFiels->setDefaultValue($email);
        }

        // Force start session before send content (need to protection)
        $this->getSession()->start();
    }


    /**
     * @throws Nette\Application\AbortException
     */
    public function renderChangePassword(string $token): void
    {
        try {
            $this->getUserActiveRowFromToken($token);

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

    public function actionCancelReset(string $token): void
    {
        try {
            $user = $this->getUserActiveRowFromToken($token);
            $this->userManager->stopResetPassword($user->email);
        } catch (SignResetPasswordTokenException $e) {
            // User doesn't exists, just fake response
            /** @noinspection PhpUnhandledExceptionInspection */
            usleep(random_int(10_000, 200_000));
        }

        $this->flashMessage('Proces pro reset hesla byl zrušen, odkaz v e-mailu je nyní neplatný.', 'success');
        $this->redirect('Dashboard:');
    }

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
     * @throws Nette\Application\AbortException
     * @throws Nette\InvalidArgumentException
     * @throws UI\InvalidLinkException
     */
    public function resetFormSuccess(UI\Form $form): void
    {
        $values = $form->values;
        $email = $values['email'];

        try {
            $token = $this->userManager->startResetPassword($email);
            $jwt = $this->jwt->encode($email, $token, '+1 hour', $this->getResetPasswordAudience());
            $this->sendChangeNotification($email, $jwt);
        } catch (UserNotFoundException $e) {
            // User doesn't exists, just fake response
            /** @noinspection PhpUnhandledExceptionInspection */
            usleep(random_int(10_000, 200_000));
        }

        $this->flashMessage(
            'Proces pro reset hesla byl zahájen. Pokud Vámi zadaný e-mail' .
            ' existuje v databázi, byla na něj právě odeslána zpráva s pokyny pro dokončení procesu. Všechny dříve' .
            ' odeslané žádosti o reset hesla jsou tímto okamžikem zneplatněny.' .
            ' Žádost je platná 1 hodinu.',
            'success'
        );
        $this->redirect('this');
    }


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
        $this->userManager->stopResetPassword($email);

        $this->flashMessage('Heslo bylo změněno', 'success');
        $this->redirect('Sign:in');
    }


    /**
     * @throws UI\InvalidLinkException
     */
    private function sendChangeNotification(string $email, string $token): void
    {
        $templateFile = __DIR__ . '/templates/Sign/resetMail.latte';
        $latte = new Latte\Engine;
        $mail = new Mail\Message;

        $abuseLink = (new Nette\Http\UrlImmutable('mailto:pan@jakubboucek.cz'))
            ->withQueryParameter('subject', 'Bounce: Remove unwanted notifications (ikofein.cz)')
            ->withQueryParameter('body', 'Dostávám e-maily [ikofein.cz/admin/sign/resetPessword], žádám o zrušení!');

        $params = [
            'title' => "Reset hesla k webu ikofein.cz",
            'resetLink' => $this->link('//changePassword', ['token' => $token]),
            'cancelLink' => $this->link('//cancelReset', ['token' => $token]),
            'abuseLink' => $abuseLink,
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
     * @return ActiveRow
     * @throws SignResetPasswordTokenException
     */
    private function getUserActiveRowFromToken(string $token): ActiveRow
    {
        try {
            $tokenData = $this->jwt->decode($token, $this->getResetPasswordAudience());
        } catch (JwtException $e) {
            throw new SignResetPasswordTokenException(
                'Odkaz na reset hesla je poškozený, zkuste jej poslat znovu.',
                0,
                $e
            );
        }

        $email = $tokenData['subject'];
        $token = $tokenData['value'];

        try {
            $user = $this->userManager->getUserByEmail($email);
        } catch (UserNotFoundException $e) {
            throw new SignResetPasswordTokenException('Uživatel neexistuje, nebo byl smazán');
        }

        if ($this->userManager->verifyResetPasswordToken($email, $token) === false) {
            throw new SignResetPasswordTokenException('Odkaz na reset hesla byl zneplatněn, zkuste jej poslat znovu.');
        }

        return $user;
    }

    private function getResetPasswordAudience(): string
    {
        return Jwt::ISSUER_ID . '/' . self::RESET_PASSWORD_AUDIENCE;
    }
}
