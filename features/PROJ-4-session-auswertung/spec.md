# PROJ-4: Auswertung nach Session

**Status:** Planned
**Erstellt:** 2026-11-22

## Abhängigkeiten
- PROJ-1 (Import von API-Aufrufen aus OTLP-JSON-Dateien): liefert die gespeicherten Aufrufe mit Session
- PROJ-2 (Liste der API-Aufrufe im Admin-Panel): Ziel des Sprungs von einer Session zu ihren Aufrufen; bekommt dafür einen Filter „Session“; Anzeigeformate gelten hier genauso
- PROJ-3 (Daily Usage Overview): Filter, Standard-Zeitraum und Darstellung fehlender Kosten gelten hier genauso

## Begriffe
- **Aufruf:** ein gespeicherter API-Aufruf aus PROJ-1.
- **Session:** alle Aufrufe mit derselben gespeicherten Session-ID.
- **Deutsche Zeit:** Zeitzone Europe/Berlin, mit Sommer- und Winterzeit.
- **Passender Aufruf:** ein Aufruf, der zu den gesetzten Filtern (Zeitraum, Modell) passt.
- **Session-Zeile:** eine Zeile für genau eine Session, berechnet nur aus ihren passenden Aufrufen.
- **Gesamtsumme:** Summe aller Session-Zeilen, die zu den gesetzten Filtern passen.

## User Stories
- Als Betreiber möchte ich Token und Kosten je Claude-Code-Session sehen, damit ich weiß, welche Arbeitssitzung wie viel gekostet hat.
- Als Betreiber möchte ich nach Kosten, Token oder Zeit sortieren, damit ich die teuerste oder neueste Session schnell finde.
- Als Betreiber möchte ich Zeitraum und Modelle filtern, damit ich Sessions einer Woche oder eines Modells vergleichen kann.
- Als Betreiber möchte ich von einer Session zu ihren einzelnen Aufrufen springen, damit ich eine auffällige Session genauer prüfen kann.
- Als Betreiber möchte ich eine Gesamtsumme mit der Anzahl der Sessions sehen, damit ich den Zeitraum ohne eigenes Rechnen einschätzen kann.

## Außerhalb des Umfangs
- Diagramme und Grafiken
- Auswertung nach Prompt, Claude-Code-Version oder Nutzer
- Zeilen je Session und Modell oder aufklappbare Unterzeilen
- Detailseite je Session (der Sprung führt zur Liste aus PROJ-2)
- Namen, Notizen oder Markierungen für Sessions
- Dauer einer Session als eigene Spalte
- Suche nach Session-ID auf der Sessions-Seite
- Export (z. B. CSV)
- Budgets, Grenzwerte, Warnungen
- Kosten selbst berechnen (z. B. aus Token und Preisliste) für Aufrufe ohne Kosten
- Fertige Zeiträume zum Anklicken (heute, letzte 7 Tage, …) und Uhrzeit im Zeitraum-Filter
- Einstellbare Zeitzone
- Automatische Aktualisierung, während die Seite offen ist
- Dashboard-Widget auf der Startseite des Panels
- Anlegen, Ändern oder Löschen von Daten
- Rollen oder Rechte über die bestehende Zugangsregel des Panels hinaus

## Spalten

| Spalte | Inhalt | Sortierbar |
|--------|--------|------------|
| Session | Session-ID, vollständig | nein |
| Erster Aufruf | Zeitpunkt des frühesten passenden Aufrufs | ja |
| Letzter Aufruf | Zeitpunkt des spätesten passenden Aufrufs | ja |
| Aufrufe | Anzahl der passenden Aufrufe | ja |
| Modelle | Modellnamen der passenden Aufrufe | nein |
| Input-Token | Summe | ja |
| Output-Token | Summe | ja |
| Cache-Lese-Token | Summe | ja |
| Cache-Schreib-Token | Summe | ja |
| Kosten (USD) | Summe der bekannten Kosten, ggf. mit Hinweis auf fehlende Kosten | ja |

