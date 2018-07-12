<?php

namespace App\Presenters;

use App\Component\PostRenderer;
use App\Model\PageNotFoundException;
use App\Model\WebDir;
use Nette;
use Nette\Application\BadRequestException;

class StaticPresenter extends Nette\Application\UI\Presenter
{
    public const LANG_COOKIE = 'lang';

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
     * @var PostRenderer
     */
    private $postRenderer;


    /**
     * StaticPresenter constructor.
     * @param PostRenderer $postRenderer
     */
    public function __construct(PostRenderer $postRenderer, WebDir $wwwDir)
    {
        parent::__construct();
        $this->postRenderer = $postRenderer;
        $this->wwwDir = $wwwDir;
    }


    /**
     *
     */
    public function beforeRender()
    {
        $this->template->readyForPost = true;
        $this->template->wwwDir = $this->wwwDir->getPath();
    }


    /**
     * @param string $page
     * @param string|null $lang
     * @throws BadRequestException
     * @throws Nette\Application\AbortException
     * @throws Nette\Application\UI\InvalidLinkException
     */
    public function renderDefault($page = '', $lang = null)
    {
        [$pageKey, $realLang] = $this->match($page, $lang);

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
     * @param string $page
     * @param string|null $lang
     * @return array
     * @throws BadRequestException
     * @throws Nette\Application\AbortException
     */
    private function match($page, $lang)
    {
        //homepage - redirect to defined lang variant
        if (empty($page) && empty($lang)) {
            $this->getHttpResponse()->addHeader('Vary', 'Accept-Language');
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
        } catch (PageNotFoundException $e) {
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
     * @param array $pageset
     * @throws Nette\Application\AbortException
     */
    private function redirectTo(array $pageset): void
    {
        $queryParameters = $this->getHttpRequest()->getQuery();
        $this->redirect('default', $queryParameters + [
                'lang' => $pageset[1],
                'page' => $pageset[0],
            ]);
    }


    /**
     * @param string|null $lang
     * @return false|int
     */
    private function getLangKey($lang)
    {
        if ($lang === 'cz') {
            $lang = 'cs';
        }

        $langKey = array_search($lang, $this->langs, true);

        if ($langKey === false) {
            return false;
        }

        return (int)$langKey;
    }


    /**
     * @return string
     */
    private function determineLang()
    {
        $lang = $this->getBrowserLang();
        $lang = $this->getCookiesLang($lang);

        if ($lang === 'cz') {
            $lang = 'cs';
        }
        if (!\in_array($lang, $this->langs, true)) {
            $lang = 'en';
        }
        return $lang;
    }


    /**
     * @param string $default
     * @return string
     */
    private function getBrowserLang($default = ''): string
    {
        $header = $this->getHttpRequest()->getHeader('accept-language');
        return substr($header ?? $default, 0, 2);
    }


    /**
     * @param string|null $default
     * @return mixed
     */
    private function getCookiesLang($default = null)
    {
        $cookie = $this->getHttpRequest()->getCookie(self::LANG_COOKIE);
        return $cookie ?? $default;
    }


    /**
     * @param string $keyUrl
     * @return string
     * @throws PageNotFoundException
     */
    private function getPageKeyByUrl($keyUrl): string
    {
        $reversedMap = $this->getReversedPageMap();

        if (isset($reversedMap[$keyUrl])) {
            return $reversedMap[$keyUrl];
        }

        throw new PageNotFoundException();
    }


    /**
     * @return array
     */
    private function getReversedPageMap(): array
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
     * @param string $pageKey
     * @return array
     * @throws Nette\Application\UI\InvalidLinkException
     */
    private function getAllLangsLinks($pageKey): array
    {
        $keyUrls = $this->pageMap[$pageKey];
        $links = [];
        if (count($keyUrls) && key($keyUrls) === -1) {
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
