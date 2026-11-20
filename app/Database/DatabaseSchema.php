<?php
declare(strict_types=1);

require_once __DIR__ . '/Database.php';

/**
 * Legt alle Tabellen an und ergänzt fehlende Spalten älterer Installationen.
 * Funktioniert für MySQL/MariaDB und SQLite. Bestehende Daten bleiben erhalten.
 */
final class DatabaseSchema
{
    /**
     * Spalten je Tabelle: [Name => [MySQL-Typ, SQLite-Typ]]. Die ID-Spalte wird automatisch ergänzt.
     */
    private const TABLES = [
        'users' => [
            'username' => ["VARCHAR(190) NOT NULL UNIQUE", "TEXT NOT NULL UNIQUE"],
            'email' => ["VARCHAR(190) NOT NULL DEFAULT ''", "TEXT DEFAULT ''"],
            'password_hash' => ["VARCHAR(255) NOT NULL", "TEXT NOT NULL"],
            'role' => ["VARCHAR(32) NOT NULL DEFAULT 'subadmin'", "TEXT NOT NULL DEFAULT 'subadmin'"],
            'permissions' => ["VARCHAR(500) NOT NULL DEFAULT '[]'", "TEXT NOT NULL DEFAULT '[]'"],
            'active' => ["TINYINT NOT NULL DEFAULT 1", "INTEGER NOT NULL DEFAULT 1"],
            'created_at' => ["TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP", "TEXT DEFAULT CURRENT_TIMESTAMP"],
        ],
        'settings' => [
            'setting_key' => ["VARCHAR(190) NOT NULL UNIQUE", "TEXT NOT NULL UNIQUE"],
            'setting_value' => ["MEDIUMTEXT NULL", "TEXT DEFAULT ''"],
            'updated_at' => ["TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP", "TEXT DEFAULT CURRENT_TIMESTAMP"],
        ],
        'events' => [
            'title' => ["VARCHAR(255) NOT NULL", "TEXT NOT NULL"],
            'description' => ["MEDIUMTEXT NULL", "TEXT DEFAULT ''"],
            'event_date' => ["DATE NULL", "TEXT DEFAULT ''"],
            'start_time' => ["TIME NULL", "TEXT DEFAULT ''"],
            'location' => ["VARCHAR(255) NOT NULL DEFAULT ''", "TEXT DEFAULT ''"],
            'published' => ["TINYINT NOT NULL DEFAULT 0", "INTEGER NOT NULL DEFAULT 0"],
            'updated_at' => ["TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP", "TEXT DEFAULT CURRENT_TIMESTAMP"],
        ],
        'tracks' => [
            'name' => ["VARCHAR(255) NOT NULL", "TEXT NOT NULL"],
            'description' => ["MEDIUMTEXT NULL", "TEXT DEFAULT ''"],
            'surface' => ["VARCHAR(190) NOT NULL DEFAULT ''", "TEXT DEFAULT ''"],
            'length' => ["VARCHAR(100) NOT NULL DEFAULT ''", "TEXT DEFAULT ''"],
            'classes' => ["VARCHAR(255) NOT NULL DEFAULT ''", "TEXT DEFAULT ''"],
            'electricity' => ["TINYINT NOT NULL DEFAULT 0", "INTEGER DEFAULT 0"],
            'toilet' => ["TINYINT NOT NULL DEFAULT 0", "INTEGER DEFAULT 0"],
            'paddock' => ["TINYINT NOT NULL DEFAULT 0", "INTEGER DEFAULT 0"],
            'guest_drivers' => ["TINYINT NOT NULL DEFAULT 0", "INTEGER DEFAULT 0"],
            'published' => ["TINYINT NOT NULL DEFAULT 0", "INTEGER DEFAULT 0"],
            'show_status' => ["TINYINT NOT NULL DEFAULT 0", "INTEGER DEFAULT 0"],
            'status' => ["VARCHAR(32) NOT NULL DEFAULT 'closed'", "TEXT DEFAULT 'closed'"],
            'updated_at' => ["TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP", "TEXT DEFAULT CURRENT_TIMESTAMP"],
        ],
        'sponsors' => [
            'name' => ["VARCHAR(255) NOT NULL", "TEXT NOT NULL"],
            'url' => ["VARCHAR(1000) NOT NULL DEFAULT ''", "TEXT DEFAULT ''"],
            'description' => ["MEDIUMTEXT NULL", "TEXT DEFAULT ''"],
            'published' => ["TINYINT NOT NULL DEFAULT 0", "INTEGER NOT NULL DEFAULT 0"],
            'updated_at' => ["TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP", "TEXT DEFAULT CURRENT_TIMESTAMP"],
        ],
        'pages' => [
            'slug' => ["VARCHAR(190) NOT NULL UNIQUE", "TEXT NOT NULL UNIQUE"],
            'title' => ["VARCHAR(255) NOT NULL", "TEXT NOT NULL"],
            'content' => ["MEDIUMTEXT NULL", "TEXT DEFAULT ''"],
            'published' => ["TINYINT NOT NULL DEFAULT 0", "INTEGER NOT NULL DEFAULT 0"],
            'updated_at' => ["TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP", "TEXT DEFAULT CURRENT_TIMESTAMP"],
        ],
        'media' => [
            'filename' => ["VARCHAR(255) NOT NULL", "TEXT NOT NULL"],
            'title' => ["VARCHAR(255) NOT NULL DEFAULT ''", "TEXT DEFAULT ''"],
            'description' => ["MEDIUMTEXT NULL", "TEXT DEFAULT ''"],
            'category' => ["VARCHAR(64) NOT NULL DEFAULT 'onroad'", "TEXT DEFAULT 'onroad'"],
            'mime_type' => ["VARCHAR(190) NOT NULL DEFAULT ''", "TEXT DEFAULT ''"],
            'published' => ["TINYINT NOT NULL DEFAULT 0", "INTEGER NOT NULL DEFAULT 0"],
            'uploaded_at' => ["TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP", "TEXT DEFAULT CURRENT_TIMESTAMP"],
        ],
        'results' => [
            'event_id' => ["INT UNSIGNED NULL", "INTEGER"],
            'title' => ["VARCHAR(255) NOT NULL", "TEXT NOT NULL"],
            'content' => ["MEDIUMTEXT NULL", "TEXT DEFAULT ''"],
            'published' => ["TINYINT NOT NULL DEFAULT 0", "INTEGER NOT NULL DEFAULT 0"],
            'updated_at' => ["TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP", "TEXT DEFAULT CURRENT_TIMESTAMP"],
        ],
        'news' => [
            'title' => ["VARCHAR(255) NOT NULL", "TEXT NOT NULL"],
            'summary' => ["MEDIUMTEXT NULL", "TEXT DEFAULT ''"],
            'content' => ["MEDIUMTEXT NULL", "TEXT DEFAULT ''"],
            'image_url' => ["VARCHAR(1000) NOT NULL DEFAULT ''", "TEXT DEFAULT ''"],
            'published' => ["TINYINT NOT NULL DEFAULT 0", "INTEGER NOT NULL DEFAULT 0"],
            'created_at' => ["TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP", "TEXT DEFAULT CURRENT_TIMESTAMP"],
            'updated_at' => ["TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP", "TEXT DEFAULT CURRENT_TIMESTAMP"],
        ],
        'logs' => [
            'user_id' => ["INT UNSIGNED NULL", "INTEGER"],
            'action' => ["VARCHAR(100) NOT NULL", "TEXT NOT NULL"],
            'details' => ["MEDIUMTEXT NULL", "TEXT DEFAULT ''"],
            'created_at' => ["TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP", "TEXT DEFAULT CURRENT_TIMESTAMP"],
        ],
        'admin_login_attempts' => [
            'ip_hash' => ["CHAR(64) NOT NULL", "TEXT NOT NULL"],
            'attempted_at' => ["INT UNSIGNED NOT NULL", "INTEGER NOT NULL"],
        ],
    ];

