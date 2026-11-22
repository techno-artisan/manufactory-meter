# PROJ-2: Liste der API-Aufrufe im Admin-Panel

**Status:** Planned
**Erstellt:** 2026-11-22

## Abhängigkeiten
- PROJ-1 (Import von API-Aufrufen aus OTLP-JSON-Dateien): liefert die gespeicherten Aufrufe

## Begriffe
- **Aufruf:** ein gespeicherter API-Aufruf aus PROJ-1.
- **Deutsche Zeit:** Zeitzone Europe/Berlin, mit Sommer- und Winterzeit.

## User Stories
- Als Betreiber möchte ich alle gespeicherten API-Aufrufe im Admin-Panel als Liste sehen, damit ich den Token-Verbrauch ohne Datenbank-Werkzeug prüfen kann.
- Als Betreiber möchte ich die Liste nach Modell und Zeitraum filtern, damit ich gezielt einzelne Modelle oder Tage ansehen kann.
- Als Betreiber möchte ich nach Token, Kosten oder Dauer sortieren, damit ich teure oder langsame Aufrufe schnell finde.
- Als Betreiber möchte ich einen einzelnen Aufruf mit Original-Eintrag öffnen, damit ich auch Werte ohne eigene Spalte sehe.

## Außerhalb des Umfangs
- Summen, Auswertungen und Diagramme (eigenes Feature)
- Anlegen, Ändern oder Löschen von Aufrufen, auch keine Massenaktionen
- Import oder Upload von Dateien im Panel
- Export (z. B. CSV)
- Freitextsuche
- Filter nach Session, Prompt oder Claude-Code-Version
- Fertige Zeiträume zum Anklicken (heute, letzte 7 Tage, …)
- Uhrzeit im Zeitraum-Filter
- Einstellbare Zeitzone
- Automatische Aktualisierung der Liste während sie offen ist
- Eigene Spalten für Werte, die nur im Original-Eintrag stehen (z. B. `user.email`)
- Rollen oder Rechte über die bestehende Zugangsregel des Panels hinaus

## Spalten der Liste

| Spalte | Sichtbar | Sortierbar |
|--------|----------|------------|
| Zeitpunkt | immer | ja |
| Modell | immer | ja |
| Input-Token | immer | ja |
| Output-Token | immer | ja |
| Cache-Lese-Token | immer | ja |
| Cache-Schreib-Token | immer | ja |
| Kosten (USD) | immer | ja |
| Dauer (ms) | immer | ja |
| Session | einblendbar, anfangs ausgeblendet | nein |
| Prompt | einblendbar, anfangs ausgeblendet | nein |
| Claude-Code-Version | einblendbar, anfangs ausgeblendet | nein |

## Anzeigeformate
- Zeitpunkte in deutscher Zeit, Format `TT.MM.JJJJ HH:MM:SS`.
- Token und Dauer als ganze Zahl mit deutschem Tausenderpunkt, z. B. `12.345`.
- Kosten in der Liste mit genau 4 Nachkommastellen und Komma, z. B. `0,0123 $`.
- Kosten in der Detailansicht mit voller gespeicherter Genauigkeit.
- Fehlende Kosten, Dauer, Session, Prompt und Version erscheinen als `–`, nie als `0`.

