# PROJ-1: Import von API-Aufrufen aus OTLP-JSON-Dateien

**Status:** Planned
**Erstellt:** 2026-11-21

## Abhängigkeiten
- Keine

## Begriffe
- **Datei:** Ausgabe des File-Exporters des OpenTelemetry Collectors. Jede Zeile ist ein JSON-Objekt im OTLP-JSON-Format (`resourceLogs` → `scopeLogs` → `logRecords`).
- **Aufruf:** ein Log-Eintrag des Claude-Code-Ereignisses `claude_code.api_request`. Eine Zeile kann mehrere Log-Einträge enthalten.
- **Andere Ereignisse:** alle Log-Einträge, die kein `api_request` sind (z. B. `user_prompt`, `tool_result`).

## User Stories
- Als Betreiber möchte ich eine OTLP-JSON-Datei per Konsolenbefehl einlesen, damit jeder API-Aufruf mit Token, Kosten und Zuordnung gespeichert ist.
- Als Betreiber möchte ich dieselbe Datei beliebig oft einlesen können, damit ich eine wachsende Datei regelmäßig importieren kann, ohne doppelte Einträge zu erzeugen.
- Als Betreiber möchte ich nach dem Import eine Zusammenfassung mit kaputten Zeilen sehen, damit ich Probleme in der Datei erkenne.

## Außerhalb des Umfangs
- Anzeige, Liste oder Auswertung der Aufrufe im Admin-Panel (eigenes Feature)
- Hochladen einer Datei über das Admin-Panel
- Mehrere Dateien, Verzeichnisse oder Platzhalter in einem Aufruf
- Komprimierte Dateien (z. B. `.gz`)
- Empfang der Daten direkt per OTLP-Schnittstelle (HTTP/gRPC)
- Speichern der anderen Ereignisse
- Zeitgesteuertes oder automatisches Einlesen
- Ändern oder Löschen bereits gespeicherter Aufrufe
- Eigene Felder für `user.email`, `user.account_uuid`, `organization.id`

## Gespeicherte Werte je Aufruf

| Wert | Quelle | Pflicht | Fehlt der Wert |
|------|--------|---------|----------------|
| Zeitpunkt | `event.timestamp` | ja | Eintrag ist kaputt |
| Modell | `model` | ja | Eintrag ist kaputt |
| Input-Token | `input_tokens` | ja | Eintrag ist kaputt |
| Output-Token | `output_tokens` | ja | Eintrag ist kaputt |
| Cache-Lese-Token | `cache_read_tokens` | nein | 0 |
| Cache-Schreib-Token | `cache_creation_tokens` | nein | 0 |
| Kosten in USD | `cost_usd` | nein | leer (nicht 0) |
| Dauer in ms | `duration_ms` | nein | leer (nicht 0) |
| Session | `session.id` | nein | leer |
| Prompt | `prompt.id` | nein | leer |
| Claude-Code-Version | `app.version` | nein | leer |
| Original-Eintrag | kompletter Log-Eintrag als JSON | ja | – |
| Fingerabdruck | über den kompletten Log-Eintrag | ja | – |

## Akzeptanzkriterien
- [ ] AK-1: Angenommen eine Datei mit einem gültigen `api_request`-Eintrag, wenn `php artisan tokens:import <pfad>` läuft, dann ist genau ein Aufruf gespeichert, mit allen Werten aus der Tabelle „Gespeicherte Werte je Aufruf“.
- [ ] AK-2: Angenommen eine Zeile enthält mehrere `api_request`-Einträge, wenn der Import läuft, dann ist jeder dieser Einträge als eigener Aufruf gespeichert.
- [ ] AK-3: Angenommen eine Datei enthält neben `api_request` auch andere Ereignisse, wenn der Import läuft, dann sind nur die `api_request`-Einträge gespeichert und die anderen Ereignisse werden nicht als Fehler gemeldet.
- [ ] AK-4: Angenommen eine Datei wurde bereits vollständig eingelesen, wenn sie erneut eingelesen wird, dann entsteht kein neuer Aufruf und alle Einträge werden als „schon vorhanden“ gezählt.
- [ ] AK-5: Angenommen eine bereits eingelesene Datei wurde um neue Zeilen ergänzt, wenn sie erneut eingelesen wird, dann werden nur die neuen `api_request`-Einträge gespeichert.
- [ ] AK-6: Angenommen dieselben Einträge stehen in einer zweiten Datei mit anderem Namen, wenn diese eingelesen wird, dann entsteht kein doppelter Aufruf.
- [ ] AK-7: Angenommen ein Import ist abgeschlossen, wenn der Befehl endet, dann gibt er die Anzahl der neu gespeicherten, der schon vorhandenen und der kaputten Einträge aus.
- [ ] AK-8: Angenommen eine Zeile ist kein gültiges JSON, wenn der Import läuft, dann wird sie übersprungen, alle anderen gültigen Einträge werden gespeichert und die Ausgabe nennt die Zeilennummer mit dem Grund.
- [ ] AK-9: Angenommen einem `api_request`-Eintrag fehlt ein Pflichtwert (Zeitpunkt, Modell, `input_tokens` oder `output_tokens`), wenn der Import läuft, dann wird dieser Eintrag nicht gespeichert und die Ausgabe nennt die Zeilennummer und den fehlenden Wert.
- [ ] AK-10: Angenommen mindestens ein Eintrag oder eine Zeile war kaputt, wenn der Befehl endet, dann endet er mit einem Fehlercode ungleich 0.
- [ ] AK-11: Angenommen keine Zeile und kein Eintrag war kaputt, wenn der Befehl endet, dann endet er mit Fehlercode 0, auch wenn alle Einträge schon vorhanden waren.
- [ ] AK-12: Angenommen die angegebene Datei existiert nicht oder ist nicht lesbar, wenn der Befehl läuft, dann bricht er mit einer verständlichen Fehlermeldung und einem Fehlercode ungleich 0 ab und speichert nichts.
- [ ] AK-13: Angenommen einem `api_request`-Eintrag fehlen `cache_read_tokens` oder `cache_creation_tokens`, wenn der Import läuft, dann wird für den fehlenden Wert 0 gespeichert.
- [ ] AK-14: Angenommen einem `api_request`-Eintrag fehlen `cost_usd`, `duration_ms`, `session.id`, `prompt.id` oder `app.version`, wenn der Import läuft, dann wird der Eintrag gespeichert und der fehlende Wert bleibt leer (nicht 0).
- [ ] AK-15: Angenommen eine Datei mit 100.000 Zeilen mit je einem `api_request`-Eintrag, wenn der Import auf einem normalen Entwicklerrechner läuft, dann sind alle Einträge nach höchstens 2 Minuten gespeichert und der Befehl bricht nicht wegen Speichermangels ab.
- [ ] AK-16: Angenommen ein gespeicherter Aufruf, wenn man ihn abfragt, dann enthält er den kompletten Original-Log-Eintrag als JSON, inklusive der Attribute, die keine eigenen Felder haben.

