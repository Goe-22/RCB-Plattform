# RC Bande Zwickau e.V. – Website und Plattform

## Öffentliche Website (GitHub Pages)
Die statische Website liegt im Repository-Hauptverzeichnis und verwendet ein gemeinsames responsives Designsystem:
- `assets/css/site.css` – Layout, Komponenten und mobile Darstellung
- `assets/js/site.js` – zugängliche mobile Navigation
- HTML-Seiten für Verein, Strecken, Termine, Ergebnisse, Reglement, Galerie, Downloads, Live-Timing, Kontakt und Rechtliches

Interne Links sind relativ aufgebaut, damit die Website auch unter dem GitHub-Pages-Projektpfad `/RCB-Plattform/` funktioniert. Die Website zeigt keine erfundenen Veranstaltungstermine oder Ergebnisse an. Bereiche ohne freigegebene Vereinsdaten sind entsprechend gekennzeichnet.

## PHP-Plattform / Adminbereich
Der PHP-Adminbereich liegt unter `public/admin/index.php`. Er benötigt PHP 8.3+, PDO SQLite, fileinfo und Schreibrechte für `storage/` sowie `public/uploads/`. **GitHub Pages führt PHP nicht aus.** Der Adminbereich ist daher nicht auf der GitHub-Pages-Adresse nutzbar. Für den vollständigen Betrieb der dynamischen Plattform ist ein PHP-fähiges Hosting mit `public/` als DocumentRoot erforderlich.

Der Adminbereich bietet einen CSRF-geschützten SQL-Export der SQLite-Datenbank. Backups enthalten vertrauliche Daten und müssen sicher aufbewahrt werden; ein Web-Upload zur Wiederherstellung ist bewusst nicht vorgesehen.\n\nDie statische GitHub-Pages-Website und die PHP-Plattform sind derzeit getrennte Auslieferungswege. Änderungen, die im PHP-Adminbereich gespeichert werden, aktualisieren die statischen HTML-Seiten auf GitHub Pages nicht automatisch.

## Vor dem öffentlichen Vereinsbetrieb noch zu ergänzen
- Offizielle Anschrift, Vertretungsberechtigte, Kontakt-E-Mail und Vereinsregisterdaten
- Datenschutztext passend zum tatsächlich gewählten Hosting und allen eingesetzten Diensten
- Bestätigte Streckenangaben, Trainingszeiten und Gastfahrer-Regeln
- Freigegebene Fotos, Logo, Ausschreibungen und Ergebnisdateien
- Anbindung und Test der eingesetzten Zeitnahme für Live-Timing
- Browserprüfung auf der veröffentlichten GitHub-Pages-Adresse und Prüfung aller rechtlichen Angaben

Die Seitenstruktur und Navigation sind vorbereitet; ein vollständiger Live-Test der veröffentlichten Website sowie der PHP-Plattform auf einem tatsächlichen Webhosting ist noch erforderlich.
