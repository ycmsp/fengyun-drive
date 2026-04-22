<?php
/**
 * 登录页面
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/Database.php';
require_once __DIR__ . '/includes/Auth.php';

$auth = new Auth();

// 如果已登录，跳转到网盘
if ($auth->check()) {
    header('Location: ' . APP_URL . '/drive.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);

    $result = $auth->login($username, $password, $remember);
    if ($result['success']) {
        header('Location: ' . APP_URL . '/drive.php');
        exit;
    } else {
        $error = $result['message'];
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>登录 - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="auth-page">
    <div class="auth-container">
        <div class="auth-box">
            <div class="auth-header">
                <h1><?php echo APP_NAME; ?></h1>
                <p>安全、便捷的个人云存储</p>
            </div>

            <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST" action="" class="auth-form">
                <div class="form-group">
                    <label for="username">用户名/邮箱</label>
                    <input type="text" id="username" name="username" required autofocus
                           placeholder="请输入用户名或邮箱">
                </div>

                <div class="form-group">
                    <label for="password">密码</label>
                    <input type="password" id="password" name="password" required
                           placeholder="请输入密码">
                </div>

                <div class="form-group form-checkbox">
                    <label>
                        <input type="checkbox" name="remember">
                        <span>记住我</span>
                    </label>
                </div>

                <button type="submit" class="btn btn-primary btn-block">登录</button>
            </form>

            <div class="auth-footer">
                <p>还没有账号？<a href="register.php">立即注册</a></p>
            </div>
        </div>
    </div>
</body>
</html>
