<?php

namespace RateLimit;

/**
 * 总调度器 - 限速检查 → 排队 → 放行/拒绝
 * 业务方只需 require rate_limit.php，内部自动调用 Guard::handle()
 */
class Guard
{
    private Storage $storage;
    private RateLimiter $rateLimiter;
    private Queue $queue;
    private Logger $logger;
    private Blacklist $blacklist;
    private Visitor $visitor;
    private array $config;
    private string $action;

    public function __construct(array $config, string $action = 'default')
    {
        $this->config = $config;
        $this->action = $action;
        $this->storage = Storage::getInstance($config['db_path']);
        $this->rateLimiter = new RateLimiter($this->storage, $config);
        $this->queue = new Queue($this->storage, $config);
        $this->logger = new Logger($this->storage, $config['log_enabled'] ?? true);
        $this->blacklist = new Blacklist($this->storage);
        $this->visitor = new Visitor();
    }

    /**
     * 主处理入口
     */
    public function handle(): void
    {
        $fingerprint = $this->visitor->getFingerprint();
        $ip = $this->visitor->getIp();

        // 1. 处理轮询请求
        if (!empty($_GET['rl_poll'])) {
            $this->handlePoll($_GET['rl_poll']);
            return;
        }

        // 2. 处理带放行令牌的请求
        if (!empty($_GET['rl_request_id'])) {
            if ($this->queue->redeem($_GET['rl_request_id'])) {
                $this->logger->log($fingerprint, $ip, $this->action, 'redeemed', 'request_id=' . $_GET['rl_request_id']);
                // 放行，继续执行业务代码
                return;
            }
        }

        // 3. 黑名单检查
        if ($this->blacklist->isBlocked($ip, $fingerprint)) {
            $this->logger->log($fingerprint, $ip, $this->action, 'rejected', 'blacklisted');
            $this->outputBlocked();
            exit;
        }

        // 4. 白名单直接放行
        if ($this->blacklist->isWhitelisted($ip, $fingerprint)) {
            $this->logger->log($fingerprint, $ip, $this->action, 'allowed', 'whitelisted');
            return;
        }

        // 5. 限速检查
        if ($this->rateLimiter->allow($fingerprint, $ip, $this->action)) {
            // 放行，同时推动队列前进（释放一个排队位置）
            $this->queue->advanceQueue(1);
            $this->logger->log($fingerprint, $ip, $this->action, 'allowed');
            return;
        }

        // 6. 限速不通过，进入排队
        $this->logger->log($fingerprint, $ip, $this->action, 'rate_limited');
        $queueInfo = $this->queue->enqueue($fingerprint, $this->action);

        if ($queueInfo === null) {
            // 队列满
            $this->logger->log($fingerprint, $ip, $this->action, 'rejected', 'queue_full');
            $this->outputQueueFull();
            exit;
        }

        $this->logger->log($fingerprint, $ip, $this->action, 'queued', 'position=' . $queueInfo['position']);
        $this->outputQueuePage($queueInfo);
        exit;
    }

    /**
     * 处理轮询
     */
    private function handlePoll(string $requestId): void
    {
        $result = $this->queue->poll($requestId);

        if ($result === null) {
            $this->jsonResponse([
                'ready'   => false,
                'status'  => 'not_found',
                'message' => '请求不存在或已过期',
            ], 404);
            return;
        }

        if ($result['status'] === 'timeout') {
            $this->jsonResponse($result, 408);
            return;
        }

        $this->jsonResponse($result);
    }

