# PROJ-5: CSV-Export der API-Aufrufe

**Status:** Planned
**Erstellt:** 2026-11-22

## Abhängigkeiten
- PROJ-1 (Import von API-Aufrufen aus OTLP-JSON-Dateien): liefert die gespeicherten Aufrufe; `tokens:import` ist das Vorbild für Aufruf und Meldungen
- PROJ-2 (Liste der API-Aufrufe im Admin-Panel): Spalten und Bedeutung der Werte gelten hier genauso; am Panel ändert sich nichts

## Begriffe
- **Aufruf:** ein gespeicherter API-Aufruf aus PROJ-1.
- **Deutsche Zeit:** Zeitzone Europe/Berlin, mit Sommer- und Winterzeit.
- **Tag:** ein Kalendertag in deutscher Zeit.
- **Passender Aufruf:** ein Aufruf, der zu allen angegebenen Optionen (`--from`, `--to`, `--model`, `--session`) passt.
- **Exportdatei:** die CSV-Datei, die der Befehl schreibt.

## User Stories
- Als Betreiber möchte ich die gespeicherten Aufrufe mit einem Artisan-Befehl als CSV-Datei exportieren, damit ich sie in Excel weiter auswerten kann.
- Als Betreiber möchte ich den Export auf Zeitraum, Modelle und eine Session eingrenzen, damit die Datei nur die Aufrufe enthält, die ich brauche.
- Als Betreiber möchte ich die Datei per Doppelklick in deutschem Excel öffnen, damit Spalten, Zahlen und Umlaute ohne Nacharbeit stimmen.
- Als Betreiber möchte ich den Befehl auch automatisch (z. B. per Cron) laufen lassen, damit ich regelmäßig Exporte bekomme.

## Außerhalb des Umfangs
- Knopf oder Seite für den Export im Admin-Panel
- Export der Tagesübersicht (PROJ-3) oder der Sessions-Seite (PROJ-4) bzw. von Summen
- Original-Eintrag (JSON) in der Exportdatei
- Auswahl oder Reihenfolge der Spalten per Option
- Andere Formate (Excel-Datei, JSON) oder internationales CSV-Format (Komma, Dezimalpunkt, UTC)
- Ausgabe auf den Bildschirm (stdout) statt in eine Datei
- Export im Hintergrund, per E-Mail oder in einen Cloud-Speicher
- Obergrenze für die Anzahl der Zeilen
- Wählbare Sortierung
- Uhrzeit in `--from`/`--to`, fertige Zeiträume (heute, letzte 7 Tage, …), einstellbare Zeitzone
- Filter nach Prompt oder Claude-Code-Version
- Anlegen von fehlenden Ordnern
- Rückfrage oder Sperre in der Umgebung `production`

## Befehl

```
php artisan tokens:export <pfad> [--from=JJJJ-MM-TT] [--to=JJJJ-MM-TT] [--model=NAME]... [--session=ID] [--force]
```

| Angabe | Pflicht | Bedeutung |
|--------|---------|-----------|
| `pfad` | ja | Zieldatei; relativ zum aktuellen Verzeichnis oder absolut |
| `--from` | nein | erster Tag (deutsche Zeit), inklusive |
| `--to` | nein | letzter Tag (deutsche Zeit), inklusive |
| `--model` | nein | Modellname, exakt; mehrfach angebbar, dann zählt jedes der Modelle |
| `--session` | nein | Session-ID, exakt |
| `--force` | nein | vorhandene Datei überschreiben |

Ohne Filter-Option enthält die Exportdatei alle gespeicherten Aufrufe.

## Aufbau der Exportdatei

Spalten in dieser Reihenfolge, Kopfzeile genau mit diesen Namen:

| Nr. | Kopfzeile | Inhalt |
|-----|-----------|--------|
| 1 | ID | ID des Aufrufs |
| 2 | Zeitpunkt | Zeitpunkt in deutscher Zeit |
| 3 | Modell | Modellname |
| 4 | Input-Token | Anzahl |
| 5 | Output-Token | Anzahl |
| 6 | Cache-Lese-Token | Anzahl |
| 7 | Cache-Schreib-Token | Anzahl |
| 8 | Kosten (USD) | Kosten |
| 9 | Dauer (ms) | Dauer |
| 10 | Session | Session-ID |
| 11 | Prompt | Prompt-ID |
| 12 | Claude-Code-Version | Version |

