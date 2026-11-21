# Design: PROJ-1 Import von API-Aufrufen aus OTLP-JSON-Dateien

**Spec:** [spec.md](spec.md)

## A) Komponenten
```
Konsolenbefehl "tokens:import <pfad>"
+-- Prüfung der Datei (existiert, lesbar, keine Verzeichnis) → sonst Abbruch
+-- Zeilenweises Lesen (immer nur eine Zeile im Speicher)
|   +-- Zerleger für eine Zeile (OTLP-Aufbau → Log-Einträge)
|   |   +-- Erkennung "api_request" (alle anderen Ereignisse werden still übergangen)
|   |   +-- Auslesen und Prüfen der Werte (Pflicht, Standardwerte, Zahlenformate)
|   |   +-- Fingerabdruck über den kompletten Log-Eintrag
|   +-- Sammeln in Paketen zu je 500 Aufrufen → Speichern "einfügen oder ignorieren"
+-- Zusammenfassung: neu · schon vorhanden · kaputt (+ je kaputter Stelle Zeile und Grund)
+-- Fehlercode: 0 nur, wenn nichts kaputt war
```
Keine Oberfläche (laut Spec außerhalb des Umfangs).

## B) Datenmodell
Jeder gespeicherte Aufruf (Tabelle `api_requests`) hat:
- Zeitpunkt (UTC, mit Millisekunden)
- Modell (Text)
- Input-, Output-, Cache-Lese- und Cache-Schreib-Token (ganze Zahlen, Cache-Werte ohne Angabe 0)
- Kosten in USD (Dezimalzahl, darf leer sein)
- Dauer in ms (ganze Zahl, darf leer sein)
- Session, Prompt, Claude-Code-Version (Text, dürfen leer sein)
- Original-Eintrag (kompletter Log-Eintrag als JSON-Text)
- Fingerabdruck (SHA-256, **eindeutig** – die Datenbank selbst verhindert Doppelte)
- Anlage-Zeitpunkt

Gespeichert in: SQLite (laut `docs/stack.md`), neue Migration. Index auf Zeitpunkt für spätere Auswertungen.

## C) Technische Entscheidungen
- **Erkennung von `api_request`:** Attribut `event.name` = `api_request` oder Text des Eintrags = `claude_code.api_request`. So bleibt der Import robust, falls einer der beiden Hinweise fehlt.
- **Fingerabdruck:** SHA-256 über den Log-Eintrag, wobei die Schlüssel der JSON-Objekte vor dem Berechnen sortiert werden. Gleicher Inhalt ergibt immer denselben Abdruck, egal aus welcher Datei oder Zeile – das deckt AK-4, AK-5, AK-6, EC-4 und EC-9 ab.
- **Doppelte verhindert die Datenbank** (eindeutiger Index + "einfügen oder ignorieren"): Auch zwei gleichzeitig laufende Imports (EC-5) können keinen Aufruf doppelt anlegen. "Schon vorhanden" = Einträge im Paket minus tatsächlich eingefügte.
- **Pakete zu 500 Aufrufen in einer Transaktion:** schnell genug für 100.000 Zeilen in deutlich unter 2 Minuten (AK-15), Speicher bleibt konstant. Bricht der Prozess ab, fehlt höchstens das laufende Paket; ein neuer Import ergänzt es (EC-9).
- **Kaputt ist der einzelne Eintrag**, wenn das JSON gültig ist; die ganze Zeile nur bei ungültigem JSON oder fehlendem OTLP-Aufbau (Product Decision der Spec).
- **Zahlen:** OTLP liefert Zahlen als Zahl oder Text (`intValue`, `doubleValue`, `stringValue`). Alle Formen werden gelesen; Token müssen ganze Zahlen ≥ 0 sein, sonst "ungültiger Wert".
- **Zeitpunkt:** aus `event.timestamp` (ISO-Format), nach UTC umgerechnet. Nicht lesbar → "ungültiger Wert".
- **Leere Zeilen** werden ignoriert und nicht gezählt (EC-2).

## D) Abhängigkeiten
- Keine neuen Pakete

## E) Backend nötig?
Ja, weil die Aufrufe dauerhaft in der Datenbank gespeichert werden (Tabelle + Konsolenbefehl). Keine Oberfläche, keine Schnittstelle nach außen.

## Decision Log
- **Duplikaterkennung per eindeutigem Index in der Datenbank** | **Alternative:** vorher nachschauen, ob der Abdruck existiert | **Abwägung:** Nachschauen ist bei gleichzeitigen Imports nicht sicher und langsamer; der Index ist sicher und schnell (2026-11-21)
- **Paketweises Speichern** | **Alternative:** jeden Aufruf einzeln speichern | **Abwägung:** einzeln wäre bei 100.000 Zeilen deutlich langsamer; ein abgebrochenes Paket wird beim nächsten Import ergänzt (2026-11-21)
- **Schlüssel sortieren vor dem Fingerabdruck** | **Alternative:** Abdruck über den Rohtext der Zeile | **Abwägung:** Eine Zeile enthält mehrere Einträge, und Leerzeichen/Schlüsselreihenfolge sind für den Inhalt bedeutungslos (2026-11-21)

## Umsetzung
- Tabelle `api_requests` (Migration) mit eindeutigem Fingerabdruck, Modell `ApiRequest` (Original-Eintrag wird beim Abfragen als Struktur zurückgegeben).
- Zerleger für eine Zeile (`app/Support/TokenImport/OtlpLineParser.php`) liefert gefundene Aufrufe und Gründe für kaputte Stellen; der Befehl `tokens:import` (`app/Console/Commands/ImportTokens.php`) liest zeilenweise, speichert in Paketen zu 500 und gibt die Zusammenfassung aus.
- Fehlen mehrere Werte oder sind mehrere ungültig, nennt die Meldung alle (z. B. `Zeile 5: Pflichtwert fehlt: model; ungültiger Wert: input_tokens`). Bei mehreren Einträgen in einer Zeile steht zusätzlich „(Eintrag n)“ dabei.
- Kosten und Dauer werden ebenfalls geprüft: negativ oder keine Zahl gilt als „ungültiger Wert“. Eine Dauer mit Nachkommastellen wird auf ganze ms gerundet.
- Gemessen: 100.000 Zeilen in ca. 12 s (erneuter Import ca. 8 s) bei 128 MB Speichergrenze; zwei gleichzeitige Imports ergeben genau 100.000 Aufrufe.
- Keine Abweichung vom Plan.
