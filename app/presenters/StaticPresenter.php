<?php

namespace App\Presenters;

use App\Component\PostRenderer;
use Nette;
use Nette\Application\BadRequestException;
use Nette\Http;

class StaticPresenter extends Nette\Application\UI\Presenter
{
    const LANG_COOKIE = 'lang';

    /**
     * @var array
     */
    private $langs = ['cs', 'en'];

    /**
     * @var array
     */
    private $pageMap = [
        'homepage' => [-1 => ''],
        'lunch' => ['poledne', 'lunch'],
        'dinner' => ['vecer', 'dinner'],
        'beverages' => ['napoje', 'beverages'],
        'gallery' => ['galerie', 'gallery'],
        'contact' => ['kontakt', 'contact'],
    ];

    /**
     * @var array
     */
    private $aliases = [
        'home' => ['', 'en'],
        'uvod' => ['', 'cs'],
        'index' => ['', 'cs'],

    ];

    /**
     * @var Http\Request
     */
    private $httpRequest;
    /**
     * @var Http\Response
     */
    private $httpResponse;
    /**
     * @var PostRenderer
     */
    private $postRenderer;


    /**
     * StaticPresenter constructor.
     * @param Http\Request $httpRequest
     * @param Http\Response $httpResponse
     * @param PostRenderer $postRenderer
     */
    public function __construct(Http\Request $httpRequest, Http\Response $httpResponse, PostRenderer $postRenderer)
    {
        $this->httpRequest = $httpRequest;
        $this->httpResponse = $httpResponse;
        $this->postRenderer = $postRenderer;
    }


    /**
     *
     */
    public function beforeRender()
    {
        $this->template->readyForPost = true;
        $this->template->wwwDir = $this->context->getParameters()['wwwDir'];
    }


    /**
     * @param string $page
     * @param null $lang
     * @throws BadRequestException
     * @throws Nette\Application\AbortException
     * @throws Nette\Application\UI\InvalidLinkException
     */
    public function renderDefault($page = '', $lang = null)
    {
        $pageset = $this->match($page, $lang);

        list($pageKey, $realLang) = $pageset;
        $this->template->lang = $realLang;
        $this->template->title = $pageKey;
        $this->template->altLangs = $this->getAllLangsLinks($pageKey);

        $this->setView("$realLang-$pageKey");
    }


    /**
     * @return PostRenderer
     */
    public function createComponentPost()
    {
        return $this->postRenderer;
    }


    /**
     * @param $page
     * @param $lang
     * @return array
     * @throws BadRequestException
     * @throws Nette\Application\AbortException
     */
    private function match($page, $lang)
    {
        //check alias
        if (isset($this->aliases[$page])) {
            $this->redirectTo($this->aliases[$page]);
        }

        //determine
        if (empty($page) && empty($lang)) {
            $this->redirectTo([
                '',
                $this->determineLang()
            ]);
        }

        //parse map
        foreach ($this->pageMap as $pageKey => $keyUrls) {
            foreach ($keyUrls as $langId => $keyUrl) {
                if ($page == $keyUrl) {
                    //get lang for universal key
                    if ($langId == -1) {
                        $langId = $this->getLangKey($lang);
                        if ($langId === false) {
                            $langId = $this->getLangKey($this->determineLang());
                        }
                    }
                    if ($this->langs[$langId] == $lang) {
                        return [$pageKey, $lang];
                    } else {
                        $this->redirectTo([$keyUrl, $this->langs[$langId]]);
                    }
                }
            }
        }
        throw new BadRequestException("Unknown page \"$lang/$page\"");
    }


    /**
     * @param $pageset
     * @throws Nette\Application\AbortException
     */
    private function redirectTo($pageset)
    {
        $queryParameters = $this->httpRequest->getQuery();
        $this->redirect('default', $queryParameters + [
                'lang' => $pageset[1],
                'page' => $pageset[0],
            ]);
    }


    /**
     * @param $lang
     * @return false|int|string
     */
    private function getLangKey($lang)
    {
        return array_search($lang, $this->langs);
    }


    /**
     * @return bool|mixed|string
     */
    private function determineLang()
    {
        $lang = $this->getBrowserLang();
        $lang = $this->getCookiesLang($lang);

        if ($lang == 'cz') {
            $lang = 'cs';
        }
        if (!in_array($lang, $this->langs)) {
            $lang = 'en';
        }
        return $lang;
    }


    /**
     * @param string $default
     * @return bool|string
     */
    private function getBrowserLang($default = '')
    {
        $header = $this->httpRequest->getHeader('accept-language', $default);
        return substr($header, 0, 2);
    }


    /**
     * @param null $default
     * @return mixed
     */
    private function getCookiesLang($default = null)
    {
        return $this->httpRequest->getCookie(self::LANG_COOKIE, $default);
    }


    /**
     * @param $pageKey
     * @return array
     * @throws Nette\Application\UI\InvalidLinkException
     */
    private function getAllLangsLinks($pageKey)
    {
        $keyUrls = $this->pageMap[$pageKey];
        $links = [];
        if (count($keyUrls) && key($keyUrls) == -1) {
            $keyUrl = current($keyUrls);
            foreach ($this->langs as $lang) {
                $url = $this->link('//default', [
                    'lang' => $lang,
                    'page' => $keyUrl,
                ]);
                $links[$lang] = $url;
            }
        } else {
            foreach ($keyUrls as $langId => $keyUrl) {
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
