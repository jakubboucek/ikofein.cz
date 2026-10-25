<?php

declare(strict_types=1);

namespace App\AdminModule\Presenters;

use App\Forms\BootstrapizeForm;
use App\Forms\PostEditFormData;
use App\Model;
use App\Entity\Post;
use DateTimeImmutable;
use Latte;
use Nette;
use Nette\Application\BadRequestException;
use Nette\Application\UI;
use Nette\Bridges\ApplicationLatte\DefaultTemplate;
use Nette\Mail;

/** @property-read DefaultTemplate $template */
class PostPresenter extends Nette\Application\UI\Presenter
{
    use RequireLoggedUser;


    public function __construct(
        private readonly Model\PostManager $postManager,
        private readonly Mail\Mailer $mailer,
    ) {
        parent::__construct();
    }


    /**
     * @throws BadRequestException
     */
    public function renderDetail(string $key): void
    {
        try {
            $post = $this->postManager->getPostByKey($key);
        } catch (Model\PostNotFoundException $e) {
            throw new BadRequestException("Post with key \"$key\" doesn't exists.", 404, $e);
        }


        /** @var UI\Form $form */
        $form = $this['postEditForm'];
        $defaults = [
            'key' => $key,
            'published' => $post->isPublished(),
            'published_from' => $post->publishedFrom?->format('Y-m-d'),
            'published_to' => $post->publishedTo?->format('Y-m-d'),
            'content_cs' => $post->contentCs,
            'content_en' => $post->contentEn,
        ];
        $form->setDefaults($defaults);

        $this->template->post = $post;
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


    public function postEditFormSuccess(UI\Form $form, PostEditFormData $values): void
    {
        $currentDate = new DateTimeImmutable();

        $publishedFrom = $values->published_from !== '' ? new DateTimeImmutable($values->published_from) : null;
        $publishedTo = $values->published_to !== '' ? new DateTimeImmutable($values->published_to) : null;

        if ($values->published) {
            if ($publishedFrom === null) {
                $publishedFrom = $currentDate;
            } elseif ($publishedFrom > $currentDate) {
                $form->addError(
                    'Čas publikování je nastaven do budoucna, ale současně máte uvedeno, že se má nyní publikovat.'
                    . ' Buď zrušte volbu publikovat a nebo smažte datum publikování.'
                );
            }
        }

        if ($values->published && $publishedTo && $publishedTo < $currentDate) {
            $form->addError(
                'Čas konce publikování již nastal, ale současně máte uvedeno, že se má nyní publikovat.'
                . ' Buď zrušte volbu publikovat a nebo smažte datum publikování.'
            );
        }

        if ($form->hasErrors()) {
            return;
        }

        $changes = new Post;
        $changes->publishedFrom = $publishedFrom;
        $changes->publishedTo = $publishedTo;
        $changes->contentCs = $values->content_cs;
        $changes->contentEn = $values->content_en;

        $current = $this->postManager->save($values->key, $changes);
        $this->sendChangeNotification($current);

        $this->flashMessage('Uloženo', 'success');
        $this->redirect('Dashboard:');
    }


    private function sendChangeNotification(Post $post): void
    {
        $templateFile = __DIR__ . '/templates/Post/changeNotificationMail.latte';
        $latte = new Latte\Engine;
        $mail = new Mail\Message;

        $params = [
            'title' => "Změna na webu ikofein.cz ({$post->title})",
            'post' => $post,
        ];

        $mail->setFrom('no-reply@ikofein.cz', 'Kofein automat')
            ->setSubject($params['title'])
            ->addTo('pan@jakubboucek.cz', 'Jakub Bouček')
            ->setHtmlBody($latte->renderToString($templateFile, $params));
        $this->mailer->send($mail);
    }
}
