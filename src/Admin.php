<?php

namespace RateLimit;

/**
 * 管理后台 - 认证与数据接口
 */
class Admin
{
    private Storage $storage;
    private array $config;
    private string $sessionKey = 'rl_admin_logged_in';

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->storage = Storage::getInstance($config['db_path']);
    }

    /**
     * 启动 session
     */
    public function startSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * 验证登录
     */
    public function login(string $username, string $password): bool
    {
        $this->startSession();

        $adminUser = $this->storage->getConfig('admin_user', $this->config['admin_user']);
        $adminPass = $this->storage->getConfig('admin_pass', $this->config['admin_pass']);

        if ($username === $adminUser && $password === $adminPass) {
            $_SESSION[$this->sessionKey] = true;
            $_SESSION['rl_admin_login_time'] = time();
            return true;
        }
        return false;
    }

    /**
     * 登出
     */
    public function logout(): void
    {
        $this->startSession();
        unset($_SESSION[$this->sessionKey]);
        session_destroy();
    }

    /**
     * 检查是否已登录
     */
    public function isLoggedIn(): bool
    {
        $this->startSession();
        if (empty($_SESSION[$this->sessionKey])) {
            return false;
        }
        // 24小时过期
        $loginTime = $_SESSION['rl_admin_login_time'] ?? 0;
        if (time() - $loginTime > 86400) {
            unset($_SESSION[$this->sessionKey]);
            return false;
        }
        return true;
    }

    /**
     * 要求登录，未登录跳转到登录页
     */
    public function requireLogin(): void
    {
        if (!$this->isLoggedIn()) {
            header('Location: login.php');
            exit;
        }
    }

    /**
     * 获取配置值（代理 Storage）
     */
    public function getConfig(string $key, $default = null)
    {
        return $this->storage->getConfig($key, $default);
    }

    /**
     * 获取仪表盘数据
     */
    public function getDashboardData(): array
    {
        $queue = new Queue($this->storage, $this->config);
        $logger = new Logger($this->storage, true);

        return [
            'queue_length'     => $queue->getLength(),
            'processing_count' => $queue->getProcessingCount(),
            'queue_list'       => $queue->getQueueList(20),
            'stats'            => $logger->getStats(),
            'recent_logs'      => $logger->getRecentLogs(10),
            'active_visitors'  => $logger->getActiveVisitors(),
            'config'           => [
                'global_rate'    => $this->storage->getConfig('global_rate', $this->config['global_rate']),
                'global_burst'   => $this->storage->getConfig('global_burst', $this->config['global_burst']),
                'ip_rate'        => $this->storage->getConfig('ip_rate', $this->config['ip_rate']),
                'ip_burst'       => $this->storage->getConfig('ip_burst', $this->config['ip_burst']),
                'queue_max_size' => $this->storage->getConfig('queue_max_size', $this->config['queue_max_size']),
                'queue_timeout'  => $this->storage->getConfig('queue_timeout', $this->config['queue_timeout']),
            ],
        ];
    }

    /**
     * 保存配置
     */
    public function saveSettings(array $settings): void
    {
        $allowedKeys = [
            'global_rate', 'global_burst', 'ip_rate', 'ip_burst',
            'queue_max_size', 'queue_timeout', 'poll_interval',
            'queue_title', 'queue_message', 'queue_full', 'queue_timeout_msg',
            'admin_user', 'admin_pass',
        ];

        foreach ($settings as $key => $value) {
            if (in_array($key, $allowedKeys, true)) {
                $this->storage->setConfig($key, $value);
            }
        }
    }

    /**
     * 清空队列
     */
    public function clearQueue(): int
    {
        $queue = new Queue($this->storage, $this->config);
        return $queue->clear();
    }

    /**
     * 清理过期日志
     */
    public function cleanupLogs(int $days = 30): int
    {
        $logger = new Logger($this->storage, true);
        return $logger->cleanup($days);
    }

    /**
     * 获取黑白名单
     */
    public function getBlacklist(): array
    {
        $bl = new Blacklist($this->storage);
        return $bl->getAll();
    }

    /**
     * 添加黑名单/白名单
     */
    public function addBlacklist(string $type, string $value, string $action, string $reason = ''): void
    {
        $bl = new Blacklist($this->storage);
        if ($action === 'block') {
            $bl->addBlock($type, $value, $reason);
        } else {
            $bl->addAllow($type, $value, $reason);
        }
    }

    /**
     * 删除黑白名单规则
     */
    public function removeBlacklist(int $id): void
    {
        $bl = new Blacklist($this->storage);
        $bl->remove($id);
    }

    /**
     * 查询日志
     */
    public function queryLogs(?string $event = null, ?string $ip = null, ?string $startDate = null, ?string $endDate = null, int $page = 1, int $perPage = 50): array
    {
        $logger = new Logger($this->storage, true);
        $startTime = $startDate ? strtotime($startDate) : null;
        $endTime = $endDate ? strtotime($endDate . ' 23:59:59') : null;
        $offset = ($page - 1) * $perPage;

        return $logger->queryLogs($event, $ip, $startTime, $endTime, $perPage, $offset);
    }
}
