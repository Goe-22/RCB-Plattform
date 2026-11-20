<?php
declare(strict_types=1);

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/DatabaseSchema.php';

/** Legt Tabellen und Standardeinstellungen an (MySQL/MariaDB oder SQLite). */
final class Initialize
{
    public static function run(): void
    {
        $db = Database::connect();
        DatabaseSchema::ensure($db);

        $defaults = [
            'site_name' => 'RC Bande Zwickau e.V.',
            'site_description' => 'RC-Modellsport in Zwickau',
            'site_enabled' => '1',
            'live_enabled' => '0',
        ];
        $stmt = $db->prepare(Database::isMysql()
            ? 'INSERT IGNORE INTO settings (setting_key, setting_value) VALUES (?, ?)'
            : 'INSERT OR IGNORE INTO settings (setting_key, setting_value) VALUES (?, ?)');
        foreach ($defaults as $key => $value) {
            $stmt->execute([$key, $value]);
        }
    }
}
