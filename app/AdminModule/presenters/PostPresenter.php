<?php

namespace App\AdminModule\Presenters;

use App\Model,
	App\Forms\BootstrapizeForm,
	Latte,
	Nette,
	Nette\Application\BadRequestException,
	Nette\Application\UI,
	Nette\Mail,
	Nette\Http\Request,
	Nette\Http\Response,
	JakubBoucek\Aws;

class PostPresenter extends Nette\Application\UI\Presenter
{
	private $postModel;
	private $mailer;

	public function __construct( Model\Post $postModel, Aws\SesMailer $mailer ) {
        $this->postModel = $postModel;
        $this->mailer = $mailer;
    }

	public function startup() {
		parent::startup();
		if( ! $this->user->isLoggedIn() ) {
			$this->redirect('Sign:in');
		}
	}

	public function renderDetail($key) {
		try {
		$post = $this->postModel->getPostByKey($key);
		}
		catch(Model\PostNotFoundException $e) {
			throw new BadRequestException( "Post with key \"$key\" doesn't exists.", 404, $e );
		}


		$form = $this['postEditForm'];
		$defaults = [
			'key' => $key,
			'published' => $post['info']['isPublished'],
			'published_from' => $post['published_from'] ? $post['published_from']->format('Y-m-d') : NULL ,
			'published_to' => $post['published_to'] ? $post['published_to']->format('Y-m-d') : NULL ,
			'content_cs' => $post['content_cs'],
			'content_en' => $post['content_en'],
		];
		$form->setDefaults($defaults);

		$this->template->post = $post;
	}

	public function createComponentPostEditForm() {
		$form = new UI\Form;
		$form->addCheckbox('published', 'Publikováno')
			->setOption('description', 'Skryjete-li příspěvek, bude jeho místo prázdné');

		$form->addText('published_from', 'Publikováno od')
			->setType('date')
			->setOption('description', 'Před tímto časem se příspěvek nezobrazí. Nevyplňujte, nechcete-li plánovat zobrazení.');

		$form->addText('published_to', 'Publikováno do')
			->setType('date')
			->setOption('description', 'Po tomto čase se příspěvek nezobrazí (naposledy bude zobrazen před tímto datem). Nevyplňujte, nechcete-li plánovat konec zobrazení.');

		$form->addTextArea('content_cs', 'Český obsah')
			->getControlPrototype()->setRows(20);

		$form->addTextArea('content_en', 'Anglický obsah')
			->getControlPrototype()->setRows(20);

		$form->addSubmit('send', 'Uložit');

		$form->addHidden('key');

		$form->addProtection('Z důvodu ochrany prosím odešlete ještě jednou');

		$form->onSuccess[] = [$this, 'postEditFormSuccess'];
		BootstrapizeForm::bootstrapize( $form );
		return $form;
	}

	public function postEditFormSuccess (UI\Form $form, $values) {
		$currentDate = new \DateTime();

		if($values['published_from']) {
			$values['published_from'] = new \DateTime($values['published_from']);
		}
		else {
			$values['published_from'] = NULL;
		}

		if($values['published_to']) {
			$values['published_to'] = new \DateTime($values['published_to']);
		}
		else {
			$values['published_to'] = NULL;
		}

		if($values['published'] && empty($values['published_from'])) {
			$values['published_from'] = $currentDate;
		}
		elseif($values['published'] && $values['published_from'] > $currentDate ) {
			$form->addError('Čas publikování je nastaven do budoucna, ale současně máte uvedeno, že se má nyní publikovat. Buď zrušte volbu publikovat a nebo smažte datum publikování.');
		}

		if( $values['published'] && $values['published_to'] && $values['published_to'] < $currentDate ) {
			$form->addError('Čas konce publikování již nastal, ale současně máte uvedeno, že se má nyní publikovat. Buď zrušte volbu publikovat a nebo smažte datum publikování.');
		}

		if($form->hasErrors()) {
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

	private function sendChangeNotification( $post ) {
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
