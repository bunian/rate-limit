<?php
/**
 * 游客限速排队系统 - 默认配置
 * 管理后台可动态覆盖以下配置（存储在 SQLite config 表中）
 */

return [
    // ===== 限速参数 =====
    'global_rate'       => 10,   // 全局每秒请求数（所有游客总和）
    'global_burst'      => 20,   // 全局令牌桶容量（允许突发数）
    'ip_rate'           => 2,    // 单IP每秒请求数
    'ip_burst'          => 5,    // 单IP令牌桶容量

    // ===== 排队参数 =====
    'queue_max_size'    => 100,  // 队列最大长度，超出返回系统繁忙
    'queue_timeout'     => 30,   // 排队超时时间（秒）
    'poll_interval'     => 1,    // 前端轮询间隔（秒）

    // ===== 排队页面文案 =====
    'queue_title'       => '系统繁忙',
    'queue_message'     => '当前访问人数较多，请耐心等待...',
    'queue_position'    => '当前排队第 {position} 位',
    'queue_eta'         => '预计等待约 {eta} 秒',
    'queue_full'        => '系统繁忙，请稍后再试',
    'queue_timeout_msg' => '排队超时，请重新提交',

    // ===== 响应格式 =====
    // true: API场景返回JSON; false: 网页场景返回HTML排队页
    'json_response'     => false,

    // ===== 后台认证 =====
    'admin_user'        => 'admin',
    'admin_pass'        => 'admin123', // 首次登录后建议修改

    // ===== 存储 =====
    'db_path'           => __DIR__ . '/data/rate_limit.db',

    // ===== 日志 =====
    'log_enabled'       => true,
    'log_retention_days'=> 30,
];
