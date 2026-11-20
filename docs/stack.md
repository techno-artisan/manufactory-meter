# Stack

Verbindliche Beschreibung des Stacks und der Konventionen. Wer in diesem Projekt baut, hält sich daran.

## Stack

- Sprache und Laufzeit: PHP 8.5 (`composer.json` verlangt `^8.5`)
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

## Aufbau

Das Stack-Pack verweist für alles Projektbezogene hierher.

- Ablage: Laravel-Standard. Namespace `App\` in `app/`, dazu `Database\Factories\` und `Database\Seeders\` (`composer.json`, `autoload`); keine Module und keine eigenen Pakete
- Panel: `admin` unter `/admin`, Provider `app/Providers/Filament/AdminPanelProvider.php`; er entdeckt Ressourcen, Seiten und Widgets in `app/Filament/Resources/`, `Pages/` und `Widgets/` selbst, ohne Eintrag
- PHPStan prüft `app`, `database` und `routes` (`phpstan.neon`)
- Tests: Vorbild `tests/Feature/AdminPanelTest.php`; Testdatenbank ist SQLite im Speicher (`phpunit.xml`)
- Test-Login: `admin@example.test`, Passwort `password`, angelegt von `database/seeders/TestLoginSeeder.php`; `DatabaseSeeder` ruft ihn nur in `local` und `testing` auf. Anmeldung unter `http://127.0.0.1:$PORT/admin`
- Zugang zum Panel: `canAccessPanel()` in `app/Models/User.php` erlaubt ihn nur in `local` und `testing`

## Konventionen

Die Regeln des Stack-Packs `laravel` gelten verbindlich. Darüber hinaus:

- Die Prüfungen stehen in `.factory/setup.json` unter `checks`: `pint`, `phpstan`, `pest`. Alle drei müssen vor jeder Übergabe grün sein. Prüfbedingungen werden nicht abgeschwächt: kein niedrigeres PHPStan-Level, keine Baseline und keine Ausnahmen, um Funde zu verdecken.
- Funde von PHPStan werden im Code behoben, nicht ausgeblendet.
- Neue Tabellen und Spalten entstehen nur als Migrationen. Keine SQL-Befehle von Hand gegen eine Datenbank.
- Tests laufen gegen SQLite und rufen keine externen Dienste auf.
- Geheimnisse stehen nie im Code und nicht in Git. `.env` ist von Git ausgenommen; die Vorlage ist `.env.example`.
- Was `install` erzeugt (`.env`, `database/database.sqlite`, `vendor/`, `node_modules/`, Build-Ausgaben), ist von Git ausgenommen.
- Der Entwicklungsserver lauscht auf `127.0.0.1:$PORT` (siehe `dev` in `.factory/setup.json`).
