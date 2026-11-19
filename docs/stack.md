# Stack

Verbindliche Beschreibung des Stacks und der Konventionen. Wer in diesem Projekt baut, hält sich daran.

## Stack

- Sprache und Laufzeit: PHP 8.5 (`composer.json` verlangt `^8.3`)
- Framework: Laravel 13
- Oberfläche: Filament 5 (Admin-Panel unter `/admin`) mit Livewire 4
- Datenhaltung: SQLite (`database/database.sqlite`), Migrationen mit `php artisan migrate`
- Login: Filament-Login des Admin-Panels; ein Test-Login mit bekanntem Passwort entsteht nur in der Umgebung `local` und in Tests
- Tests: Pest
- Statische Analyse: Larastan (PHPStan), Level 8, zwei Prozesse
- Formatierung: Pint
- Paketverwaltung: Composer (PHP), npm (Frontend-Build mit Vite)
- Zusatzdienste: keine (kein Redis, keine Mailserver, keine externen APIs)
- Hosting: keines; das Projekt läuft nur lokal und im Factory-Job

## Stack-Packs

- `laravel`

## Konventionen

Die Regeln des Stack-Packs `laravel` gelten verbindlich. Darüber hinaus:

- Die Prüfungen stehen in `.factory/setup.json` unter `checks`: `pint`, `phpstan`, `pest`. Alle drei müssen vor jeder Übergabe grün sein. Prüfbedingungen werden nicht abgeschwächt: kein niedrigeres PHPStan-Level, keine Baseline und keine Ausnahmen, um Funde zu verdecken.
- Funde von PHPStan werden im Code behoben, nicht ausgeblendet.
- Neue Tabellen und Spalten entstehen nur als Migrationen. Keine SQL-Befehle von Hand gegen eine Datenbank.
- Tests laufen gegen SQLite und rufen keine externen Dienste auf.
- Geheimnisse stehen nie im Code und nicht in Git. `.env` ist von Git ausgenommen; die Vorlage ist `.env.example`.
- Was `install` erzeugt (`.env`, `database/database.sqlite`, `vendor/`, `node_modules/`, Build-Ausgaben), ist von Git ausgenommen.
- Der Entwicklungsserver lauscht auf `127.0.0.1:$PORT` (siehe `dev` in `.factory/setup.json`).
