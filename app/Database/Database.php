<?php
declare(strict_types=1);

/**
 * Datenbankverbindung für Website und Adminbereich.
 *
 * Liegt config/database.php mit 'driver' => 'mysql' vor, wird MySQL/MariaDB verwendet.
 * Ohne diese Datei (oder mit 'driver' => 'sqlite') wird storage/database.sqlite genutzt.
 */
final class Database
{
    private static ?PDO $connection = null;
    private static ?array $config = null;

    public static function configFile(): string
    {
        return dirname(__DIR__, 2) . '/config/database.php';
    }

    public static function config(): array
    {
        if (self::$config === null) {
            $file = self::configFile();
            $loaded = is_file($file) ? require $file : [];
            self::$config = is_array($loaded) ? $loaded : [];
        }
        return self::$config;
    }

    public static function driver(): string
    {
        return (self::config()['driver'] ?? '') === 'mysql' ? 'mysql' : 'sqlite';
    }

    public static function isMysql(): bool
    {
        return self::driver() === 'mysql';
    }

    public static function sqlitePath(): string
    {
        return dirname(__DIR__, 2) . '/storage/database.sqlite';
    }

    public static function connect(): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        $config = self::config();
        $driver = (string)($config['driver'] ?? '');
        if ($driver !== '' && !in_array($driver, ['mysql', 'sqlite'], true)) {
            throw new RuntimeException('Unsupported database driver.');
        }

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        if ($driver === 'mysql') {
            foreach (['host', 'database', 'username', 'password'] as $required) {
                if (!isset($config[$required]) || $config[$required] === '') {
                    throw new RuntimeException('Database configuration is incomplete.');
                }
            }
            $dsn = sprintf(
                'mysql:host=%s;%sdbname=%s;charset=%s',
                $config['host'],
                isset($config['port']) && $config['port'] !== '' ? 'port=' . (int)$config['port'] . ';' : '',
                $config['database'],
                $config['charset'] ?? 'utf8mb4'
            );
            self::$connection = new PDO($dsn, (string)$config['username'], (string)$config['password'], $options);
            // Datumsvergleiche sollen in deutscher Zeit erfolgen.
            self::$connection->exec("SET time_zone = '" . (new DateTimeImmutable('now', new DateTimeZone('Europe/Berlin')))->format('P') . "'");
            return self::$connection;
        }

        $databasePath = self::sqlitePath();
        $directory = dirname($databasePath);
        if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
            throw new RuntimeException('Storage directory is not writable.');
        }
        self::$connection = new PDO('sqlite:' . $databasePath, null, null, $options);
        self::$connection->exec('PRAGMA foreign_keys = ON');
        self::$connection->exec('PRAGMA busy_timeout = 5000');
        return self::$connection;
    }

    /** Spaltennamen einer Tabelle, für MySQL und SQLite. */
    public static function columns(PDO $db, string $table): array
    {
        if (!preg_match('/^[a-z_]+$/', $table)) {
            return [];
        }
        if (self::isMysql()) {
            return $db->query('SHOW COLUMNS FROM `' . $table . '`')->fetchAll(PDO::FETCH_COLUMN);
        }
        return array_column($db->query('PRAGMA table_info(' . $table . ')')->fetchAll(PDO::FETCH_ASSOC), 'name');
    }
}