## Anzeigeformate
- Zeitpunkte in deutscher Zeit, Format `TT.MM.JJJJ HH:MM:SS`.
- Modelle: jedes Modell genau einmal, alphabetisch, durch Komma und Leerzeichen getrennt, z. B. `model-a, model-b`.
- Anzahl und Token als ganze Zahl mit deutschem Tausenderpunkt, z. B. `12.345`.
- Kosten mit genau 4 Nachkommastellen und Komma, z. B. `0,0123 $`.
- Kosten-Summen zählen nur Aufrufe mit gespeicherten Kosten. Fehlen in einer Zeile bei n Aufrufen die Kosten (1 ≤ n < Anzahl), steht hinter der Summe `(n ohne Kosten)`, z. B. `1,2345 $ (2 ohne Kosten)`. Fehlen bei allen Aufrufen der Zeile die Kosten, steht `–`. Das gilt für Session-Zeile und Gesamtsumme.
- Gespeicherte Kosten von 0 zählen als bekannt (`0,0000 $`), nicht als fehlend.

## Akzeptanzkriterien

### Seite und Inhalt
- [ ] AK-1: Angenommen ein angemeldeter Admin, wenn er im Menü „Sessions“ öffnet, dann sieht er eine Seite mit Titel „Sessions“ und einer Tabelle mit den 10 Spalten aus der Tabelle „Spalten“.
- [ ] AK-2: Angenommen Session S1 mit 3 Aufrufen (Input-Token 100, 200 und 300, Kosten 0.01, 0.02 und 0.03 USD) und Session S2 mit 1 Aufruf, wenn der Admin die Seite ansieht, dann gibt es genau eine Zeile für S1 mit Aufrufe `3`, Input-Token `600` und Kosten `0,0600 $` und genau eine Zeile für S2; entsprechend für alle vier Token-Spalten.
- [ ] AK-3: Angenommen eine Session mit Aufrufen um 2026-11-21 09:00:00 UTC, 10:00:00 UTC und 11:30:00 UTC, wenn die Seite angezeigt wird, dann steht als erster Aufruf `21.11.2026 10:00:00` und als letzter Aufruf `21.11.2026 12:30:00`.
- [ ] AK-4: Angenommen eine Session mit Aufrufen der Modelle model-b, model-a und noch einmal model-b, wenn die Seite angezeigt wird, dann steht in der Spalte Modelle `model-a, model-b`.
- [ ] AK-5: Angenommen eine Session-ID mit 36 Zeichen, wenn die Seite angezeigt wird, dann steht die Session-ID vollständig in der Zeile.
- [ ] AK-6: Angenommen eine Zeile mit 12345 Input-Token, 1234567 Cache-Lese-Token und Kosten 0.01234567 USD, wenn die Seite angezeigt wird, dann steht dort `12.345`, `1.234.567` und `0,0123 $`.

### Fehlende Kosten
- [ ] AK-7: Angenommen eine Session mit 3 Aufrufen, davon 2 mit Kosten 0.01 und 0.02 USD und 1 ohne Kosten, wenn die Seite angezeigt wird, dann steht in der Kosten-Spalte `0,0300 $ (1 ohne Kosten)`.
- [ ] AK-8: Angenommen eine Session, deren Aufrufe alle keine Kosten haben, wenn die Seite angezeigt wird, dann steht in der Kosten-Spalte `–` und nicht `0,0000 $`.
- [ ] AK-9: Angenommen eine Session, deren einziger Aufruf gespeicherte Kosten von 0 hat, wenn die Seite angezeigt wird, dann steht in der Kosten-Spalte `0,0000 $`.

