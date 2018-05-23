<?php

namespace App\Presenters;

use App\Component\PostRenderer;
use App\Model\PageNotFound;
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
        //homepage - redirect to defined lang variant
        if (empty($page) && empty($lang)) {
            $this->redirectTo([
                '',
                $this->determineLang()
            ]);
        }


        //get clean lang variant
        $langId = $this->getLangKey($lang);
        if ($langId === false) {
            $langId = $this->getLangKey($this->determineLang());
        }
        $safeLang = $this->langs[$langId];


        //parse page
        try {
            $pageKey = $this->getPageKeyByUrl($page);
        } catch (PageNotFound $e) {
            throw new BadRequestException("Unknown page \"$lang/$page\"");
        }


        //get clean page
        if (isset($this->pageMap[$pageKey][$langId])) {      //lang url variant
            $safePage = $this->pageMap[$pageKey][$langId];
        } elseif (isset($this->pageMap[$pageKey][-1])) {     //universal url variant
            $safePage = $this->pageMap[$pageKey][-1];
        } else {
            throw new Nette\InvalidStateException("In pageMap is missing page \"$lang/$page\"");
        }


        //check and fix canonical URL
        if ($lang != $safeLang || $page != $safePage) {
            $this->redirectTo([$safePage, $safeLang]);
        }

        return [$pageKey, $lang];
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
     * @param string $keyUrl
     * @return string
     * @throws PageNotFound
     */
    private function getPageKeyByUrl($keyUrl)
    {
        $reversedMap = $this->getReversedPageMap();

        if (isset($reversedMap[$keyUrl])) {
            return $reversedMap[$keyUrl];
        }

        throw new PageNotFound();
    }


    /**
     * @return array
     */
    private function getReversedPageMap()
    {
        $reversedMap = [];

        foreach ($this->pageMap as $pageKey => $keyUrls) {
            foreach ($keyUrls as $langId => $keyUrl) {
                $reversedMap[$keyUrl] = $pageKey;
            }
        }

        return $reversedMap;
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
