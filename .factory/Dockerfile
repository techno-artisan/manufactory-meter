# Job-Image für Laravel-Projekte: erweitert das Job-Image des Upstream um PHP und
# Composer in fester Version, geprüft per SHA-256. Eine Abnahme-Regel (ADR-0021) ersetzt in der
# Kopie des Playbooks die Abnahme im Browser durch eine Fallunterscheidung nach der Oberfläche.
#
# Diese Datei ist die einzige Quelle des Laravel-Job-Image. Sie liegt byte-gleich
# als .factory/Dockerfile im Pilot-Repository; Änderungen nur hier und dann dorthin
# kopieren. Aufbau, Versionen anheben und Kopie: job-images/laravel/README.md.
FROM software-factory-job:latest

ARG PHP_VERSION=8.5.13
ARG PHP_SHA256=607602acaf0c3061818e9916ec44a058e8b3d43959a83862b9cd3ec7bf5b785f
ARG COMPOSER_VERSION=2.11.2
ARG COMPOSER_SHA256=dd158c0afd41d15fac89e1b4e47b5d42911fe316c3687cd5edac98319b25d028

USER root

# Bau- und Laufzeitpakete. Die Laufzeitbibliotheken kommen als Abhängigkeit der
# -dev-Pakete mit; diese bleiben deshalb im Image.
RUN apt-get update \
 && apt-get install -y --no-install-recommends \
      xz-utils \
      gcc \
      g++ \
      make \
      libc6-dev \
      pkg-config \
      libxml2-dev \
      libsqlite3-dev \
      libssl-dev \
      libonig-dev \
      libicu-dev \
      libzip-dev \
      zlib1g-dev \
 && rm -rf /var/lib/apt/lists/*

# PHP aus dem Quellarchiv. Schalter nur für Erweiterungen der Liste, die PHP nicht
# von selbst baut: openssl, mbstring, intl, zip, zlib (zlib braucht der Phar von
# Pint). CGI und phpdbg braucht niemand; ohne sie ist der Bau kürzer.
RUN set -eux; \
    cd /tmp; \
    curl -fsSL -o php.tar.xz "https://www.php.net/distributions/php-${PHP_VERSION}.tar.xz"; \
    echo "${PHP_SHA256}  php.tar.xz" | sha256sum -c -; \
    mkdir php-src; \
    tar -xJf php.tar.xz -C php-src --strip-components=1; \
    rm php.tar.xz; \
    cd php-src; \
    ./configure \
      --prefix=/usr/local \
      --disable-cgi \
      --disable-phpdbg \
      --with-openssl \
      --enable-mbstring \
      --enable-intl \
      --with-zip \
      --with-zlib; \
    make -j"$(nproc)"; \
    make install; \
    cd /; \
    rm -rf /tmp/php-src

RUN set -eux; \
    curl -fsSL -o /tmp/composer.phar "https://getcomposer.org/download/${COMPOSER_VERSION}/composer.phar"; \
    echo "${COMPOSER_SHA256}  /tmp/composer.phar" | sha256sum -c -; \
    install -m 0755 /tmp/composer.phar /usr/local/bin/composer; \
    rm /tmp/composer.phar

# Stack-Pack: Der Bau-Agent im Container liest diese Datei als einziges Regelwerk
# für Laravel, sobald docs/stack.md des Projekts unter „Stack-Packs“ laravel nennt.
# COPY legt sie als root mit Modus 0644 an, also lesbar für pwuser.
COPY <<'EOF' /factory/workflows/starter-kit/stacks/laravel.md
# Stack-Pack: Laravel + Filament 5 + Livewire 4 + Pest

Gilt, wenn `docs/stack.md` unter „Stack-Packs“ `laravel` nennt.

**Verbote:** keine neuen Abhängigkeiten, keine Änderung an Anmeldung und Berechtigungen, an `.factory/`, an `docs/stack.md` (auch nicht löschen), an Prüfdefinitionen und an den Konfigurationen von Pint, PHPStan und Pest ohne ausdrücklichen Auftrag in der Spec; keine echten externen Dienste.

## Struktur
- **Vorrang hat das Projekt.** Vor der ersten neuen Datei lesen: `docs/stack.md`, `composer.json` (`autoload` mit Namespaces, eingebundene Pakete und Module), `phpstan.neon` (`paths`) und vorhandenen Code derselben Art. Neue Dateien dorthin, wo gleichartige schon liegen, mit dem Namespace aus `composer.json`
- Projektbezogenes steht in `docs/stack.md`: Ablage, Panels, Test-Login und Vorbilder für Tests
- Nur wenn das Projekt nichts anderes zeigt, gilt der Laravel-Standard: Modelle in `app/Models/`, Migrationen in `database/migrations/`, Factories in `database/factories/`, Seeder in `database/seeders/`, Filament-Ressourcen, -Seiten und -Widgets in `app/Filament/Resources/`, `Pages/`, `Widgets/`, Livewire-Komponenten in `app/Livewire/`, Tests in `tests/Feature/` (HTTP, Livewire, Datenbank) und `tests/Unit/` (ohne Framework-Start)
- `php artisan make:*` nur, wo das Ziel diesem Standard entspricht; sonst eine vorhandene Nachbardatei als Vorbild nehmen. Filament findet Ressourcen, Seiten und Widgets nur in den Ordnern, die der Panel-Provider entdeckt (`discover…`), sonst braucht es dort einen Eintrag
- Livewire-Komponenten als Klasse (`php artisan make:livewire <Name> --class`), nicht als Single-File-Komponente: PHPStan prüft nur PHP-Dateien in den `paths` aus `phpstan.neon`
- Features stehen unter `features/<ordner>/spec.md`, die Übersicht in `features/INDEX.md`

## Oberfläche
- Verwaltungsoberflächen **immer mit Filament**: `php artisan make:filament-resource <Model> --no-interaction` statt eigener Controller und Blade-Seiten (Ablage siehe Struktur)
- Filament 5: Formulare in `form(Schema $schema)`, Tabellen in `table(Table $table)`. Felder aus `Filament\Forms\Components`, Layout aus `Filament\Schemas\Components`, Spalten aus `Filament\Tables\Columns`, alle Aktionen aus `Filament\Actions`. Namespaces aus Filament 3 und 4 nicht aus dem Gedächtnis übernehmen, sondern unter `vendor/filament/` nachlesen
- Livewire 4 nur, wo Filament nicht reicht. Der Zustand liegt auf dem Server, öffentliche Eigenschaften sind für den Browser sichtbar und änderbar: keine Geheimnisse und keine ganzen Modelle darin
- Livewire- und Filament-Aktionen prüfen Berechtigung und Eingabe selbst, nicht nur die Oberfläche
- Kein eigenes CSS und keine Inline-Styles, solange die Spec es nicht verlangt

## Backend
- Tabellen und Spalten entstehen nur als Migration, nie per SQL von Hand
- Eingaben validieren (Filament-Felder oder `FormRequest`); Schreibvorgänge über mehrere Tabellen in `DB::transaction()`
- Eloquent statt Roh-SQL; Beziehungen per Eager Loading laden, keine Abfragen in Schleifen
- Modelle: `#[Fillable]` gezielt setzen, nie `$guarded = []`
- Typen überall angeben (Parameter, Rückgabe, Eigenschaften). PHPStan läuft auf Level 8: Funde im Code beheben, nie mit Baseline oder `@phpstan-ignore` verdecken
- Entwurfsprinzipien:
  - Zusammenhalt: Was sich gemeinsam ändert, liegt zusammen; eine Klasse hat einen Grund zur Änderung
  - Verborgene Entscheidungen: Hinter einer kleinen Schnittstelle bleibt verborgen, wie etwas gelöst ist; Aufrufer fragen nicht nach dem Innenleben
  - Einfachste Lösung: zuerst ohne neue Klasse, Schicht oder Muster; mehr nur, wenn es nachweislich nötig ist
  - Keine Abstraktion auf Vorrat: erst beim dritten gleichen Fall verallgemeinern; eine falsche Abstraktion kostet mehr als doppelter Code
  - Nur ändern, was der Auftrag verlangt; fremden Code nicht „mitverbessern“
- Geheimnisse nie im Code und nicht in Git; Konfiguration über `.env` und `config/`

## Tests
- Pest: Tests als `it('…', function () { … })` mit deutscher Beschreibung; Vorbild sind vorhandene Tests des Projekts (`docs/stack.md`)
- Die Testdatenbank steht in `phpunit.xml`; `RefreshDatabase` je Testdatei mit `uses(RefreshDatabase::class)` einbinden
- Filament und Livewire testen mit `Livewire::test(<Seite>::class)`, etwa `fillForm`, `call`, `assertHasFormErrors`, `assertCanSeeTableRecords`
- Jedes neue Verhalten bekommt einen Test; ein Fehler bekommt zuerst einen Test, der ihn zeigt
- Tests rufen keine externen Dienste auf
- Test-Login für die Abnahme: `admin@example.test` mit Passwort `password`, nur in `local` und `testing`. Seeder, Panel und Adresse nennt `docs/stack.md`; die Abnahme läuft über `http://127.0.0.1:$PORT`
- Gelingt die Anmeldung nicht, zuerst `APP_ENV` prüfen, nicht die Zugangsregel des Panels ändern

## Befehle
- Maßgeblich sind `install`, `dev` und `checks` in `.factory/setup.json`
- `dev` lauscht auf `127.0.0.1:$PORT`, nicht auf `0.0.0.0`
- Formatieren mit `vendor/bin/pint` (ohne `--test`); die Checks nacheinander ausführen, nicht parallel
EOF

# Abnahme-Regel, ADR-0021: ersetzt in der Kopie des Playbooks die Abnahme-Schritte 3 und 4;
# scheitert, wenn der alte Wortlaut fehlt. Das Skript steht als COPY-Heredoc hier; der lokale
# Test job-images/laravel/test-playbook-rule.sh schneidet es heraus und prüft genau diesen Text.
# Der Block endet nur an PLAYBOOK_RULE; die inneren Marken ALT und NEU heißen anders.
COPY <<'PLAYBOOK_RULE' /tmp/playbook-rule.sh
#!/bin/bash
# Abnahme-Regel (ADR-0021): ersetzt in der Kopie des Playbooks die Schritte 3 und 4 der Abnahme.
# Aufruf: bash playbook-rule.sh <datei>. Scheitert mit Exit 1, wenn der alte Wortlaut fehlt (Upstream hat das Playbook
# geändert); die Datei bleibt dann unverändert. Lokaler Test: job-images/laravel/test-playbook-rule.sh.
set -euo pipefail

file=${1:?Aufruf: playbook-rule.sh <datei>}

alt=$(cat <<'ALT'
3. Starte die App (`.factory/setup.json` → `dev`) auf einem freien Port und teste **jedes Szenario selbst im Browser** (Playwright-Skript mit Node, Chromium ist installiert). Halte wichtige Zustände mit `factory-screenshot` fest.
4. Prüfe zusätzlich kurz auf Offensichtliches: Konsolenfehler, kaputte Darstellung auf 375px, Eingaben, die die App zum Absturz bringen.
ALT
)

neu=$(cat <<'NEU'
3. Entscheide zuerst, ob der Auftrag eine Oberfläche betrifft: Enthält er eine Oberfläche oder ändert er etwas, das in einer Oberfläche sichtbar ist (Filament-Ressource, Seite, Widget, Livewire-Komponente, Blade-Ansicht, Ausgabe im Panel)? Im Zweifel gilt: ja. Der Abnahmebericht nennt, welcher Fall vorlag und warum.
   - **Mit Oberfläche:** Starte die App (`.factory/setup.json` → `dev`) auf einem freien Port und teste **jedes Szenario selbst im Browser** (Playwright-Skript mit Node, Chromium ist installiert). Halte wichtige Zustände mit `factory-screenshot` fest.
   - **Ohne Oberfläche:** Teste **jedes Szenario selbst per Aufruf** (Artisan-Befehl, Pest, HTTP-Anfrage), ohne Browser. Der Abnahmebericht nennt je Szenario den Aufruf und die beobachtete Ausgabe. Screenshots entfallen in diesem Fall.
4. Prüfe zusätzlich kurz auf Offensichtliches: in beiden Fällen Eingaben, die die App zum Absturz bringen; mit Oberfläche außerdem Konsolenfehler und kaputte Darstellung auf 375px.
NEU
)

mapfile -t alt_zeilen <<< "$alt"
mapfile -t nr3 < <(grep -n -x -F -- "${alt_zeilen[0]}" "$file" | cut -d: -f1)
mapfile -t nr4 < <(grep -n -x -F -- "${alt_zeilen[1]}" "$file" | cut -d: -f1)

if (( ${#nr3[@]} != 1 || ${#nr4[@]} != 1 || nr4[0] != nr3[0] + 1 )); then
    echo "Abnahme-Regel: alter Wortlaut der Schritte 3 und 4 fehlt in $file" >&2
    exit 1
fi

tmp=$(mktemp)
trap 'rm -f -- "$tmp"' EXIT
{
    head -n $((nr3[0] - 1)) -- "$file"
    printf '%s\n' "$neu"
    tail -n +$((nr4[0] + 1)) -- "$file"
} > "$tmp"
cat -- "$tmp" > "$file"
PLAYBOOK_RULE

RUN bash /tmp/playbook-rule.sh /factory/playbook.md && rm /tmp/playbook-rule.sh

# Prüfung als letzter RUN-Schritt: scheitert eine Zeile, scheitert der Bau.
# HOME=/root, damit Composer als root nichts unter /home/pwuser anlegt.
RUN set -eux; \
    php -v; \
    php -v | grep -qF "PHP ${PHP_VERSION} "; \
    php -m; \
    for ext in ctype date dom fileinfo filter hash iconv intl json libxml mbstring \
               openssl pcre phar reflection session simplexml tokenizer xml \
               xmlreader xmlwriter zip zlib pdo_sqlite; do \
      php -m | grep -qix "$ext" || { echo "Erweiterung fehlt: $ext" >&2; exit 1; }; \
    done; \
    HOME=/root composer --version; \
    HOME=/root composer --version | grep -qF "Composer version ${COMPOSER_VERSION} "; \
    test -r /factory/workflows/starter-kit/stacks/laravel.md; \
    grep -qF -- '- **Ohne Oberfläche:**' /factory/playbook.md; \
    test -r /factory/playbook.md

USER pwuser
