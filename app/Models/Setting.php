<?php

require_once __DIR__ . '/../Database/Database.php';

class Setting
{
    public static function get(string $key, $default = null)
    {
        $db = Database::connect();

        $stmt = $db->prepare(
            'SELECT setting_value
             FROM settings
             WHERE setting_key = ?
             LIMIT 1'
        );

        $stmt->execute([$key]);

        $result = $stmt->fetch();

        if (!$result) {
            return $default;
        }

        return $result['setting_value'];
    }

    public static function set(
        string $key,
        string $value
    ): bool {

        $db = Database::connect();

        $stmt = $db->prepare(
            'INSERT INTO settings
                (setting_key, setting_value)
             VALUES
                (?, ?)
             ON CONFLICT(setting_key)
             DO UPDATE SET
                setting_value = excluded.setting_value,
                updated_at = CURRENT_TIMESTAMP'
        );

        return $stmt->execute([
            $key,
            $value
        ]);
    }
}
