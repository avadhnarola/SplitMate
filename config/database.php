<?php
// SplitMate Database Connection using PDO

require_once __DIR__ . '/config.php';

class Database
{
    private static ?PDO $instance = null;

    public static function getConnection(): PDO
    {
        if (self::$instance === null) {
            $host = env('DB_HOST', '127.0.0.1');
            $port = env('DB_PORT', '3306');
            $dbname = env('DB_NAME', 'splitmate_db');
            $user = env('DB_USER', 'root');
            $pass = env('DB_PASS', '');
           

            $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];

            try {
                self::$instance = new PDO($dsn, $user, $pass, $options);
            } catch (PDOException $e) {
                // If running in dev mode, show meaningful error; otherwise generic
                if (DEV_MODE) {
                    die("Database Connection Error: " . htmlspecialchars($e->getMessage()));
                } else {
                    die("Database connection failed. Please contact the administrator.");
                }
            }
        }
        return self::$instance;
    }
}

// Global shortcut helper
function db(): PDO
{
    return Database::getConnection();
}

