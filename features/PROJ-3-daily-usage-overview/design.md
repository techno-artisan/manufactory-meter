# Design: PROJ-3 Daily Usage Overview

**Spec:** [spec.md](spec.md)

## A) Komponenten
```
Admin-Panel (/admin)
+-- Menüeintrag „Tagesübersicht“ (direkt unter „API-Aufrufe“)
+-- Seite „Tagesübersicht“ (nur lesend, /admin/tagesuebersicht)
    +-- Tabelle mit 8 Spalten: Tag, Modell, Aufrufe, Input-Token, Output-Token,
    |   Cache-Lese-Token, Cache-Schreib-Token, Kosten (USD)
    |   +-- je Tag (neuester oben):
    |   |   +-- Modellzeilen (alphabetisch), anklickbar → Liste „API-Aufrufe“
    |   |   +-- Tagessumme (fett, nicht anklickbar)
    |   |   +-- oder: eine Zeile „ohne Aufrufe“ mit lauter Nullen (nur mit Option)
    |   +-- Gesamtsumme als letzte Zeile jeder Seite (gilt für alle passenden Tage)
    +-- Filter
    |   +-- Zeitraum: Tag „von“, Tag „bis“ (beide optional, inklusive; Standard letzte 30 Tage)
    |   +-- Modell (Mehrfachauswahl aus den gespeicherten Modellen)
    |   +-- Schalter „Tage ohne Aufrufe zeigen“ (Standard aus)
    +-- Seitenweise Anzeige nach ganzen Tagen (Standard 10 Tage pro Seite, umschaltbar 10 / 31)
    +-- Leer-Hinweise
        +-- keine Daten: „Noch keine API-Aufrufe“ + Hinweis auf `php artisan tokens:import`
        +-- Filter ohne Treffer: „Keine Aufrufe für diese Filter“ + „Filter zurücksetzen“
```
Keine Schaltflächen zum Anlegen, Ändern oder Löschen.

## B) Datenmodell
Keine neuen Daten und keine Migration. Die Seite liest nur die Tabelle `api_requests` aus PROJ-1 (Zeitpunkt in UTC, Modell, vier Token-Werte, Kosten mit 10 Nachkommastellen oder leer).

Für die Anzeige werden die Aufrufe bei jedem Laden neu zusammengezählt; nichts wird zwischengespeichert. Neue Aufrufe aus einem laufenden Import erscheinen deshalb beim nächsten Filtern, Blättern oder Neuladen (EC-7).

## C) Technische Entscheidungen
- **Eigene Filament-Seite mit Filament-Tabelle auf berechneten Zeilen:** Filter, Blättern, Leer-Hinweise und das Merken der Einstellungen funktionieren genau wie in der Liste aus PROJ-2. Die Zeilen (Modellzeile, Tagessumme, leerer Tag, Gesamtsumme) werden auf dem Server berechnet und der Tabelle fertig übergeben.
- **Zusammenzählen in zwei Schritten:** Die Datenbank zählt die Aufrufe je UTC-Stunde und Modell zusammen (schnell, nutzt den Index auf dem Zeitpunkt). Danach ordnet die App jede Stunde dem deutschen Kalendertag zu. Weil Deutschland immer um ganze Stunden von UTC abweicht, landet jede Stunde eindeutig an genau einem Tag – auch am 25-Stunden-Tag der Zeitumstellung (EC-2, AK-12). SQLite kennt keine Zeitzonen-Regeln, deshalb diese Aufteilung.
- **Zeitraum wie in PROJ-2:** Der deutsche Kalendertag wird in einen UTC-Bereich umgerechnet (von 00:00 „von“ bis vor 00:00 des Tages nach „bis“). Dieselbe Regel wie in der Liste sorgt dafür, dass der Sprung dort genau dieselben Aufrufe zeigt (AK-19).
- **Kosten exakt summiert:** Jede gespeicherte Kostenangabe wird als ganze Zahl in Zehnmilliardstel-Dollar aufsummiert und erst bei der Anzeige auf 4 Stellen gerundet. So entstehen keine Rundungsfehler beim Aufsummieren vieler kleiner Beträge (EC-6), und große Summen bleiben ohne wissenschaftliche Schreibweise (EC-5).
- **Fehlende Kosten werden mitgezählt:** Jede Zeile kennt Anzahl der Aufrufe und Anzahl ohne Kosten. Daraus ergibt sich die Anzeige `x $`, `x $ (n ohne Kosten)` oder `–`; ohne Aufrufe immer `0,0000 $`.
- **Blättern nach ganzen Tagen:** Eine Seite enthält eine feste Anzahl Tage mit all ihren Modellzeilen; ein Tag wird nie geteilt (AK-7). Die Anzahl der Einträge unter der Tabelle bezieht sich deshalb auf Tage.
- **Gesamtsumme auf jeder Seite:** Sie wird immer aus allen passenden Tagen berechnet, nicht aus der angezeigten Seite, und steht als letzte Zeile jeder Seite.
- **Tage ohne Aufrufe:** Mit eingeschalteter Option wird jeder Tag zwischen Start und Ende ohne passende Aufrufe als eine Zeile mit Nullen gezeigt. Start ist „von“, sonst der Tag des ältesten passenden Aufrufs; Ende ist „bis“, höchstens heute (AK-23, EC-3). Liegt „bis“ vor „von“, gibt es keine Tage (EC-1). Ist die Datenbank leer, erscheint immer der Import-Hinweis (AK-25).
- **Sprung zur Liste:** Ein Klick auf eine Modellzeile öffnet die Liste „API-Aufrufe“ mit Modell- und Zeitraum-Filter in der Adresse; die Liste übernimmt Filter aus der Adresse bereits.
- **Einstellungen bleiben erhalten:** Filter und Option werden in der Sitzung gemerkt (wie PROJ-2), also auch nach Rückkehr über das Menü (AK-24). „Filter zurücksetzen“ stellt den Standard-Zeitraum der letzten 30 Tage wieder her.
- **Zahlenformate:** Die vorhandenen deutschen Anzeigeformate aus PROJ-2 werden wiederverwendet und um die exakte Kostensumme ergänzt.
- **Zugang:** Die bestehende Panel-Regel (Anmeldung, kein Zugang in `production`) gilt unverändert.

