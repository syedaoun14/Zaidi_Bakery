<?php
// ============================================================
// config/database.php  — MS SQL Server 2014 Connection
// ============================================================

define('DB_SERVER',   'localhost');   // or your SQL Server IP
define('DB_NAME',     'zaidi_bakery');
define('DB_USER',     'sa');          // change to your SQL user
define('DB_PASSWORD', 'YourPassword123!'); // change to your password
define('DB_PORT',     1433);

class Database {
    private static ?Database $instance = null;
    private        ?\PDO     $conn     = null;

    private function __construct() {
        $dsn = "sqlsrv:Server=" . DB_SERVER . "," . DB_PORT . ";Database=" . DB_NAME . ";Encrypt=no;TrustServerCertificate=yes";
        try {
            $this->conn = new \PDO($dsn, DB_USER, DB_PASSWORD, [
                \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (\PDOException $e) {
            http_response_code(500);
            die(json_encode(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]));
        }
    }

    public static function getInstance(): Database {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    public function getConn(): \PDO { return $this->conn; }

    // Convenience helpers
    public function query(string $sql, array $params = []): \PDOStatement {
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function fetchAll(string $sql, array $params = []): array {
        return $this->query($sql, $params)->fetchAll();
    }

    public function fetchOne(string $sql, array $params = []): ?array {
        $row = $this->query($sql, $params)->fetch();
        return $row ?: null;
    }

    public function execute(string $sql, array $params = []): int {
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }

    public function lastInsertId(): string { return $this->conn->lastInsertId(); }
}
