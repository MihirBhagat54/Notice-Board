<?php
// =============================================================
// app/core/Database.php — MySQLi Singleton Wrapper
// =============================================================

class Database
{
    private static ?mysqli $conn = null;

    public static function connect(): mysqli
    {
        if (self::$conn === null) {
            self::$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            if (self::$conn->connect_error) {
                error_log('DB Connection failed: ' . self::$conn->connect_error);
                die('Database connection failed. Please contact the administrator.');
            }
            self::$conn->set_charset('utf8mb4');
        }
        return self::$conn;
    }

    public static function query(string $sql, string $types = '', mixed ...$params): mysqli_stmt|false
    {
        $db   = self::connect();
        $stmt = $db->prepare($sql);
        if (!$stmt) {
            error_log('DB prepare failed: ' . $db->error . ' | SQL: ' . $sql);
            return false;
        }
        if ($types && $params) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        return $stmt;
    }

    public static function fetchAll(string $sql, string $types = '', mixed ...$params): array
    {
        $stmt = self::query($sql, $types, ...$params);
        if (!$stmt) return [];
        $result = $stmt->get_result();
        return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }

    public static function fetchOne(string $sql, string $types = '', mixed ...$params): ?array
    {
        $stmt = self::query($sql, $types, ...$params);
        if (!$stmt) return null;
        $result = $stmt->get_result();
        $row    = $result ? $result->fetch_assoc() : null;
        return $row ?: null;
    }

    public static function insertID(): int
    {
        return self::connect()->insert_id;
    }

    public static function affectedRows(): int
    {
        return self::connect()->affected_rows;
    }

    public static function raw(): mysqli
    {
        return self::connect();
    }
}