## Akzeptanzkriterien
- [ ] AK-1: Angenommen ein angemeldeter Admin und 3 gespeicherte Aufrufe, wenn er im Menü „API-Aufrufe“ öffnet, dann sieht er eine Liste mit Titel „API-Aufrufe“, die alle 3 Aufrufe mit den 8 immer sichtbaren Spalten aus der Tabelle „Spalten der Liste“ zeigt.
- [ ] AK-2: Angenommen Aufrufe mit verschiedenen Zeitpunkten, wenn die Liste ohne weitere Auswahl öffnet, dann steht der neueste Aufruf oben und der älteste unten.
- [ ] AK-3: Angenommen die Liste ist frisch geöffnet, wenn der Admin sie ansieht, dann sind Session, Prompt und Claude-Code-Version ausgeblendet.
- [ ] AK-4: Angenommen ein Aufruf mit Session, Prompt und Version, wenn der Admin diese drei Spalten einblendet, dann zeigt die Liste deren Werte für diesen Aufruf.
- [ ] AK-5: Angenommen mehrere Aufrufe, wenn der Admin auf den Kopf einer sortierbaren Spalte klickt (Zeitpunkt, Modell, eine der vier Token-Spalten, Kosten, Dauer), dann ist die Liste nach dieser Spalte sortiert; ein zweiter Klick kehrt die Richtung um.
- [ ] AK-6: Angenommen die Spalten Session, Prompt und Version sind eingeblendet, wenn der Admin auf ihren Kopf klickt, dann ändert sich die Sortierung nicht.
- [ ] AK-7: Angenommen gespeicherte Aufrufe der Modelle A, B und C, wenn der Admin den Modell-Filter öffnet, dann stehen genau A, B und C zur Auswahl, ohne Modelle, die nicht gespeichert sind.
- [ ] AK-8: Angenommen Aufrufe der Modelle A, B und C, wenn der Admin im Modell-Filter A und B wählt, dann zeigt die Liste nur die Aufrufe von A und B.
- [ ] AK-9: Angenommen im Modell-Filter ist nichts gewählt, wenn die Liste angezeigt wird, dann zeigt sie die Aufrufe aller Modelle.
- [ ] AK-10: Angenommen Aufrufe am 20., 21. und 22.11.2026 (deutsche Zeit), wenn der Admin „von“ 21.11.2026 und „bis“ 21.11.2026 setzt, dann zeigt die Liste nur die Aufrufe vom 21.11.2026; Aufrufe um 00:00:00 und um 23:59:59 dieses Tages sind enthalten.
- [ ] AK-11: Angenommen Aufrufe am 20., 21. und 22.11.2026, wenn der Admin nur „von“ 21.11.2026 setzt, dann zeigt die Liste die Aufrufe vom 21. und 22.11.2026.
- [ ] AK-12: Angenommen Aufrufe am 20., 21. und 22.11.2026, wenn der Admin nur „bis“ 21.11.2026 setzt, dann zeigt die Liste die Aufrufe vom 20. und 21.11.2026.
- [ ] AK-13: Angenommen ein Aufruf mit gespeichertem Zeitpunkt 2026-11-21 23:30 UTC, wenn der Admin „von“ und „bis“ auf 22.11.2026 setzt, dann erscheint der Aufruf, weil er in deutscher Zeit am 22.11.2026 um 00:30 stattfand.
- [ ] AK-14: Angenommen Modell-Filter und Zeitraum sind gesetzt, wenn die Liste angezeigt wird, dann zeigt sie nur Aufrufe, die beide Bedingungen erfüllen.
- [ ] AK-15: Angenommen Aufrufe mit gespeicherten Zeitpunkten 2026-07-01 12:00:00 UTC und 2026-01-15 12:00:00 UTC, wenn die Liste angezeigt wird, dann steht dort `01.07.2026 14:00:00` und `15.01.2026 13:00:00`.
- [ ] AK-16: Angenommen ein Aufruf mit 12345 Input-Token, 1234567 Cache-Lese-Token, Dauer 4321 ms und Kosten 0.01234567 USD, wenn die Liste angezeigt wird, dann steht dort `12.345`, `1.234.567`, `4.321` und `0,0123 $`.
- [ ] AK-17: Angenommen einem Aufruf fehlen Kosten, Dauer, Session, Prompt und Version, wenn die Liste mit eingeblendeten Zusatzspalten angezeigt wird, dann steht in diesen fünf Zellen `–` und nirgends `0`.
- [ ] AK-18: Angenommen ein gespeicherter Aufruf, wenn der Admin in der Liste auf ihn klickt, dann öffnet sich die Detailansicht „API-Aufruf“ mit allen Werten aus der Liste (auch Session, Prompt und Version), den Kosten mit voller gespeicherter Genauigkeit und dem Original-Eintrag als eingerücktes, lesbares JSON.
- [ ] AK-19: Angenommen ein Aufruf, dessen Original-Eintrag `user.email` mit dem Wert `nutzer@example.test` enthält, wenn der Admin die Detailansicht öffnet, dann ist `nutzer@example.test` im angezeigten Original-Eintrag zu sehen.
- [ ] AK-20: Angenommen ein angemeldeter Admin, wenn er die Liste oder die Detailansicht ansieht, dann gibt es keine Schaltflächen und keine Massenaktionen zum Anlegen, Ändern oder Löschen von Aufrufen.
- [ ] AK-21: Angenommen ein angemeldeter Admin, wenn er eine Adresse zum Anlegen oder Ändern eines Aufrufs direkt aufruft, dann bekommt er keine Eingabemaske, sondern eine Fehlerseite, und die gespeicherten Aufrufe bleiben unverändert.
- [ ] AK-22: Angenommen 120 gespeicherte Aufrufe, wenn die Liste öffnet, dann zeigt sie 50 Aufrufe pro Seite und der Admin kann auf 25 oder 100 pro Seite umschalten.
- [ ] AK-23: Angenommen 100.000 gespeicherte Aufrufe auf einem normalen Entwicklerrechner, wenn der Admin eine Seite der Liste lädt, einen Filter setzt oder umsortiert, dann ist die Seite jeweils nach höchstens 2 Sekunden angezeigt.
- [ ] AK-24: Angenommen keine gespeicherten Aufrufe, wenn der Admin die Liste öffnet, dann sieht er den Hinweis „Noch keine API-Aufrufe“ und den Befehl `php artisan tokens:import` als Hinweis zum Einlesen.
- [ ] AK-25: Angenommen gespeicherte Aufrufe, aber keiner passt zu den gesetzten Filtern, wenn die Liste angezeigt wird, dann sieht der Admin den Hinweis „Keine Aufrufe für diese Filter“ und kann die Filter zurücksetzen.
- [ ] AK-26: Angenommen der Admin hat Filter gesetzt und eine Detailansicht geöffnet, wenn er zur Liste zurückgeht, dann sind die Filter noch gesetzt.
- [ ] AK-27: Angenommen ein nicht angemeldeter Besucher, wenn er die Liste oder eine Detailansicht aufruft, dann wird er zur Anmeldeseite geleitet und sieht keine Aufrufe.
- [ ] AK-28: Angenommen die Umgebung ist `production`, wenn ein angemeldeter Benutzer die Liste aufruft, dann wird der Zugang verweigert (bestehende Regel des Panels).

