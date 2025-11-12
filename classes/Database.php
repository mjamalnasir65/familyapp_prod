<?php

class Database {
    private static ?Database $instance = null;
    private \PDO $pdo;

    private function __construct() {
        // Support both new public/ layout and legacy parent config path
        $configTried = false;
        $paths = [
            __DIR__ . '/../config/config.php',            // public/config (new structure)
            dirname(__DIR__, 2) . '/config/config.php',    // legacy root/config fallback
        ];
        foreach ($paths as $p) {
            if (is_file($p)) { require_once $p; $configTried = true; break; }
        }
        if (!$configTried) {
            throw new \RuntimeException('Configuration file not found in expected paths.');
        }

        $host    = defined('DB_HOST') ? DB_HOST : 'localhost';
        $db      = defined('DB_NAME') ? DB_NAME : '';
        $user    = defined('DB_USER') ? DB_USER : '';
        $pass    = defined('DB_PASS') ? DB_PASS : '';
        $charset = defined('DB_CHARSET') ? DB_CHARSET : 'utf8mb4';
        $port    = defined('DB_PORT') ? DB_PORT : null;

        if ($db === '') {
            throw new \RuntimeException('Database name (DB_NAME) not defined.');
        }

        $dsn = "mysql:host={$host};" . ($port ? "port={$port};" : '') . "dbname={$db};charset={$charset}";
        $options = [
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $this->pdo = new \PDO($dsn, $user, $pass, $options);
        } catch (\PDOException $e) {
            throw new \RuntimeException('DB connection failed: ' . $e->getMessage(), 0, $e);
        }
    }

    public static function getInstance(): Database {
        return self::$instance ?? (self::$instance = new self());
    }

    public function getConnection(): \PDO { return $this->pdo; }
}
