# Bezpečnost Nette

- Formuláře používají automatickou ochranu Nette Forms/Application 3.3 a HTTP 3.4 přes Fetch Metadata (`Sec-Fetch-Site`, `FetchSite::SameOrigin`). Nepovolují `allowCrossOrigin()` a nepřidávají deprecated `addProtection()`. Požadavky z jiných originů včetně subdomén se nezpracují; pro prohlížeče bez Fetch Metadata používá Nette fallback s cookie `_nss` (`SameSite=Strict`). Tento fallback rozlišuje site, nikoli origin. Viz [CSRF v Nette](https://blog.nette.org/cs/csrf-konecne-resi-prohlizec).
- Odkazy v e-mailu otevírají běžné GET stránky, nikoli měnící signály. Změna hesla a zrušení žádosti probíhají až odesláním formuláře ze stejného originu.
- Aplikace nestahuje uživatelem zadané URL na serveru. Při přidání takové funkce použij `UrlValidator` / `IPAddress` z HTTP 3.4, připni ověřené IP při spojení a ověř každý redirect. SSRF ochrana se netýká generování odkazů. Viz [SSRF v Nette HTTP](https://blog.nette.org/cs/nette-http-ssrf-ochrana).
- Absolutní odkazy obnovy hesla záměrně generuje standardní Nette `Presenter::link()` podle požadavku. Produkční webserver/proxy musí přijímat pouze správné hostnames a správně předávat HTTPS.
