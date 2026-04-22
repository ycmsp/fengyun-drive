<?php
/**
 * 用户认证API
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/functions.php';

$auth = new Auth();
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'register':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            error('请求方式错误', 405);
        }

        $data = json_decode(file_get_contents('php://input'), true);
        $username = $data['username'] ?? '';
        $email = $data['email'] ?? '';
        $password = $data['password'] ?? '';
        $nickname = $data['nickname'] ?? '';

        $result = $auth->register($username, $email, $password, $nickname);
        if ($result['success']) {
            success(['user_id' => $result['user_id']], $result['message']);
        } else {
            error($result['message']);
        }
        break;

    case 'login':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            error('请求方式错误', 405);
        }

        $data = json_decode(file_get_contents('php://input'), true);
        $username = $data['username'] ?? '';
        $password = $data['password'] ?? '';
        $remember = $data['remember'] ?? false;

        $result = $auth->login($username, $password, $remember);
        if ($result['success']) {
            success($result['user'], $result['message']);
        } else {
            error($result['message'], 401);
        }
        break;

    case 'logout':
        $result = $auth->logout();
        success(null, $result['message']);
        break;

    case 'check':
        if ($auth->check()) {
            success($auth->user(), '已登录');
        } else {
            error('未登录', 401);
        }
        break;

    case 'profile':
        if (!$auth->check()) {
            error('请先登录', 401);
        }

        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            success($auth->user());
        } elseif ($_SERVER['REQUEST_METHOD'] === 'PUT') {
            $data = json_decode(file_get_contents('php://input'), true);
            $result = $auth->updateProfile($auth->id(), $data);
            if ($result['success']) {
                success(null, $result['message']);
            } else {
                error($result['message']);
            }
        }
        break;

    case 'password':
        if (!$auth->check()) {
            error('请先登录', 401);
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
            error('请求方式错误', 405);
        }

        $data = json_decode(file_get_contents('php://input'), true);
        $oldPassword = $data['old_password'] ?? '';
        $newPassword = $data['new_password'] ?? '';

        $result = $auth->changePassword($auth->id(), $oldPassword, $newPassword);
        if ($result['success']) {
            success(null, $result['message']);
        } else {
            error($result['message']);
        }
        break;

    default:
        error('未知操作', 404);
}
