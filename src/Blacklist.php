<?php

namespace RateLimit;

/**
 * 黑白名单管理
 */
class Blacklist
{
    private Storage $storage;

    public function __construct(Storage $storage)
    {
        $this->storage = $storage;
    }

    /**
     * 检查 IP 是否被封禁
     */
    public function isBlocked(string $ip, string $fingerprint): bool
    {
        $pdo = $this->storage->getPdo();
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) as cnt FROM blacklist 
             WHERE action = 'block' AND ((type = 'ip' AND value = ?) OR (type = 'fingerprint' AND value = ?))"
        );
        $stmt->execute([$ip, $fingerprint]);
        return (int)$stmt->fetch()['cnt'] > 0;
    }

    /**
     * 检查是否在白名单（完全跳过限速）
     */
    public function isWhitelisted(string $ip, string $fingerprint): bool
    {
        $pdo = $this->storage->getPdo();
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) as cnt FROM blacklist 
             WHERE action = 'allow' AND ((type = 'ip' AND value = ?) OR (type = 'fingerprint' AND value = ?))"
        );
        $stmt->execute([$ip, $fingerprint]);
        return (int)$stmt->fetch()['cnt'] > 0;
    }

    /**
     * 添加黑名单
     */
    public function addBlock(string $type, string $value, string $reason = ''): void
    {
        $pdo = $this->storage->getPdo();
        $stmt = $pdo->prepare(
            'INSERT INTO blacklist (type, value, action, reason, created_at) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$type, $value, 'block', $reason, time()]);
    }

    /**
     * 添加白名单
     */
    public function addAllow(string $type, string $value, string $reason = ''): void
    {
        $pdo = $this->storage->getPdo();
        $stmt = $pdo->prepare(
            'INSERT INTO blacklist (type, value, action, reason, created_at) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$type, $value, 'allow', $reason, time()]);
    }

    /**
     * 删除记录
     */
    public function remove(int $id): void
    {
        $pdo = $this->storage->getPdo();
        $stmt = $pdo->prepare('DELETE FROM blacklist WHERE id = ?');
        $stmt->execute([$id]);
    }

    /**
     * 获取所有规则
     */
    public function getAll(): array
    {
        $pdo = $this->storage->getPdo();
        $stmt = $pdo->query('SELECT * FROM blacklist ORDER BY created_at DESC');
        return $stmt->fetchAll();
    }
}