    public static function ensure(PDO $db): void
    {
        $mysql = Database::isMysql();
        foreach (self::TABLES as $table => $columns) {
            $parts = [$mysql ? 'id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY' : 'id INTEGER PRIMARY KEY AUTOINCREMENT'];
            foreach ($columns as $name => [$mysqlType, $sqliteType]) {
                $parts[] = $name . ' ' . ($mysql ? $mysqlType : $sqliteType);
            }
            $sql = 'CREATE TABLE IF NOT EXISTS ' . ($mysql ? '`' . $table . '`' : $table) . ' (' . implode(', ', $parts) . ')';
            if ($mysql) {
                $sql .= ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
            }
            $db->exec($sql);

            // Fehlende Spalten älterer Versionen ergänzen (ohne UNIQUE/NOT NULL ohne Default).
            $existing = Database::columns($db, $table);
            foreach ($columns as $name => [$mysqlType, $sqliteType]) {
                if (in_array($name, $existing, true)) {
                    continue;
                }
                $type = $mysql ? $mysqlType : $sqliteType;
                $type = str_replace(' UNIQUE', '', $type);
                if (str_contains($type, 'NOT NULL') && !str_contains($type, 'DEFAULT')) {
                    $type = str_replace('NOT NULL', 'NULL', $type);
                }
                if (!$mysql && str_contains($type, 'CURRENT_TIMESTAMP')) {
                    // SQLite erlaubt beim Nachrüsten keinen nicht-konstanten Default.
                    $type = "TEXT DEFAULT ''";
                }
                $db->exec('ALTER TABLE ' . ($mysql ? '`' . $table . '`' : $table) . ' ADD COLUMN ' . $name . ' ' . $type);
            }
        }

        if ($mysql) {
            $indexes = $db->query('SHOW INDEX FROM admin_login_attempts')->fetchAll(PDO::FETCH_COLUMN, 2);
            if (!in_array('idx_login_attempts_ip_time', $indexes, true)) {
                $db->exec('CREATE INDEX idx_login_attempts_ip_time ON admin_login_attempts (ip_hash, attempted_at)');
            }
        } else {
            $db->exec('CREATE INDEX IF NOT EXISTS idx_login_attempts_ip_time ON admin_login_attempts (ip_hash, attempted_at)');
        }
    }
}
