# Databázové migrace

SQL soubory v `structures/` mají formát `YYYY-MM-DD-HHMMSS-description.sql` kompatibilní s Nextras Migrations. Obsahují změny struktury i nezbytné změny dat; aplikované soubory neměň. Migrační runner zatím není zapojený.

Migrace se aplikují jednou, v pořadí názvů, na existující databázi. Nejde o kompletní schéma pro prázdnou DB. Před nasazením zastav web a jiné zapisující procesy, vytvoř zálohu a zaznamenej aplikovaný název v provozní evidenci. DDL MariaDB provádí implicitní commit; soubor jako celek nelze vrátit transakcí.

```sh
docker compose stop web
docker compose exec -T mysqldb mariadb -uroot -pdevstack default < migrations/structures/2026-10-25-110000-password-reset-tokens.sql
docker compose exec -T mysqldb mariadb -uroot -pdevstack default < migrations/structures/2026-10-25-120000-base64url-reset-tokens.sql
docker compose start web
```

Migrace resetu hesel převádí `user` i `post` na InnoDB a ruší existující resetové odkazy. Vyžaduje odpovídající nový kód aplikace. Hesla a obsah zachovává. Návrat ke staré verzi vyžaduje obnovu zálohy i kódu.

Navazující migrace `base64url-reset-tokens` mění selector a hash na textové sloupce ASCII s kolací `ascii_bin`. Ruší vydané odkazy ve starém hexadecimálním formátu; nové používají Base64url bez paddingu. Již aplikované migrace nespouštěj znovu.