## Edge Cases
- EC-1: „bis“ liegt vor „von“: die Liste ist leer und zeigt den Hinweis aus AK-25; kein Absturz.
- EC-2: Mehrere Aufrufe haben denselben Zeitpunkt: beim Blättern erscheint jeder Aufruf genau einmal, keiner doppelt und keiner fehlt.
- EC-3: Sortieren nach Kosten oder Dauer, wenn Werte fehlen: Aufrufe ohne Wert stehen in beiden Richtungen am Ende.
- EC-4: Tag der Zeitumstellung (z. B. 25.10.2026, 25 Stunden lang): der Zeitraum-Filter umfasst den ganzen deutschen Kalendertag.
- EC-5: Während die Liste offen ist, läuft ein Import: neue Aufrufe erscheinen erst nach Neuladen oder Blättern; kein Fehler.
- EC-6: Sehr langer Modellname oder sehr großer Original-Eintrag: der Modellname ist vollständig lesbar, der Original-Eintrag wird vollständig angezeigt und ist scrollbar.
- EC-7: Detailansicht eines Aufrufs, der nicht existiert (falsche ID in der Adresse): Fehlerseite „nicht gefunden“, kein Absturz.
- EC-8: Kosten von 0 USD (gespeichert als 0, nicht leer): Anzeige `0,0000 $`, nicht `–`.

## Technische Anforderungen
- Nur lesender Zugriff auf die gespeicherten Aufrufe; das Feature ändert keine Daten.
- Kein Netzwerkzugriff nach außen.
- Testdaten sind erfunden: E-Mail-Adressen nur unter der Domain `example.test`, IDs (z. B. `session.id`, `prompt.id`, `user.account_uuid`, `organization.id`) zufällig erzeugt, keine Zeile und kein Wert aus echten Daten. Das gilt für Tests, Seeder, Factories und Beispiele in der Doku.

## Offene Fragen
- Keine

## Product Decisions
- Spalten: Zeitpunkt, Modell, vier Token-Werte, Kosten, Dauer immer sichtbar; Session, Prompt und Version einblendbar, anfangs aus: übersichtliche Liste, Zuordnung trotzdem erreichbar (2026-11-22)
- Standard-Sortierung neueste oben, Umsortieren nach Zeitpunkt, Modell, Token, Kosten und Dauer; nicht nach Session, Prompt, Version: teure oder langsame Aufrufe schnell finden, Sortieren nach IDs bringt nichts (2026-11-22)
- Modell-Filter als Mehrfachauswahl aus vorhandenen Modellen: mehrere Modelle vergleichbar, keine leeren Treffer durch Tippfehler (2026-11-22)
- Zeitraum als Tage von/bis, beide optional, beide Tage inklusive: einfach zu bedienen; Uhrzeit und fertige Zeiträume sind nicht nötig (2026-11-22)
- Anzeige und Tagesgrenzen in Europe/Berlin: „heute“ passt zum Kalender des Betreibers; gespeichert bleibt UTC (2026-11-22)
- Detailansicht nur lesend mit formatiertem Original-Eintrag: nur so sind Attribute ohne eigene Spalte sichtbar (2026-11-22)
- Keine Summen in diesem Feature: PROJ-2 bleibt eine reine Liste, Auswertungen werden ein eigenes Feature (2026-11-22)
- 50 pro Seite, umschaltbar 25/50/100; höchstens 2 Sekunden bei 100.000 Aufrufen: passt zur Mengen-Anforderung aus PROJ-1 (2026-11-22)
- Deutsches Zahlenformat, Kosten mit 4 Nachkommastellen in der Liste, fehlende Werte als `–`: kleine Kosten bleiben sichtbar, „unbekannt“ ist nicht „kostenlos“ (2026-11-22)
- Menüeintrag und Titel „API-Aufrufe“, Detailansicht „API-Aufruf“: deutsch und neutral, „Token-Verbrauch“ bleibt für die Auswertung frei (2026-11-22)
- Filter bleiben nach Rückkehr aus der Detailansicht erhalten: sonst müsste man sie nach jedem Blick in einen Aufruf neu setzen (2026-11-22)
- Fehlende Kosten oder Dauer stehen beim Sortieren in beiden Richtungen am Ende: sonst stehen bei „teuerste zuerst“ die unbekannten Werte oben (2026-11-22)
