# Pokyny pro práci na projektu

- Komunikace a dokumentace jsou česky. Identifikátory, komentáře v kódu a commit messages jsou anglicky.
- Kód, šablony a konfiguraci odsazuj čtyřmi mezerami, nikoli tabulátory; toto pravidlo má přednost před formátovacími konvencemi Nette.
- Konstruktory s deklarací vlastností v parametrech a funkce/metody s atributy parametrů (např. `#[SensitiveParameter]`) zapisuj vždy víceřádkově: každý parametr na vlastní řádek s koncovou čárkou, i když je parametr jediný.
- Třídy nesoucí formulářová data pojmenovávej se suffixem `FormData` (např. `PostEditFormData`).
- Projektové PHP konstanty pojmenovávej v PascalCase.
- Dokumentace má být stručná a popisovat aktuální konečný stav aplikace. Průběžné poznámky a changelog patří do commit messages nebo PR. Dokumentaci vícefázového refaktoringu po dokončení konsoliduj.
- Poznatky o kódu a projektu zapisuj sem nebo do odkazovaných souborů v `docs/`. Paměť (`MEMORY.md`, `memory/`) je výhradně pro osobní preference a zpětnou vazbu uživatele.
- PHP/Composer, PHP testy, aplikační PHP skripty a databázové příkazy spouštěj výhradně v Dockeru. Node/npm a frontendové nástroje spouštěj na hostu přes `nvm`; Compose zatím nemá Node službu. PHP ani MySQL nespouštěj na hostu. Základem PHP prostředí je `docker-compose.yml`.
- Frontend ověřuj ve vlastní instanci Chrome přes MCP. Recykluj existující okna a záložky; nevytvářej nové pro každý krok či iteraci. Pro odlišná prostředí (například aplikaci a MailDev) používej samostatné současně otevřené záložky. Aplikace je po spuštění dostupná na `http://localhost:8080/`.

## Orientace

Web restaurace Kofein používá PHP 8.5, Nette Application/Forms 3.3, Nette HTTP 3.4, Latte 3.1, MariaDB a frontend sestavovaný přes Vite/SCSS. Veřejný web má českou a anglickou verzi; administrace upravuje obsah uložený v databázi.

- [README.md](README.md): vstupní přehled.
- [docs/architektura.md](docs/architektura.md): struktura, routování, obsah, přihlášení a integrace.
- [docs/vyvoj.md](docs/vyvoj.md): Docker, konfigurace, sestavení, kontroly a nasazení.
- [migrations/README.md](migrations/README.md): SQL migrace kompatibilní s Nextras Migrations.
- [docs/upgrade-nette.md](docs/upgrade-nette.md): bezpečnostní pravidla Nette.

## Důležité souvislosti

- `local/Config/config.neon` se načítá povinně po společné konfiguraci. Lokální tajné hodnoty a `.deployment-credentials.php` nepatří do Gitu ani dokumentace.
- Veřejný kořen je `www/`, nikoli kořen repozitáře. `temp/` a `log/` musí být zapisovatelné.
- Frontendové zdroje jsou v `assets/`, produkční build včetně manifestu je verzovaný v `www/assets/`. Po změně zdrojů nebo závislostí spusť `npm run build` a commituj i přegenerované výstupy. Node určuje `.nvmrc`; build spouštěj na hostu přes `nvm`. Podrobnosti jsou v [návodu](docs/vyvoj.md#sestavení-frontendu).
- Obsah má aktivní verzi `current` a historické kopie. Zachovej historii a invalidaci cache při změnách modelu `PostManager`.
- `App\Entity\Post` slouží i pro partial update: nepřidávej výchozí hodnoty vlastností a šablonám předávej pouze úplně hydratované entity. Viz [datový model](docs/architektura.md#obsah-a-databáze) a [ochrana entit před Rectorem](docs/vyvoj.md#rector-a-entity).
- Lokální databáze je testovací a její data lze při testování libovolně měnit; další testovací databáze není nutná.
- SQL migrace jsou v `migrations/structures/`, integrační testy `Nette\Tester\TestCase` v `tests/` (spuštění `composer test` v Dockeru). Kompletní výchozí schéma ani seed nejsou v repozitáři. Frontend se ověřuje buildem a v Chrome; PHPStan se spouští pomocí Composeru.
- Uložení obsahu i obnova hesla odesílají e-mail přes Nette Mailer. Výchozí SMTP míří do lokálního MailDev (`http://localhost:1080/`); produkční konfigurace přepíná na sendmail pomocí `mail.smtp: false`.
- Nasazovací skripty pracují s produkcí, včetně mazání vzdálených souborů.
