<?php

namespace RateLimit;

/**
 * FIFO 排队管理器
 * 超限请求进入队列，按顺序放行
 */
class Queue
{
    private Storage $storage;
    private array $config;

    public function __construct(Storage $storage, array $config)
    {
        $this->storage = $storage;
        $this->config = $config;
    }

    /**
     * 请求入队
     *
     * @param string $fingerprint 访客指纹
     * @param string $action 接口标识
     * @return array|null 成功返回排队信息，队列满返回null
     */
    public function enqueue(string $fingerprint, string $action = 'default'): ?array
    {
        $maxSize = (int)$this->storage->getConfig('queue_max_size', $this->config['queue_max_size']);
        $timeout = (int)$this->storage->getConfig('queue_timeout', $this->config['queue_timeout']);

        $this->storage->beginTransaction();
        try {
            $pdo = $this->storage->getPdo();

            // 先清理超时请求
            $this->cleanupExpiredInternal();

            // 检查当前队列长度
            $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM queue WHERE status = 'waiting'");
            $count = (int)$stmt->fetch()['cnt'];

            if ($count >= $maxSize) {
                $this->storage->commit();
                return null;
            }

            // 生成唯一请求ID
            $requestId = bin2hex(random_bytes(16));
            $now = time();

            $stmt = $pdo->prepare(
                'INSERT INTO queue (fingerprint, request_id, action, enqueued_at, expires_at, status) 
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([$fingerprint, $requestId, $action, $now, $now + $timeout, 'waiting']);

            // 获取排队位置
            $queueId = (int)$pdo->lastInsertId();
            $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM queue WHERE status = 'waiting' AND id <= $queueId");
            $position = (int)$stmt->fetch()['cnt'];

            // 预估等待时间（假设每秒处理 global_rate 个）
            $globalRate = (float)$this->storage->getConfig('global_rate', $this->config['global_rate']);
            $eta = (int)ceil($position / max($globalRate, 1));

            $this->storage->commit();

            return [
                'request_id'    => $requestId,
                'position'      => $position,
                'wait_estimate' => $eta,
                'queue_id'      => $queueId,
            ];
        } catch (\Exception $e) {
            $this->storage->rollBack();
            throw $e;
        }
    }

    /**
     * 检查是否轮到该请求（轮询接口）
     *
     * @param string $requestId
     * @return array|null 返回状态信息，不存在返回null
     */
    public function poll(string $requestId): ?array
    {
        $pdo = $this->storage->getPdo();

        // 清理超时
        $this->cleanupExpired();

        $stmt = $pdo->prepare('SELECT * FROM queue WHERE request_id = ?');
        $stmt->execute([$requestId]);
        $row = $stmt->fetch();

        if ($row === false) {
            return null;
        }

        if ($row['status'] === 'timeout') {
            return [
                'ready'   => false,
                'status'  => 'timeout',
                'message' => $this->config['queue_timeout_msg'],
            ];
        }

        if ($row['status'] === 'processing') {
            return [
                'ready'  => true,
                'status' => 'ready',
            ];
        }

        // waiting 状态：计算当前位置
        $queueId = (int)$row['id'];
        $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM queue WHERE status = 'waiting' AND id <= $queueId");
        $position = (int)$stmt->fetch()['cnt'];

        $globalRate = (float)$this->storage->getConfig('global_rate', $this->config['global_rate']);
        $eta = (int)ceil($position / max($globalRate, 1));

        return [
            'ready'         => false,
            'status'        => 'waiting',
            'position'      => $position,
            'wait_estimate' => $eta,
        ];
    }

    /**
     * 尝试放行队首请求（在每次请求通过时调用，推动队列前进）
     * 返回被放行的 request_id 列表
     */
    public function advanceQueue(int $count = 1): array
    {
        $this->storage->beginTransaction();
        try {
            $pdo = $this->storage->getPdo();
            $released = [];

            $stmt = $pdo->prepare(
                "SELECT id, request_id FROM queue WHERE status = 'waiting' 
                 ORDER BY id ASC LIMIT ?"
            );
            $stmt->bindValue(1, $count, \PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll();

            foreach ($rows as $row) {
                $updateStmt = $pdo->prepare(
                    "UPDATE queue SET status = 'processing' WHERE id = ? AND status = 'waiting'"
                );
                $updateStmt->execute([$row['id']]);
                if ($updateStmt->rowCount() > 0) {
                    $released[] = $row['request_id'];
                }
            }

            $this->storage->commit();
            return $released;
        } catch (\Exception $e) {
            $this->storage->rollBack();
            return [];
        }
    }

    /**
     * 验证并使用放行令牌（业务请求带 request_id 时调用）
     * 验证成功后标记为 done，返回 true
     */
    public function redeem(string $requestId): bool
    {
        $this->storage->beginTransaction();
        try {
            $pdo = $this->storage->getPdo();

            $stmt = $pdo->prepare('SELECT status FROM queue WHERE request_id = ?');
            $stmt->execute([$requestId]);
            $row = $stmt->fetch();

            if ($row === false || $row['status'] !== 'processing') {
                $this->storage->commit();
                return false;
            }

            $stmt = $pdo->prepare(
                "UPDATE queue SET status = 'done' WHERE request_id = ? AND status = 'processing'"
            );
            $stmt->execute([$requestId]);
            $success = $stmt->rowCount() > 0;

            $this->storage->commit();
            return $success;
        } catch (\Exception $e) {
            $this->storage->rollBack();
            return false;
        }
    }

    /**
     * 标记完成
     */
    public function markDone(string $requestId): void
    {
        $pdo = $this->storage->getPdo();
        $stmt = $pdo->prepare("UPDATE queue SET status = 'done' WHERE request_id = ?");
        $stmt->execute([$requestId]);
    }

    /**
     * 获取当前等待队列长度
     */
    public function getLength(): int
    {
        $pdo = $this->storage->getPdo();
        $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM queue WHERE status = 'waiting'");
        return (int)$stmt->fetch()['cnt'];
    }

    /**
     * 获取处理中数量
     */
    public function getProcessingCount(): int
    {
        $pdo = $this->storage->getPdo();
        $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM queue WHERE status = 'processing'");
        return (int)$stmt->fetch()['cnt'];
    }

    /**
     * 清理超时请求（公开方法）
     */
    public function cleanupExpired(): int
    {
        $this->storage->beginTransaction();
        try {
            $count = $this->cleanupExpiredInternal();
            $this->storage->commit();
            return $count;
        } catch (\Exception $e) {
            $this->storage->rollBack();
            return 0;
        }
    }

    /**
     * 内部清理（需在事务中调用）
     */
    private function cleanupExpiredInternal(): int
    {
        $pdo = $this->storage->getPdo();
        $now = time();

        // 标记超时
        $stmt = $pdo->prepare(
            "UPDATE queue SET status = 'timeout' WHERE status = 'waiting' AND expires_at < ?"
        );
        $stmt->execute([$now]);
        $count = $stmt->rowCount();

        // 删除已完成/超时超过1小时的记录
        $pdo->exec(
            "DELETE FROM queue WHERE status IN ('done', 'timeout') AND enqueued_at < " . ($now - 3600)
        );

        return $count;
    }

    /**
     * 获取队列列表（后台用）
     */
    public function getQueueList(int $limit = 50): array
    {
        $pdo = $this->storage->getPdo();
        $stmt = $pdo->prepare(
            "SELECT * FROM queue WHERE status IN ('waiting', 'processing') 
             ORDER BY id ASC LIMIT ?"
        );
        $stmt->bindValue(1, $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * 清空队列（后台管理用）
     */
    public function clear(): int
    {
        $pdo = $this->storage->getPdo();
        $stmt = $pdo->exec("DELETE FROM queue WHERE status = 'waiting'");
        return $stmt;
    }
}
