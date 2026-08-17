<?php

namespace RateLimit;

/**
 * 访客识别 - 基于 IP + User-Agent + Cookie 生成唯一指纹
 */
class Visitor
{
    private string $fingerprint;
    private string $ip;
    private string $userAgent;

    public function __construct()
    {
        $this->ip = $this->detectIp();
        $this->userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

        // 使用 Cookie 持久化指纹，避免同一访客 IP 变化导致误判
        if (!empty($_COOKIE['rl_visitor_id'])) {
            $this->fingerprint = $_COOKIE['rl_visitor_id'];
        } else {
            $this->fingerprint = $this->generateFingerprint();
            // 设置长期 Cookie（1年）
            setcookie('rl_visitor_id', $this->fingerprint, time() + 31536000, '/', '', false, true);
        }
    }

    /**
     * 获取访客唯一指纹
     */
    public function getFingerprint(): string
    {
        return $this->fingerprint;
    }

    /**
     * 获取访客 IP
     */
    public function getIp(): string
    {
        return $this->ip;
    }

    /**
     * 获取 User-Agent
     */
    public function getUserAgent(): string
    {
        return $this->userAgent;
    }

    /**
     * 生成指纹（IP + UA + 随机盐 的哈希）
     */
    private function generateFingerprint(): string
    {
        $salt = bin2hex(random_bytes(8));
        return hash('sha256', $this->ip . '|' . $this->userAgent . '|' . $salt);
    }

    /**
     * 检测真实 IP（兼容反向代理）
     */
    private function detectIp(): string
    {
        $headers = [
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'HTTP_CLIENT_IP',
            'REMOTE_ADDR',
        ];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = trim(explode(',', $_SERVER[$header])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return '0.0.0.0';
    }
}
