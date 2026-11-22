# QA-Bericht: PROJ-3 Daily Usage Overview (#14)

**Spec:** [spec.md](spec.md) · **Design:** [design.md](design.md)
**Getestet am:** 2026-11-22 · **Commit:** siehe Pull Request (Branch `factory/14`)

## Ergebnis
**Produktionsreif:** Ja
- Akzeptanzkriterien: 30 von 30 bestanden
- Bugs: Critical 0 · High 0 · Medium 0 · Low 0

Geprüft mit Pest (Livewire-Tests je Kriterium) und im Browser (Chromium, angemeldet als Test-Admin) mit 100.000 erfundenen Aufrufen über 365 Tage, bei 1440px und 375px.

## Akzeptanzkriterien
- ✅ AK-1: Menüeintrag „Tagesübersicht“, Seitentitel und 8 Spalten in der richtigen Reihenfolge.
- ✅ AK-2: Eine Modellzeile je Tag und Modell mit summierten Aufrufen, vier Token-Werten und Kosten (`2`, `300`, `0,0300 $`).
- ✅ AK-3: Neuester Tag oben.
- ✅ AK-4: Modelle eines Tages alphabetisch.
- ✅ AK-5: Tagessumme (fett) unter den Modellzeilen jedes Tages.
- ✅ AK-6: Gesamtsumme als letzte Zeile.
- ✅ AK-7: Blättern nach ganzen Tagen (10 oder 31 Tage pro Seite); Gesamtsumme auf jeder Seite über alle passenden Tage; „Showing 11 to 20 of 30“ zählt Tage.
- ✅ AK-8: Beim ersten Öffnen „von“ = heute − 29 Tage, „bis“ = heute; ältere Aufrufe fehlen auch in den Summen.
- ✅ AK-9: Ein Tag, Aufrufe um 00:00:00 und 23:59:59 deutscher Zeit zählen mit.
- ✅ AK-10: Nur „von“ gesetzt.
- ✅ AK-11: Nur „bis“ gesetzt.
- ✅ AK-12: 2026-11-21 23:30 UTC zählt zum 22.11.2026.
- ✅ AK-13: Modell-Filter bietet genau die gespeicherten Modelle an.
- ✅ AK-14: Modell-Filter wirkt auf Zeilen, Tages- und Gesamtsumme.
- ✅ AK-15: `0,0300 $ (1 ohne Kosten)`.
- ✅ AK-16: `–`, wenn alle Kosten fehlen; gespeicherte 0 bleibt `0,0000 $`.
- ✅ AK-17: Tagessumme `(4 ohne Kosten)`, Gesamtsumme zählt über alle Tage.
- ✅ AK-18: `12.345`, `1.234.567`, `0,0123 $`.
- ✅ AK-19: Klick auf eine Modellzeile öffnet „API-Aufrufe“ mit Modell und Tag als Filter; im Browser 70 Aufrufe in der Zeile = 70 Treffer in der Liste.
- ✅ AK-20: Tage ohne Aufrufe standardmäßig ausgeblendet.
- ✅ AK-21: Mit Option erscheint der leere Tag mit `0` und `0,0000 $`; Gesamtsumme unverändert.
- ✅ AK-22: Mit Modell-Filter erscheint ein Tag, an dem nur andere Modelle Aufrufe haben, als leerer Tag.
- ✅ AK-23: Leere Tage beginnen ohne „von“ beim ältesten passenden Aufruf und enden spätestens heute.
- ✅ AK-24: Filter und Option bleiben nach Sprung zur Liste und Rückkehr über das Menü gesetzt (Test und Browser).
- ✅ AK-25: Leere Datenbank: „Noch keine API-Aufrufe“ mit `php artisan tokens:import`, auch mit Option.
- ✅ AK-26: „Keine Aufrufe für diese Filter“ mit „Filter zurücksetzen“; danach wieder die letzten 30 Tage.
- ✅ AK-27: Keine Zeilen-, Kopf- oder Sammelaktionen.
- ✅ AK-28: Nachtest nach Abnahme Runde 1 (Befund B-1: Seitengröße 100 brauchte bis 5,3 s): Die Seitengröße 100 ist entfernt, angeboten werden nur noch 10 und 31 Tage; andere Werte (auch per manipulierter Anfrage) fallen auf eine angebotene Größe zurück. Im Browser mit 100.000 Aufrufen über 365 Tage und der größten Größe 31: Umschalten auf 31 ca. 0,73 s, „von“ entfernen ca. 0,77 s, neu laden (365 Tage) 1,23–1,35 s, Option einschalten ca. 1,06 s, neu laden mit Option ca. 1,22 s, Blättern ca. 1,07 s. Pest-Leistungstest mit 100.000 Aufrufen und größter Seitengröße prüft jeden Schritt auf unter 2 s.
- ✅ AK-29: Nicht angemeldet → Weiterleitung zur Anmeldung.
- ✅ AK-30: In `production` → 403.

## Edge Cases
- ✅ EC-1: „bis“ vor „von“: keine Tage, Filter-Hinweis, auch mit Option.
- ✅ EC-2: 25.10.2026 (25 Stunden): alle 4 Aufrufe des Tages genau einmal, Nachbartage nicht dabei.
- ✅ EC-3: „bis“ in der Zukunft: leere Tage enden heute.
- ✅ EC-4: Sehr langer Modellname vollständig ausgegeben (Tabelle scrollt waagerecht).
- ✅ EC-5: 10 Mrd. Token korrekt als `10.000.000.000`.
- ✅ EC-6: 10.000 × 0.00001234 USD = `0,1234 $` (exakte Ganzzahl-Summe).
- ✅ EC-7: Kein Zwischenspeicher; jede Anzeige rechnet neu.
- ✅ EC-8: Option ohne „von“ über 365 Tage: 0,6 s.

## Bugs
- Keine.

## Sicherheit
- Nur lesend: Test bestätigt, dass die Tabelle nach dem Anzeigen unverändert ist.
- Zugang über die bestehende Panel-Regel (Anmeldung, kein Zugang in `production`), getestet.
- Modellnamen mit HTML werden maskiert ausgegeben; der Link zur Liste kodiert den Namen in der Adresse.
- Filterwerte gehen nur als gebundene Parameter in die Abfrage; ungültige Daten werden ignoriert (getestet mit SQL-artigen Texten).
- Hinweis (kein Fehler dieses Features): Werden Filterwerte über eine manipulierte Livewire-Anfrage mit falschem Typ gesendet (z. B. Zeitraum als Text statt als Feld), bricht Filament selbst beim Anzeigen der Filter ab. Das betrifft jede Filament-Tabelle im Panel, auch die Liste aus PROJ-2, und lässt sich nur durch einen angemeldeten Admin auslösen.

## Automatisierte Tests
- `tests/Feature/DailyUsage/DailyUsagePageTest.php`: 39 Tests (je Akzeptanzkriterium, Edge Cases, Sicherheit). Dazu ein Leistungstest mit 100.000 Aufrufen und der größten angebotenen Seitengröße (AK-28) sowie Tests für die angebotenen und erzwungenen Seitengrößen. Gesamte Suite grün. Kein E2E-Framework im Stack; die Browserprüfung lief mit einem Playwright-Skript außerhalb des Repos.

## Screenshots
Siehe Pull Request.
