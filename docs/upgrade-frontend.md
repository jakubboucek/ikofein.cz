# Předání modernizace frontendu

Tento dokument je zadání a plán vícefázového refaktoringu, nikoli popis hotové implementace. Po dokončení přenes platné informace do běžné dokumentace a tento plán konsoliduj nebo odstraň.

## Stav a autorizovaný rozsah

- Upgrade PHP na 8.5 je dokončen. Nová práce se týká frontendu a nezbytné integrace do PHP.
- Větev `fe-update` je již založena z `update`, výchozí commit `9a8001a`. Implementace frontendu dosud nezačala.
- Uživatel schválil realizaci a samostatné commity ucelených kroků. Kvůli provázanosti akceptuje i nefunkční mezilehlé commity; konečný stav musí být ověřený.
- Náhradou TinyMCE je uživatelem zvolený Jodit; praktický pokus s verzí 4.18.10 prošel. Tiptap zůstává záložní možností při problémech během integrace.
- Zachovat veřejný vzhled, českou i anglickou verzi, tiskové styly, obsah a historii úprav. Nejde o redesign ani další backendový refaktoring.

## Zadání

1. Nahradit Grunt a Bower pomocí Vite.
2. Zapojit `@nette/vite-plugin` a PHP `nette/assets`: manifest a hashované soubory nahradí invalidaci přes `filemtime()`.
3. Přepsat LESS do Sass (SCSS).
4. Zcela odstranit jQuery včetně závislých pluginů.
5. Nahradit Magnific Popup moderní galerií. Uživatel uvedl basicLightbox; doporučení analýzy je PhotoSwipe kvůli hotovému ovládání galerie.
6. Bootstrap a Nette Forms distribuovat z npm jako součást buildu, bez dosavadních CDN.
7. Editor a další nákladné části načítat dynamickými importy pouze tam, kde jsou potřeba.
8. U editoru stačí základní formátování textu a tabulky. Preferovat spolehlivé řešení bez komerční licenční zátěže.

## Zjištěný výchozí stav

### Build a veřejný web

- `package.json`: Grunt, `grunt-contrib-less`, `grunt-contrib-uglify-es`; `npm test` je neúspěšný zástupný příkaz.
- `bower.json`: jQuery a Magnific Popup.
- `Gruntfile.js` sestavuje verzované `www/js/main.js`, `www/css/main.css` a `www/css/admin.css` ze zdrojů v `asset/`.
- `asset/js/core.js` pouze nastavuje jazykovou cookie přes jQuery. Jazykovou cookie již spravuje server ve `StaticPresenter`; klientský kód lze odstranit.
- `asset/js/gallery.js` inicializuje Magnific Popup nad odkazy. `asset/less/gallery.less` obsahuje jeho přechodové efekty.
- Hlavní `asset/less/style.less` má přibližně 340 řádků, proměnné, vnořování, media queries a aritmetiku šířek. Obsahuje styly tabulek, mobilu a tisku. Fonty a obrázky používají absolutní cesty `/fonts/…` a `/img/…`; při migraci je nutné vědomě zachovat nebo převést jejich obsluhu.
- Galerie v českých a anglických šablonách má 20 odkazů na originály a malé náhledy. Soubory jsou v `www/img/gallery/photos/` a `thumbnails/`. Zůstat musí funkční odkazy i bez JS. Pro PhotoSwipe doplnit skutečné rozměry originálů; současné obecné alternativní texty nejsou vhodné.
- `app/Presenters/@layout.latte` generuje CSS/JS URL přes `filemtime($wwwDir …)`. Související `App\Model\WebDir` a předávání `$wwwDir` ve Static/Error4xx presenteru po odstranění posledního použití odstranit.
- GTM a jazykové odkazy v layoutu zachovat.

### Administrace

