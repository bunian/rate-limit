<?php
/**
 * 管理后台入口 - 路由分发
 */
require_once __DIR__ . '/../src/Autoloader.php';
RateLimit\Autoloader::register();

$config = require __DIR__ . '/../config.php';
$admin = new RateLimit\Admin($config);

// 处理登出
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    $admin->logout();
    header('Location: login.php');
    exit;
}

// 处理 AJAX 请求
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
    header('Content-Type: application/json; charset=utf-8');

    if (!$admin->isLoggedIn()) {
        echo json_encode(['error' => 'unauthorized'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $action = $_GET['api'] ?? $_POST['api'] ?? '';

    switch ($action) {
        case 'dashboard':
            echo json_encode($admin->getDashboardData(), JSON_UNESCAPED_UNICODE);
            break;

        case 'save_settings':
            $admin->saveSettings($_POST);
            echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
            break;

        case 'clear_queue':
            $count = $admin->clearQueue();
            echo json_encode(['success' => true, 'cleared' => $count], JSON_UNESCAPED_UNICODE);
            break;

        case 'cleanup_logs':
            $days = (int)($_POST['days'] ?? 30);
            $count = $admin->cleanupLogs($days);
            echo json_encode(['success' => true, 'cleaned' => $count], JSON_UNESCAPED_UNICODE);
            break;

        case 'add_blacklist':
            $type = $_POST['type'] ?? 'ip';
            $value = trim($_POST['value'] ?? '');
            $actionType = $_POST['action_type'] ?? 'block';
            $reason = $_POST['reason'] ?? '';
            if ($value) {
                $admin->addBlacklist($type, $value, $actionType, $reason);
                echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode(['success' => false, 'error' => '值不能为空'], JSON_UNESCAPED_UNICODE);
            }
            break;

        case 'remove_blacklist':
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                $admin->removeBlacklist($id);
                echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
            }
            break;

        default:
            echo json_encode(['error' => 'unknown api'], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// 页面路由
$admin->requireLogin();
$page = $_GET['page'] ?? 'dashboard';

$pages = ['dashboard', 'settings', 'blacklist', 'logs'];
if (!in_array($page, $pages, true)) {
    $page = 'dashboard';
}

// 包含对应页面
include __DIR__ . '/' . $page . '.php';
