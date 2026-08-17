<?php
/**
 * 后台登录页
 */
require_once __DIR__ . '/../src/Autoloader.php';
RateLimit\Autoloader::register();

$config = require __DIR__ . '/../config.php';
$admin = new RateLimit\Admin($config);

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    if ($admin->login($username, $password)) {
        header('Location: index.php');
        exit;
    }
    $error = '用户名或密码错误';
}

// 已登录直接跳转
if ($admin->isLoggedIn()) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>登录 - 限速排队管理后台</title>
    <link rel="stylesheet" href="../assets/admin.css">
</head>
<body class="login-body">
    <div class="login-container">
        <div class="login-header">
            <div class="login-logo">RL</div>
            <h1>限速排队管理后台</h1>
            <p>Rate Limit & Queue Admin</p>
        </div>
        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="POST" class="login-form">
            <div class="form-group">
                <label>用户名</label>
                <input type="text" name="username" required autofocus placeholder="请输入用户名">
            </div>
            <div class="form-group">
                <label>密码</label>
                <input type="password" name="password" required placeholder="请输入密码">
            </div>
            <button type="submit" class="btn btn-primary btn-block">登 录</button>
        </form>
        <div class="login-footer">
            <p>默认账号: admin / admin123</p>
            <p>登录后请及时修改密码</p>
        </div>
    </div>
</body>
</html>
