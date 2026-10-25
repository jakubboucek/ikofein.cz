# Vývoj a provoz

## Docker

Zdrojem je projektový `docker-compose.yml` a [dokumentace docker-lamp-devstack](https://github.com/jakubboucek/docker-lamp-devstack#readme). Používají se hotové obrazy bez lokálního Dockerfile.

| Služba | Image | Přístup z hosta | Data |
| --- | --- | --- | --- |
| `web` | `jakubboucek/lamp-devstack-php:8.3-debug` | `127.0.0.1:8080` → port 80 | Projekt → `/var/www/html` |
| `mysqldb` | `jakubboucek/lamp-devstack-mysql` | `127.0.0.1:33060` → port 3306 | `.docker/mysql/data` → `/var/lib/mysql` |

PHP image obsahuje Apache, Composer, Git a rozšíření včetně `pdo_mysql`; varianta `-debug` přidává Xdebug. Webový kořen je standardně `/var/www/html/www`, takže `app/`, `local/` a `vendor/` nejsou veřejné. Node.js a npm nejsou součástí zde ověřeného PHP image.

Obě služby mají `TZ=Europe/Prague` a `restart: always`. `web` má `APP_DEBUG=1`. PHP tag sleduje řadu 8.3; MariaDB nemá explicitní tag a používá pohyblivé `latest`. Konkrétní verze závisí na staženém image. Upstream publikuje obrazy pro `linux/amd64`; na ARM je potřeba podpora této platformy v Dockeru.

MariaDB má databázi `default`, uživatele `root` a vývojové heslo `devstack`. PHP se připojuje na `mysqldb:3306`, nikoli na hostitelský port 33060. Aplikace v DSN používá `charset=utf8`, přestože image má výchozí charset `utf8mb4`.

## První spuštění

1. Připrav neversionovaný `local/Config/config.neon`. Bootstrap jej vždy načítá. Musí doplnit zejména `parameters.jwt.base64-key` (Base64 podpisový klíč) a platné `aws.credentials.key` a `aws.credentials.secret`; podle prostředí může přepsat databázi.
2. Zajisti zapisovatelné `temp/` a `log/` a spusť kontejnery.
3. Nainstaluj Composer závislosti podle lockfile.
4. Obnov odpovídající databázi z dostupné zálohy. Vytvoření databáze `default` image nenahradí aplikační tabulky ani data. Repozitář nemá SQL schéma, migrace, seed ani výchozí účet.

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

## Běžné příkazy

Příkazy spouštěj z kořene projektu. PHP, Composer a aplikační skripty nikdy nespouštěj na hostu.

```sh
docker compose exec web php --version
docker compose exec web composer check-platform-reqs
docker compose exec web composer phpstan
docker compose logs --tail=100 web mysqldb
docker compose stop
```

Pro příkaz bez běžících služeb lze použít `docker compose run --rm --no-deps web <command>`; tento režim nezajišťuje databázi ani zpřístupnění webu.

PHPStan 2 používá úroveň 5 nad `app/` a `libs/`. V `phpstan.neon` se ručně načítají `nette/phpstan-rules` pro porozumění Nette a `phpstan/phpstan-deprecation-rules` pro kontrolu deprecated API; automatický instalátor rozšíření není potřeba. `rector.php` konfiguruje Rector 2 pro `app/`, `libs/` a `www/`; jeho spuštění není součástí běžného startu. Automatická testovací sada není přítomna a `npm test` končí záměrně chybou.

Lokální databáze obsahuje testovací data, která lze při testování měnit. Samostatná další testovací databáze není nutná. PHPStan databázi nepotřebuje.

Současné šablony a databázové řádky používají dynamické vlastnosti. `universalObjectCratesClasses` v `phpstan.neon` proto povoluje tyto vlastnosti pro `Nette\Application\UI\Template`, `Nette\Bridges\ApplicationLatte\Template` a `Nette\Database\Table\ActiveRow`. Jejich názvy a hodnoty nejsou tímto nastavením typově ověřované; pro ostatní objekty kontrola vlastností zůstává aktivní.

### Účet pro ruční testování

V lokální testovací databázi je ponechán účet `Codex Test` (role `admin`):

- Přihlášení: `http://localhost:8080/admin`
- E-mail: `codex-test@example.test`
- Heslo: `Kofein-Local-Test-2026!`

Jde výhradně o lokální testovací přihlašovací údaje. Databáze ani tento účet se nemigrují na produkci. Účet lze opakovaně používat při kontrolách přes Chrome MCP. Uložení obsahu a zahájení obnovy hesla používají AWS SES; bez lokálního zachytávání pošty tyto akce při běžné kontrole neodesílej.

## Sestavení frontendu

Zdroje jsou v `asset/`, závislosti Boweru určuje `bower.json`. Grunt vyžaduje současně `node_modules/` a `bower_components/`. Výstupy v `www/css/` a `www/js/main.js` jsou verzované. Compose nemá službu pro Node ani projekt neurčuje jeho verzi.

Následující příklad používá jednorázový Node kontejner; Node 22 je zde volba pro spuštění nástrojů, nikoli deklarovaný požadavek projektu. Sestavení tímto postupem není automaticky ověřováno testy:

```sh
docker run --rm -v "$PWD:/app" -w /app node:22 npm ci
docker run --rm -v "$PWD:/app" -w /app node:22 npx --yes --package=bower bower install --allow-root
docker run --rm -v "$PWD:/app" -w /app node:22 ./node_modules/.bin/grunt
```

Po změně zdrojů zkontroluj diff vygenerovaných souborů. Frontend ověřuj ve vlastní instanci Chrome přes MCP a recykluj stejné okno. Zkontroluj dotčené stránky v obou jazycích, konzoli a načtení assetů. Uložení obsahu a reset hesla odesílají e-mail přes SES.

## Ladění

Compose nastavuje `XDEBUG_CONFIG=client_host=host.docker.internal` a `PHP_IDE_CONFIG=serverName=docker-cli`. V PhpStormu odpovídá serveru `docker-cli` mapování kořene projektu na `/var/www/html`. Pro spuštění kódu používej Docker interpreter pro PHP 8.3.

Podle [dokumentace Xdebugu v image](https://github.com/jakubboucek/docker-lamp-devstack#xdebug) se ladění aktivuje triggerem; profiler a trace zapisují do `/var/www/html/log`. Příklad pro CLI:

```sh
docker compose exec -e XDEBUG_TRIGGER=1 web php path/to/script.php
```

Tracy zapisuje do `log/`, Nette používá `temp/`. Image umožňuje změnit PHP nastavení proměnnými jako `PHP_MEMORY_LIMIT`; další INI nastavení lze připojit do `/usr/local/etc/php/conf.d/`. Projekt vlastní INI soubor nepřipojuje.

V debug režimu se neexistující stránka zobrazí jako Tracy výjimka s HTTP 500. Pro ověření běžné chybové stránky lze pouze v testovacím prohlížeči nastavit cookie `app-debug-mode=0` pro cestu `/`; bez debug režimu vrací aplikace HTTP 404. Po kontrole cookie odstraň.

## Nasazení

`deployment.php` nastavuje FTPS přenos na produkci. Přenáší `app/`, `libs/`, `www/` a `vendor/`, vynechává `www/.well-known` a dovoluje mazání vzdálených souborů. Přihlašovací údaje bere z `.deployment-credentials.php`. Lokální konfigurace se nepřenáší.

`bin/deploy-dry.sh` spouští testovací režim nasazovacího nástroje, `bin/deploy.sh` skutečné nasazení. Oba vyžadují globálně dostupný Composer příkaz `deployment` uvnitř kontejneru; není projektovou Composer závislostí. I tyto skripty spouštěj pouze v Dockeru.

Po nasazení se volá produkční `www/deployment.php?after`, který znovu vytvoří `temp/`, smaže starou cache a odstraní `log/email-sent`. Tento endpoint mění stav aplikace a neslouží ke kontrole dostupnosti.
