# Pokyny pro práci na projektu

- Komunikace a dokumentace jsou česky. Identifikátory, komentáře v kódu a commit messages jsou anglicky.
- Dokumentace má být stručná a popisovat aktuální konečný stav aplikace. Průběžné poznámky a changelog patří do commit messages nebo PR. Dokumentaci vícefázového refaktoringu po dokončení konsoliduj.
- Poznatky o kódu a projektu zapisuj sem nebo do odkazovaných souborů v `docs/`. Paměť (`MEMORY.md`, `memory/`) je výhradně pro osobní preference a zpětnou vazbu uživatele.
- Testy, aplikační skripty a nástroje PHP/Composer, Node/npm i databázové příkazy spouštěj výhradně v Dockeru. PHP ani MySQL nespouštěj na hostu. Základem prostředí je `docker-compose.yml`.
- Frontend ověřuj ve vlastní instanci Chrome přes MCP, opakovaně používej stejné okno. Aplikace je po spuštění dostupná na `http://localhost:8080/`.

## Orientace

Web restaurace Kofein používá PHP 8.3, Nette 3.2, Latte 3, MariaDB a frontend sestavovaný přes Grunt/LESS. Veřejný web má českou a anglickou verzi; administrace upravuje obsah uložený v databázi.

- [README.md](README.md): vstupní přehled.
- [docs/architektura.md](docs/architektura.md): struktura, routování, obsah, přihlášení a integrace.
- [docs/vyvoj.md](docs/vyvoj.md): Docker, konfigurace, sestavení, kontroly a nasazení.

## Důležité souvislosti

- `local/Config/config.neon` se načítá povinně po společné konfiguraci. Lokální tajné hodnoty a `.deployment-credentials.php` nepatří do Gitu ani dokumentace.
- Veřejný kořen je `www/`, nikoli kořen repozitáře. `temp/` a `log/` musí být zapisovatelné.
- `www/css/*.css` a `www/js/main.js` jsou verzované výstupy Gruntu. Zdroje upravuj v `asset/` a související výstupy přegeneruj v Dockeru.
- Obsah má aktivní verzi `current` a historické kopie. Zachovej historii a invalidaci cache při změnách modelu `Post`.
- Lokální databáze je testovací a její data lze při testování libovolně měnit; další testovací databáze není nutná.
- Repozitář neobsahuje databázové migrace, seed ani automatickou testovací sadu. `npm test` je pouze neúspěšný zástupný příkaz; PHPStan se spouští pomocí Composeru.
- Uložení obsahu i obnova hesla odesílají skutečný e-mail přes AWS SES. Nasazovací skripty pracují s produkcí, včetně mazání vzdálených souborů.
