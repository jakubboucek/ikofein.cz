<?php

declare(strict_types=1);

namespace App\AdminModule\Presenters;

use App\Forms\BootstrapizeForm;
use App\Model\PasswordReset;
use App\Model\InvalidPasswordResetException;
use App\Model\UserManager;
use Latte;
use Nette;
use Nette\Application\Attributes\Persistent;
use Nette\Application\UI;
use Nette\Forms\Control;
use Nette\Forms\Controls\TextInput;
use Nette\Mail;
use Nette\Security\SimpleIdentity;
use Nette\Utils\ArrayHash;

class SignPresenter extends Nette\Application\UI\Presenter
{
    #[Persistent]
    public ?string $backlink = null;


    public function __construct(
        private readonly UserManager $userManager,
        private readonly PasswordReset $passwordReset,
        private readonly Mail\Mailer $mailer,
    ) {
        parent::__construct();
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
    }


    public function renderReset(): void
    {
        if ($this->getUser()->isLoggedIn()) {
            /** @var SimpleIdentity $identity */
            $identity = $this->getUser()->getIdentity();
            $email = $identity->email;

            /** @var UI\Form $resetForm */
            $resetForm = $this['resetForm'];
            /** @var TextInput $emailField */
            $emailField = $resetForm['email'];
            $emailField->setDefaultValue($email);
        }
    }


    /**
     * @throws Nette\Application\AbortException
     */
    public function renderChangePassword(string $token): void
    {
        try {
            $this->passwordReset->verify($token, $this->getHttpRequest()->getRemoteAddress() ?? 'unknown');

            /** @var UI\Form $passwordForm */
            $passwordForm = $this['setPasswordForm'];
            /** @var Nette\Forms\Controls\HiddenField $tokenField */
            $tokenField = $passwordForm['token'];
            $tokenField->setDefaultValue($token);
        } catch (InvalidPasswordResetException $e) {
            $this->flashMessage($e->getMessage(), 'danger');
            $this->redirect('Dashboard:');
        }
    }


    public function actionOut(): never
    {
        $this->getUser()->logout(true);
        $this->redirect(':Static:');
    }

    protected function startup(): void
    {
        parent::startup();
        $this->getSession()->start();
        $this->getHttpResponse()->setHeader('Referrer-Policy', 'no-referrer');
        $this->getHttpResponse()->setHeader('Cache-Control', 'no-store');
        $identity = $this->getUser()->getIdentity();
        if ($this->getUser()->isLoggedIn() && (!$identity || !$this->userManager->isIdentityCurrent($identity))) {
            $this->getUser()->logout(true);
        }
    }

    public function renderCancelReset(string $token): void
    {
        $this['cancelResetForm']->setDefaults(['token' => $token]);
    }

    public function createComponentCancelResetForm(): UI\Form
    {
        $form = new UI\Form;
        $form->addHidden('token');
        $form->addSubmit('send', 'Zrušit žádost o změnu hesla');
        $form->onSuccess[] = function (UI\Form $form, ArrayHash $values): void {
            try {
                $this->passwordReset->cancel($values['token'], $this->getHttpRequest()->getRemoteAddress() ?? 'unknown');
            } catch (InvalidPasswordResetException) {
                // Cancellation is deliberately idempotent from the user's perspective.
            }
            $this->flashMessage('Žádost byla zrušena nebo již není platná.', 'success');
            $this->redirect('Sign:in');
        };
        BootstrapizeForm::bootstrapize($form);
        return $form;
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

        $form->onSuccess[] = $this->signInFormSuccess(...);
        BootstrapizeForm::bootstrapize($form);
        return $form;
    }


    /**
     * @throws Nette\Application\AbortException
     */
    public function signInFormSuccess(UI\Form $form, ArrayHash $values): void
    {
        try {
            $this->getUser()->setExpiration($values->remember ? '14 days' : '20 minutes');
            $this->getUser()->login($values['email'], $values['password']);
        } catch (Nette\Security\AuthenticationException) {
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

        $form->onSuccess[] = $this->resetFormSuccess(...);
        BootstrapizeForm::bootstrapize($form);
        return $form;
    }


    public function resetFormSuccess(UI\Form $form): void
    {
        $values = $form->getValues();
        $email = $values['email'];

        $request = $this->passwordReset->request($email, $this->getHttpRequest()->getRemoteAddress() ?? 'unknown');
        if ($request !== null) {
            try {
                $this->sendChangeNotification($request['email'], $request['token']);
            } catch (Mail\SendException) {
                // Do not expose account existence or log reset tokens via exception arguments.
                \Tracy\Debugger::log('Password reset email delivery failed.', \Tracy\ILogger::ERROR);
            }
        }
        $this->flashMessage(
            'Pokud účet existuje a nebyl překročen limit žádostí, obdržíte e-mail s odkazem platným jednu hodinu. Nový odkaz nahrazuje předchozí.',
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
            ->addRule(UI\Form::MinLength, 'Heslo musí mít alespoň %d znaků.', 15)
            ->addRule(static fn(Control $input): bool => strlen($input->getValue()) <= 72, 'Heslo smí mít nejvýše 72 bajtů v UTF-8.')
            ->setHtmlAttribute('autofocus')
            ->setHtmlAttribute('autocomplete', 'new-password');

        $form->addPassword('password2', 'Heslo znovu:')
            ->setRequired('Heslo musí být vyplněno')
            ->addRule(UI\Form::MinLength, 'Heslo musí mít alespoň %d znaků.', 15)
            ->addRule(static fn(Control $input): bool => strlen($input->getValue()) <= 72, 'Heslo smí mít nejvýše 72 bajtů v UTF-8.')
            ->addRule(UI\Form::Equal, 'Hesla se neshodují', $form['password'])
            ->setHtmlAttribute('autocomplete', 'new-password');

        $form->addSubmit('send', 'Nastavit nové heslo');

        $form->onSuccess[] = $this->setPasswordFormSuccess(...);
        BootstrapizeForm::bootstrapize($form);
        return $form;
    }


    /**
     * @throws Nette\Application\AbortException
     */
    public function setPasswordFormSuccess(UI\Form $form, ArrayHash $values): void
    {
        try {
            $email = $this->passwordReset->complete($values['token'], $values['password'], $this->getHttpRequest()->getRemoteAddress() ?? 'unknown');
        } catch (InvalidPasswordResetException $e) {
            $form->addError($e->getMessage());
            return;
        }
        $this->getUser()->logout(true);
        try {
            $mail = (new Mail\Message)
                ->setFrom('no-reply@ikofein.cz', 'Kofein automat')
                ->addTo($email)
                ->setSubject('Heslo k webu ikofein.cz bylo změněno')
                ->setBody('Vaše heslo bylo změněno a předchozí přihlášení zneplatněna. Pokud jste změnu neprovedli, kontaktujte správce webu.');
            $this->mailer->send($mail);
        } catch (Mail\SendException) {
            \Tracy\Debugger::log('Password change notification delivery failed.', \Tracy\ILogger::ERROR);
        }
        $this->flashMessage('Heslo bylo změněno', 'success');
        $this->redirect('Sign:in');
    }


    /**
     * @throws UI\InvalidLinkException
     * @throws Mail\SendException
     */
    private function sendChangeNotification(string $email, string $token): void
    {
        $templateFile = __DIR__ . '/templates/Sign/resetMail.latte';
        $latte = new Latte\Engine;
        $mail = new Mail\Message;

        $abuseLink = new Nette\Http\UrlImmutable('mailto:pan@jakubboucek.cz')
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
        $this->mailer->send($mail);
    }


}
