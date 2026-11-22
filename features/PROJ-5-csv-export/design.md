# Design: PROJ-5 CSV-Export der API-Aufrufe

**Spec:** [spec.md](spec.md)

## A) Komponenten
```
Konsole
+-- Artisan-Befehl `tokens:export <pfad>` (neben `tokens:import`)
    +-- Prüfung der Angaben (vor jedem Lesen und Schreiben)
    |   +-- Pfad: Ordner vorhanden und beschreibbar, Pfad kein Ordner,
    |   |   vorhandene Datei nur mit `--force`
    |   +-- `--from` / `--to`: echtes Datum im Format JJJJ-MM-TT, `--to` nicht vor `--from`
    |   +-- `--model` / `--session`: kein leerer Wert
    +-- Abfrage der passenden Aufrufe (nur lesend, Zeile für Zeile)
    +-- CSV-Zeilenformat (deutsches Excel: `;`, CRLF, Dezimalkomma, deutsche Zeit,
    |   Anführungszeichen nach RFC 4180, Schutz gegen Formeln)
    +-- Schreiben in eine temporäre Datei im Zielordner, am Ende Umbenennen auf den Pfad
    +-- Meldung „Exportiert: n Aufrufe“
```
Am Admin-Panel ändert sich nichts.

## B) Datenmodell
Keine neuen Daten, keine Migration. Der Befehl liest nur die Tabelle `api_requests` aus PROJ-1, und zwar nur die 12 Spalten der Exportdatei. Der Original-Eintrag (`raw`, enthält z. B. `user.email`) wird gar nicht erst gelesen (AK-11).

Die vorhandenen Indizes auf Zeitpunkt, Modell und Session (PROJ-1, PROJ-2, PROJ-4) reichen für Filter und Sortierung.

## C) Technische Entscheidungen
- **Ein Artisan-Befehl wie `tokens:import`:** gleiche Art des Aufrufs und der Meldungen, läuft in jeder Umgebung ohne Rückfrage (AK-31).
- **Erst alles prüfen, dann schreiben:** Ungültige Angaben, fehlender Ordner oder vorhandene Datei ohne `--force` führen zu einer klaren Meldung und Exit-Code 1, bevor irgendeine Datei angelegt wird (AK-23, AK-25, AK-27 bis AK-29). Fehlt der Pfad ganz, meldet Laravel selbst den fehlenden Pflichtwert (AK-30).
- **Zeitraum wie in der Liste aus PROJ-2:** Ein deutscher Kalendertag wird in einen UTC-Bereich umgerechnet: ab 00:00 deutscher Zeit des „von“-Tages bis vor 00:00 des Tages nach „bis“. So stimmen Export und Panel überein, auch an Tagen der Zeitumstellung (AK-13 bis AK-16, EC-1).
- **Zeile für Zeile lesen und schreiben (Streaming):** Die Datenbank liefert die Aufrufe sortiert (Zeitpunkt, dann ID) in einer einzigen Abfrage; jede Zeile wird sofort geschrieben. Der Speicherbedarf bleibt dadurch unabhängig von der Anzahl klein (AK-32), und weil es eine einzige Abfrage ist, kommt kein Aufruf doppelt vor, auch wenn parallel importiert wird (EC-3).
- **Zahlen ohne Umweg über Rundung:** Token, Dauer und ID werden als ganze Zahl geschrieben. Kosten liefert SQLite als Gleitkommazahl; der Befehl nimmt die kürzeste Dezimaldarstellung, die genau diesen gespeicherten Wert ergibt, schreibt sie ohne wissenschaftliche Schreibweise aus, entfernt Nullen am Ende und ersetzt den Punkt durch ein Komma (AK-6, EC-7).
- **Fehlende Werte:** leere Zelle, nie `–` oder `0` (AK-7). Die Anzeigeformate des Panels (`–`, Tausenderpunkt, `$`) werden bewusst nicht wiederverwendet.
- **Formel-Schutz nur für Textwerte:** Modell, Session, Prompt und Version bekommen ein `'` vorangestellt, wenn sie mit `=`, `+`, `-` oder `@` beginnen; Zahlen nie (AK-9, EC-6). Danach folgt das Anführungszeichen-Quoting nach RFC 4180 für `;`, `"` und Zeilenumbrüche (AK-8).
- **Sicheres Schreiben:** Die Datei entsteht zuerst unter einem temporären Namen im Zielordner und wird erst nach erfolgreichem Ende auf den Pfad umbenannt. Bei einem Fehler wird die temporäre Datei gelöscht; eine vorher vorhandene Datei bleibt unverändert (EC-2, AK-24).
- **Relativer Pfad:** wird zum aktuellen Verzeichnis des Aufrufs aufgelöst (AK-26); Leerzeichen und Umlaute im Namen bleiben erhalten (EC-8).

## D) Abhängigkeiten
- Keine neuen Pakete

## E) Backend nötig?
Ja, aber nur lesend: ein Konsolenbefehl liest die gespeicherten Aufrufe und schreibt eine Datei. Keine Schreibzugriffe auf die Datenbank (AK-33), kein Netzwerk.

## Decision Log
- **Eigenes CSV-Zeilenformat statt `fputcsv`** | **Alternative:** PHP-eigenes `fputcsv` | **Abwägung:** `fputcsv` schreibt Zeilenende LF und quotet nach eigenen Regeln (z. B. bei Leerzeichen); die Spec verlangt CRLF und Quoting genau bei `;`, `"` und Zeilenumbruch. Ein kleiner eigener Formatierer ist eindeutig testbar (2026-11-22)
- **Eine sortierte Abfrage mit Cursor** | **Alternative:** in Paketen nach ID blättern | **Abwägung:** Blättern nach ID passt nicht zur Sortierung nach Zeitpunkt und könnte bei parallelem Import Zeilen doppelt oder gar nicht liefern; der Cursor hält immer nur eine Zeile im Speicher (2026-11-22)
- **Temporäre Datei im selben Ordner + Umbenennen** | **Alternative:** direkt in den Pfad schreiben | **Abwägung:** Umbenennen im selben Ordner ist ein einziger Schritt; es bleibt nie eine halbe Datei am Pfad stehen (2026-11-22)
- **Kosten aus der kürzesten exakten Darstellung** | **Alternative:** feste 10 Nachkommastellen (`decimal:10`) | **Abwägung:** feste Stellen erzeugen bei großen Beträgen erfundene Ziffern hinter der Gleitkomma-Genauigkeit; die kürzeste Darstellung gibt genau den gespeicherten Wert wieder (2026-11-22)
