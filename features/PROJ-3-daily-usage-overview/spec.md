# PROJ-3: Daily Usage Overview

**Status:** Planned
**Erstellt:** 2026-11-22

## Abhängigkeiten
- PROJ-1 (Import von API-Aufrufen aus OTLP-JSON-Dateien): liefert die gespeicherten Aufrufe
- PROJ-2 (Liste der API-Aufrufe im Admin-Panel): Ziel des Sprungs von einer Zeile zu den einzelnen Aufrufen; Anzeigeformate und Zeitraum-Filter gelten hier genauso

## Begriffe
- **Aufruf:** ein gespeicherter API-Aufruf aus PROJ-1.
- **Deutsche Zeit:** Zeitzone Europe/Berlin, mit Sommer- und Winterzeit.
- **Tag:** ein Kalendertag in deutscher Zeit. Ein Aufruf gehört zu dem Tag, an dem er in deutscher Zeit stattfand.
- **Modellzeile:** eine Zeile für genau einen Tag und ein Modell, mit den Summen aller Aufrufe dieses Modells an diesem Tag.
- **Tagessumme:** Summe aller Modellzeilen eines Tages.
- **Gesamtsumme:** Summe aller Tage, die zu den gesetzten Filtern passen.

## User Stories
- Als Betreiber möchte ich Token und Kosten je Tag und Modell sehen, damit ich weiß, was jeder Tag gekostet hat und welches Modell den Verbrauch treibt.
- Als Betreiber möchte ich Tages- und Gesamtsummen sehen, damit ich die Kosten eines Tages und eines Zeitraums nicht selbst addieren muss.
- Als Betreiber möchte ich Zeitraum und Modelle filtern, damit ich einzelne Wochen oder Modelle vergleichen kann.
- Als Betreiber möchte ich von einer Zeile zu den einzelnen Aufrufen springen, damit ich auffällige Tage genauer prüfen kann.
- Als Betreiber möchte ich Tage ohne Aufrufe auf Wunsch einblenden, damit ich Lücken in den Daten erkenne.

## Außerhalb des Umfangs
- Diagramme und Grafiken
- Wochen-, Monats- oder Jahresübersicht
- Auswertung nach Session, Prompt, Claude-Code-Version oder Nutzer
- Export (z. B. CSV)
- Budgets, Grenzwerte, Warnungen
- Umrechnung in andere Währungen
- Kosten selbst berechnen (z. B. aus Token und Preisliste) für Aufrufe ohne Kosten
- Umsortieren der Tabelle per Klick auf Spaltenköpfe
- Fertige Zeiträume zum Anklicken (heute, letzte 7 Tage, …) und Uhrzeit im Zeitraum-Filter
- Einstellbare Zeitzone
- Automatische Aktualisierung, während die Seite offen ist
- Sprung zu den Aufrufen von einer Tagessumme oder der Gesamtsumme aus
- Dashboard-Widget auf der Startseite des Panels
- Anlegen, Ändern oder Löschen von Daten
- Rollen oder Rechte über die bestehende Zugangsregel des Panels hinaus

## Spalten

| Spalte | Inhalt |
|--------|--------|
| Tag | Datum in deutscher Zeit |
| Modell | Modellname |
| Aufrufe | Anzahl der Aufrufe |
| Input-Token | Summe |
| Output-Token | Summe |
| Cache-Lese-Token | Summe |
| Cache-Schreib-Token | Summe |
| Kosten (USD) | Summe der bekannten Kosten, ggf. mit Hinweis auf fehlende Kosten |

## Anzeigeformate
- Tag im Format `TT.MM.JJJJ`.
- Anzahl und Token als ganze Zahl mit deutschem Tausenderpunkt, z. B. `12.345`.
- Kosten mit genau 4 Nachkommastellen und Komma, z. B. `0,0123 $`.
- Kosten-Summen zählen nur Aufrufe mit gespeicherten Kosten. Fehlen in einer Zeile bei n Aufrufen die Kosten (1 ≤ n < Anzahl), steht hinter der Summe `(n ohne Kosten)`, z. B. `1,2345 $ (2 ohne Kosten)`. Fehlen bei allen Aufrufen der Zeile die Kosten, steht `–`. Das gilt für Modellzeile, Tagessumme und Gesamtsumme.
- Gespeicherte Kosten von 0 zählen als bekannt (`0,0000 $`), nicht als fehlend.

