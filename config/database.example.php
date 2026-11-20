<?php
// Vorlage. Auf dem Webhosting als config/database.php speichern (oberhalb des Webroots!).
// Die echte config/database.php niemals ins Repository hochladen.
//
// Ohne config/database.php verwendet die Plattform automatisch SQLite (storage/database.sqlite).
return [
    'driver' => 'mysql',            // 'mysql' für MySQL/MariaDB oder 'sqlite'
    'host' => '127.0.0.1',
    'database' => 'DATENBANKNAME',
    'username' => 'DATENBANKBENUTZER',
    'password' => 'DATENBANKPASSWORT',
    'charset' => 'utf8mb4',
    // Einmaliger Schlüssel für das Anlegen des ersten Admin-Kontos (mindestens 32 zufällige Zeichen).
    'initial_setup_key' => 'REPLACE_WITH_RANDOM_KEY',
];