- `app/Presenters/Admin/@layout.latte` načítá Bootstrap 3.3.7 CSS/theme z CDN a mimo `Sign:*` také jQuery slim, Bootstrap JS, TinyMCE 5 z Tiny Cloud a vzdálené `netteForms.js`.
- TinyMCE se inicializuje nad všemi textarea (`entity_encoding: 'raw'`, čeština), načítá se zbytečně i na dashboardu. Přihlašovací/resetovací stránky naopak klientskou validaci Nette nyní nenačítají.
- Bootstrap 3 používá `data-toggle`, `data-target`, starou navigaci, glyphicon a staré třídy tlačítek/formulářů.
- `app/Forms/BootstrapizeForm.php` používá například `form-group`, `has-error`, `control-label`, `help-block`, `form-horizontal`, `btn-default`. Přechod na Bootstrap 5 vyžaduje změnu rendereru i šablon, nestačí výměna CSS.
- `app/Presenters/Admin/Post/detail.latte` vykresluje formulář `postEditForm`. Editor musí před odesláním synchronizovat HTML do příslušné textarea.
- Editují se obsahy `alert`, `beverages`, `dinner`, `lunch`. Uložené HTML obsahuje historické inline styly; zachování obsahu a vzhledu při otevření a uložení je zásadní kontrola.

### Prostředí a nasazení

- Docker Compose má PHP 8.5, MariaDB 11.8 a MailDev, zatím nemá Node službu. Podle upřesnění uživatele spouštět Node/npm/build/lint na hostu přes `nvm`; PHP a databázi nadále výhradně v Dockeru. Při pokusu byl na hostu Node 26.12.1 a npm 11.20.0.
- Aplikace: `http://localhost:8080/`, MailDev: `http://localhost:1080/`.
- Po minulém deployi byly ve `vendor/` jen produkční závislosti. Před PHP kontrolami ověřit stav a případně obnovit dev závislosti pomocí `composer install` v Dockeru.
- Dosavadní build výstupy jsou verzované. U nového buildu výslovně rozhodnout a dokumentovat způsob distribuce; deploy nesmí skončit bez manifestu nebo jeho souborů.
- Nasazení maže vzdálené soubory. Zohlednit staré otevřené stránky, které mohou později potřebovat předchozí lazy-loaded chunk. Manifest a odpovídající soubory musí tvořit konzistentní celek.
- CSP používá nonce a `strict-dynamic`. Ověřit produkční moduly i vývojové HMR; po odstranění CDN zúžit pouze skutečně nepotřebná povolení. Cloudflare e-mailová obfuskace byla vypnuta, není třeba ji řešit.

## Doporučená řešení a zdroje

### Vite a Nette Assets

