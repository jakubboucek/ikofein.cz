<?php

declare(strict_types=1);

namespace App\Presenters;

use App\Component\PostRenderer;
use App\Model\PageNotFoundException;
use App\Model\WebDir;
use Nette;
use Nette\Application\BadRequestException;

class StaticPresenter extends Nette\Application\UI\Presenter
{
    public const LANG_COOKIE = 'lang';

    private const LANGS = [
        'en' => 'en-US',
        'cs' => 'cs-CZ'
    ];

    private const PAGE_MAP = [
        'homepage' => [-1 => ''],
        'lunch' => ['lunch', 'poledne'],
        'dinner' => ['dinner', 'vecer'],
        'beverages' => ['beverages', 'napoje'],
        'gallery' => ['gallery', 'galerie'],
        'contact' => ['contact', 'kontakt'],
    ];

    private PostRenderer $postRenderer;
    private WebDir $wwwDir;


    public function __construct(PostRenderer $postRenderer, WebDir $wwwDir)
    {
        parent::__construct();
        $this->postRenderer = $postRenderer;
        $this->wwwDir = $wwwDir;
    }


    public function beforeRender(): void
    {
        $this->template->readyForPost = true;
        $this->template->wwwDir = $this->wwwDir->getPath();
    }


    /**
     * @throws BadRequestException
     * @throws Nette\Application\AbortException
     * @throws Nette\Application\UI\InvalidLinkException
     * @throws Nette\InvalidStateException
     */
    public function renderDefault(?string $page = null, ?string $lang = null): void
    {
        [$pageKey, $realLang] = $this->match($page, $lang);

        $this->template->lang = $realLang;
        $this->template->title = $pageKey;
        $this->template->altLangs = $this->getAllLangsLinks($pageKey);

        $this->getHttpResponse()->addHeader('Content-Language', self::LANGS[$realLang]);

        $this->setView("$realLang-$pageKey");
    }


    public function createComponentPost(): PostRenderer
    {
        return $this->postRenderer;
    }


    /**
     * @throws BadRequestException
     * @throws Nette\Application\AbortException
     * @throws Nette\InvalidStateException
     */
    private function match(?string $page, ?string $lang): array
    {
        //homepage - redirect to defined lang variant
        if ($page === null && $lang === null) {
            $this->getHttpResponse()->addHeader('Vary', 'Accept-Language');
            $this->redirectTo([
                '',
                $this->determineLang()
            ]);
        }


        //get clean lang variant
        $safeLang = $this->isLangValid($lang) ? $lang : $this->determineLang();


        //parse page
        try {
            $pageKey = $this->getPageKeyByUrl($page);
        } catch (PageNotFoundException $e) {
            throw new BadRequestException("Unknown page \"$lang/$page\"", Nette\Http\IResponse::S404_NOT_FOUND);
        }


        //get clean page
        $langId = $this->getLangKey($safeLang);
        if (isset(self::PAGE_MAP[$pageKey][$langId])) {      //lang url variant
            $safePage = self::PAGE_MAP[$pageKey][$langId];
        } elseif (isset(self::PAGE_MAP[$pageKey][-1])) {     //universal url variant
            $safePage = self::PAGE_MAP[$pageKey][-1];
        } else {
            throw new Nette\InvalidStateException("In pageMap is missing page \"$lang/$page\"");
        }

        $page = $page ?? '';

        //check and fix canonical URL
        if ($lang !== $safeLang || $page !== $safePage) {
            $this->redirectTo([$safePage, $safeLang]);
        }

        return [$pageKey, $lang];
    }


    /**
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


    private function getLangKey(?string $lang): ?int
    {
        if ($lang === 'cz') {
            $lang = 'cs';
        }

        $langKey = array_key_exists($lang, self::LANGS);

        if ($langKey === false) {
            return null;
        }

        return (int)$langKey;
    }


    private function determineLang(string $default = ''): string
    {
        $lang = $this->getBrowserLang($default);
        $lang = $this->getCookiesLang($lang);

        if ($lang === 'cz') {
            $lang = 'cs';
        }

        // If lang invalid (or empty), use default
        if (!$this->isLangValid($lang)) {
            $lang = $this->getDefaultLang();
        }

        return $lang;
    }


    private function getBrowserLang(string $default = ''): string
    {
        $request = $this->getHttpRequest();

        if ($request instanceof Nette\Http\Request) {
            $detectedLanguage = $request->detectLanguage(array_keys(self::LANGS));
            return $detectedLanguage ?? $default;
        }

        return substr($request->getHeader('Accept-Language') ?? $default, 0, 2);
    }


    private function getCookiesLang(string $default = ''): string
    {
        $cookie = $this->getHttpRequest()->getCookie(self::LANG_COOKIE);
        return $cookie ?? $default;
    }


    private function getDefaultLang(): string
    {
        return array_key_first(self::LANGS);
    }


    /**
     * @throws PageNotFoundException
     */
    private function getPageKeyByUrl(?string $keyUrl): string
    {
        $reversedMap = $this->getReversedPageMap();

        if (isset($reversedMap[$keyUrl])) {
            return $reversedMap[$keyUrl];
        }

        throw new PageNotFoundException("Unknown page $keyUrl in pageMap");
    }


    /**
     * Return 1D array with simplified pair url=>key from $pageMap
     * @return array
     */
    private function getReversedPageMap(): array
    {
        $reversedMap = [];

        foreach (self::PAGE_MAP as $pageKey => $keyUrls) {
            foreach ($keyUrls as $langId => $keyUrl) {
                $reversedMap[$keyUrl] = $pageKey;
            }
        }

        return $reversedMap;
    }


    /**
     * Return array of absolute URLs to Static::default presenter for all available langs
     * @throws Nette\Application\UI\InvalidLinkException
     */
    private function getAllLangsLinks(string $pageKey): array
    {
        $keyUrls = self::PAGE_MAP[$pageKey];
        $links = [];
        if (count($keyUrls) && key($keyUrls) === -1) {
            $keyUrl = current($keyUrls);
            foreach (self::LANGS as $lang => $fullLang) {
                $url = $this->link('//default', [
                    'lang' => $lang,
                    'page' => $keyUrl,
                ]);
                $links[$lang] = $url;
            }
        } else {
            $langKeys = array_keys(self::LANGS);
            foreach ($keyUrls as $langId => $keyUrl) {
                $lang = $langKeys[$langId];
                $url = $this->link('//default', [
                    'lang' => $lang,
                    'page' => $keyUrl,
                ]);
                $links[$lang] = $url;
            }
        }
        return $links;
    }


    private function isLangValid(?string $lang): bool
    {
        return array_key_exists($lang, self::LANGS);
    }
}