    /**
     * 输出排队页面
     */
    private function outputQueuePage(array $queueInfo): void
    {
        $jsonResponse = $this->config['json_response'] ?? false;
        if (defined('RL_JSON_RESPONSE') && RL_JSON_RESPONSE) {
            $jsonResponse = true;
        }

        if ($jsonResponse) {
            $this->jsonResponse([
                'code'          => 429,
                'message'       => $this->config['queue_message'],
                'request_id'    => $queueInfo['request_id'],
                'position'      => $queueInfo['position'],
                'wait_estimate' => $queueInfo['wait_estimate'],
            ], 429);
            return;
        }

        // HTML 排队页
        $title = htmlspecialchars($this->config['queue_title']);
        $message = htmlspecialchars($this->config['queue_message']);
        $position = (int)$queueInfo['position'];
        $eta = (int)$queueInfo['wait_estimate'];
        $requestId = htmlspecialchars($queueInfo['request_id']);
        $pollInterval = (int)($this->config['poll_interval'] ?? 1) * 1000;
        $currentUrl = $this->getCurrentUrl();

        header('HTTP/1.1 429 Too Many Requests');
        header('Content-Type: text/html; charset=utf-8');
        header('Retry-After: ' . max($eta, 1));

        echo <<<HTML
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$title}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: #f5f7fa;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            color: #333;
        }
        .queue-box {
            background: #fff;
            border-radius: 12px;
            padding: 40px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            text-align: center;
            max-width: 420px;
            width: 90%;
        }
        .spinner {
            width: 48px;
            height: 48px;
            border: 4px solid #e0e0e0;
            border-top-color: #4a90d9;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 24px;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        h1 { font-size: 22px; margin-bottom: 12px; color: #222; }
        .msg { color: #666; margin-bottom: 24px; font-size: 14px; line-height: 1.6; }
        .info {
            background: #f0f7ff;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 20px;
        }
        .info-row { display: flex; justify-content: space-between; margin: 6px 0; font-size: 14px; }
        .info-label { color: #888; }
        .info-value { color: #4a90d9; font-weight: 600; }
        .progress-bar {
            width: 100%;
            height: 6px;
            background: #e0e0e0;
            border-radius: 3px;
            overflow: hidden;
            margin-top: 12px;
        }
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #4a90d9, #67b8f5);
            border-radius: 3px;
            transition: width 0.5s ease;
            width: 10%;
        }
        .tip { font-size: 12px; color: #aaa; margin-top: 16px; }
    </style>
</head>
<body>
    <div class="queue-box">
        <div class="spinner"></div>
        <h1>{$title}</h1>
        <p class="msg">{$message}</p>
        <div class="info">
            <div class="info-row">
                <span class="info-label">当前排队</span>
                <span class="info-value">第 <span id="pos">{$position}</span> 位</span>
            </div>
            <div class="info-row">
                <span class="info-label">预计等待</span>
                <span class="info-value"><span id="eta">{$eta}</span> 秒</span>
            </div>
            <div class="progress-bar">
                <div class="progress-fill" id="progress"></div>
            </div>
        </div>
        <p class="tip">页面将自动继续，请勿关闭...</p>
    </div>
    <script>
    (function() {
        var requestId = '{$requestId}';
        var currentUrl = '{$currentUrl}';
        var pollInterval = {$pollInterval};
        var maxPosition = {$position};

        function updateProgress(pos) {
            var pct = Math.max(5, Math.min(100, ((maxPosition - pos + 1) / maxPosition) * 100));
            document.getElementById('progress').style.width = pct + '%';
        }
        updateProgress({$position});

        function poll() {
            var url = currentUrl + (currentUrl.indexOf('?') >= 0 ? '&' : '?') + 'rl_poll=' + requestId;
            fetch(url, { credentials: 'same-origin' })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.ready) {
                        // 排到了，带上 request_id 重新请求
                        var sep = currentUrl.indexOf('?') >= 0 ? '&' : '?';
                        window.location.href = currentUrl + sep + 'rl_request_id=' + requestId;
                        return;
                    }
                    if (data.status === 'timeout' || data.status === 'not_found') {
                        document.querySelector('.msg').textContent = data.message || '排队超时，请刷新重试';
                        document.querySelector('.spinner').style.display = 'none';
                        return;
                    }
                    if (data.position !== undefined) {
                        document.getElementById('pos').textContent = data.position;
                        updateProgress(data.position);
                    }
                    if (data.wait_estimate !== undefined) {
                        document.getElementById('eta').textContent = data.wait_estimate;
                    }
                    setTimeout(poll, pollInterval);
                })
                .catch(function() {
                    setTimeout(poll, pollInterval * 2);
                });
        }
        setTimeout(poll, pollInterval);
    })();
    </script>
</body>
</html>
HTML;
    }

    /**
     * 输出队列满
     */
    private function outputQueueFull(): void
    {
        $jsonResponse = $this->config['json_response'] ?? false;
        if (defined('RL_JSON_RESPONSE') && RL_JSON_RESPONSE) {
            $jsonResponse = true;
        }

        $message = $this->config['queue_full'];

        if ($jsonResponse) {
            $this->jsonResponse([
                'code'    => 503,
                'message' => $message,
            ], 503);
            return;
        }

        header('HTTP/1.1 503 Service Unavailable');
        header('Content-Type: text/html; charset=utf-8');
        header('Retry-After: 10');
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>系统繁忙</title>';
        echo '<style>body{font-family:sans-serif;display:flex;justify-content:center;align-items:center;min-height:100vh;margin:0;background:#f5f7fa;}';
        echo '.box{background:#fff;padding:40px;border-radius:12px;text-align:center;box-shadow:0 4px 20px rgba(0,0,0,0.08);}';
        echo 'h1{color:#e74c3c;margin:0 0 12px;}p{color:#666;}</style></head><body>';
        echo '<div class="box"><h1>系统繁忙</h1><p>' . htmlspecialchars($message) . '</p>';
        echo '<p style="margin-top:16px;font-size:13px;color:#aaa;">请 10 秒后刷新重试</p></div></body></html>';
    }

    /**
     * 输出被封禁
     */
    private function outputBlocked(): void
    {
        header('HTTP/1.1 403 Forbidden');
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>访问被拒绝</title>';
        echo '<style>body{font-family:sans-serif;display:flex;justify-content:center;align-items:center;min-height:100vh;margin:0;background:#f5f7fa;}';
        echo '.box{background:#fff;padding:40px;border-radius:12px;text-align:center;box-shadow:0 4px 20px rgba(0,0,0,0.08);}';
        echo 'h1{color:#e74c3c;margin:0 0 12px;}p{color:#666;}</style></head><body>';
        echo '<div class="box"><h1>访问被拒绝</h1><p>您的 IP 已被限制访问</p>';
        echo '<p style="margin-top:16px;font-size:13px;color:#aaa;">如有疑问请联系管理员</p></div></body></html>';
    }

    /**
     * 输出 JSON
     */
    private function jsonResponse(array $data, int $code = 200): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
    }

    /**
     * 获取当前 URL（不含 rl_ 参数）
     */
    private function getCurrentUrl(): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';

        // 移除 rl_ 开头的查询参数
        $parts = parse_url($uri);
        $path = $parts['path'] ?? '/';
        $query = $parts['query'] ?? '';

        if ($query) {
            parse_str($query, $params);
            foreach ($params as $key => $val) {
                if (strpos($key, 'rl_') === 0) {
                    unset($params[$key]);
                }
            }
            $query = http_build_query($params);
        }

        return $scheme . '://' . $host . $path . ($query ? '?' . $query : '');
    }
}