[Nette Vite plugin](https://github.com/nette/vite-plugin) propojuje npm `@nette/vite-plugin` s PHP `nette/assets`. Při analýze uváděl podporu Vite 6.2+, 7 a 8 a Node 22+. Při instalaci ověřit aktuální stabilní verze a jejich kompatibilitu, nikoli slepě převzít čísla z tohoto předání.

[Integrace Vite](https://doc.nette.org/en/assets/vite) používá `{asset 'front.js'}` pro skripty, styly i preloady. Nastavit mapování typu `vite` s cestou odpovídající výstupu (například `assets` pro `www/assets`). Výchozí zdrojová složka pluginu je `assets`, projekt má zatím `asset`; sjednotit vědomě.

Vývojový marker `www/assets/.vite/nette.json` slouží jen pro debug režim. V Dockeru bude potřeba publikovaný port, `host: '0.0.0.0'`, pevný port a správný browser origin/CORS pro `http://localhost:8080/`. Ověřit podle aktuální dokumentace pluginu. [Nette Assets](https://doc.nette.org/en/assets) podporuje CSP nonce na generovaných elementech.

Navržené entry pointy: veřejný web a administrace, editor jako dynamicky importovaný modul. Nette Forms inicializovat i na stránkách přihlášení a resetu.

### Bootstrap a galerie

Doporučení je ponechat Bootstrap a přejít na stabilní řadu 5, nikoli zavádět Tailwind: administrace je malá a přepis všeho na utility třídy nemá zřejmý přínos. [Migrační příručka](https://getbootstrap.com/docs/5.3/migration/) popisuje odstranění jQuery, atributy `data-bs-*` a změny formulářů.

[basicLightbox](https://github.com/electerious/basicLightbox) je malý modal pro obrázky/HTML/video, ale kompletní galerijní chování by vyžadovalo další práci. [PhotoSwipe](https://photoswipe.com/getting-started/) nabízí galerii a oddělené načítání jádra: `photoswipe/lightbox`, CSS `photoswipe/style.css`, jádro přes `() => import('photoswipe')`. Vyžaduje rozměry obrázků. Při analýze byla stabilní řada 5 a řada 6 ve vývoji; instalovat ověřenou stabilní verzi.

### Zvolený editor: Jodit

Uživatel zvolil **Jodit**, Tiptap je záložní alternativa. Balíček `jodit@4.18.10` má licenci MIT; pro požadované formátování a tabulky není potřeba Pro. Výchozí stavový řádek zobrazuje odkaz „Powered by Jodit“.

Prakticky ověřeno v Chrome přes Chrome DevTools MCP CLI na lokální aplikaci:

- Všech osm obsahů (`alert`, `beverages`, `dinner`, `lunch`, vždy česky a anglicky), celkem 48 tabulek, má po načtení a serializaci shodný DOM včetně atributů, tříd a inline stylů. Porovnávalo se HTML parsované prohlížečem; nejde o shodu původního řetězce, protože NBSP se zapisuje jako `&nbsp;`.
- Pro zachování historického HTML použít `cleanHTML: {fillEmptyParagraph: false, removeEmptyElements: false, replaceOldTags: false, replaceNBSP: false}`. Výchozí konfigurace přidávala `<br>` do prázdných odstavců a buněk. Ochrany proti skriptům, event atributům a nebezpečným odkazům ponechat zapnuté.
- Český jazykový modul je `esm/langs/cs_cz.js`, konfigurace `language: 'cs_cz'`. Testovaný UMD export používá `Jodit.lang.cs_cz`; při ESM integraci ověřit skutečné API instalované verze místo slepého převzetí `Jodit.langs` z README.
- Ověřeno české ovládání a vložení tabulky 3 × 3 přes toolbar. Změna buňky existující tabulky, `synchronizeValues()`, odeslání formuláře a nové načtení z DB zachovaly přesně odeslané HTML obou jazyků. Původní obsah byl obnoven standardním formulářem a ověřen přesnou shodou; historie zůstala zachovaná. MailDev zachytil obě oznámení.
- Na veřejné české stránce poledního menu mělo původní a serializované HTML shodné rozměry a pozice všech 38 elementů, barvy, tučnost a zarovnání (po odečtení testovacího textu). Původní obsah je obnovený i na veřejné stránce.
- Pokus používal lokální UMD JS/CSS na stávající administraci a načtení skriptu s nonce. Nenahrazuje závěrečnou kontrolu ESM buildu, lazy loadingu, CSP, mobilního ovládání a klávesnicové přístupnosti. Pro zdrojový režim použít `sourceEditor: 'area'`, aby nebyl potřeba externí Ace editor.

| Varianta | Přínos | Co ověřit / náklady |
| --- | --- | --- |
| [Jodit](https://github.com/xdan/jodit) | MIT, TypeScript, bez jQuery, hotový toolbar a tabulky; vhodný pro malou administraci | Zachování starého HTML, čeština, přístupnost, CSP, velikost buildu. README zmiňuje odstranění „Powered by Jodit“ jako výhodu Pro; ověřit branding bez předpokladu, že se bude kupovat Pro. |
| [Tiptap](https://github.com/ueberdosis/tiptap) | MIT open-source jádro, vanilla JS, rozšiřitelnost, tabulky v otevřeném TableKit | Headless: vlastní toolbar a interakce. Striktní schéma může zahodit nepodporované tagy/atributy, takže historický HTML obsah vyžaduje zvláštní pozornost. |

Jodit má [standardní pluginy](https://xdsoft.net/jodit/play.html) pro tabulky, výběr buněk, změny velikosti a klávesnicovou navigaci; ESM umožňuje importovat pluginy a jazyky zvlášť. Nezaměňovat základní Jodit s placeným Pro.

Tiptap má [vanilla integraci](https://tiptap.dev/docs/editor/getting-started/install/vanilla-javascript) a [TableKit](https://tiptap.dev/docs/editor/extensions/functionality/table-kit) pro tabulku, řádky, buňky a hlavičky. Před migrací číst [pravidla schématu](https://tiptap.dev/docs/editor/core-concepts/schema). Není bezpečné předpokládat bezeztrátové načtení libovolného HTML.

TinyMCE bylo původně plánováno přes npm, ale uživatel nyní preferuje alternativu bez licenční zátěže. [Aktuální TinyMCE](https://www.tiny.cloud/docs/tinymce/latest/license-key/) rozlišuje GPL a komerční licenci. Quill nebyl doporučen kvůli potřebě tabulek nad rámec jeho základních formátů; Lexical by také znamenal více vlastní integrace.

Navržené minimum toolbaru (upřesnit podle reálného použití): odstavce/nadpisy, tučné/kurzíva, seznamy, zarovnání, odkazy, vložení a úpravy tabulky, undo/redo a odstranění formátování. Uploady ani cloud nejsou požadovány.

## Pořadí realizace

0. Hotovo: zvolen a prakticky ověřen Jodit; podmínky integrace jsou výše.
1. Zavést Vite, Nette Assets a manifest, rozdělit entry pointy. Node spouštět na hostu přes `nvm`. Stanovit build/deploy workflow.
2. Převést veřejné styly na SCSS, migrovat galerii a odstranit veřejné jQuery i redundantní cookie skript.
3. Převést administraci na Bootstrap 5, upravit renderer a šablony, přidat lokální Nette Forms.
4. Integrovat vybraný editor, jeho CSS/jazyk a dynamické načítání jen na editačních stránkách.
5. Odstranit Grunt/Bower, nepoužívané zdroje a staré výstupy, CDN odkazy a nepotřebné CSP výjimky. Konsolidovat dokumentaci.
6. Ověřit celek v dev režimu i produkčním buildu a dokončit commity.

## Ověření a hranice práce

- Přečíst aktuální `AGENTS.md`, `docs/vyvoj.md` a relevantní Nette skills (frontend; před změnami také Latte, konfigurace a Forms).
- PHP a databázové nástroje spouštět v Dockeru, Node/npm na hostu přes `nvm`. Existující PHP kontroly: `composer test`, `composer phpstan`, `composer latte-lint`, podle PHP změn také `composer rector:check`.
- Přes MCP Chrome ověřit veřejné stránky v obou jazycích, mobilní rozložení, tisk, galerii včetně klávesnice a zavření, konzoli a síť/CSP.
- Ověřit dashboard, všechny čtyři editované obsahy, uložení a zobrazení HTML/tabulek, klientskou validaci, přihlášení, odhlášení a reset hesla. Editor se nemá stahovat na dashboardu ani přihlašovacích stránkách.
- Pro aplikaci a MailDev používat dvě současné záložky a recyklovat je; nevytvářet okna pro každou iteraci. ID záložek z minulé session nejsou trvalá, nejprve zjistit aktuální stav.
- Testovací účet a postupy jsou v `docs/vyvoj.md`; lokální DB je testovací, další DB není potřeba. Při ověřování editoru obnovit původní obsah po testovací změně a zachovat historii.
- Neprovádět skutečný produkční deploy. Neměnit nesouvisející backendové chování a nevracet odstraněný test souběžného resetu hesla.