Format:
- Zeichenkodierung UTF-8 mit BOM (unsichtbare Markierung am Dateianfang, damit Excel Umlaute richtig zeigt).
- Trennzeichen Semikolon `;`, Zeilenende CRLF.
- Ein Wert, der `;`, `"` oder einen Zeilenumbruch enthält, steht in doppelten Anführungszeichen; `"` im Wert wird verdoppelt.
- Zeitpunkt im Format `TT.MM.JJJJ HH:MM:SS`.
- Token, Dauer und ID als ganze Zahl ohne Tausenderpunkt, z. B. `1234567`.
- Kosten mit Dezimalkomma, ohne `$`, mit allen gespeicherten Nachkommastellen, ohne Nullen am Ende, z. B. `0,01234567`; gespeicherte Kosten von 0 als `0`.
- Fehlende Kosten, Dauer, Session, Prompt und Version als leere Zelle, nie `–` und nie `0`.
- Textwerte (Modell, Session, Prompt, Version), die mit `=`, `+`, `-` oder `@` beginnen, bekommen ein vorangestelltes `'`, damit Excel sie nicht als Formel ausführt.
- Sortierung: ältester Aufruf zuerst; bei gleichem Zeitpunkt kleinere ID zuerst.

## Akzeptanzkriterien

### Inhalt und Format
- [ ] AK-1: Angenommen 3 gespeicherte Aufrufe, wenn der Betreiber `php artisan tokens:export <pfad>` ohne Filter-Option ausführt, dann enthält die Exportdatei die Kopfzeile aus „Aufbau der Exportdatei“ und genau 3 weitere Zeilen, und der Befehl meldet „Exportiert: 3 Aufrufe“ und endet mit Exit-Code 0.
- [ ] AK-2: Angenommen ein beliebiger Export, wenn die Exportdatei als Bytes gelesen wird, dann beginnt sie mit dem UTF-8-BOM (`EF BB BF`), Spalten sind durch `;` getrennt und jede Zeile endet mit CRLF.
- [ ] AK-3: Angenommen ein Aufruf mit allen Werten, wenn exportiert wird, dann stehen seine Werte in den 12 Spalten in der Reihenfolge der Tabelle „Aufbau der Exportdatei“.
- [ ] AK-4: Angenommen Aufrufe mit gespeicherten Zeitpunkten 2026-07-01 12:00:00 UTC und 2026-01-15 12:00:00 UTC, wenn exportiert wird, dann steht in der Spalte Zeitpunkt `01.07.2026 14:00:00` und `15.01.2026 13:00:00`.
- [ ] AK-5: Angenommen ein Aufruf mit 12345 Input-Token, 1234567 Cache-Lese-Token und Dauer 4321 ms, wenn exportiert wird, dann steht dort `12345`, `1234567` und `4321`, ohne Tausenderpunkt.
- [ ] AK-6: Angenommen Aufrufe mit gespeicherten Kosten 0.01234567, 1.5 und 0 USD, wenn exportiert wird, dann steht in der Spalte Kosten (USD) `0,01234567`, `1,5` und `0`, jeweils ohne `$`.
- [ ] AK-7: Angenommen einem Aufruf fehlen Kosten, Dauer, Session, Prompt und Version, wenn exportiert wird, dann sind diese fünf Zellen leer, und keine davon enthält `–` oder `0`.
- [ ] AK-8: Angenommen ein Aufruf mit Modellname `modell;mit "Zeichen"`, wenn exportiert wird, dann steht der Wert als `"modell;mit ""Zeichen"""` in der Datei, und die Zeile hat trotzdem genau 12 Spalten.
- [ ] AK-9: Angenommen Aufrufe mit Modellname `=SUMME(1;2)`, Session `+abc`, Prompt `-abc` und Version `@abc`, wenn exportiert wird, dann steht in der Datei `'=SUMME(1;2)` (in Anführungszeichen wegen `;`), `'+abc`, `'-abc` und `'@abc`.
- [ ] AK-10: Angenommen ein Aufruf mit Modellname `modell-ä`, wenn die Exportdatei als UTF-8 gelesen wird, dann steht dort `modell-ä`.
- [ ] AK-11: Angenommen ein Aufruf, dessen Original-Eintrag `user.email` mit dem Wert `nutzer@example.test` enthält, wenn exportiert wird, dann kommt `nutzer@example.test` in der Exportdatei nicht vor.
- [ ] AK-12: Angenommen Aufrufe mit verschiedenen Zeitpunkten und zwei Aufrufe mit gleichem Zeitpunkt, wenn exportiert wird, dann steht der älteste Aufruf direkt unter der Kopfzeile, der neueste am Ende, und von den beiden gleichzeitigen steht der mit der kleineren ID zuerst.