### Sortierung
- [ ] AK-10: Angenommen Sessions mit verschiedenen letzten Aufrufen, wenn die Seite ohne weitere Auswahl öffnet, dann steht die Session mit dem spätesten letzten Aufruf oben.
- [ ] AK-11: Angenommen mehrere Sessions, wenn der Admin auf den Kopf einer sortierbaren Spalte klickt (erster Aufruf, letzter Aufruf, Aufrufe, eine der vier Token-Spalten, Kosten), dann ist die Tabelle nach dieser Spalte sortiert; ein zweiter Klick kehrt die Richtung um.
- [ ] AK-12: Angenommen Sessions mit Kosten 0,0100 $, 0,0500 $ und eine Session mit `–`, wenn der Admin nach Kosten absteigend und danach aufsteigend sortiert, dann steht die Session mit `–` in beiden Richtungen am Ende.
- [ ] AK-13: Angenommen eine Session mit `0,0300 $ (1 ohne Kosten)` und eine mit `0,0200 $`, wenn der Admin nach Kosten absteigend sortiert, dann steht die Session mit `0,0300 $ (1 ohne Kosten)` oben (sortiert wird nach der Summe der bekannten Kosten).
- [ ] AK-14: Angenommen die Spalten Session und Modelle, wenn der Admin auf ihren Kopf klickt, dann ändert sich die Sortierung nicht.

### Filter
- [ ] AK-15: Angenommen heute ist der 22.11.2026 (deutsche Zeit), wenn der Admin die Seite zum ersten Mal öffnet, dann ist der Zeitraum auf „von“ 24.10.2026 und „bis“ 22.11.2026 gesetzt (30 Tage inklusive heute) und ältere Aufrufe fehlen in Tabelle und Summen.
- [ ] AK-16: Angenommen eine Session mit Aufrufen am 20., 21. und 22.11.2026, wenn der Admin „von“ 21.11.2026 und „bis“ 21.11.2026 setzt, dann zählt die Zeile nur die Aufrufe vom 21.11.2026, und erster und letzter Aufruf liegen am 21.11.2026; Aufrufe um 00:00:00 und 23:59:59 dieses Tages zählen mit.
- [ ] AK-17: Angenommen Sessions mit Aufrufen am 20., 21. und 22.11.2026, wenn der Admin nur „von“ 21.11.2026 setzt und „bis“ leert, dann zählen nur die Aufrufe vom 21. und 22.11.2026.
- [ ] AK-18: Angenommen Sessions mit Aufrufen am 20., 21. und 22.11.2026, wenn der Admin nur „bis“ 21.11.2026 setzt und „von“ leert, dann zählen nur die Aufrufe vom 20. und 21.11.2026.
- [ ] AK-19: Angenommen eine Session, deren Aufrufe alle am 20.11.2026 liegen, wenn der Admin „von“ 21.11.2026 setzt, dann erscheint diese Session nicht.
- [ ] AK-20: Angenommen ein Aufruf mit gespeichertem Zeitpunkt 2026-11-21 23:30 UTC, wenn der Admin „von“ und „bis“ auf 22.11.2026 setzt, dann zählt er mit, weil er in deutscher Zeit am 22.11.2026 um 00:30 stattfand.
- [ ] AK-21: Angenommen gespeicherte Aufrufe der Modelle A, B und C, wenn der Admin den Modell-Filter öffnet, dann stehen genau A, B und C zur Auswahl.
- [ ] AK-22: Angenommen eine Session mit Aufrufen der Modelle A, B und C, wenn der Admin im Modell-Filter A und B wählt, dann zählt die Zeile nur die Aufrufe von A und B, die Spalte Modelle zeigt `A, B`, und die Gesamtsumme enthält nur A und B.
- [ ] AK-23: Angenommen eine Session nur mit Aufrufen von Modell C, wenn der Admin im Modell-Filter A wählt, dann erscheint diese Session nicht.
- [ ] AK-24: Angenommen dieselben Filter auf der Sessions-Seite und der Tagesübersicht aus PROJ-3 und alle Aufrufe haben eine Session-ID, wenn der Admin beide Gesamtsummen vergleicht, dann stimmen Aufrufe, die vier Token-Werte und Kosten überein.

