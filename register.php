<?php
/**
 * 注册页面
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
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $passwordConfirm = $_POST['password_confirm'] ?? '';
    $nickname = $_POST['nickname'] ?? '';

    if ($password !== $passwordConfirm) {
        $error = '两次输入的密码不一致';
    } else {
        $result = $auth->register($username, $email, $password, $nickname);
        if ($result['success']) {
            $success = '注册成功！即将跳转到登录页面...';
            header('Refresh: 2; URL=' . APP_URL . '/login.php');
        } else {
            $error = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>注册 - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="auth-page">
    <div class="auth-container">
        <div class="auth-box">
            <div class="auth-header">
                <h1><?php echo APP_NAME; ?></h1>
                <p>创建您的个人账号</p>
            </div>

            <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <form method="POST" action="" class="auth-form">
                <div class="form-group">
                    <label for="username">用户名 *</label>
                    <input type="text" id="username" name="username" required
                           placeholder="3-20位字母、数字、下划线"
                           pattern="[a-zA-Z0-9_]{3,20}">
                </div>

                <div class="form-group">
                    <label for="email">邮箱 *</label>
                    <input type="email" id="email" name="email" required
                           placeholder="请输入有效邮箱">
                </div>

                <div class="form-group">
                    <label for="nickname">昵称</label>
                    <input type="text" id="nickname" name="nickname"
                           placeholder="可选，用于显示">
                </div>

                <div class="form-group">
                    <label for="password">密码 *</label>
                    <input type="password" id="password" name="password" required
                           placeholder="至少6位字符" minlength="6">
                </div>

                <div class="form-group">
                    <label for="password_confirm">确认密码 *</label>
                    <input type="password" id="password_confirm" name="password_confirm" required
                           placeholder="再次输入密码" minlength="6">
                </div>

                <button type="submit" class="btn btn-primary btn-block">注册</button>
            </form>

            <div class="auth-footer">
                <p>已有账号？<a href="login.php">立即登录</a></p>
            </div>
        </div>
    </div>
</body>
</html>
