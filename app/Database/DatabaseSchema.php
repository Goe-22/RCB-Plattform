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
            'display_name' => ["VARCHAR(190) NOT NULL DEFAULT ''", "TEXT DEFAULT ''"],
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
            'end_date' => ["DATE NULL", "TEXT DEFAULT ''"],
            'start_time' => ["TIME NULL", "TEXT DEFAULT ''"],
            'track_id' => ["INT UNSIGNED NULL", "INTEGER"],
            'registration_url' => ["VARCHAR(1000) NOT NULL DEFAULT ''", "TEXT DEFAULT ''"],
            'announcement_url' => ["VARCHAR(1000) NOT NULL DEFAULT ''", "TEXT DEFAULT ''"],
            'announcement_text' => ["MEDIUMTEXT NULL", "TEXT DEFAULT ''"],
            'classes_json' => ["MEDIUMTEXT NULL", "TEXT DEFAULT ''"],
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
        'content_blocks' => [
            'block_key' => ["VARCHAR(190) NOT NULL UNIQUE", "TEXT NOT NULL UNIQUE"],
            'content' => ["MEDIUMTEXT NULL", "TEXT DEFAULT ''"],
            'updated_by' => ["INT UNSIGNED NULL", "INTEGER"],
            'updated_at' => ["TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP", "TEXT DEFAULT CURRENT_TIMESTAMP"],
        ],
        'content_drafts' => [
            'block_key' => ["VARCHAR(190) NOT NULL UNIQUE", "TEXT NOT NULL UNIQUE"],
            'content' => ["MEDIUMTEXT NULL", "TEXT"],
            'updated_by' => ["INT UNSIGNED NULL", "INTEGER"],
            'updated_at' => ["TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP", "TEXT DEFAULT CURRENT_TIMESTAMP"],
        ],
        'site_updates' => [
            'title' => ["VARCHAR(255) NOT NULL", "TEXT NOT NULL"],
            'url' => ["VARCHAR(500) NOT NULL DEFAULT ''", "TEXT DEFAULT ''"],
            'created_at' => ["TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP", "TEXT DEFAULT CURRENT_TIMESTAMP"],
        ],
        'admin_tokens' => [
            'token_hash' => ["CHAR(64) NOT NULL UNIQUE", "TEXT NOT NULL UNIQUE"],
            'kind' => ["VARCHAR(20) NOT NULL DEFAULT 'invite'", "TEXT NOT NULL DEFAULT 'invite'"],
            'user_id' => ["INT UNSIGNED NULL", "INTEGER"],
            'permissions' => ["VARCHAR(500) NOT NULL DEFAULT '[]'", "TEXT NOT NULL DEFAULT '[]'"],
            'note' => ["VARCHAR(190) NOT NULL DEFAULT ''", "TEXT DEFAULT ''"],
            'created_by' => ["INT UNSIGNED NULL", "INTEGER"],
            'expires_at' => ["INT UNSIGNED NOT NULL DEFAULT 0", "INTEGER NOT NULL DEFAULT 0"],
            'used_at' => ["INT UNSIGNED NULL", "INTEGER"],
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

        self::migrateLegacyPages($db);
    }

    /**
     * Übernimmt Texte aus der früheren Seitenverwaltung (Tabelle pages) einmalig in die Seitenbereiche.
     */
    private static function migrateLegacyPages(PDO $db): void
    {
        require_once dirname(__DIR__) . '/Helpers/RichText.php';
        $insert = $db->prepare(Database::isMysql()
            ? 'INSERT IGNORE INTO content_blocks (block_key, content) VALUES (?, ?)'
            : 'INSERT OR IGNORE INTO content_blocks (block_key, content) VALUES (?, ?)');
        $rows = $db->query("SELECT slug, title, content FROM pages WHERE published = 1 AND slug IN ('startseite','verein','impressum','datenschutz','haftungsausschluss','strecken','termine','onroad','offroad')")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $slug = (string)$row['slug'];
            $title = trim((string)$row['title']);
            $content = trim((string)$row['content']);
            if (in_array($slug, ['strecken', 'termine', 'onroad', 'offroad'], true)) {
                if ($title !== '') { $insert->execute([$slug . '.hero.titel', $title]); }
                if ($content !== '') { $insert->execute([$slug . '.hero.text', $content]); }
                continue;
            }
            $key = $slug === 'startseite' ? 'startseite.intro' : $slug . '.inhalt';
            if ($content !== '') {
                $html = ($title !== '' && $slug !== 'impressum' ? '<h2>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h2>' : '') . RichText::fromPlain($content);
                $insert->execute([$key, $html]);
            }
        }
    }

    /**
     * Trägt die mitgelieferten Website-Bilder (Startseite, Strecken, Logo) in die Medienverwaltung ein,
     * damit sie im Adminbereich sichtbar und austauschbar sind.
     */
    public static function registerBundledImages(PDO $db, string $publicRoot): void
    {
        $images = [
            ['startseite.jpg', 'hero', 'Titelbild Startseite'],
            ['onroad.jpg', 'onroad', 'Onroad-Strecke'],
            ['offroad.jpg', 'offroad', 'Offroad-Strecke'],
            ['rcb-logo.png', 'logo', 'Vereinslogo'],
        ];
        $uploads = $publicRoot . '/uploads';
        if (!is_dir($uploads) && !@mkdir($uploads, 0755, true) && !is_dir($uploads)) {
            return;
        }
        $exists = $db->prepare('SELECT COUNT(*) FROM media WHERE filename = ? OR (category = ? AND published = 1)');
        $insert = $db->prepare('INSERT INTO media (filename, title, description, category, mime_type, published) VALUES (?, ?, ?, ?, ?, 1)');
        foreach ($images as [$file, $category, $title]) {
            $exists->execute([$file, $category]);
            if ((int)$exists->fetchColumn() > 0) {
                continue;
            }
            $target = $uploads . '/' . $file;
            $source = $publicRoot . '/assets/images/' . $file;
            if (!is_file($target) && (!is_file($source) || !@copy($source, $target))) {
                continue;
            }
            $mime = str_ends_with($file, '.png') ? 'image/png' : 'image/jpeg';
            $insert->execute([$file, $title, 'Von der Website übernommen', $category, $mime]);
        }
    }
}
