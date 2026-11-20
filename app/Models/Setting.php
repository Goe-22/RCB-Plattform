<?php
declare(strict_types=1);

require_once __DIR__ . '/../Database/Database.php';

final class Setting
{
    public static function get(string $key, mixed $default = null): mixed
    {
        $stmt = Database::connect()->prepare('SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1');
        $stmt->execute([$key]);
        $result = $stmt->fetch();
        return $result ? $result['setting_value'] : $default;
    }

    public static function set(string $key, string $value): bool
    {
        $sql = Database::isMysql()
            ? 'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = CURRENT_TIMESTAMP'
            : 'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON CONFLICT(setting_key) DO UPDATE SET setting_value = excluded.setting_value, updated_at = CURRENT_TIMESTAMP';
        return Database::connect()->prepare($sql)->execute([$key, $value]);
    }
}