## Akzeptanzkriterien
- [ ] AK-1: Angenommen ein angemeldeter Admin, wenn er im Menü „Tagesübersicht“ öffnet, dann sieht er eine Seite mit Titel „Tagesübersicht“ und einer Tabelle mit den 8 Spalten aus der Tabelle „Spalten“.
- [ ] AK-2: Angenommen am 21.11.2026 gibt es 2 Aufrufe von Modell A (Input-Token 100 und 200, Kosten 0.01 und 0.02 USD) und 1 Aufruf von Modell B, wenn der Admin die Seite ansieht, dann gibt es für den 21.11.2026 genau eine Modellzeile für A mit Aufrufe `2`, Input-Token `300` und Kosten `0,0300 $` und genau eine Modellzeile für B; entsprechend für alle vier Token-Spalten.
- [ ] AK-3: Angenommen Aufrufe an mehreren Tagen, wenn die Seite angezeigt wird, dann steht der neueste Tag oben und der älteste unten.
- [ ] AK-4: Angenommen ein Tag mit Aufrufen der Modelle B, A und C, wenn die Seite angezeigt wird, dann stehen die Modellzeilen dieses Tages alphabetisch nach Modellname: A, B, C.
- [ ] AK-5: Angenommen ein Tag mit Modellzeilen für A und B, wenn die Seite angezeigt wird, dann steht unter diesen Modellzeilen eine Tagessumme, deren Aufrufe, vier Token-Werte und Kosten die Summe der Modellzeilen A und B sind.
- [ ] AK-6: Angenommen Aufrufe an mehreren Tagen, wenn die Seite angezeigt wird, dann steht am Ende eine Gesamtsumme über alle Tage, die zu den gesetzten Filtern passen, mit Aufrufen, vier Token-Werten und Kosten.
- [ ] AK-7: Angenommen so viele Tage, dass die Tabelle auf mehrere Seiten verteilt ist, wenn der Admin eine beliebige Seite ansieht, dann gilt die Gesamtsumme für alle passenden Tage, nicht nur für die angezeigte Seite, und die Modellzeilen eines Tages stehen nie auf zwei Seiten verteilt.
- [ ] AK-8: Angenommen heute ist der 22.11.2026 (deutsche Zeit), wenn der Admin die Seite zum ersten Mal öffnet, dann ist der Zeitraum auf „von“ 24.10.2026 und „bis“ 22.11.2026 gesetzt (30 Tage inklusive heute) und ältere Aufrufe fehlen in Tabelle und Summen.
- [ ] AK-9: Angenommen Aufrufe am 20., 21. und 22.11.2026, wenn der Admin „von“ 21.11.2026 und „bis“ 21.11.2026 setzt, dann zeigt die Tabelle nur den 21.11.2026; Aufrufe um 00:00:00 und 23:59:59 dieses Tages zählen mit.
- [ ] AK-10: Angenommen Aufrufe am 20., 21. und 22.11.2026, wenn der Admin nur „von“ 21.11.2026 setzt und „bis“ leert, dann zeigt die Tabelle den 21. und 22.11.2026.
- [ ] AK-11: Angenommen Aufrufe am 20., 21. und 22.11.2026, wenn der Admin nur „bis“ 21.11.2026 setzt und „von“ leert, dann zeigt die Tabelle den 20. und 21.11.2026.
- [ ] AK-12: Angenommen ein Aufruf mit gespeichertem Zeitpunkt 2026-11-21 23:30 UTC, wenn die Seite angezeigt wird, dann zählt er zum 22.11.2026, weil er in deutscher Zeit am 22.11.2026 um 00:30 stattfand.
- [ ] AK-13: Angenommen gespeicherte Aufrufe der Modelle A, B und C, wenn der Admin den Modell-Filter öffnet, dann stehen genau A, B und C zur Auswahl.
- [ ] AK-14: Angenommen Aufrufe der Modelle A, B und C, wenn der Admin im Modell-Filter A und B wählt, dann zeigt die Tabelle nur Modellzeilen von A und B, und Tages- und Gesamtsummen enthalten nur A und B.
- [ ] AK-15: Angenommen eine Modellzeile mit 3 Aufrufen, davon 2 mit Kosten 0.01 und 0.02 USD und 1 ohne Kosten, wenn die Seite angezeigt wird, dann steht in der Kosten-Spalte `0,0300 $ (1 ohne Kosten)`.
- [ ] AK-16: Angenommen eine Modellzeile, deren Aufrufe alle keine Kosten haben, wenn die Seite angezeigt wird, dann steht in der Kosten-Spalte `–` und nicht `0,0000 $`.
- [ ] AK-17: Angenommen ein Tag mit Modellzeile A (2 Aufrufe, 1 ohne Kosten) und Modellzeile B (3 Aufrufe, alle ohne Kosten), wenn die Seite angezeigt wird, dann nennt die Tagessumme die Summe der bekannten Kosten mit `(4 ohne Kosten)`; die Gesamtsumme zählt die fehlenden Kosten über alle Tage genauso.
- [ ] AK-18: Angenommen eine Modellzeile mit 12345 Input-Token, 1234567 Cache-Lese-Token und Kosten 0.01234567 USD, wenn die Seite angezeigt wird, dann steht dort `12.345`, `1.234.567` und `0,0123 $`.
- [ ] AK-19: Angenommen eine Modellzeile für Modell A am 21.11.2026, wenn der Admin darauf klickt, dann öffnet sich die Liste „API-Aufrufe“ aus PROJ-2 mit Modell-Filter genau A und Zeitraum „von“ und „bis“ 21.11.2026, und sie zeigt genau die Aufrufe, die in der Modellzeile gezählt sind.
- [ ] AK-20: Angenommen ein Zeitraum vom 20. bis 22.11.2026 mit Aufrufen nur am 20. und 22.11.2026, wenn die Seite frisch geöffnet ist, dann erscheint der 21.11.2026 nicht.
- [ ] AK-21: Angenommen derselbe Zeitraum, wenn der Admin die Option „Tage ohne Aufrufe zeigen“ einschaltet, dann erscheint der 21.11.2026 als Tag mit Aufrufe `0`, allen Token-Werten `0` und Kosten `0,0000 $`; die Gesamtsumme ändert sich nicht.
- [ ] AK-22: Angenommen „Tage ohne Aufrufe zeigen“ ist eingeschaltet und im Modell-Filter ist A gewählt, wenn A an einem Tag im Zeitraum keine Aufrufe hat, dann erscheint dieser Tag als Tag ohne Aufrufe, auch wenn andere Modelle an dem Tag Aufrufe haben.
- [ ] AK-23: Angenommen „Tage ohne Aufrufe zeigen“ ist eingeschaltet und „von“ ist leer, wenn die Seite angezeigt wird, dann beginnen die leeren Tage frühestens am Tag des ältesten passenden Aufrufs; ist „bis“ leer, enden sie spätestens heute.
- [ ] AK-24: Angenommen der Admin hat Filter oder die Option „Tage ohne Aufrufe zeigen“ geändert und ist zur Liste gesprungen, wenn er zur Tagesübersicht zurückkehrt, dann sind seine Einstellungen noch gesetzt.
- [ ] AK-25: Angenommen keine gespeicherten Aufrufe, wenn der Admin die Seite öffnet, dann sieht er den Hinweis „Noch keine API-Aufrufe“ und den Befehl `php artisan tokens:import` als Hinweis zum Einlesen, auch wenn „Tage ohne Aufrufe zeigen“ eingeschaltet ist.
- [ ] AK-26: Angenommen gespeicherte Aufrufe, aber keiner passt zu den Filtern, und „Tage ohne Aufrufe zeigen“ ist aus, wenn die Seite angezeigt wird, dann sieht der Admin den Hinweis „Keine Aufrufe für diese Filter“ und kann die Filter zurücksetzen; danach gilt wieder der Standard-Zeitraum aus AK-8.
- [ ] AK-27: Angenommen ein angemeldeter Admin, wenn er die Seite ansieht, dann gibt es keine Schaltflächen zum Anlegen, Ändern oder Löschen.
- [ ] AK-28: Angenommen 100.000 gespeicherte Aufrufe über 365 Tage auf einem normalen Entwicklerrechner, wenn der Admin die Seite lädt, einen Filter setzt, die Option umschaltet oder blättert, dann ist die Seite jeweils nach höchstens 2 Sekunden angezeigt.
- [ ] AK-29: Angenommen ein nicht angemeldeter Besucher, wenn er die Seite aufruft, dann wird er zur Anmeldeseite geleitet und sieht keine Zahlen.
- [ ] AK-30: Angenommen die Umgebung ist `production`, wenn ein angemeldeter Benutzer die Seite aufruft, dann wird der Zugang verweigert (bestehende Regel des Panels).

