# Design: PROJ-2 Liste der API-Aufrufe im Admin-Panel

**Spec:** [spec.md](spec.md)

## A) Komponenten
```
Admin-Panel (/admin)
+-- Menüeintrag „API-Aufrufe“
+-- Liste „API-Aufrufe“ (nur lesend)
|   +-- Spalten: Zeitpunkt, Modell, 4 × Token, Kosten (USD), Dauer (ms)
|   +-- Einblendbare Spalten (anfangs aus): Session, Prompt, Claude-Code-Version
|   +-- Filter
|   |   +-- Modell (Mehrfachauswahl aus den gespeicherten Modellen)
|   |   +-- Zeitraum: Tag „von“, Tag „bis“ (beide optional, beide inklusive)
|   +-- Seitenweise Anzeige: 50 pro Seite, umschaltbar 25 / 50 / 100
|   +-- Leer-Hinweise
|       +-- keine Daten: „Noch keine API-Aufrufe“ + Hinweis auf `php artisan tokens:import`
|       +-- Filter ohne Treffer: „Keine Aufrufe für diese Filter“ + Schaltfläche „Filter zurücksetzen“
+-- Detailansicht „API-Aufruf“ (nur lesend)
    +-- alle Werte der Liste inkl. Session, Prompt, Version
    +-- Kosten mit voller gespeicherter Genauigkeit
    +-- Original-Eintrag als eingerücktes JSON (scrollbar)
```
Keine Seiten zum Anlegen, Ändern oder Löschen.

## B) Datenmodell
Keine neuen Daten. Die Liste liest die Tabelle `api_requests` aus PROJ-1 (Zeitpunkt in UTC, Modell, Token, Kosten mit 10 Nachkommastellen, Dauer, Session, Prompt, Version, Original-Eintrag).

Zusätzlich ein Index auf der Spalte Modell (eigene Migration, ändert keine Daten), damit die Auswahl der Modelle im Filter und das Filtern bei 100.000 Aufrufen schnell bleiben.

## C) Technische Entscheidungen
- **Filament-Ressource mit nur zwei Seiten (Liste, Ansicht):** Adressen zum Anlegen oder Ändern existieren gar nicht und liefern deshalb „nicht gefunden“ (AK-21). Zusätzlich verbietet die Ressource Anlegen, Ändern und Löschen grundsätzlich.
- **Zeitzone:** Gespeichert bleibt UTC. Angezeigt wird in Europe/Berlin. Der Zeitraum-Filter rechnet den deutschen Kalendertag in einen UTC-Bereich um: von Tagesbeginn „von“ (deutsche Zeit) bis vor Beginn des Folgetags von „bis“. So passen Sommer-/Winterzeit und der 25-Stunden-Tag (EC-4) automatisch.
- **Zahlenformat fest deutsch** (Punkt als Tausendertrenner, Komma als Dezimalzeichen), unabhängig von der Spracheinstellung der App. Kosten in der Liste auf 4 Stellen gerundet, in der Detailansicht mit allen 10 gespeicherten Stellen.
- **Fehlende Werte als „–“:** Nur echte Leerwerte werden als Strich gezeigt; 0 bleibt 0 (EC-8).
- **Sortierung:** Standard neueste oben. Bei Kosten und Dauer stehen fehlende Werte in beiden Richtungen am Ende (EC-3). Bei jeder Sortierung wird zusätzlich nach der laufenden Nummer sortiert, damit bei gleichen Werten jede Seite eindeutig ist (EC-2).
- **Filter bleiben erhalten:** Filter, Sortierung und Seitengröße werden in der Sitzung gemerkt; nach Rückkehr aus der Detailansicht sind sie noch gesetzt (AK-26).
- **Zwei Leer-Hinweise:** Sind Filter aktiv und es gibt keinen Treffer, erscheint der Filter-Hinweis mit Zurücksetzen; sonst der Hinweis auf den Import.
- **Zugang:** Die bestehende Panel-Regel (Anmeldung, kein Zugang in `production`) gilt unverändert.

## D) Abhängigkeiten
- Keine neuen Pakete

## E) Backend nötig?
Ja, aber nur lesend: Abfragen der vorhandenen Tabelle über das Admin-Panel. Keine Schreibvorgänge, keine Schnittstelle nach außen.

## Decision Log
- **Ressource ohne Anlege-/Ändern-Seiten** | **Alternative:** Seiten anlegen und per Berechtigung sperren | **Abwägung:** Was nicht existiert, kann nicht versehentlich freigeschaltet werden; Fehlerseite „nicht gefunden“ genügt AK-21 (2026-11-22)
- **Tagesgrenzen per UTC-Bereich statt Datumsfunktion der Datenbank** | **Alternative:** Zeitpunkt in der Abfrage nach deutscher Zeit umrechnen | **Abwägung:** SQLite kennt keine Zeitzonen-Regeln; der Bereich nutzt außerdem den vorhandenen Index auf dem Zeitpunkt (2026-11-22)
- **Filter in der Sitzung merken** | **Alternative:** nur über die Adresse | **Abwägung:** funktioniert auch, wenn der Admin über das Menü statt über „Zurück“ zur Liste kommt (2026-11-22)
- **Index auf Modell** | **Alternative:** kein Index | **Abwägung:** kleine Migration, die nur einen Index anlegt; hält Filter-Auswahl und Modellfilter bei großen Datenmengen schnell (2026-11-22)
