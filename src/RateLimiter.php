<?php

namespace RateLimit;

/**
 * 令牌桶限速器
 * 支持全局限速 + 单IP限速 + 接口级(action)限速
 */
class RateLimiter
{
    private Storage $storage;
    private array $config;

    public function __construct(Storage $storage, array $config)
    {
        $this->storage = $storage;
        $this->config = $config;
    }

    /**
     * 检查是否允许请求（同时检查全局和单IP）
     *
     * @param string $fingerprint 访客指纹
     * @param string $ip IP地址
     * @param string $action 接口标识
     * @return bool
     */
    public function allow(string $fingerprint, string $ip, string $action = 'default'): bool
    {
        // 单IP限速
        $ipKey = 'ip:' . $ip . ':' . $action;
        $ipRate = (float)$this->storage->getConfig('ip_rate', $this->config['ip_rate']);
        $ipBurst = (float)$this->storage->getConfig('ip_burst', $this->config['ip_burst']);
        if (!$this->consumeToken($ipKey, $ipRate, $ipBurst)) {
            return false;
        }

        // 全局限速
        $globalKey = 'global:' . $action;
        $globalRate = (float)$this->storage->getConfig('global_rate', $this->config['global_rate']);
        $globalBurst = (float)$this->storage->getConfig('global_burst', $this->config['global_burst']);
        if (!$this->consumeToken($globalKey, $globalRate, $globalBurst)) {
            // 全局不通过时，回退IP的令牌（避免双重消耗）
            $this->refundToken($ipKey);
            return false;
        }

        return true;
    }

    /**
     * 消耗一个令牌（原子操作）
     */
    private function consumeToken(string $key, float $rate, float $burst): bool
    {
        $this->storage->beginTransaction();
        try {
            $pdo = $this->storage->getPdo();
            $now = microtime(true);

            // 查找或创建记录
            $stmt = $pdo->prepare('SELECT tokens, last_refill FROM visitors WHERE fingerprint = ?');
            $stmt->execute([$key]);
            $row = $stmt->fetch();

            if ($row === false) {
                // 新记录：初始化为满桶
                $tokens = $burst;
                $lastRefill = $now;
                $stmt = $pdo->prepare(
                    'INSERT INTO visitors (fingerprint, ip, tokens, last_refill, created_at, last_seen) 
                     VALUES (?, ?, ?, ?, ?, ?)'
                );
                $stmt->execute([$key, '', $tokens, $lastRefill, time(), time()]);
            } else {
                $tokens = (float)$row['tokens'];
                $lastRefill = (float)$row['last_refill'];

                // 计算应补充的令牌数
                $elapsed = $now - $lastRefill;
                $refillAmount = $elapsed * $rate;
                $tokens = min($burst, $tokens + $refillAmount);
                $lastRefill = $now;
            }

            // 检查是否有足够令牌
            if ($tokens < 1) {
                // 更新补充时间但不消耗
                $stmt = $pdo->prepare(
                    'UPDATE visitors SET tokens = ?, last_refill = ?, last_seen = ? WHERE fingerprint = ?'
                );
                $stmt->execute([$tokens, $lastRefill, time(), $key]);
                $this->storage->commit();
                return false;
            }

            // 消耗一个令牌
            $tokens -= 1;
            $stmt = $pdo->prepare(
                'UPDATE visitors SET tokens = ?, last_refill = ?, last_seen = ? WHERE fingerprint = ?'
            );
            $stmt->execute([$tokens, $lastRefill, time(), $key]);

            $this->storage->commit();
            return true;
        } catch (\Exception $e) {
            $this->storage->rollBack();
            throw $e;
        }
    }

    /**
     * 退还一个令牌（用于全局不通过时回退IP消耗）
     */
    private function refundToken(string $key): void
    {
        try {
            $pdo = $this->storage->getPdo();
            $stmt = $pdo->prepare('UPDATE visitors SET tokens = tokens + 1 WHERE fingerprint = ?');
            $stmt->execute([$key]);
        } catch (\Exception $e) {
            // 忽略退还失败
        }
    }

    /**
     * 获取当前剩余令牌数（用于后台展示）
     */
    public function getTokens(string $key): float
    {
        $pdo = $this->storage->getPdo();
        $stmt = $pdo->prepare('SELECT tokens FROM visitors WHERE fingerprint = ?');
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        return $row ? (float)$row['tokens'] : 0;
    }

    /**
     * 重置某个key的令牌桶（后台管理用）
     */
    public function resetBucket(string $key): void
    {
        $pdo = $this->storage->getPdo();
        $stmt = $pdo->prepare('DELETE FROM visitors WHERE fingerprint = ?');
        $stmt->execute([$key]);
    }
}