### Filter
- [ ] AK-13: Angenommen Aufrufe am 20., 21. und 22.11.2026 (deutsche Zeit), wenn mit `--from=2026-11-21 --to=2026-11-21` exportiert wird, dann enthält die Datei nur die Aufrufe vom 21.11.2026; Aufrufe um 00:00:00 und um 23:59:59 dieses Tages sind enthalten.
- [ ] AK-14: Angenommen Aufrufe am 20., 21. und 22.11.2026, wenn nur mit `--from=2026-11-21` exportiert wird, dann enthält die Datei die Aufrufe vom 21. und 22.11.2026.
- [ ] AK-15: Angenommen Aufrufe am 20., 21. und 22.11.2026, wenn nur mit `--to=2026-11-21` exportiert wird, dann enthält die Datei die Aufrufe vom 20. und 21.11.2026.
- [ ] AK-16: Angenommen ein Aufruf mit gespeichertem Zeitpunkt 2026-11-21 23:30 UTC, wenn mit `--from=2026-11-22 --to=2026-11-22` exportiert wird, dann ist der Aufruf enthalten, weil er in deutscher Zeit am 22.11.2026 um 00:30 stattfand.
- [ ] AK-17: Angenommen Aufrufe der Modelle A, B und C, wenn mit `--model=A --model=B` exportiert wird, dann enthält die Datei nur die Aufrufe von A und B.
- [ ] AK-18: Angenommen Aufrufe der Sessions S1 und S2 und Aufrufe ohne Session, wenn mit `--session=S1` exportiert wird, dann enthält die Datei nur die Aufrufe von S1.
- [ ] AK-19: Angenommen Aufrufe ohne Session, wenn ohne `--session` exportiert wird, dann sind sie enthalten, mit leerer Zelle in der Spalte Session.
- [ ] AK-20: Angenommen Zeitraum, Modell und Session sind angegeben, wenn exportiert wird, dann enthält die Datei nur Aufrufe, die alle drei Bedingungen erfüllen.
- [ ] AK-21: Angenommen gespeicherte Aufrufe, aber keiner passt zu den Optionen (auch bei unbekanntem Modell oder unbekannter Session), wenn exportiert wird, dann enthält die Datei nur die Kopfzeile, der Befehl meldet „Exportiert: 0 Aufrufe“ und endet mit Exit-Code 0.
- [ ] AK-22: Angenommen keine gespeicherten Aufrufe, wenn exportiert wird, dann gilt dasselbe wie in AK-21.

### Zieldatei
- [ ] AK-23: Angenommen am Pfad liegt schon eine Datei, wenn ohne `--force` exportiert wird, dann bricht der Befehl mit einer Fehlermeldung ab, die den Pfad und `--force` nennt, endet mit Exit-Code ungleich 0, und die vorhandene Datei ist unverändert.
- [ ] AK-24: Angenommen am Pfad liegt schon eine Datei, wenn mit `--force` exportiert wird, dann ist sie durch die neue Exportdatei ersetzt.
- [ ] AK-25: Angenommen der Ordner des Pfads existiert nicht, ist nicht beschreibbar oder der Pfad ist ein Ordner, wenn exportiert wird (auch mit `--force`), dann bricht der Befehl mit einer Fehlermeldung ab, endet mit Exit-Code ungleich 0 und legt weder Datei noch Ordner an.
- [ ] AK-26: Angenommen ein relativer Pfad, wenn exportiert wird, dann liegt die Exportdatei relativ zum aktuellen Verzeichnis, aus dem der Befehl gestartet wurde.

### Ungültige Angaben
- [ ] AK-27: Angenommen `--from` oder `--to` ist kein gültiges Datum im Format `JJJJ-MM-TT` (z. B. `21.11.2026`, `2026-02-30`, `morgen`), wenn exportiert wird, dann bricht der Befehl mit einer Fehlermeldung ab, die die Option und das erwartete Format nennt, endet mit Exit-Code ungleich 0 und schreibt keine Datei.
- [ ] AK-28: Angenommen `--to` liegt vor `--from`, wenn exportiert wird, dann bricht der Befehl mit einer Fehlermeldung ab, endet mit Exit-Code ungleich 0 und schreibt keine Datei.
- [ ] AK-29: Angenommen `--model=` oder `--session=` mit leerem Wert, wenn exportiert wird, dann bricht der Befehl mit einer Fehlermeldung ab, endet mit Exit-Code ungleich 0 und schreibt keine Datei.
- [ ] AK-30: Angenommen der Befehl wird ohne Pfad aufgerufen, wenn er startet, dann bricht er mit einer Fehlermeldung ab und schreibt keine Datei.

### Betrieb und Leistung
- [ ] AK-31: Angenommen die Umgebung ist `production`, wenn der Befehl ausgeführt wird, dann exportiert er ohne Rückfrage wie in jeder anderen Umgebung.
- [ ] AK-32: Angenommen 100.000 gespeicherte Aufrufe auf einem normalen Entwicklerrechner, wenn ohne Filter exportiert wird, dann ist der Befehl nach höchstens 10 Sekunden fertig, die Datei enthält 100.001 Zeilen, und er kommt mit dem PHP-Speicherlimit von 128 MB aus.
- [ ] AK-33: Angenommen ein Export, wenn der Befehl fertig ist, dann sind die gespeicherten Aufrufe unverändert.

