<?php

class Database
{
    private static ?PDO $connection = null;

    public static function connect(): PDO
    {
        if (self::$connection === null) {

            $databasePath = __DIR__ . '/../../storage/database.sqlite';

            $directory = dirname($databasePath);

            if (!is_dir($directory)) {
                mkdir($directory, 0755, true);
            }

            self::$connection = new PDO(
                'sqlite:' . $databasePath
            );

            self::$connection->setAttribute(
                PDO::ATTR_ERRMODE,
                PDO::ERRMODE_EXCEPTION
            );

            self::$connection->setAttribute(
                PDO::ATTR_DEFAULT_FETCH_MODE,
                PDO::FETCH_ASSOC
            );
        }

        return self::$connection;
    }
}
