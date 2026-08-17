<?php

namespace RateLimit;

/**
 * 限速/排队事件日志
 */
class Logger
{
    private Storage $storage;
    private bool $enabled;

    public function __construct(Storage $storage, bool $enabled = true)
    {
        $this->storage = $storage;
        $this->enabled = $enabled;
    }

    /**
     * 记录事件
     *
     * @param string $fingerprint 访客指纹
     * @param string $ip IP地址
     * @param string $action 接口标识
     * @param string $event 事件类型: allowed / rate_limited / queued / timeout / rejected / redeemed
     * @param string $detail 详情
     */
    public function log(string $fingerprint, string $ip, string $action, string $event, string $detail = ''): void
    {
        if (!$this->enabled) {
            return;
        }

        try {
            $pdo = $this->storage->getPdo();
            $stmt = $pdo->prepare(
                'INSERT INTO logs (fingerprint, ip, action, event, detail, created_at) 
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([$fingerprint, $ip, $action, $event, $detail, time()]);
        } catch (\Exception $e) {
            // 日志失败不影响主流程
        }
    }

    /**
     * 获取统计数据（后台仪表盘用）
     */
    public function getStats(): array
    {
        $pdo = $this->storage->getPdo();
        $today = strtotime('today');

        $stmt = $pdo->prepare(
            'SELECT event, COUNT(*) as cnt FROM logs WHERE created_at >= ? GROUP BY event'
        );
        $stmt->execute([$today]);
        $rows = $stmt->fetchAll();

        $stats = [
            'allowed'      => 0,
            'rate_limited' => 0,
            'queued'       => 0,
            'timeout'      => 0,
            'rejected'     => 0,
            'redeemed'     => 0,
        ];

        foreach ($rows as $row) {
            $stats[$row['event']] = (int)$row['cnt'];
        }

        return $stats;
    }

    /**
     * 获取最近日志
     */
    public function getRecentLogs(int $limit = 20): array
    {
        $pdo = $this->storage->getPdo();
        $stmt = $pdo->prepare(
            'SELECT * FROM logs ORDER BY created_at DESC LIMIT ?'
        );
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    }

    /**
     * 查询日志（带筛选）
     */
    public function queryLogs(?string $event = null, ?string $ip = null, ?int $startTime = null, ?int $endTime = null, int $limit = 100, int $offset = 0): array
    {
        $pdo = $this->storage->getPdo();
        $where = [];
        $params = [];

        if ($event) {
            $where[] = 'event = ?';
            $params[] = $event;
        }
        if ($ip) {
            $where[] = 'ip LIKE ?';
            $params[] = '%' . $ip . '%';
        }
        if ($startTime) {
            $where[] = 'created_at >= ?';
            $params[] = $startTime;
        }
        if ($endTime) {
            $where[] = 'created_at <= ?';
            $params[] = $endTime;
        }

        $sql = 'SELECT * FROM logs';
        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY created_at DESC LIMIT ? OFFSET ?';

        $stmt = $pdo->prepare($sql);
        // 绑定筛选参数
        $i = 1;
        foreach ($params as $param) {
            $stmt->bindValue($i++, $param);
        }
        $stmt->bindValue($i++, $limit, \PDO::PARAM_INT);
        $stmt->bindValue($i, $offset, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * 清理过期日志
     */
    public function cleanup(int $retentionDays = 30): int
    {
        $pdo = $this->storage->getPdo();
        $cutoff = time() - ($retentionDays * 86400);
        $stmt = $pdo->prepare('DELETE FROM logs WHERE created_at < ?');
        $stmt->execute([$cutoff]);
        return $stmt->rowCount();
    }

    /**
     * 获取活跃访客数（最近5分钟有记录）
     */
    public function getActiveVisitors(): int
    {
        $pdo = $this->storage->getPdo();
        $since = time() - 300;
        $stmt = $pdo->prepare(
            'SELECT COUNT(DISTINCT fingerprint) as cnt FROM logs WHERE created_at >= ?'
        );
        $stmt->execute([$since]);
        return (int)$stmt->fetch()['cnt'];
    }
}
