<?php

declare(strict_types=1);

namespace App\Presenters;

use App\Component\PostRenderer;
use App\Model\PageNotFoundException;
use App\Model\WebDir;
use Nette;
use Nette\Application\BadRequestException;
use OutOfRangeException;

class StaticPresenter extends Nette\Application\UI\Presenter
{
    public const LANG_COOKIE = 'lang';

    private const array LANGS = [
        'en' => 'en-US',
        'cs' => 'cs-CZ'
    ];

    private const array PAGE_MAP = [
        'homepage' => ['homepage', 'homepage'],
        'lunch' => ['lunch', 'poledne'],
        'dinner' => ['dinner', 'vecer'],
        'beverages' => ['beverages', 'napoje'],
        'gallery' => ['gallery', 'galerie'],
        'contact' => ['contact', 'kontakt'],
    ];

    private readonly PostRenderer $postRenderer;
    private readonly WebDir $wwwDir;


    public function __construct(PostRenderer $postRenderer, WebDir $wwwDir)
    {
        parent::__construct();
        $this->postRenderer = $postRenderer;
        $this->wwwDir = $wwwDir;
    }


    #[\Override]
    public function beforeRender(): void
    {
        $this->template->readyForPost = true;
        $this->template->wwwDir = $this->wwwDir->getPath();
    }


    /**
     * @throws BadRequestException
     * @throws OutOfRangeException
     * @throws Nette\Application\UI\InvalidLinkException
     */
    public function renderDefault(string $page = null, ?string $lang = null): void
    {
        try {
            [$pageKey, $realLang] = $this->match($page, $lang);
        } catch (PageNotFoundException $e) {
            throw new BadRequestException("Page '$lang/$page' does not found", 0, $e);
        }

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
     * @throws PageNotFoundException|OutOfRangeException
     */
    private function match(string $page, ?string $lang): array
    {
        $requestPage = $page;
        $requestLang = $lang;


        // parse page
        $pageKey = $this->getPageKeyByUrl($page);


        // lang not defined - determine it
        if ($lang === null) {
            // try detect it by cookie
            if (($lang = $this->getCookiesLang()) === null) {
                // otherwise detect it by browser
                $this->getHttpResponse()->addHeader('Vary', 'Accept-Language');
                $lang = $this->getBrowserLang();
            }

            // If detected lang invalid (or empty), use default
            // prevent mystery 404 by invalid HTTP header or cookie
            if ($this->isLangValid($lang) === false) {
                $lang = $this->getDefaultLang();
            }
        }

        $langKey = $this->getLangKey($lang);

        if (isset(self::PAGE_MAP[$pageKey][$langKey]) === false) {
            throw new OutOfRangeException("Unable to search PAGE_MAP[$pageKey][$langKey]");
        }

        $page = self::PAGE_MAP[$pageKey][$langKey];

        $this->getHttpResponse()->setCookie(
            self::LANG_COOKIE,
            $lang,
            '+1 month',
            null,
            null,
            $this->getHttpRequest()->isSecured(),
            true
        );

        //check and fix canonical URL
        if ($requestLang !== $lang || $requestPage !== $page) {
            $this->redirectTo($page, $lang);
        }

        return [$pageKey, $lang];
    }


    /**
     * @throws Nette\Application\AbortException
     */
    private function redirectTo(string $page, string $lang): void
    {
        $queryParameters = $this->getHttpRequest()->getQuery();
        $this->redirect(
            'default',
            $queryParameters + [
                'lang' => $lang,
                'page' => $page,
            ]
        );
    }


    private function getLangKey(string $lang): int
    {
        $langKey = array_search($lang, array_keys(self::LANGS), true);

        if ($langKey === false) {
            throw new PageNotFoundException("Unknown lang '$lang' in LANGS");
        }

        return (int)$langKey;
    }

    private function getBrowserLang(): ?string
    {
        $request = $this->getHttpRequest();

        if ($request instanceof Nette\Http\Request) {
            return $request->detectLanguage(array_keys(self::LANGS));
        }

        if (($header = $request->getHeader('Accept-Language')) !== null) {
            return substr($header, 0, 2);
        }

        return null;
    }


    private function getCookiesLang(): ?string
    {
        return $this->getHttpRequest()->getCookie(self::LANG_COOKIE);
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

        throw new PageNotFoundException("Unknown page '$keyUrl' in PAGE_MAP");
    }


    /**
     * Return 1D array with simplified pair url=>key from PAGE_MAP
     * @return array
     */
    private function getReversedPageMap(): array
    {
        $reversedMap = [];

        foreach (self::PAGE_MAP as $pageKey => $keyUrls) {
            foreach ($keyUrls as $keyUrl) {
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
        $langKeys = array_keys(self::LANGS);
        foreach ($keyUrls as $langId => $keyUrl) {
            $lang = $langKeys[$langId];
            $url = $this->link('//default', [
                'lang' => $lang,
                'page' => $keyUrl,
            ]);
            $links[$lang] = $url;
        }
        return $links;
    }


    private function isLangValid(?string $lang): bool
    {
        return array_key_exists($lang, self::LANGS);
    }
}