## D) Abhängigkeiten
- Keine neuen Pakete

## E) Backend nötig?
Ja, aber nur lesend: Zusammenzählen der vorhandenen Aufrufe auf dem Server. Keine Schreibvorgänge, keine Schnittstelle nach außen.

## Decision Log
- **Zählen je UTC-Stunde in der Datenbank, Zuordnung zum deutschen Tag in der App** | **Alternative:** Umrechnung in deutsche Zeit direkt in der Abfrage | **Abwägung:** SQLite kennt keine Sommer-/Winterzeit; je Stunde zu zählen bleibt bei 100.000 Aufrufen schnell und ist an jedem Tag der Zeitumstellung korrekt (2026-11-22)
- **Filament-Tabelle mit berechneten Zeilen** | **Alternative:** eigene Blade-Tabelle | **Abwägung:** Filter, Blättern, Sitzung und Leer-Hinweise gibt es fertig und sehen aus wie in PROJ-2; eigenes CSS ist nicht nötig (2026-11-22)
- **Gesamtsumme als letzte Zeile jeder Seite** | **Alternative:** Kopfbereich über der Tabelle | **Abwägung:** Spec verlangt die Gesamtsumme „am Ende“ und mit denselben Spalten (2026-11-22)
- **Tag ohne Aufrufe als eine Zeile ohne Modell** | **Alternative:** Zeile je gewähltem Modell | **Abwägung:** kompakt und eindeutig; keine Tagessumme nötig, weil alle Werte 0 sind (2026-11-22)
- **Feste Seitengrößen nach Tagen (10 / 31)** | **Alternative:** Seitengröße nach Zeilen | **Abwägung:** nur so wird kein Tag auf zwei Seiten verteilt (2026-11-22)
- **Keine Seitengröße 100, angebotene Größen werden erzwungen** | **Alternative:** Darstellung von ca. 500 Zeilen beschleunigen | **Abwägung:** 100 Tage pro Seite brauchten in der Abnahme bis 5,3 s und verletzten AK-28; 31 Tage bleiben unter 1,4 s (2026-11-22)

## Umsetzung
- Eigene Filament-Seite `app/Filament/Pages/DailyUsage.php` unter `/admin/tagesuebersicht` mit einer Filament-Tabelle auf berechneten Zeilen. Keine Aktionen, keine Formulare zum Ändern.
- Das Zusammenzählen steckt in `app/Support/DailyUsage/DailyUsageReport.php` (Summen je UTC-Stunde und Modell aus der Datenbank, Zuordnung zum deutschen Tag, Tage ohne Aufrufe, Tage einer Seite). `UsageTotals` hält die Summen einer Zeile, Kosten als ganze Zahl in Zehnmilliardstel-Dollar. `DayPaginator` sorgt dafür, dass „Showing 1 to 10 of 30“ Tage zählt, nicht Zeilen.
- Anzeige der Kostensumme ohne Gleitkomma in `GermanFormat::costSum()` (Rundung auf 4 Stellen, `(n ohne Kosten)`, `–`).
- Tage ohne Aufrufe erscheinen als eine Zeile mit Modell `–` und lauter Nullen, ohne eigene Tagessumme und ohne Link.
- Tagessumme und Gesamtsumme sind fett. Die Gesamtsumme steht als letzte Zeile jeder Seite, Spalte „Tag“ zeigt „Gesamtsumme“.
- Der Sprung zur Liste nutzt deren Filter in der Adresse (`filters[model][values][]`, `filters[period][from|until]`). Die Liste übernimmt diese Filter und merkt sie sich danach in ihrer Sitzung.
- Gemessen im Browser (lokal, 100.000 Aufrufe über 365 Tage, 4 Modelle): Seite laden ca. 0,9 s, Blättern ca. 0,2 s, Option einschalten ca. 0,2 s, „von“ entfernen mit eingeschalteter Option (365 Tage) ca. 0,6 s.
- Abweichung vom Plan: keine. Wie in PROJ-2 bleiben Beschriftungen, die Filament selbst mitbringt („Filters“, „Apply filters“, „Reset“, „Per page“, „Showing … results“), englisch, weil die App-Sprache `en` ist. Bei vielen Spalten scrollt die Tabelle waagerecht, wie die Liste aus PROJ-2.
