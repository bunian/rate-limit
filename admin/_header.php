<?php
/**
 * 后台公共头部
 */
if (!defined('IN_ADMIN')) define('IN_ADMIN', true);
$currentPage = $page ?? 'dashboard';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? '仪表盘' ?> - 限速排队管理后台</title>
    <link rel="stylesheet" href="../assets/admin.css">
</head>
<body>
<div class="admin-layout">
    <aside class="sidebar">
        <div class="sidebar-header">
            <div class="logo">RL</div>
            <span class="logo-text">限速管理</span>
        </div>
        <nav class="nav-menu">
            <a href="index.php?page=dashboard" class="nav-item <?= $currentPage === 'dashboard' ? 'active' : '' ?>">
                <span class="nav-icon">📊</span> 仪表盘
            </a>
            <a href="index.php?page=settings" class="nav-item <?= $currentPage === 'settings' ? 'active' : '' ?>">
                <span class="nav-icon">⚙️</span> 参数配置
            </a>
            <a href="index.php?page=blacklist" class="nav-item <?= $currentPage === 'blacklist' ? 'active' : '' ?>">
                <span class="nav-icon">🚫</span> 黑白名单
            </a>
            <a href="index.php?page=logs" class="nav-item <?= $currentPage === 'logs' ? 'active' : '' ?>">
                <span class="nav-icon">📋</span> 日志查询
            </a>
        </nav>
        <div class="sidebar-footer">
            <a href="index.php?action=logout" class="nav-item logout">
                <span class="nav-icon">🚪</span> 退出登录
            </a>
        </div>
    </aside>
    <main class="main-content">
        <header class="topbar">
            <h1 class="page-title"><?= $pageTitle ?? '仪表盘' ?></h1>
            <div class="topbar-actions">
                <span class="status-badge" id="liveStatus">● 实时</span>
            </div>
        </header>
        <div class="content-wrapper">
