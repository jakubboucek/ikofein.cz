# Architektura

## Struktura

| Cesta | Účel |
| --- | --- |
| `www/` | Veřejný kořen Apache, vstup `index.php`, statické soubory a výstupy Gruntu |
| `app/Bootstrap.php` | Inicializace Nette, Tracy, RobotLoaderu a konfigurace |
| `app/Config/config.neon` | Společná konfigurace a DI služby |
| `local/Config/config.neon` | Povinné lokální přepsání konfigurace, mimo Git |
| `app/Router/` | Definice rout |
| `app/Presenters/` | Veřejné stránky, chybové presentery a jejich šablony |
| `app/AdminModule/Presenters/` | Přihlášení, obnova hesel, přehled a editace obsahu |
| `app/Model/` | Obsah, uživatelé, JWT a cesta k veřejným souborům |
| `app/Components/Post/` | Vykreslování a cache dynamického obsahu |
| `app/Forms/` | Úprava vykreslování Nette Forms pro Bootstrap |
| `libs/Aws/` | DI rozšíření AWS a mailer pro SES |
| `asset/` | Zdrojový JavaScript a LESS |
| `temp/`, `log/` | Cache a diagnostika; obsah se neverzuje |

`www/index.php` načte Composer a spustí aplikaci přes `App\Bootstrap`. RobotLoader prochází `app/` a `libs/`. Společná konfigurace se načítá před lokální. Časové pásmo aplikace je `Europe/Prague`; debug režim určuje `redbitcz/debug-mode-enabler`.

## Stránky a routování

První routa obsluhuje `admin/<presenter>/<action>`, s výchozím `Dashboard:default`. Druhá obsluhuje veřejné stránky přes `Static:default`.

| Stránka | Česká cesta | Anglická cesta |
| --- | --- | --- |
| Úvod | `/cs/` | `/en/` |
| Polední menu | `/cs/poledne` | `/en/lunch` |
| Večerní menu | `/cs/vecer` | `/en/dinner` |
| Nápoje | `/cs/napoje` | `/en/beverages` |
| Galerie | `/cs/galerie` | `/en/gallery` |
| Kontakt | `/cs/kontakt` | `/en/contact` |

`StaticPresenter` mapuje URL na šablony `<lang>-<page>.latte`. Bez jazyka v URL použije cookie `lang`, potom jazyk prohlížeče; výchozí jazyk je angličtina. Nastaví jazykovou cookie na měsíc a přesměruje na kanonickou jazykovou URL.

## Obsah a databáze

Databázový přístup používá Nette Database Explorer. Následující přehled popisuje pole používaná kódem; SQL schéma, migrace ani seed nejsou v repozitáři.

| Tabulka | Pole používaná aplikací |
| --- | --- |
| `post` | `key`, `version`, `title`, `content_cs`, `content_en`, `published_from`, `published_to`, `hash`, `created_at`, `created_by` |
| `user` | `id`, `name`, `email`, `password`, `role`, `reset_hash` |

- Šablony používají obsahové klíče `lunch`, `dinner`, `beverages` a `alert`.
- Aktivní řádek má `version = current`. `Post::save()` aktualizuje existující aktivní řádek, doplní autora, čas a náhodný hash a vloží historickou kopii s verzí ve formátu `DateTime::ATOM`. Nevytváří nový obsahový klíč.
- Publikovaný obsah musí mít `published_from`, nesmí být naplánován do budoucna ani mít prošlé `published_to`. Samostatný databázový příznak publikování model nepoužívá.
- `PostRenderer` vybírá `content_cs` nebo `content_en` a cachuje výsledek včetně chybějícího obsahu na 20 minut. Uložení zneplatní cache daného klíče. Časové hranice publikace se tak mohou na veřejném webu projevit až po expiraci cache.
- Obsah z editoru se vykresluje jako HTML pomocí `noescape`.

## Administrace a e-maily

`RequireLoggedUser` chrání přehled a editaci obsahu a uchovává návratový odkaz při přesměrování na přihlášení. `UserManager` ověřuje e-mail a hash hesla přes Nette Passwords; při potřebě aktualizuje hash. Role se přenáší do identity, uvedený guard kontroluje přihlášení. Konfigurace session má expiraci 14 dní.

Obnova hesla používá JWT s HS256 a platností jedné hodiny. JWT obsahuje e-mail a náhodný token; databáze uchovává jeho SHA-256 hash v Base64. Kontroluje se podpis, expirace, issuer a audience. Nová žádost nahrazuje předchozí token, změna hesla jej zneplatní.

`JakubBoucek\Aws\SesMailer` odesílá přes AWS SES. Výchozí region rozšíření je `eu-west-1`. E-mail se odesílá při obnově hesla a po uložení obsahu; uložení do databáze předchází odeslání oznámení. Composer z AWS SDK ponechává pouze službu SES.

## Frontend

Veřejný web používá jQuery a Magnific Popup z Boweru, vlastní skripty a LESS. Grunt vytváří `www/js/main.js`, `www/css/main.css` a `www/css/admin.css`. Veřejný layout přidává k URL CSS a JS čas změny souboru.

Administrace načítá Bootstrap 3, jQuery, TinyMCE 5 a Nette Forms z externích zdrojů. Veřejný web používá Google Tag Manager a na kontaktní stránce Google Maps. CSP je nastavena ve společné konfiguraci; skripty v šablonách používají nonce.
