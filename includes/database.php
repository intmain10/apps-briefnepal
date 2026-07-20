<?php
/**
 * Database layer — a thin, safe PDO wrapper (singleton).
 *
 * Always uses prepared statements. Never interpolate user input into SQL.
 *
 * Usage:
 *   $db = Database::getInstance();
 *   $rows = $db->select("SELECT * FROM tools WHERE category_id = ?", [$id]);
 *
 * @package OmniTools
 */

declare(strict_types=1);

final class Database
{
    private static ?Database $instance = null;
    private ?PDO $pdo = null;
    private bool $connected = false;

    private function __construct()
    {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            DB_HOST,
            DB_NAME,
            DB_CHARSET
        );

        try {
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_STRINGIFY_FETCHES  => false,
            ]);
            $this->connected = true;
        } catch (PDOException $e) {
            // The platform is designed to also run without a DB (the tool
            // registry lives in code). We degrade gracefully rather than
            // white-screen when MySQL is not yet provisioned.
            $this->connected = false;
            if (DEBUG_MODE) {
                error_log('OmniTools DB connection failed: ' . $e->getMessage());
            }
        }
    }

    public static function getInstance(): Database
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function isConnected(): bool
    {
        return $this->connected;
    }

    public function pdo(): ?PDO
    {
        return $this->pdo;
    }

    /**
     * Run a query with bound params and return all rows.
     *
     * @param array<int|string,mixed> $params
     * @return array<int,array<string,mixed>>
     */
    public function select(string $sql, array $params = []): array
    {
        if (!$this->connected) {
            return [];
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Return a single row (or null).
     *
     * @param array<int|string,mixed> $params
     * @return array<string,mixed>|null
     */
    public function selectOne(string $sql, array $params = []): ?array
    {
        if (!$this->connected) {
            return null;
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /**
     * Run an INSERT/UPDATE/DELETE. Returns affected row count.
     *
     * @param array<int|string,mixed> $params
     */
    public function execute(string $sql, array $params = []): int
    {
        if (!$this->connected) {
            return 0;
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    /**
     * Insert and return last insert id.
     *
     * @param array<int|string,mixed> $params
     */
    public function insert(string $sql, array $params = []): int
    {
        if (!$this->connected) {
            return 0;
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $this->pdo->lastInsertId();
    }
}
