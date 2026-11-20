# RC Bande Zwickau e.V. – Website und Plattform

## Öffentliche Website (GitHub Pages)
Die statische Website liegt im Repository-Hauptverzeichnis und verwendet ein gemeinsames responsives Designsystem:
- `assets/css/site.css` – Layout, Komponenten und mobile Darstellung
- `assets/js/site.js` – zugängliche mobile Navigation
- HTML-Seiten für Verein, Strecken, Termine, Ergebnisse, Reglement, Downloads, Live-Timing, Kontakt und Rechtliches

Interne Links sind relativ aufgebaut, damit die Website auch unter dem GitHub-Pages-Projektpfad `/RCB-Plattform/` funktioniert. Die Website zeigt keine erfundenen Veranstaltungstermine oder Ergebnisse an. Bereiche ohne freigegebene Vereinsdaten sind entsprechend gekennzeichnet.

## PHP-Plattform / Adminbereich
Der PHP-Adminbereich liegt unter `public/admin/index.php`. Er benötigt PHP 8.3+, PDO MySQL oder PDO SQLite, fileinfo und Schreibrechte für `storage/` sowie `public/uploads/`. **GitHub Pages führt PHP nicht aus.** Der Adminbereich ist daher nicht auf der GitHub-Pages-Adresse nutzbar. Für den vollständigen Betrieb der dynamischen Plattform ist ein PHP-fähiges Hosting mit `public/` als DocumentRoot erforderlich.

Der Adminbereich bietet einen CSRF-geschützten SQL-Export der SQLite-Datenbank. Backups enthalten vertrauliche Daten und müssen sicher aufbewahrt werden; ein Web-Upload zur Wiederherstellung ist bewusst nicht vorgesehen.

Die statische GitHub-Pages-Website und die PHP-Plattform sind derzeit getrennte Auslieferungswege. Änderungen, die im PHP-Adminbereich gespeichert werden, aktualisieren die statischen HTML-Seiten auf GitHub Pages nicht automatisch.

## Datenbank: MySQL/MariaDB oder SQLite
Die Plattform läuft mit beiden Datenbanken:
- **MySQL/MariaDB** (z. B. LXI-Hosting): `config/database.example.php` als `config/database.php` **oberhalb des Webroots** speichern und Zugangsdaten sowie einen zufälligen `initial_setup_key` (mind. 32 Zeichen) eintragen. Diese Datei niemals ins Repository hochladen.
- **SQLite**: Ohne `config/database.php` wird automatisch `storage/database.sqlite` verwendet.

Tabellen und fehlende Spalten legt der Adminbereich beim Aufruf selbst an (`app/Database/DatabaseSchema.php`). Bestehende Daten bleiben erhalten.

## Veröffentlichung auf LXI (rcb-test.lxi.be)
Auf dem Hosting ist `public_html/` der Webroot. Zuordnung der Ordner:

| Repository | Hosting |
|---|---|
| `public/*` | `public_html/` |
| `app/` | `app/` (neben `public_html`) |
| `config/database.php` | `config/` (neben `public_html`, nicht im Repository) |
| `storage/` | `storage/` (neben `public_html`) |

Beim Aktualisieren immer **alle** geänderten Dateien hochladen, besonders `public/inc/bootstrap.php` zusammen mit den Seiten. `public_html/uploads/` und `config/database.php` auf dem Server nicht überschreiben. Danach einmal `/admin/` aufrufen, damit neue Tabellen/Spalten angelegt werden.

Seitentexte lassen sich im Adminbereich unter „Seiten“ über diese Seitenkennungen anpassen: `startseite`, `verein`, `strecken`, `termine`, `onroad`, `offroad`, `reglement`, `impressum`, `datenschutz`, `haftungsausschluss`.

## Vor dem öffentlichen Vereinsbetrieb noch zu ergänzen
- Offizielle Anschrift, Vertretungsberechtigte, Kontakt-E-Mail und Vereinsregisterdaten
- Datenschutztext passend zum tatsächlich gewählten Hosting und allen eingesetzten Diensten
- Verbindliche Streckenfreigabe, Trainingszeiten und Gastfahrer-Regeln vor einem Besuch prüfen
- Freigegebene Fotos, Logo, Ausschreibungen und Ergebnisdateien
- Anbindung und Test der eingesetzten Zeitnahme für Live-Timing
- Browserprüfung auf der veröffentlichten GitHub-Pages-Adresse und Prüfung aller rechtlichen Angaben

Die Seitenstruktur und Navigation sind vorbereitet; ein vollständiger Live-Test der veröffentlichten Website sowie der PHP-Plattform auf einem tatsächlichen Webhosting ist noch erforderlich.


## Verifizierte öffentliche Vereinsinformationen
Die aktuelle Kontaktseite verweist auf die öffentlich genannte Vereins-E-Mail und das Vereinsgelände. Streckenlängen und Beläge wurden anhand der bisherigen Vereinswebsite eingetragen. Vor dem Livebetrieb sollte der Vorstand diese Angaben bestätigen. Externe Termin- und Ergebnislinks bleiben als zusätzliche Referenz erhalten.
