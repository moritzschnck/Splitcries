# Splitcries – Kostenaufteilung (MVP)

## Idee
Splitcries ist ein kleines Web-Dashboard, um gemeinsame Ausgaben in einer Gruppe zu erfassen und am Ende auszurechnen, wer wem wie viel schuldet. Ziel war ein funktionsfähiger Prototyp (MVP) mit Fokus auf Logik und Nachvollziehbarkeit.

## Funktionsumfang (MVP)
- Personen anlegen
- Ausgaben erfassen (Wer hat bezahlt? Betrag, Beschreibung)
- Automatische Berechnung:
  - Gesamtbetrag
  - Anteil pro Person
  - Salden (positiv = bekommt Geld, negativ = schuldet Geld)
  - Vorschläge für Zahlungen („X zahlt Y …“)

## Technische Umsetzung
- PHP (Server-side Rendering)
- SQLite als Datenbank (eine Datei im Projektordner)
- PDO mit Prepared Statements (SQL sicher, keine String-Concats)

## Warum SQLite?
Ursprünglich war eine MySQL/MariaDB-Datenbank geplant. In der gegebenen Umgebung war jedoch der MySQL-PDO-Treiber nicht verfügbar bzw. nicht installierbar ohne Adminrechte. SQLite benötigt keinen separaten DB-Server und läuft damit reproduzierbar auf jeder Maschine. Die Datenmodell- und Rechenlogik bleibt dabei identisch und kann später auf MySQL migriert werden.

## Start (lokal)
Voraussetzung: PHP installiert.

```bash
php -S localhost:8000 -t public