## Edge Cases
- EC-1: „bis“ liegt vor „von“: keine Tage, Hinweis aus AK-26, auch mit eingeschalteter Option; kein Absturz.
- EC-2: Tag der Zeitumstellung (z. B. 25.10.2026, 25 Stunden lang): alle Aufrufe des deutschen Kalendertags zählen zu genau diesem Tag, keiner fehlt und keiner zählt doppelt.
- EC-3: „bis“ liegt in der Zukunft: zukünftige Tage erscheinen nicht, auch nicht mit eingeschalteter Option.
- EC-4: Sehr langer Modellname: vollständig lesbar.
- EC-5: Sehr große Summen (z. B. mehrere Milliarden Token im Zeitraum): korrekt angezeigt, kein Überlauf, keine wissenschaftliche Schreibweise.
- EC-6: Viele kleine Kosten (z. B. 10.000 × 0.00001234 USD): die Summe ist auf 4 Nachkommastellen genau, ohne Rundungsfehler durch Aufsummieren gerundeter Werte.
- EC-7: Während die Seite offen ist, läuft ein Import: neue Aufrufe erscheinen erst nach Neuladen, Filtern oder Blättern; kein Fehler.
- EC-8: Eingeschaltete Option und sehr langer Zeitraum ohne „von“-Grenze bei alten Daten: die Seite bleibt bedienbar und hält AK-28 ein.

