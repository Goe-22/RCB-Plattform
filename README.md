# RC Bande Zwickau Plattform

## Entwicklungsstand

Die Plattform wird schrittweise von einer statischen Vereinswebseite zu einer PHP-Anwendung ausgebaut.

## Adminbereich

Der neue Adminbereich liegt unter `public/admin/index.php`. Bei Hosting mit dem Repository-Hauptverzeichnis als DocumentRoot kann alternativ `/admin/index.php` verwendet werden.

Enthaltene Funktionen:

- Ersteinrichtung des ersten Administratorkontos mit selbst gewähltem Passwort
- Anmeldung und Abmeldung mit regenerierter Session-ID
- Passwort-Hashing über PHP `password_hash()` / Prüfung über `password_verify()`
- CSRF-Token für Formularaktionen
- Begrenzung wiederholter fehlgeschlagener Anmeldungen
- Dashboard und Aktionsprotokoll
- Anlegen, Bearbeiten und Löschen von Terminen, Strecken, Sponsoren und Inhaltsseiten
- Seiteneinstellungen und Verwaltung weiterer Admin-Konten
- Datenbankinitialisierung über SQLite/PDO
- Responsive Oberfläche für Desktop und Mobilgeräte

## Voraussetzungen für den Betrieb

- PHP 8.3 oder neuer
- PHP-Erweiterung PDO SQLite aktiviert
- Schreibrechte für PHP auf das Verzeichnis `storage/`
- HTTPS für den produktiven Betrieb

**Wichtig:** GitHub ist hier der Quellcode-Speicher. GitHub Pages führt PHP nicht aus. Der Adminbereich funktioniert deshalb erst auf einem PHP-fähigen Webhosting. Empfohlen ist, `public/` als DocumentRoot einzustellen, damit Quellcode, Konfiguration und `storage/` nicht öffentlich ausgeliefert werden.

Bei Hosting mit `public/` als DocumentRoot ist der Adminbereich unter `/admin/` erreichbar. Die alternative Datei `admin/index.php` ist nur für Hosting gedacht, bei dem das Repository-Hauptverzeichnis als DocumentRoot eingerichtet wurde. Vor einem Livebetrieb sollte ausschließlich HTTPS zugelassen werden.

## Ersteinrichtung

1. Repository auf einen PHP-fähigen Webserver übertragen.
2. DocumentRoot auf den Ordner `public/` setzen.
3. PHP 8.3+ und PDO SQLite aktivieren.
4. Schreibrechte für `storage/` einrichten; das Verzeichnis darf nicht öffentlich erreichbar sein.
5. `https://DEINE-DOMAIN/admin/` öffnen.
6. Beim ersten Aufruf einen Benutzernamen und ein einzigartiges Passwort mit mindestens 12 Zeichen festlegen.

Es gibt kein fest eingebautes Standardpasswort. Zugangsdaten nicht in Git eintragen.

## Aktuelle Grenzen

Die Admin-Daten werden in SQLite gespeichert. Die öffentlichen HTML-Seiten sind noch nicht vollständig mit diesen Daten verbunden; veröffentlichte Inhalte aus dem Adminbereich erscheinen daher nicht automatisch auf der öffentlichen Webseite. Medien-Upload, Datenbank-Backup/Restore und öffentliche Inhalte-Anbindung müssen vor einem produktiven Vereinsbetrieb noch ergänzt und getestet werden.

## Projektziele

- Vereinsseiten und Streckeninformationen
- Veranstaltungen und Ergebnisse
- Mediathek und Downloads
- Live-Timing
- Zeitnahmeprogramm und mobile Erweiterungen

## Entwickler

RC Bande Zwickau e.V.
