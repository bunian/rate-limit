<?php
/**
 * 游客限速排队系统 - 介入入口
 *
 * 使用方法：在需要限速的 PHP 文件顶部加入：
 *   require_once __DIR__ . '/rate-limit/rate_limit.php';
 *
 * 可选常量（在 require 之前定义）：
 *   define('RL_ACTION', 'submit_form');  // 接口标识，不同接口独立限速
 *   define('RL_JSON_RESPONSE', true);    // API场景返回JSON而非HTML排队页
 */

if (!defined('RL_ACTION')) {
    define('RL_ACTION', 'default');
}

// 加载配置
$rlConfig = require __DIR__ . '/config.php';

// 注册自动加载
require_once __DIR__ . '/src/Autoloader.php';
RateLimit\Autoloader::register();

// 启动调度
$rlGuard = new RateLimit\Guard($rlConfig, RL_ACTION);
$rlGuard->handle();

// 如果执行到这里，说明请求已放行，继续执行业务代码
