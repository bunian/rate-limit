<?php

namespace RateLimit;

use PDO;
use PDOException;

/**
 * SQLite 存储层 - 封装数据库连接、表初始化、事务锁
 */
class Storage
{
    private static ?self $instance = null;
    private PDO $pdo;
    private string $dbPath;
    private bool $inTransaction = false;

    private function __construct(string $dbPath)
    {
        $this->dbPath = $dbPath;
        $dir = dirname($dbPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $this->pdo = new PDO('sqlite:' . $dbPath);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        // 开启 WAL 模式提升并发读性能
        $this->pdo->exec('PRAGMA journal_mode=WAL');
        $this->pdo->exec('PRAGMA busy_timeout=5000');

        $this->initTables();
    }

    public static function getInstance(string $dbPath): self
    {
        if (self::$instance === null) {
            self::$instance = new self($dbPath);
        }
        return self::$instance;
    }

    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    /**
     * 开启立即事务（获取写锁，防止并发写入冲突）
     * 使用原生 SQL 而非 PDO beginTransaction，以支持 BEGIN IMMEDIATE
     */
    public function beginTransaction(): void
    {
        if (!$this->inTransaction) {
            $this->pdo->exec('BEGIN IMMEDIATE');
            $this->inTransaction = true;
        }
    }

    public function commit(): void
    {
        if ($this->inTransaction) {
            $this->pdo->exec('COMMIT');
            $this->inTransaction = false;
        }
    }

    public function rollBack(): void
    {
        if ($this->inTransaction) {
            try {
                $this->pdo->exec('ROLLBACK');
            } catch (\Exception $e) {
                // 忽略回滚失败
            }
            $this->inTransaction = false;
        }
    }

    /**
     * 初始化数据库表
     */
    private function initTables(): void
    {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS visitors (
                fingerprint TEXT PRIMARY KEY,
                ip TEXT,
                tokens REAL DEFAULT 0,
                last_refill REAL DEFAULT 0,
                created_at INTEGER,
                last_seen INTEGER
            )
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS queue (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                fingerprint TEXT,
                request_id TEXT UNIQUE,
                action TEXT,
                enqueued_at INTEGER,
                expires_at INTEGER,
                status TEXT DEFAULT 'waiting'
            )
        ");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_queue_status ON queue(status, id)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_queue_request ON queue(request_id)");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS config (
                key TEXT PRIMARY KEY,
                value TEXT
            )
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS blacklist (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                type TEXT,
                value TEXT,
                action TEXT,
                reason TEXT,
                created_at INTEGER
            )
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                fingerprint TEXT,
                ip TEXT,
                action TEXT,
                event TEXT,
                detail TEXT,
                created_at INTEGER
            )
        ");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_logs_created ON logs(created_at)");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_logs_event ON logs(event)");
    }

    /**
     * 获取配置值（优先从数据库，其次从默认配置）
     */
    public function getConfig(string $key, $default = null)
    {
        $stmt = $this->pdo->prepare('SELECT value FROM config WHERE key = ?');
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        if ($row !== false) {
            $value = $row['value'];
            // 尝试自动转换类型
            if (is_numeric($value) && strpos($value, '.') === false) {
                return (int)$value;
            }
            if (is_numeric($value)) {
                return (float)$value;
            }
            if ($value === 'true') return true;
            if ($value === 'false') return false;
            return $value;
        }
        return $default;
    }

    /**
     * 设置配置值
     */
    public function setConfig(string $key, $value): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO config (key, value) VALUES (?, ?) 
             ON CONFLICT(key) DO UPDATE SET value = excluded.value'
        );
        $stmt->execute([$key, (string)$value]);
    }

    /**
     * 获取所有配置
     */
    public function getAllConfig(): array
    {
        $stmt = $this->pdo->query('SELECT key, value FROM config');
        $result = [];
        foreach ($stmt->fetchAll() as $row) {
            $result[$row['key']] = $row['value'];
        }
        return $result;
    }
}
