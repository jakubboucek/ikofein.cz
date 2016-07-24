<?php

namespace App\Presenters;

use App\Component\PostRenderer,
	Nette,
	Nette\Http,
	Nette\Application\BadRequestException;

class StaticPresenter extends Nette\Application\UI\Presenter
{
	const LANG_COOKIE = 'lang';

	private $langs = ['cs', 'en'];

	private $pageMap = [
		'homepage' => [-1 => ''],
		'lunch' => ['poledne', 'lunch'],
		'dinner' => ['vecer', 'dinner'],
		'beverages' => ['napoje', 'beverages'],
		'gallery' => ['galerie', 'gallery'],
		'contact' => ['kontakt', 'contact'],
	];

	private $aliases = [
		'home' => ['', 'en'],
		'uvod' => ['', 'cs'],
		'index' => ['', 'cs'],

	];

	private $httpRequest;
	private $httpResponse;
	private $postRenderer;

	public function __construct(Http\Request $httpRequest, Http\Response $httpResponse, PostRenderer $postRenderer) {
		$this->httpRequest = $httpRequest;
		$this->httpResponse = $httpResponse;
		$this->postRenderer = $postRenderer;
	}

	public function beforeRender() {
		$this->template->readyForPost = TRUE;
	}

	public function renderDefault($page = '', $lang = NULL) {
		$pageset = $this->match($page, $lang);

		list($pageKey, $realLang) = $pageset;
		$this->template->lang = $realLang;
		$this->template->title = $pageKey;
		$this->template->altLangs = $this->getAllLangsLinks($pageKey);

		$this->setView("$realLang-$pageKey");
	}

	public function createComponentPost() {
		return $this->postRenderer;
	}

	private function match($page, $lang) {
		//check alias
		if(isset($this->aliases[$page])) {
			$this->redirectTo($this->aliases[$page]);
		}

		//determine
		if(empty($page) && empty($lang)) {
			$this->redirectTo([
				'',
				$this->determineLang()
			]);
		}

		//parse map
		foreach ($this->pageMap as $pageKey => $keyUrls) {
			foreach ($keyUrls as $langId => $keyUrl) {
				if($page == $keyUrl) {
					//get lang for universal key
					if($langId == -1) {
						$langId = $this->getLangKey($lang);
						if($langId === FALSE) {
							$langId = $this->getLangKey($this->determineLang());
						}
					}
					if($this->langs[$langId] == $lang) {
						return [$pageKey, $lang];
					}
					else {
						$this->redirectTo([$keyUrl, $this->langs[$langId]]);
					}
				}
			}
		}
		throw new BadRequestException( "Unknown page \"$lang/$page\"" );
	}

	private function redirectTo($pageset) {
		$this->redirect('default', [
			'lang' => $pageset[1],
			'page' => $pageset[0],
		]);
	}

	private function getLangKey($lang) {
		return array_search($lang, $this->langs);
	}

	private function determineLang() {
		$lang = $this->getBrowserLang();
		$lang = $this->getCookiesLang($lang);

		if($lang == 'cz') {
			$lang = 'cs';
		}
		if(!in_array($lang, $this->langs)){
			$lang = 'en';
		}
		return $lang;
	}

	private function getBrowserLang($default = '') {
		$header = $this->httpRequest->getHeader('HTTP_ACCEPT_LANGUAGE', $default);
		return substr($header, 0, 2);
	}

	private function getCookiesLang($default = NULL) {
		return $this->httpRequest->getCookie(self::LANG_COOKIE, $default);
	}

	private function getAllLangsLinks($pageKey) {
		$keyUrls = $this->pageMap[$pageKey];
		$links = [];
		if(count($keyUrls) && key($keyUrls) == -1) {
			$keyUrl = current($keyUrls);
			foreach($this->langs as $lang) {
				$url = $this->link('//default', [
					'lang' => $lang,
					'page' => $keyUrl,
				]);
				$links[$lang] = $url;
			}
		}
		else {
			foreach($keyUrls as $langId => $keyUrl) {
				$lang = $this->langs[$langId];
				$url = $this->link('//default', [
					'lang' => $lang,
					'page' => $keyUrl,
				]);
				$links[$lang] = $url;
			}
		}
		return $links;
	}
}
