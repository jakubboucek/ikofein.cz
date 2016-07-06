<?php

namespace App\Component;

use App\Model,
	Nette\Caching,
	Nette\Application\UI\Control;

class PostRenderer extends Control
{
	private $postModel;
	private $cache;

	public function __construct( Model\Post $postModel, Caching\IStorage $storage) {
		$this->postModel = $postModel;
		$this->cache = new Caching\Cache($storage, 'post');
	}

	public function render( $key, $lang, $template = 'post' )
	{
		$post = $this->getPost( $key );


		$template = $this->getTemplateFile( $template );

		$template->isEmpty = empty($post);

		$content = '';
		if(isset($post['content_' . $lang])) {
			$content = $post['content_' . $lang];
		}
		$template->content = $content;

		$template->render();
	}

	private function getPost( $key ) {
		return $this->cache->load( $key, function(& $dependencies) use ($key) {
			$dependencies = [
				Caching\Cache::EXPIRE => '20 minutes',
			];

			$post = $this->postModel->tryFindPostByKey($key, TRUE);

			return $post;
		});
	}

	private function getTemplateFile($templateName) {
		if(!preg_match('/^[-.a-z0-9]+$/i', $templateName)) {
			throw new \Nette\Application\ApplicationException('Invalid template name: ' . $templateName);
		}

		$filename = __DIR__ . '/' . $templateName . '.latte';
		if(!file_exists($filename)){
			throw new \Nette\Application\ApplicationException("Template \"$filename\" doesn't exists");
		}

		$template = $this->template;
		$template->setFile($filename);
		return $template;
	}
}