## Edge Cases
- EC-1: Leere Datei oder Datei ohne `api_request`-Einträge: Import endet erfolgreich mit 0 neu, 0 vorhanden, 0 kaputt.
- EC-2: Leere Zeilen (auch am Dateiende) gelten nicht als kaputt und werden ignoriert.
- EC-3: Halb geschriebene letzte Zeile (Collector schreibt noch): gilt als kaputte Zeile nach AK-8. Beim nächsten Import, wenn sie vollständig ist, wird sie normal gespeichert.
- EC-4: Zwei Einträge mit exakt gleichem Inhalt (gleicher Fingerabdruck) in derselben Datei: es wird nur einer gespeichert, der zweite zählt als „schon vorhanden“.
- EC-5: Zwei Imports derselben Datei laufen gleichzeitig: danach ist jeder Aufruf genau einmal gespeichert.
- EC-6: Gültiges JSON, aber nicht im OTLP-Aufbau (z. B. kein `resourceLogs`): gilt als kaputte Zeile mit Grund.
- EC-7: Zahlenwerte kommen im OTLP-JSON teils als Zahl, teils als Text (z. B. `"intValue": "123"`): beide Formen werden als Zahl gelesen.
- EC-8: Ein Token-Wert ist negativ oder keine Zahl: der Eintrag gilt als kaputt nach AK-9, Grund „ungültiger Wert“.
- EC-9: Der Import bricht mittendrin ab (z. B. Prozess beendet): bereits gespeicherte Aufrufe bleiben; ein erneuter Import ergänzt den Rest ohne Doppelte.

## Technische Anforderungen
- Kein Netzwerkzugriff; die Datei wird nur lokal gelesen.
- Zeitpunkte werden einheitlich in UTC gespeichert.
- Testdaten sind erfunden: E-Mail-Adressen nur unter der Domain `example.test`, IDs (z. B. `session.id`, `prompt.id`, `user.account_uuid`, `organization.id`) zufällig erzeugt, keine Zeile aus echten Daten.

## Offene Fragen
- Keine

## Product Decisions
- Quelle ist der OTLP-JSON-File-Exporter des OpenTelemetry Collectors mit Claude-Code-Ereignissen; nur `claude_code.api_request` wird gespeichert: das ist die Token-Erfassung, andere Ereignisse enthalten keine Token (2026-11-21)
- Start per Konsolenbefehl `php artisan tokens:import <pfad>`, kein Upload im Panel: kleinster Umfang, keine Upload-Grenzen, skriptbar (2026-11-21)
- Original-Log-Eintrag wird als JSON mitgespeichert: spätere Auswertungen kommen ohne neuen Import an weitere Felder. Folge: `user.email` u. a. stehen darin, bekommen aber keine eigenen Felder (2026-11-21)
- Doppelte werden über einen Fingerabdruck des kompletten Log-Eintrags erkannt: funktioniert auch bei angehängten Zeilen und umbenannten Dateien und hängt nicht an `event.sequence`, das in älteren Versionen fehlen könnte (2026-11-21)
- Kaputte Zeilen werden übersprungen statt Alles-oder-nichts, aber mit Fehlercode gemeldet: eine halb geschriebene letzte Zeile soll den Import nicht blockieren, Skripte erkennen Probleme trotzdem (2026-11-21)
- Kaputt ist der einzelne Log-Eintrag, nicht die ganze Zeile, wenn das JSON gültig ist: andere gültige Einträge derselben Zeile gehen nicht verloren (2026-11-21)
- Pflicht: Zeitpunkt, Modell, `input_tokens`, `output_tokens`; fehlende Cache-Token = 0; fehlende Kosten, Dauer und Zuordnung = leer: „unbekannt“ ist nicht dasselbe wie „kostenlos“ (2026-11-21)
- Keine Anzeige im Panel in diesem Feature: Feature bleibt klein und ohne Oberfläche testbar (2026-11-21)
- Keine echte Beispieldatei; Testdaten werden nach dem dokumentierten Format von Claude Code erfunden: keine echten persönlichen Daten im Repository (2026-11-21)
- Mengen-Anforderung 100.000 Zeilen in höchstens 2 Minuten: der Collector hängt laufend an, die Datei wächst; Wert gilt „fürs Erste“ (2026-11-21)