## Technische Anforderungen
- Nur lesender Zugriff auf die gespeicherten Aufrufe; das Feature ändert keine Daten.
- Kein Netzwerkzugriff nach außen.
- Testdaten sind erfunden: E-Mail-Adressen nur unter der Domain `example.test`, IDs zufällig erzeugt, keine Zeile und kein Wert aus echten Daten. Das gilt für Tests, Seeder, Factories und Beispiele in der Doku.

## Offene Fragen
- Keine

## Product Decisions
- Eigene Seite „Tagesübersicht“ mit einer Modellzeile je Tag und Modell: alle Zahlen auf einen Blick, auch bei vielen Modellen lesbar; aufklappbare Unterzeilen oder Spalten je Modell verworfen (2026-11-22)
- Tagessumme je Tag und Gesamtsumme über alle passenden Tage: die Kernfrage „was hat der Tag / der Zeitraum gekostet?“ ohne eigenes Rechnen beantworten (2026-11-22)
- Filter wie in PROJ-2 (Zeitraum von/bis, Modell-Mehrfachauswahl), Standard die letzten 30 Tage inklusive heute; Summen gelten für die gefilterten Daten: begrenzt die Seite und macht die Gesamtsumme aussagekräftig (2026-11-22)
- Fehlende Kosten: Summe der bekannten Kosten mit Hinweis „(n ohne Kosten)“, `–` wenn alle fehlen: Teilsumme bleibt sichtbar, ohne Vollständigkeit vorzutäuschen (2026-11-22)
- Klick auf eine Modellzeile öffnet die Liste aus PROJ-2 mit passendem Tag und Modell: auffällige Tage ohne Filter-Handarbeit prüfen (2026-11-22)
- Tage ohne Aufrufe standardmäßig ausgeblendet, per Option einblendbar: Standard bleibt kompakt, Lücken sind auf Wunsch sichtbar (2026-11-22)
- Tage ohne Aufrufe zeigen Kosten `0,0000 $`, nicht `–`: ohne Aufrufe sind die Kosten wirklich 0, nicht unbekannt (2026-11-22)
- Modellzeilen innerhalb eines Tages alphabetisch, Tage neueste zuerst, kein Umsortieren: feste, vorhersagbare Reihenfolge reicht für eine Übersicht (2026-11-22)
- Anzeigeformate wie PROJ-2 (deutsche Zahlen, Kosten mit 4 Nachkommastellen): einheitliches Bild im Panel (2026-11-22)
- Höchstens 2 Sekunden bei 100.000 Aufrufen: gleiche Mengen-Anforderung wie PROJ-1 und PROJ-2 (2026-11-22)