### Gesamtsumme und Seiten
- [ ] AK-25: Angenommen mehrere Sessions, wenn die Seite angezeigt wird, dann steht am Ende eine Gesamtsumme mit der Anzahl der Sessions, Aufrufen, vier Token-Werten und Kosten über alle Sessions, die zu den Filtern passen.
- [ ] AK-26: Angenommen 3 Sessions mit zusammen 4 Aufrufen ohne Kosten, wenn die Seite angezeigt wird, dann nennt die Gesamtsumme die Summe der bekannten Kosten mit `(4 ohne Kosten)`; haben alle Aufrufe keine Kosten, steht `–`.
- [ ] AK-27: Angenommen 120 passende Sessions, wenn die Seite öffnet, dann zeigt sie 50 Sessions pro Seite und der Admin kann auf 25 oder 100 pro Seite umschalten.
- [ ] AK-28: Angenommen so viele Sessions, dass die Tabelle auf mehrere Seiten verteilt ist, wenn der Admin eine beliebige Seite ansieht, dann gilt die Gesamtsumme für alle passenden Sessions, nicht nur für die angezeigte Seite.

### Sprung zur Liste
- [ ] AK-29: Angenommen eine Session S1 und auf der Sessions-Seite sind „von“ 20.11.2026, „bis“ 22.11.2026 und Modell A gesetzt, wenn der Admin auf die Zeile von S1 klickt, dann öffnet sich die Liste „API-Aufrufe“ aus PROJ-2 mit Session-Filter S1, Zeitraum 20.11.2026 bis 22.11.2026 und Modell-Filter A, und sie zeigt genau die Aufrufe, die in der Zeile gezählt sind.
- [ ] AK-30: Angenommen auf der Sessions-Seite sind „von“ oder „bis“ leer oder kein Modell gewählt, wenn der Admin auf eine Zeile klickt, dann ist der entsprechende Filter in der Liste ebenfalls leer.
- [ ] AK-31: Angenommen die Liste „API-Aufrufe“ mit gesetztem Session-Filter, wenn der Admin sie ansieht, dann ist der Session-Filter als gesetzter Filter mit der Session-ID sichtbar und lässt sich einzeln entfernen; danach zeigt die Liste wieder Aufrufe aller Sessions (im Rahmen der übrigen Filter).
- [ ] AK-32: Angenommen der Admin hat auf der Sessions-Seite Filter, Sortierung oder Seitengröße geändert und ist zur Liste gesprungen, wenn er zur Sessions-Seite zurückkehrt, dann sind seine Einstellungen noch gesetzt.

### Leere Zustände, Rechte, Leistung
- [ ] AK-33: Angenommen keine gespeicherten Aufrufe, wenn der Admin die Seite öffnet, dann sieht er den Hinweis „Noch keine API-Aufrufe“ und den Befehl `php artisan tokens:import` als Hinweis zum Einlesen.
- [ ] AK-34: Angenommen gespeicherte Aufrufe, aber keiner passt zu den Filtern, wenn die Seite angezeigt wird, dann sieht der Admin den Hinweis „Keine Aufrufe für diese Filter“ und kann die Filter zurücksetzen; danach gilt wieder der Standard-Zeitraum aus AK-15.
- [ ] AK-35: Angenommen ein angemeldeter Admin, wenn er die Seite ansieht, dann gibt es keine Schaltflächen zum Anlegen, Ändern oder Löschen.
- [ ] AK-36: Angenommen 100.000 gespeicherte Aufrufe in 5.000 Sessions über 365 Tage auf einem normalen Entwicklerrechner, wenn der Admin die Seite lädt, einen Filter setzt, umsortiert oder blättert, dann ist die Seite jeweils nach höchstens 2 Sekunden angezeigt.
- [ ] AK-37: Angenommen 100.000 gespeicherte Aufrufe in 5.000 Sessions, wenn der Admin in der Liste „API-Aufrufe“ den Session-Filter setzt oder per Sprung öffnet, dann ist die Liste nach höchstens 2 Sekunden angezeigt.
- [ ] AK-38: Angenommen ein nicht angemeldeter Besucher, wenn er die Seite aufruft, dann wird er zur Anmeldeseite geleitet und sieht keine Zahlen.
- [ ] AK-39: Angenommen die Umgebung ist `production`, wenn ein angemeldeter Benutzer die Seite aufruft, dann wird der Zugang verweigert (bestehende Regel des Panels).

