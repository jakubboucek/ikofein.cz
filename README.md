# ikofein.cz

Web restaurace Kofein s českou a anglickou verzí, jídelními lístky, galerií, kontakty a administrací obsahu.

Technologie: PHP 8.5, Nette Application/Forms 3.3, Nette HTTP 3.4, Latte 3.1, MariaDB, Vite, SCSS, Bootstrap 5, PhotoSwipe a Jodit. PHP závislosti určuje `composer.lock`, npm závislosti `package-lock.json`.

## Lokální provoz

PHP a databáze běží v Dockeru; Node/npm na hostu přes `nvm`. Před prvním spuštěním připrav `local/Config/config.neon` a databázi podle [vývojového návodu](docs/vyvoj.md).

```sh
docker compose up -d
docker compose exec web composer install
source ~/.nvm/nvm.sh
nvm use
npm ci
npm run build
```

Web: [http://localhost:8080/](http://localhost:8080/). Administrace: [http://localhost:8080/admin](http://localhost:8080/admin).

## Dokumentace

- [Pravidla práce](AGENTS.md)
- [Architektura a datový model](docs/architektura.md)
- [Vývojové prostředí, kontroly a nasazení](docs/vyvoj.md)
