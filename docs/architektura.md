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
| `app/Model/` | Obsah, uživatelé, obnova hesla a cesta k veřejným souborům |
| `app/Entity/` | Typovaná data článku pro čtení i částečné změny |
| `app/Components/Post/` | Vykreslování dynamického obsahu |
| `app/Forms/` | Úprava vykreslování Nette Forms pro Bootstrap |
| `asset/` | Zdrojový JavaScript a LESS |
| `temp/`, `log/` | Cache a diagnostika; obsah se neverzuje |

`www/index.php` načte Composer a spustí aplikaci přes `App\Bootstrap`. RobotLoader prochází `app/`. Společná konfigurace se načítá před lokální. Časové pásmo aplikace je `Europe/Prague`; debug režim určuje `redbitcz/debug-mode-enabler`.

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

Databázový přístup používá Nette Database Explorer. Následující přehled popisuje pole používaná kódem; úplné výchozí SQL schéma ani seed nejsou v repozitáři. Inkrementální migrace jsou v `migrations/structures/`; aplikační tabulky používají InnoDB.

| Tabulka | Pole používaná aplikací |
| --- | --- |
| `post` | `key`, `version`, `title`, `content_cs`, `content_en`, `published_from`, `published_to`, `hash`, `created_at`, `created_by` |
| `user` | `id`, `name`, `email`, `password`, `role`, `reset_hash`, `reset_selector`, `reset_expires_at`, `reset_requested_at`, `auth_version` |

- Šablony používají obsahové klíče `lunch`, `dinner`, `beverages` a `alert`.
- Aktivní řádek má `version = current`. `PostManager::save()` v transakci zamkne existující aktivní řádek, aktualizuje jej, doplní autora, čas a náhodný hash a vloží historickou kopii. Vrací úplnou entitu historické kopie pro notifikační e-mail; její verze obsahuje ISO datum s mikrosekundami a časovým pásmem. Nevytváří nový obsahový klíč. Prázdná změna pouze vrátí aktuální článek.
- `App\Model\PostManager` převádí řádky přes `jakubboucek/hydrator` (`NetteDatabase`) na `App\Entity\Post`. Čtení je úplné; částečná entita při ukládání mění jen inicializované vlastnosti. Explicitní `null` vymaže hodnotu. Model povoluje změnu titulku, technické poznámky, obsahů a termínů publikace; klíč a technická metadata řídí sám. Datumové vlastnosti jsou `DateTimeImmutable`, názvy vlastností camelCase. Vlastnosti nemají výchozí hodnoty.
- Publikovaný obsah musí mít `published_from`, nesmí být naplánován do budoucna ani mít prošlé `published_to`. Samostatný databázový příznak publikování model nepoužívá.
- `PostRenderer` vybírá jazykový obsah entity. Model cachuje publikovanou entitu nebo nepřítomnost obsahu na 20 minut v namespace `post-entity-v1`, odděleném od staré cache polí. Uložení po dokončení transakce zneplatní cache daného klíče. Časové hranice publikace se tak mohou na veřejném webu projevit až po expiraci cache.
- Obsah z editoru se vykresluje jako HTML pomocí `noescape`.

## Administrace a e-maily

`RequireLoggedUser` chrání přehled a editaci obsahu a uchovává návratový odkaz při přesměrování na přihlášení. `UserManager` ověřuje e-mail a hash hesla přes Nette Passwords; při potřebě aktualizuje hash. Role se přenáší do identity, uvedený guard kontroluje přihlášení. Konfigurace session má expiraci 14 dní.

Obnovu hesla zajišťuje `PasswordReset`: odkaz obsahuje selector (16 náhodných bajtů) a verifier (32 bajtů), oba v Base64url bez paddingu. DB uchovává selector jako stejný 22znakový text jako v odkazu a SHA-256 verifieru jako 43znakový Base64url text v ASCII sloupcích s kolací `ascii_bin`. Verifier se do DB neukládá; nekanonické kódování se odmítá; porovnání používá `hash_equals()`. Platnost je hodina, časy resetu se ukládají v UTC. Nová přijatá žádost nahrazuje předchozí token. Dokončení a zrušení používají transakci a zámek uživatelského řádku `FOR UPDATE`; dokončení mění heslo, maže token a zvyšuje `auth_version`. Autentizace včetně rehashování používá stejný zámek. Staré sessions a identity bez verze se při dalším chráněném požadavku odhlásí. Do identity se nekopírují resetové údaje ani hash hesla.

Žádosti jsou omezené na 20 za 15 minut na IP, 5 za hodinu na účet a nejméně 60 sekund mezi vydáním tokenů. Ověřování, dokončení a rušení sdílejí limit 60 pokusů za 15 minut na IP. Čítače v `password_reset_limit` se mění pod transakčním zámkem a starší než den se při provozu odstraňují. Neúspěšný verifier token neruší. Odpověď na žádost neprozrazuje existenci účtu, ale synchronní odesílání e-mailu může ovlivnit čas odpovědi.

GET pouze zobrazuje formuláře; změna i zrušení resetu vyžadují POST s CSRF ochranou Nette Forms. Absolutní odkazy vytváří standardní `Presenter::link()`. Stránky přihlášení a resetu mají `no-store`, `no-referrer` a nenačítají externí JavaScript. Nové heslo má alespoň 15 znaků a nejvýše 72 bajtů UTF-8 kvůli současnému bcryptu. Po změně následuje oznámení e-mailem a běžné přihlášení. Selhání oznámení nevrací již provedenou změnu hesla.

Presentery přijímají rozhraní `Nette\Mail\Mailer`. Výchozí `SmtpMailer` odesílá do vývojového MailDev na `maildev:1025`; produkční `mail.smtp: false` vybírá `SendmailMailer`. E-mail se odesílá při obnově hesla a po uložení obsahu; uložení do databáze předchází odeslání oznámení.

## Frontend

Veřejný web používá jQuery a Magnific Popup z Boweru, vlastní skripty a LESS. Grunt vytváří `www/js/main.js`, `www/css/main.css` a `www/css/admin.css`. Veřejný layout přidává k URL CSS a JS čas změny souboru.

Administrace načítá Bootstrap 3, jQuery, TinyMCE 5 a Nette Forms z externích zdrojů. Veřejný web používá Google Tag Manager a na kontaktní stránce Google Maps. CSP je nastavena ve společné konfiguraci; skripty v šablonách používají nonce.
