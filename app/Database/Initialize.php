<?php

require_once __DIR__ . '/Database.php';

class Initialize
{
    public static function run(): void
    {
        $db = Database::connect();

        $sql = <<<SQL

CREATE TABLE IF NOT EXISTS settings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    setting_key TEXT NOT NULL UNIQUE,
    setting_value TEXT DEFAULT '',
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS pages (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    slug TEXT NOT NULL UNIQUE,
    title TEXT NOT NULL,
    content TEXT DEFAULT '',
    published INTEGER DEFAULT 0,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS tracks (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    description TEXT DEFAULT '',
    surface TEXT DEFAULT '',
    length TEXT DEFAULT '',
    classes TEXT DEFAULT '',
    electricity INTEGER DEFAULT 0,
    toilet INTEGER DEFAULT 0,
    paddock INTEGER DEFAULT 0,
    guest_drivers INTEGER DEFAULT 0,
    published INTEGER DEFAULT 0,
    show_status INTEGER DEFAULT 0,
    status TEXT DEFAULT 'closed',
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS reglements (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    description TEXT DEFAULT '',
    rules TEXT DEFAULT '',
    published INTEGER DEFAULT 0,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS events (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    description TEXT DEFAULT '',
    event_date DATE,
    start_time TIME,
    location TEXT DEFAULT '',
    published INTEGER DEFAULT 0,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS results (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    event_id INTEGER,
    title TEXT NOT NULL,
    content TEXT DEFAULT '',
    published INTEGER DEFAULT 0,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS sponsors (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    url TEXT DEFAULT '',
    description TEXT DEFAULT '',
    published INTEGER DEFAULT 0,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS social_links (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    platform TEXT NOT NULL,
    url TEXT DEFAULT '',
    enabled INTEGER DEFAULT 0,
    show_footer INTEGER DEFAULT 0,
    show_home INTEGER DEFAULT 0,
    show_tracks INTEGER DEFAULT 0,
    show_events INTEGER DEFAULT 0,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS maintenance (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    event_date DATE,
    start_time TIME,
    location TEXT DEFAULT '',
    description TEXT DEFAULT '',
    contact TEXT DEFAULT '',
    published INTEGER DEFAULT 0,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS media (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    filename TEXT NOT NULL,
    title TEXT DEFAULT '',
    description TEXT DEFAULT '',
    category TEXT DEFAULT 'other',
    type TEXT DEFAULT '',
    published INTEGER DEFAULT 0,
    uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT NOT NULL UNIQUE,
    email TEXT DEFAULT '',
    password_hash TEXT NOT NULL,
    role TEXT NOT NULL DEFAULT 'admin',
    active INTEGER DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    action TEXT NOT NULL,
    details TEXT DEFAULT '',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS live_settings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    enabled INTEGER DEFAULT 0,
    show_menu INTEGER DEFAULT 0,
    show_training INTEGER DEFAULT 0,
    show_races INTEGER DEFAULT 0,
    save_results INTEGER DEFAULT 0,
    source TEXT DEFAULT 'manual',
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

SQL;

        $db->exec($sql);

        self::insertDefaults($db);
    }

    private static function insertDefaults(PDO $db): void
    {
        $defaults = [

            'site_name' =>
                'RC Bande Zwickau e.V.',

            'site_description' =>
                'RC-Modellsport in Zwickau',

            'site_enabled' =>
                '1',

            'live_enabled' =>
                '0',

            'results_enabled' =>
                '1',

            'news_enabled' =>
                '0',

            'facebook_enabled' =>
                '0'
        ];

        $stmt = $db->prepare(
            'INSERT OR IGNORE INTO settings
             (setting_key, setting_value)
             VALUES (?, ?)'
        );

        foreach ($defaults as $key => $value) {

            $stmt->execute([
                $key,
                $value
            ]);
        }

        $live = $db->prepare(
            'INSERT INTO live_settings
             (enabled)
             SELECT 0
             WHERE NOT EXISTS
             (
                 SELECT 1
                 FROM live_settings
             )'
        );

        $live->execute();
    }
}
