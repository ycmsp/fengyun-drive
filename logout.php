<?php
/**
 * 退出登录
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/Database.php';
require_once __DIR__ . '/includes/Auth.php';

$auth = new Auth();
$auth->logout();

header('Location: ' . APP_URL . '/login.php');
exit;
