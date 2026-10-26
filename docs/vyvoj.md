# Vývoj a provoz

## Docker

Zdrojem je projektový `docker-compose.yml` a [dokumentace docker-lamp-devstack](https://github.com/jakubboucek/docker-lamp-devstack#readme). Používají se hotové obrazy bez lokálního Dockerfile.

| Služba | Image | Přístup z hosta | Data |
| --- | --- | --- | --- |
| `web` | `jakubboucek/lamp-devstack-php:8.5-debug` | `127.0.0.1:8080` → port 80 | Projekt → `/var/www/html` |
| `mysqldb` | `jakubboucek/lamp-devstack-mysql:11.8` | `127.0.0.1:33060` → port 3306 | `.docker/mysql/data` → `/var/lib/mysql` |
| `maildev` | `maildev/maildev` | `127.0.0.1:1080` → port 1080 | Bez trvalého svazku |

PHP image obsahuje Apache, Composer, Git a rozšíření včetně `pdo_mysql`; varianta `-debug` přidává Xdebug. Webový kořen je standardně `/var/www/html/www`, takže `app/`, `local/` a `vendor/` nejsou veřejné. Node.js a npm nejsou součástí zde ověřeného PHP image.

Služby `web` a `mysqldb` mají `TZ=Europe/Prague`; všechny služby mají `restart: always`. `web` má `APP_DEBUG=1`. PHP tag sleduje řadu 8.5; MariaDB je připnutá na řadu 11.8 odpovídající produkci, se zachováním aktualizací záplat při stažení nového image. Upstream publikuje obrazy pro `linux/amd64`; na ARM je potřeba podpora této platformy v Dockeru.

MariaDB má databázi `default`, uživatele `root` a vývojové heslo `devstack`. PHP se připojuje na `mysqldb:3306`, nikoli na hostitelský port 33060. Aplikace v DSN používá `charset=utf8`, přestože image má výchozí charset `utf8mb4`.

## E-maily

Aplikace používá `Nette\Mail\Mailer`. Výchozí konfigurace odesílá přes SMTP do služby `maildev` na portu 1025 bez autentizace. MailDev zprávy zachytává, neodesílá je příjemcům. Jeho rozhraní je na [http://localhost:1080/](http://localhost:1080/); SMTP port není publikovaný na hostu. Zprávy nejsou uloženy v trvalém svazku.

Na produkci nastav v `local/Config/config.neon`:

```neon
mail:
    smtp: false
```

Tím se použije `Nette\Mail\SendmailMailer` a systémové odesílání pošty nakonfigurované na serveru. Konfigurace neobsahuje sekci `aws`; aplikace toto rozšíření neregistruje. Produkční přepsání konfigurace se nepřenáší deploymentem.

## První spuštění

1. Připrav neversionovaný `local/Config/config.neon`. Bootstrap jej vždy načítá. Může být prázdný (`[]`) nebo podle prostředí přepsat databázi a mailer.
2. Zajisti zapisovatelné `temp/` a `log/` a spusť kontejnery.
3. Nainstaluj Composer závislosti podle lockfile.
4. Připrav frontend příkazy v části [Sestavení frontendu](#sestavení-frontendu).
5. Obnov odpovídající databázi z dostupné zálohy. Vytvoření databáze `default` image nenahradí aplikační tabulky ani data. Repozitář nemá úplné výchozí SQL schéma ani seed; na obnovenou DB aplikuj chybějící migrace podle `migrations/README.md`.

```sh
docker compose up -d
docker compose exec web composer install
docker compose ps
```

Web je na [http://localhost:8080/](http://localhost:8080/). Databázovou konzoli spouštěj uvnitř kontejneru; na výzvu použij vývojové heslo:

```sh
docker compose exec mysqldb mariadb -u root -p default
```

Data databáze přetrvávají v `.docker/mysql/data`; tento adresář nemaž při běžném restartu. Lokální konfiguraci, databázová data a přístupové údaje necommituj.

Při přechodu na nižší řadu MariaDB obnov aplikační databázi z logického exportu do nově inicializovaného úložiště; nepoužívej původní datový adresář ani systémové databáze vyšší verze. Aplikační tabulky používají InnoDB; konzistentní logický export může použít `--single-transaction`, během exportu však neměň schéma. Fyzickou zálohu datového adresáře pořizuj až po čistém zastavení databáze.

## Běžné příkazy

Příkazy spouštěj z kořene projektu. PHP, Composer a aplikační skripty nikdy nespouštěj na hostu.

```sh
docker compose exec web php --version
docker compose exec web composer check-platform-reqs
docker compose exec web composer phpstan
docker compose exec web composer latte-lint
docker compose logs --tail=100 web mysqldb
docker compose stop
```

Pro příkaz bez běžících služeb lze použít `docker compose run --rm --no-deps web <command>`; tento režim nezajišťuje databázi ani zpřístupnění webu.

`composer latte-lint` kontroluje syntaxi všech Latte šablon v `app/`. Vlastní proměnné mají deklarace `{varType}`; standardní proměnné presenterů dodává Nette. Deklarace pomáhají IDE, lint neověřuje skutečné typy předaných hodnot. Jazyk veřejného layoutu vždy předává presenter, u chybových stránek je explicitně `en`.

PHPStan 2 používá úroveň 8 nad `app/`. V `phpstan.neon` se ručně načítají `nette/phpstan-rules` pro porozumění Nette a `phpstan/phpstan-deprecation-rules` pro kontrolu deprecated API; automatický instalátor rozšíření není potřeba. Integrační testy používají Nette Tester (`TestCase`, samostatné testovací metody a `dataProvider`) a spouštějí se příkazem `docker compose exec -T web composer test`. Vyžadují migrovanou testovací DB; vlastní testovací data po dokončení odstraní. Bootstrap zamyká běh DB testů proti souběhu. Jednorázovost resetovacího tokenu ověřuje test jeho opakovaného použití. Tester standardně načítá konfiguraci PHP kontejneru. Pro běh bez Xdebugu použij `docker compose exec -e XDEBUG_MODE=off -T web composer test`.

Lokální databáze obsahuje testovací data, která lze při testování měnit. Samostatná další testovací databáze není nutná. PHPStan databázi nepotřebuje.

PHPStan, Rector a Tester jsou vývojové závislosti. Po instalaci s `--no-dev` je pro lokální kontroly obnov příkazem `docker compose exec web composer install`. Úrovně PHPStanu vyšší než 8 nejsou součástí projektové kontroly.

HTTP integrační testy vyžadují běžící Apache ve službě `web` a rozšíření `curl`. Zevnitř kontejneru volají `http://localhost` (port 80). Ověřují ochranu formuláře proti cizímu originu, fallback `_nss` pro starší prohlížeče a otevření resetovacích odkazů bez spotřebování tokenu. Rozšíření `curl` je vývojový požadavek Composeru; projektový PHP image jej obsahuje.

`tests/PublicPages.phpt` ověřuje volbu jazyka podle cookie a `Accept-Language`, včetně výchozí angličtiny při chybějící nebo nepodporované preferenci.

`tests/Post.phpt` ověřuje hydrataci, částečné aktualizace, explicitní `null`, historii, cache a publikační hranice. Vytváří a odstraňuje vlastní obsahové klíče.

`tests/FormData.phpt` ověřuje mapování skutečných controls do typovaných tříd `FormData`, včetně prázdných řetězců a nezaškrtnutých checkboxů, a shodu s typy argumentů handlerů.

Současné šablony a databázové řádky používají dynamické vlastnosti. `universalObjectCratesClasses` v `phpstan.neon` proto povoluje tyto vlastnosti pro `Nette\Application\UI\Template`, `Nette\Bridges\ApplicationLatte\Template` a `Nette\Database\Table\ActiveRow`. Jejich názvy a hodnoty nejsou tímto nastavením typově ověřované; pro ostatní objekty kontrola vlastností zůstává aktivní.

### Rector a entity

`rector.php` používá pravidla do PHP 8.5 nad `app/` a `www/`, včetně odstranění mrtvého kódu a zjednodušování konstrukcí. Návrhy zobrazí `docker compose exec -T web composer rector:check`, změny aplikuje `docker compose exec -T web composer rector`. Zachovává čtyři mezery. Záměrně nemění kontroly `null` na `instanceof` ani statické pomocné metody na instanční; výsledek vždy projdi z hlediska čitelnosti a ověř testy a PHPStanem.

Pro `app/Entity` je vypnuto pouze pravidlo `RestoreDefaultNullToNullableTypePropertyRector`, podle [README hydratoru](https://github.com/jakubboucek/hydrator/blob/v0.7.1/README.md#rector-configuration). Ostatní pravidla se na entity vztahují. Pokud vzniknou entity nebo jejich společné předky mimo tento adresář, rozšiř výjimku i na ně. Samotné rozhraní `Entity` před doplněním výchozího `null` nechrání a výjimka neodstraní hodnoty doplněné dříve.

### Účet pro ruční testování

V lokální testovací databázi je ponechán účet `Codex Test` (role `admin`):

- Přihlášení: `http://localhost:8080/admin`
- E-mail: `codex-test@example.test`
- Heslo: `Kofein-Local-Test-2026!`

Jde výhradně o lokální testovací přihlašovací údaje. Databáze ani tento účet se nemigrují na produkci. Účet lze opakovaně používat při kontrolách přes Chrome MCP. E-maily při uložení obsahu i obnově hesla zachytává lokální MailDev.

## Sestavení frontendu

Node/npm se spouštějí na hostu přes `nvm`, verzi určuje `.nvmrc`. PHP a databáze zůstávají v Dockeru. Závislosti jsou zamčené v `package-lock.json`; zdroje v `assets/`, konfigurace ve `vite.config.js`. Produkční výstupy `www/assets/` včetně `.vite/manifest.json` jsou verzované. Po změně zdrojů nebo závislostí je přegeneruj a commituj společně se zdroji; čistý checkout tak obsahuje hotový build:

```sh
source ~/.nvm/nvm.sh
nvm install
nvm use
npm ci
npm run build
```

Jodit tvoří samostatný větší chunk (přibližně 185 kB gzip JS a 36 kB gzip CSS); Vite na jeho velikost upozorňuje, ale dashboard ani veřejné stránky jej nestahují.

Pro vývoj spusť `npm run dev`. Vite poslouchá na `http://localhost:5173`, aplikaci dál otevírej na `http://localhost:8080/`. Pevný port a CORS jsou nastavené pro tuto adresu. Plugin zapisuje `www/assets/.vite/nette.json`; tento vývojový marker je vyloučen z Gitu i deploye a Nette Assets jej používá jen v debug režimu. Změny SCSS/JS se načítají přes HMR, změny Latte vyvolají obnovení stránky. Před produkčním buildem zastav dev server; při jeho násilném ukončení může zůstat marker, který odstraní nový build.

Frontend ověřuj ve vlastní instanci Chrome přes MCP; pokud nejsou přímé nástroje dostupné, lze použít Chrome DevTools MCP CLI přes `npm exec --yes --package=chrome-devtools-mcp -- chrome-devtools …`. Recykluj záložky a ponech samostatnou pro MailDev. Kontroluj obě jazykové verze, mobil, tisk, klávesnici galerie, konzoli/CSP, přihlášení/reset a ukládání editoru. Testovací obsah po ověření obnov se zachováním historie.

## Ladění

Compose nastavuje `XDEBUG_CONFIG=client_host=host.docker.internal`; `PHP_IDE_CONFIG` je zakomentované. Pro ladění se serverem `docker-cli` odkomentuj `PHP_IDE_CONFIG=serverName=docker-cli` a v PhpStormu nastav mapování kořene projektu na `/var/www/html`. Pro spuštění kódu používej Docker interpreter pro PHP 8.5.

Podle [dokumentace Xdebugu v image](https://github.com/jakubboucek/docker-lamp-devstack#xdebug) se ladění aktivuje triggerem; profiler a trace zapisují do `/var/www/html/log`. Příklad pro CLI:

```sh
docker compose exec -e XDEBUG_TRIGGER=1 web php path/to/script.php
```

Tracy zapisuje do `log/`, Nette používá `temp/`. Image umožňuje změnit PHP nastavení proměnnými jako `PHP_MEMORY_LIMIT`; další INI nastavení lze připojit do `/usr/local/etc/php/conf.d/`. Projekt vlastní INI soubor nepřipojuje.

V debug režimu se neexistující stránka zobrazí jako Tracy výjimka s HTTP 500. Pro ověření běžné chybové stránky lze pouze v testovacím prohlížeči nastavit cookie `app-debug-mode=0` pro cestu `/`; bez debug režimu vrací aplikace HTTP 404. Po kontrole cookie odstraň.

## Nasazení

Produkce používá Cloudflare. Ochrana e-mailových adres přes Email Address Obfuscation je vypnutá. Aplikace zachovává CSP s nonce a `strict-dynamic`; automaticky vložené skripty bez odpovídajícího povolení mohou být blokované. Cloudflare Web Analytics není v aplikaci integrované.

`.deployment.php` používá jedinou sekci `Production`, která přenáší `app/`, `www/` včetně verzovaného buildu a `vendor/`. Vynechává `www/.well-known` a vývojový marker `www/assets/.vite/nette.json`. Nastavení `allowDelete: true` odstraňuje i staré hashované assety; cílem je čistý deploy bez hromadění minulých buildů.

Přihlašovací údaje bere z `.deployment-credentials.php`, lokální konfigurace se nepřenáší. Nasazuj zdroje a odpovídající commitovaný build ze stejného checkoutu; během přenosu jej nepřegenerovávej.

`bin/deploy-dry.sh` spouští testovací režim nasazovacího nástroje, `bin/deploy.sh` skutečné nasazení. Oba vyžadují globálně dostupný Composer příkaz `deployment` uvnitř kontejneru; není projektovou Composer závislostí. I tyto skripty spouštěj pouze v Dockeru.

Po nasazení se volá produkční `www/deployment.php?after`, který znovu vytvoří `temp/`, smaže starou cache a odstraní `log/email-sent`. Tento endpoint mění stav aplikace a neslouží ke kontrole dostupnosti.
