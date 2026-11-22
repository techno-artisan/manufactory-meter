# QA-Bericht: PROJ-5 CSV-Export der API-Aufrufe (#21)

**Spec:** [spec.md](spec.md) · **Design:** [design.md](design.md)
**Getestet am:** 2026-11-22 · **Commit:** `424c115`

## Ergebnis
**Produktionsreif:** Ja
- Akzeptanzkriterien: 33 von 33 bestanden
- Bugs: Critical 0 · High 0 · Medium 0 · Low 0

Keine Oberfläche betroffen (reiner Artisan-Befehl); getestet per Pest und per Aufruf von `php artisan tokens:export` in der Konsole.

## Akzeptanzkriterien
- ✅ AK-1: 3 Aufrufe → Kopfzeile + 3 Zeilen, „Exportiert: 3 Aufrufe“, Exit-Code 0
- ✅ AK-2: Datei beginnt mit `EF BB BF`, 11 Semikolons je Zeile, jede Zeile endet mit CRLF, kein einzelnes LF
- ✅ AK-3: 12 Werte in der Reihenfolge der Spec-Tabelle, ID zuerst
- ✅ AK-4: 2026-07-01 12:00 UTC → `01.07.2026 14:00:00`, 2026-01-15 12:00 UTC → `15.01.2026 13:00:00`
- ✅ AK-5: `12345`, `1234567`, `4321` ohne Tausenderpunkt
- ✅ AK-6: `0,01234567`, `1,5`, `0`, kein `$` in der Datei
- ✅ AK-7: fehlende Kosten, Dauer, Session, Prompt, Version → leere Zellen
- ✅ AK-8: `"modell;mit ""Zeichen"""`, Zeile hat 12 Spalten
- ✅ AK-9: `"'=SUMME(1;2)"`, `'+abc`, `'-abc`, `'@abc`
- ✅ AK-10: `modell-ä` korrekt als UTF-8
- ✅ AK-11: `nutzer@example.test` aus dem Original-Eintrag kommt nicht vor (Spalte wird nicht gelesen)
- ✅ AK-12: ältester zuerst, bei gleichem Zeitpunkt kleinere ID zuerst
- ✅ AK-13: `--from=2026-11-21 --to=2026-11-21` → nur 21.11., inkl. 00:00:00 und 23:59:59
- ✅ AK-14: nur `--from` → 21. und 22.11.
- ✅ AK-15: nur `--to` → 20. und 21.11.
- ✅ AK-16: 2026-11-21 23:30 UTC ist am 22.11. enthalten, Zeitpunkt `22.11.2026 00:30:00`
- ✅ AK-17: `--model=A --model=B` → nur A und B
- ✅ AK-18: `--session=S1` → nur S1, keine Aufrufe ohne Session
- ✅ AK-19: ohne `--session` sind Aufrufe ohne Session mit leerer Zelle enthalten
- ✅ AK-20: Zeitraum, Modell und Session werden mit UND verknüpft
- ✅ AK-21: unbekanntes Modell / unbekannte Session / anderer Zeitraum → nur Kopfzeile, „Exportiert: 0 Aufrufe“, Exit-Code 0
- ✅ AK-22: leere Datenbank → wie AK-21
- ✅ AK-23: vorhandene Datei ohne `--force` → Meldung mit Pfad und `--force`, Exit-Code 1, Datei unverändert
- ✅ AK-24: mit `--force` ersetzt, keine temporäre Datei bleibt zurück
- ✅ AK-25: fehlender Ordner, nicht beschreibbarer Ordner, Pfad ist Ordner → Abbruch, Exit-Code 1, nichts angelegt (auch mit `--force`)
- ✅ AK-26: relativer Pfad landet im aktuellen Verzeichnis (Pest mit `chdir` und Konsole aus `/tmp/…`)
- ✅ AK-27: `21.11.2026`, `2026-02-30`, `morgen`, leer, `2026-1-05` → Meldung nennt Option und `JJJJ-MM-TT`, Exit-Code 1, keine Datei
- ✅ AK-28: `--to` vor `--from` → Abbruch, keine Datei
- ✅ AK-29: `--model=`, `--session=` und `--session` ohne Wert → Abbruch, keine Datei
- ✅ AK-30: ohne Pfad → „Not enough arguments (missing: "path")“, Exit-Code 1, keine Datei
- ✅ AK-31: in `production` ohne Rückfrage
- ✅ AK-32: 100.000 Aufrufe (SQLite-Datei, `memory_limit=128M`) in 1,6 s, 100.001 Zeilen; Pest-Test prüft < 10 s und < 32 MB zusätzlichen Speicher
- ✅ AK-33: Tabelle vor und nach dem Export identisch

## Edge Cases
- ✅ EC-1: 25.10.2026 umfasst alle 25 Stunden; die doppelte Stunde erscheint zweimal als `02:30:00`
- ✅ EC-2: Schreiben über temporäre Datei + Umbenennen; bei Fehler wird sie gelöscht. Hinweis: Ohne `pcntl` bleibt bei Strg+C die temporäre Datei `.tokens-export-…` im Ordner liegen, nie aber eine halbe Datei am Pfad
- ✅ EC-3: Export von 100.000 Aufrufen bei gleichzeitigem Import: kein Fehler, keine doppelte ID
- ✅ EC-4: `--model=A --model=A` → keine doppelten Zeilen
- ✅ EC-5: `--model=A` findet `a` nicht
- ✅ EC-6: Zahlen bekommen nie `'` (auch `-0,5`, `-5`)
- ✅ EC-7: 10 Milliarden Token und `0,0000000001` ohne wissenschaftliche Schreibweise und ohne Rundung
- ✅ EC-8: `Export März 2026.csv` wird unter genau diesem Namen angelegt

## Bugs
- Keine

## Sicherheit
- Nur lesender Datenbankzugriff, keine Netzwerkzugriffe.
- Original-Eintrag mit persönlichen Daten wird nicht gelesen.
- Filterwerte gehen als gebundene Parameter in die Abfrage (keine SQL-Injection).
- Formel-Injection in Excel durch vorangestelltes `'` bei Textwerten abgewehrt.

## Automatisierte Tests
- Unit: `tests/Unit/TokenExport/CsvFormatTest.php` (Kosten, Zahlen, Formel-Schutz, Quoting, Zeitzone)
- Feature: `tests/Feature/TokenExport/ExportTokensCommandTest.php` (alle AK, EC-1, EC-4 bis EC-8), dazu der vorhandene `CsvDelimiterTest.php` (unverändert, läuft jetzt und ist grün)
- Gesamt: Pint, PHPStan (Level 8) und Pest (239 Tests) grün

## Screenshots
- Entfällt (keine Oberfläche)
