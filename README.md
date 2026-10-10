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

Die Admin-Daten werden in SQLite gespeichert. Die öffentliche PHP-Startseite (`public/index.php`) liest veröffentlichte Termine, Strecken und Sponsoren aus derselben SQLite-Datenbank wie der Adminbereich. Unter `public/termine.php` werden kommende und vergangene veröffentlichte Veranstaltungen angezeigt, `public/strecken.php` zeigt freigegebene Strecken. Veröffentlichte Textseiten sind über `public/seite.php?slug=SEITENKENNUNG` erreichbar. Seitentexte werden als Klartext ausgegeben; HTML wird nicht ausgeführt. Name, Kurzbeschreibung und Kontakt-E-Mail aus den Einstellungen werden auf den öffentlichen PHP-Seiten verwendet.

Der öffentliche Bereich enthält außerdem Vereinsinformationen, Reglement, Kontakt, Mitglied werden, Galerie, Live-Timing-Status, Ergebnisübersicht sowie Impressum und Datenschutzhinweise. Die Adminverwaltung bietet eine Ergebnisverwaltung und eine Medienverwaltung mit Bild-Upload (JPG, PNG, WebP bis 8 MB), Dateitypprüfung, zufälligen Dateinamen und Veröffentlichungsstatus. Freigegebene Bilder erscheinen in der öffentlichen Galerie. Der Schalter „Webseite aktiv“ aktiviert den Wartungsbildschirm für öffentliche PHP-Seiten; die Live-Timing-Einstellung steuert derzeit nur den angezeigten Status und stellt noch keine Zeitnahmeverbindung her.

Noch offen vor einem produktiven Vereinsbetrieb: Datenbank-Backup/Restore, verbindliche Vereinsdaten und rechtlich geprüfte Texte für Impressum/Datenschutz, vollständige Integration der RCM-Ultimate-Zeitnahme sowie ein vollständiger Live-Test auf dem tatsächlichen PHP-Webhosting. Die Startseite nutzt derzeit bewusst eine textbasierte Hero-Fläche; echtes Vereinslogo und Streckenbilder können später im Adminbereich hochgeladen und in die Gestaltung eingebunden werden.

## Projektziele

- Vereinsseiten und Streckeninformationen
- Veranstaltungen und Ergebnisse
- Mediathek und Downloads
- Live-Timing
- Zeitnahmeprogramm und mobile Erweiterungen

## Entwickler

RC Bande Zwickau e.V.
