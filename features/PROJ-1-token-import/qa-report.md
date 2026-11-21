# QA-Bericht: PROJ-1 Import von API-Aufrufen aus OTLP-JSON-Dateien (#6)

**Spec:** [spec.md](spec.md) · **Design:** [design.md](design.md)
**Getestet am:** 2026-11-21 · **Commit:** `4f29fc3`

## Ergebnis
**Produktionsreif:** Ja
- Akzeptanzkriterien: 16 von 16 bestanden
- Bugs: Critical 0 · High 0 · Medium 0 · Low 0 (ein während des Tests gefundener Fehler wurde behoben, siehe unten)

## Akzeptanzkriterien
- ✅ AK-1: Ein gültiger Eintrag wird mit allen Werten gespeichert, Zeitpunkt nach UTC umgerechnet (Test + manuell).
- ✅ AK-2: Drei `api_request` in einer Zeile ergeben drei Aufrufe.
- ✅ AK-3: `user_prompt`, `tool_result`, `api_error` werden übergangen, Kaputt: 0, Fehlercode 0.
- ✅ AK-4: Zweiter Import: „Neu gespeichert: 0, Schon vorhanden: 3“.
- ✅ AK-5: Angehängte Zeile: nur sie wird neu gespeichert.
- ✅ AK-6: Gleiche Einträge in anderer Datei (sogar an anderer Zeilenposition): keine Doppelten.
- ✅ AK-7: Ausgabe „Neu gespeichert / Schon vorhanden / Kaputt“ am Ende jedes Laufs.
- ✅ AK-8: `Zeile 2: ungültiges JSON (Syntax error)`, übrige Einträge gespeichert.
- ✅ AK-9: Für jeden der vier Pflichtwerte: `Zeile 2: Pflichtwert fehlt: <wert>`, Eintrag nicht gespeichert.
- ✅ AK-10: Mit kaputtem Eintrag/Zeile Fehlercode 1.
- ✅ AK-11: Ohne kaputte Stellen Fehlercode 0, auch wenn alles schon vorhanden war.
- ✅ AK-12: Fehlende Datei, Verzeichnis, nicht lesbare Datei: verständliche Meldung, Fehlercode 1, nichts gespeichert.
- ✅ AK-13: Fehlende Cache-Token werden als 0 gespeichert.
- ✅ AK-14: Fehlende Kosten, Dauer, Session, Prompt, Version bleiben leer (NULL).
- ✅ AK-15: 100.000 Zeilen (96 MB) in ca. 12 s, mit `memory_limit=128M` ohne Abbruch; erneuter Import ca. 8 s.
- ✅ AK-16: Der gespeicherte Original-Eintrag ist identisch zum Log-Eintrag inkl. `user.email`, `user.account_uuid`, `organization.id`, `event.sequence`.

## Edge Cases
- ✅ EC-1: Leere Datei → 0/0/0, Fehlercode 0.
- ✅ EC-2: Leere und nur aus Leerzeichen bestehende Zeilen werden ignoriert.
- ✅ EC-3: Halbe letzte Zeile gilt als kaputt; nach Vervollständigen wird sie gespeichert.
- ✅ EC-4: Zwei gleiche Einträge in einer Datei → 1 neu, 1 schon vorhanden.
- ✅ EC-5: Zwei gleichzeitige Imports der 100.000-Zeilen-Datei (3 Durchläufe): jedes Mal genau 100.000 Aufrufe, keine Fehler.
- ✅ EC-6: `{"foo":1}`, `[1,2]`, `42`, falsche Listen → `kein OTLP-Aufbau (…)`.
- ✅ EC-7: `intValue` als Zahl und als Text, `doubleValue`, `stringValue` werden gelesen.
- ✅ EC-8: Negative, nicht-numerische oder gebrochene Token-Werte → `ungültiger Wert: <wert>`.
- ✅ EC-9: Erneuter Import nach Teilimport ergänzt nur den Rest.

## Bugs
- **Behoben während QA (High):** Die Zähler „neu/schon vorhanden“ lagen im Befehlsobjekt und summierten sich bei zwei Aufrufen im selben Prozess (z. B. aus Code per `Artisan::call`) auf. Jetzt lokal je Lauf; durch Tests abgedeckt.

## Sicherheit
- Keine Netzwerkzugriffe, keine Oberfläche, keine neue Route.
- Speichern nur mit gebundenen Parametern (keine SQL-Injektion über Dateiinhalte möglich).
- Die Ausgabe nennt nur Zeilennummer und Grund, keine Inhalte der Zeile (keine E-Mail-Adressen im Terminal-Log).
- Testdaten sind erfunden (`example.test`, zufällige IDs).

## Automatisierte Tests
- Feature: `tests/Feature/TokenImport/ImportTokensCommandTest.php` – 36 Tests (alle AK und EC außer AK-15/EC-5, die manuell gemessen wurden).
- Checks: Pint, PHPStan (Level 8), Pest – alle grün.

## Screenshots
Keine: Das Feature hat keine Oberfläche.