## Edge Cases
- EC-1: „bis“ liegt vor „von“: keine Sessions, Hinweis aus AK-34; kein Absturz.
- EC-2: Session über Mitternacht oder über die Grenze des Zeitraums: nur die passenden Aufrufe zählen (AK-16); die Session erscheint einmal, nicht einmal je Tag.
- EC-3: Tag der Zeitumstellung (z. B. 25.10.2026, 25 Stunden lang): der Zeitraum-Filter umfasst den ganzen deutschen Kalendertag; erster und letzter Aufruf zeigen die richtige deutsche Uhrzeit.
- EC-4: Mehrere Sessions mit gleichem Sortierwert (z. B. gleiche Kosten): beim Blättern erscheint jede Session genau einmal, keine doppelt und keine fehlt.
- EC-5: Session mit nur einem Aufruf: erster und letzter Aufruf sind gleich.
- EC-6: Sehr lange Session-ID oder viele Modelle in einer Session: vollständig lesbar.
- EC-7: Sehr große Summen (z. B. mehrere Milliarden Token im Zeitraum): korrekt angezeigt, kein Überlauf, keine wissenschaftliche Schreibweise.
- EC-8: Viele kleine Kosten (z. B. 10.000 × 0.00001234 USD in einer Session): die Summe ist auf 4 Nachkommastellen genau, ohne Rundungsfehler durch Aufsummieren gerundeter Werte.
- EC-9: Während die Seite offen ist, läuft ein Import: neue Aufrufe erscheinen erst nach Neuladen, Filtern, Sortieren oder Blättern; kein Fehler.
- EC-10: Session-Filter in der Liste mit einer Session-ID, die es nicht gibt (z. B. von Hand in der Adresse): leere Liste mit Hinweis „Keine Aufrufe für diese Filter“; kein Absturz.

## Technische Anforderungen
- Nur lesender Zugriff auf die gespeicherten Aufrufe; das Feature ändert keine Daten.
- Kein Netzwerkzugriff nach außen.
- Testdaten sind erfunden: E-Mail-Adressen nur unter der Domain `example.test`, IDs (auch Session-IDs) zufällig erzeugt, keine Zeile und kein Wert aus echten Daten. Das gilt für Tests, Seeder, Factories und Beispiele in der Doku.

## Offene Fragen
- Keine

## Product Decisions
- Eigene Seite „Sessions“ mit einer Zeile je Session: Überblick über alle Sessions auf einen Blick; Zeilen je Session und Modell (zu unübersichtlich) und nur ein Session-Filter in PROJ-2 (keine Übersicht) verworfen (2026-11-22)
- Filter wie PROJ-3 (Zeitraum von/bis, Modell-Mehrfachauswahl), Standard die letzten 30 Tage inklusive heute: einheitliche Bedienung im Panel (2026-11-22)
- Eine Session zählt nur mit ihren passenden Aufrufen, auch für erster/letzter Aufruf und Modelle: Summen stimmen mit der Tagesübersicht überein; Zählen der kompletten Session verworfen, weil die Summen dann abweichen (2026-11-22)
- Klick auf eine Session öffnet die Liste aus PROJ-2 mit Session-, Zeitraum- und Modell-Filter; die Liste bekommt dafür einen Filter „Session“: auffällige Sessions ohne Handarbeit prüfen, gleiches Muster wie in PROJ-3 (2026-11-22)
- Umsortieren per Spaltenkopf, Standard letzter Aufruf neueste zuerst, fehlende Kosten immer am Ende: teuerste Session mit einem Klick finden; Verhalten bei fehlenden Werten wie PROJ-2 (2026-11-22)
- Sortieren nach Kosten nutzt die Summe der bekannten Kosten: entspricht der angezeigten Zahl (2026-11-22)
- Gesamtsumme über alle passenden Sessions mit Anzahl der Sessions; 50 pro Seite, umschaltbar auf 25 oder 100: Zeitraum ohne eigenes Rechnen einschätzen; Seitengrößen wie PROJ-2 (2026-11-22)
- Anzeigeformate und Darstellung fehlender Kosten wie PROJ-2 und PROJ-3: einheitliches Bild im Panel (2026-11-22)
- Höchstens 2 Sekunden bei 100.000 Aufrufen: gleiche Mengen-Anforderung wie PROJ-1 bis PROJ-3 (2026-11-22)
