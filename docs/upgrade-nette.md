# Bezpečnost při upgradu Nette

- Po aktualizaci `nette/http` na 3.4 a `nette/forms` / `nette/application` na 3.3 prověř automatickou ochranu pomocí Fetch Metadata (`Sec-Fetch-*`, `FetchSite::SameOrigin`) a následně konsoliduj použití `addProtection()`. Do ověření upgradu zůstává současná tokenová ochrana formulářů. Otestuj cross-origin POST, přímé odkazy z e-mailu a podporované prohlížeče. Viz [CSRF v Nette](https://blog.nette.org/cs/csrf-konecne-resi-prohlizec).
- Pro serverové stahování uživatelských URL posuď `UrlValidator` a `IPAddress` z HTTP 3.4, včetně DNS rebindingu a přesměrování. SSRF není ochrana formulářů ani generování odkazů. Viz [SSRF v Nette HTTP](https://blog.nette.org/cs/nette-http-ssrf-ochrana).
- Absolutní odkazy obnovy hesla záměrně generuje standardní Nette `Presenter::link()` podle požadavku. Produkční webserver/proxy musí přijímat pouze správné hostnames a správně předávat HTTPS.
