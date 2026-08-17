<?php
/**
 * 测试示例 - 模拟一个需要限速的提交接口
 * 访问此文件测试限速和排队功能
 */

// 定义接口标识（可选）
define('RL_ACTION', 'test_submit');

// 介入限速（只需这一行）
require_once __DIR__ . '/rate_limit.php';

// 如果执行到这里，说明请求已通过限速/排队
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>限速测试 - 请求已通过</title>
    <style>
        body { font-family: sans-serif; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; background: #f0f2f5; }
        .box { background: #fff; padding: 40px; border-radius: 12px; text-align: center; box-shadow: 0 4px 20px rgba(0,0,0,0.08); max-width: 400px; }
        h1 { color: #16a34a; margin: 0 0 12px; }
        p { color: #666; margin: 8px 0; }
        .info { background: #f8fafc; padding: 16px; border-radius: 8px; margin: 20px 0; text-align: left; font-size: 13px; }
        .info div { margin: 4px 0; }
        .btn { display: inline-block; padding: 10px 24px; background: #667eea; color: #fff; text-decoration: none; border-radius: 8px; margin-top: 12px; }
        .btn:hover { background: #5568d3; }
    </style>
</head>
<body>
    <div class="box">
        <h1>✅ 请求已通过</h1>
        <p>你的请求已通过限速检查并被处理。</p>
        <div class="info">
            <div><strong>时间：</strong><?= date('Y-m-d H:i:s') ?></div>
            <div><strong>IP：</strong><?= htmlspecialchars($_SERVER['REMOTE_ADDR'] ?? 'unknown') ?></div>
            <div><strong>接口：</strong>test_submit</div>
            <div><strong>请求ID：</strong><?= htmlspecialchars($_GET['rl_request_id'] ?? '（直接放行，无排队）') ?></div>
        </div>
        <p>快速刷新页面可触发限速和排队效果。</p>
        <a href="admin/" class="btn">进入管理后台</a>
    </div>
</body>
</html>