## Edge Cases
- EC-1: Tag der Zeitumstellung (z. B. 25.10.2026, 25 Stunden lang): `--from`/`--to` umfassen den ganzen deutschen Kalendertag; die Zeitpunkte zeigen die richtige deutsche Uhrzeit, die doppelte Stunde erscheint zweimal mit gleicher Uhrzeit.
- EC-2: Fehler mitten im Schreiben (z. B. Platte voll, Abbruch mit Strg+C): am Pfad bleibt keine halbe Exportdatei liegen; eine dort vorher vorhandene Datei ist unverändert.
- EC-3: Während des Exports läuft ein Import: kein Fehler; jeder Aufruf steht höchstens einmal in der Datei.
- EC-4: `--model` mehrfach mit demselben Namen: wie einmal angegeben, keine doppelten Zeilen.
- EC-5: Modellname mit Groß-/Kleinschreibung, die nicht exakt passt: kein Treffer (exakter Vergleich).
- EC-6: Zahlenwerte (Token, Dauer, Kosten) werden nie mit `'` versehen, auch nicht negative Werte, falls es sie gäbe.
- EC-7: Sehr großer Wert (z. B. 10 Milliarden Token, Kosten mit 10 Nachkommastellen): vollständig, ohne wissenschaftliche Schreibweise und ohne Rundung.
- EC-8: Pfad mit Leerzeichen oder Umlauten: Datei wird unter genau diesem Namen angelegt.

## Technische Anforderungen
- Nur lesender Zugriff auf die gespeicherten Aufrufe; das Feature ändert keine Daten.
- Kein Netzwerkzugriff nach außen.
- Testdaten sind erfunden: E-Mail-Adressen nur unter der Domain `example.test`, IDs (z. B. `session.id`, `prompt.id`, `user.account_uuid`, `organization.id`) zufällig erzeugt, keine Zeile und kein Wert aus echten Daten. Das gilt für Tests, Seeder, Factories und Beispiele in der Doku.

## Offene Fragen
- Keine

## Product Decisions
- Export nur der einzelnen Aufrufe (Daten wie PROJ-2), keine Summen: Rohdaten reichen, Summen bildet Excel; Export aller drei Seiten verworfen, weil dreifacher Aufwand (2026-11-22)
- Nur Artisan-Befehl `tokens:export`, kein Knopf im Panel: passt zu `tokens:import`, automatisierbar; Knopf zusätzlich verworfen, weil doppelte Regeln und Tests (2026-11-22)
- Filter als Optionen `--from`, `--to`, `--model` (mehrfach), `--session` mit derselben Bedeutung wie die Filter in PROJ-2: gleiche Ergebnisse wie im Panel (2026-11-22)
- Feste Sortierung ältester zuerst: natürliche Reihenfolge für eine Datei; wählbare Sortierung bringt in Excel nichts (2026-11-22)
- Immer alle 12 Spalten inklusive ID, ohne Original-Eintrag: Datei hängt nicht von Einstellungen ab; JSON ist groß und enthält persönliche Daten wie `user.email` (2026-11-22)
- Deutsches Excel-Format (Semikolon, Dezimalkomma, deutsche Zeit, UTF-8 mit BOM, leere Zelle für fehlende Werte): Datei öffnet per Doppelklick richtig; internationales Format verworfen (2026-11-22)
- Kosten mit voller Genauigkeit ohne Nullen am Ende, ohne `$`: Excel rechnet mit der Zahl, keine Rundung (2026-11-22)
- Keine Obergrenze, kein Hintergrund-Export; höchstens 10 Sekunden bei 100.000 Aufrufen: Mengen wie PROJ-1, einfacher Ablauf (2026-11-22)
- Pfad als Pflicht-Angabe, vorhandene Datei nur mit `--force` überschreiben: kein versehentliches Überschreiben; stdout verworfen wegen Kodierungsproblemen bei Umleitung in PowerShell 5.1 (2026-11-22)
- Ungültige Datumsangaben, `--to` vor `--from` und leere Werte brechen ab; unbekanntes Modell oder Session ergibt eine Datei nur mit Kopfzeile: Tippfehler im Datum fallen auf, leere Ergebnisse sind kein Fehler (2026-11-22)
- Befehl läuft auch in `production`, wie `tokens:import`: wer ihn ausführen kann, hat ohnehin Zugriff auf die Datenbank (2026-11-22)
- Schutz gegen Formeln in Excel durch vorangestelltes `'` bei Textwerten: kleiner Aufwand, schließt ein bekanntes CSV-Risiko (2026-11-22)
- Zeilenende CRLF und Anführungszeichen nach RFC 4180: Standard für CSV, Excel liest beides (2026-11-22)
