<?php

declare(strict_types=1);

namespace App\AdminModule\Presenters;

use App\Forms\BootstrapizeForm;
use App\Model;
use DateTime;
use DateTimeInterface;
use Latte;
use Nette;
use Nette\Application\BadRequestException;
use Nette\Application\UI;
use Nette\Application\UI\Form;
use Nette\Mail;
use Nette\Utils\ArrayHash;

class PostPresenter extends Nette\Application\UI\Presenter
{
    use RequireLoggedUser;


    public function __construct(private Model\Post $postModel, private Mail\Mailer $mailer)
    {
        parent::__construct();
    }


    /**
     * @throws BadRequestException
     */
    public function renderDetail(string $key): void
    {
        try {
            $post = $this->postModel->getPostByKey($key);
        } catch (Model\PostNotFoundException $e) {
            throw new BadRequestException("Post with key \"$key\" doesn't exists.", 404, $e);
        }


        /** @var UI\Form $form */
        $form = $this['postEditForm'];
        $defaults = [
            'key' => $key,
            'published' => $post['info']['isPublished'],
            'published_from' => $post['published_from'] instanceof DateTimeInterface ? $post['published_from']->format('Y-m-d') : null,
            'published_to' => $post['published_to'] instanceof DateTimeInterface ? $post['published_to']->format('Y-m-d') : null,
            'content_cs' => $post['content_cs'],
            'content_en' => $post['content_en'],
        ];
        $form->setDefaults($defaults);

        $this->getTemplate()->post = $post;
    }


    public function createComponentPostEditForm(): UI\Form
    {
        $form = new UI\Form;
        $form->addCheckbox('published', 'Publikováno')
            ->setOption('description', 'Skryjete-li příspěvek, bude jeho místo prázdné');

        $form->addText('published_from', 'Publikováno od')
            ->setHtmlType('date')
            ->setOption(
                'description',
                'Před tímto časem se příspěvek nezobrazí. Nevyplňujte, nechcete-li plánovat zobrazení.'
            );

        $form->addText('published_to', 'Publikováno do')
            ->setHtmlType('date')
            ->setOption(
                'description',
                'Po tomto čase se příspěvek nezobrazí (naposledy bude zobrazen před tímto datem).'
                . ' Nevyplňujte, nechcete-li plánovat konec zobrazení.'
            );

        $form->addTextArea('content_cs', 'Český obsah', null, 20);

        $form->addTextArea('content_en', 'Anglický obsah', null, 20);

        $form->addSubmit('send', 'Uložit');

        $form->addHidden('key');

        $form->onSuccess[] = $this->postEditFormSuccess(...);
        BootstrapizeForm::bootstrapize($form);
        return $form;
    }


    public function postEditFormSuccess(UI\Form $form, ArrayHash $values): void
    {
        $currentDate = new DateTime();

        if ($values['published_from']) {
            $values['published_from'] = new DateTime($values['published_from']);
        } else {
            $values['published_from'] = null;
        }

        if ($values['published_to']) {
            $values['published_to'] = new DateTime($values['published_to']);
        } else {
            $values['published_to'] = null;
        }

        if ($values['published']) {
            if (empty($values['published_from'])) {
                $values['published_from'] = $currentDate;
            } elseif ($values['published_from'] > $currentDate) {
                $form->addError(
                    'Čas publikování je nastaven do budoucna, ale současně máte uvedeno, že se má nyní publikovat.'
                    . ' Buď zrušte volbu publikovat a nebo smažte datum publikování.'
                );
            }
        }

        if ($values['published'] && $values['published_to'] && $values['published_to'] < $currentDate) {
            $form->addError(
                'Čas konce publikování již nastal, ale současně máte uvedeno, že se má nyní publikovat.'
                . ' Buď zrušte volbu publikovat a nebo smažte datum publikování.'
            );
        }

        if ($form->hasErrors()) {
            return;
        }

        $data = [
            'published_from' => $values['published_from'],
            'published_to' => $values['published_to'],
            'content_cs' => $values['content_cs'],
            'content_en' => $values['content_en'],
        ];

        $current = $this->postModel->save($values['key'], $data);

        $this->sendChangeNotification($current);

        $this->flashMessage('Uloženo', 'success');
        $this->redirect('Dashboard:');
    }


    private function sendChangeNotification(array $post): void
    {
        $templateFile = __DIR__ . '/templates/Post/changeNotificationMail.latte';
        $latte = new Latte\Engine;
        $mail = new Mail\Message;

        $params = [
            'title' => "Změna na webu ikofein.cz ($post[title])",
            'post' => $post,
        ];

        $mail->setFrom('no-reply@ikofein.cz', 'Kofein automat')
            ->setSubject($params['title'])
            ->addTo('pan@jakubboucek.cz', 'Jakub Bouček')
            ->setHtmlBody($latte->renderToString($templateFile, $params));
        $mailer = $this->mailer;
        $mailer->send($mail);
    }
}
