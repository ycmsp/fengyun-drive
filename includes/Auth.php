<?php
/**
 * 用户认证类
 */
class Auth {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * 用户注册
     */
    public function register($username, $email, $password, $nickname = '') {
        // 验证用户名
        if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
            return ['success' => false, 'message' => '用户名只能包含字母、数字和下划线，长度3-20位'];
        }

        // 验证邮箱
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => '邮箱格式不正确'];
        }

        // 验证密码
        if (strlen($password) < 6) {
            return ['success' => false, 'message' => '密码长度至少6位'];
        }

        // 检查用户名是否已存在
        $existing = $this->db->fetch("SELECT id FROM users WHERE username = ?", [$username]);
        if ($existing) {
            return ['success' => false, 'message' => '用户名已被注册'];
        }

        // 检查邮箱是否已存在
        $existing = $this->db->fetch("SELECT id FROM users WHERE email = ?", [$email]);
        if ($existing) {
            return ['success' => false, 'message' => '邮箱已被注册'];
        }

        // 密码哈希
        $passwordHash = password_hash($password, PASSWORD_BCRYPT);

        // 创建用户
        $userId = $this->db->insert(
            "INSERT INTO users (username, email, password, nickname, storage_limit) VALUES (?, ?, ?, ?, ?)",
            [$username, $email, $passwordHash, $nickname ?: $username, 10737418240]
        );

        if ($userId) {
            return ['success' => true, 'message' => '注册成功', 'user_id' => $userId];
        }

        return ['success' => false, 'message' => '注册失败，请稍后重试'];
    }

    /**
     * 用户登录
     */
    public function login($username, $password, $remember = false) {
        // 支持用户名或邮箱登录
        $user = $this->db->fetch(
            "SELECT * FROM users WHERE (username = ? OR email = ?) AND status = 1",
            [$username, $username]
        );

        if (!$user) {
            return ['success' => false, 'message' => '用户名或密码错误'];
        }

        if (!password_verify($password, $user['password'])) {
            return ['success' => false, 'message' => '用户名或密码错误'];
        }

        // 更新登录信息
        $this->db->update(
            "UPDATE users SET last_login_at = NOW(), last_login_ip = ? WHERE id = ?",
            [$_SERVER['REMOTE_ADDR'] ?? '', $user['id']]
        );

        // 设置会话
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['is_admin'] = $user['is_admin'];
        $_SESSION['login_time'] = time();

        // 记住我功能
        if ($remember) {
            $token = bin2hex(random_bytes(32));
            setcookie('remember_token', $token, time() + 30 * 24 * 3600, '/');
            // 这里可以将token存入数据库实现真正的记住我功能
        }

        return ['success' => true, 'message' => '登录成功', 'user' => $this->getUserInfo($user['id'])];
    }

    /**
     * 用户登出
     */
    public function logout() {
        session_destroy();
        setcookie('remember_token', '', time() - 3600, '/');
        return ['success' => true, 'message' => '已退出登录'];
    }

    /**
     * 检查是否已登录
     */
    public function check() {
        if (isset($_SESSION['user_id']) && isset($_SESSION['login_time'])) {
            // 检查会话是否过期
            if (time() - $_SESSION['login_time'] > SESSION_LIFETIME) {
                $this->logout();
                return false;
            }
            // 更新会话时间
            $_SESSION['login_time'] = time();
            return true;
        }
        return false;
    }

    /**
     * 获取当前用户ID
     */
    public function id() {
        return $_SESSION['user_id'] ?? null;
    }

    /**
     * 获取当前用户信息
     */
    public function user() {
        if ($this->check()) {
            return $this->getUserInfo($_SESSION['user_id']);
        }
        return null;
    }

    /**
     * 获取用户信息
     */
    public function getUserInfo($userId) {
        try {
            $user = $this->db->fetch(
                "SELECT id, username, email, nickname, avatar, storage_limit, storage_used, is_admin, directory_browsing, created_at 
                 FROM users WHERE id = ?",
                [$userId]
            );
            
            // 调试信息
            if ($user) {
                $value = isset($user['directory_browsing']) ? $user['directory_browsing'] : 'not set';
                error_log("Got directory_browsing for user $userId: $value");
            }
            
            return $user;
        } catch (Exception $e) {
            // 如果 directory_browsing 字段不存在，使用旧的查询
            $user = $this->db->fetch(
                "SELECT id, username, email, nickname, avatar, storage_limit, storage_used, is_admin, created_at 
                 FROM users WHERE id = ?",
                [$userId]
            );
            // 添加默认值
            if ($user) {
                $user['directory_browsing'] = 0;
                error_log("Got user without directory_browsing field, setting to 0");
            }
            return $user;
        }
    }

    /**
     * 修改密码
     */
    public function changePassword($userId, $oldPassword, $newPassword) {
        $user = $this->db->fetch("SELECT password FROM users WHERE id = ?", [$userId]);
        if (!$user) {
            return ['success' => false, 'message' => '用户不存在'];
        }

        if (!password_verify($oldPassword, $user['password'])) {
            return ['success' => false, 'message' => '原密码错误'];
        }

        if (strlen($newPassword) < 6) {
            return ['success' => false, 'message' => '新密码长度至少6位'];
        }

        $newHash = password_hash($newPassword, PASSWORD_BCRYPT);
        $this->db->update("UPDATE users SET password = ? WHERE id = ?", [$newHash, $userId]);

        return ['success' => true, 'message' => '密码修改成功'];
    }

    /**
     * 更新用户信息
     */
    public function updateProfile($userId, $data) {
        $allowedFields = ['nickname', 'email', 'avatar', 'directory_browsing'];
        $updates = [];
        $params = [];

        // 确保 directory_browsing 字段总是被处理
        if (isset($data['directory_browsing'])) {
            $updates[] = "directory_browsing = ?";
            $params[] = (int)($data['directory_browsing'] === '1' || $data['directory_browsing'] === true);
        }

        // 处理其他字段
        foreach ($data as $key => $value) {
            if (in_array($key, $allowedFields) && $key !== 'directory_browsing') {
                $updates[] = "$key = ?";
                $params[] = $value;
            }
        }

        if (empty($updates)) {
            return ['success' => false, 'message' => '没有要更新的字段'];
        }

        $params[] = $userId;
        $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?";
        
        try {
            $result = $this->db->update($sql, $params);
            // 检查更新结果
            if ($result === false) {
                return ['success' => false, 'message' => '更新失败'];
            }
            
            // 验证更新是否成功
            $updatedUser = $this->getUserInfo($userId);
            if ($updatedUser) {
                $newValue = isset($updatedUser['directory_browsing']) ? $updatedUser['directory_browsing'] : 'not set';
                error_log("Updated directory_browsing for user $userId to $newValue");
            }
            
        } catch (Exception $e) {
            error_log("Update profile error: " . $e->getMessage());
            return ['success' => false, 'message' => '更新失败: ' . $e->getMessage()];
        }

        return ['success' => true, 'message' => '资料更新成功'];
    }

    /**
     * 检查是否是管理员
     */
    public function isAdmin() {
        return isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1;
    }

    /**
     * 获取所有用户（管理员功能）
     */
    public function getAllUsers($page = 1, $limit = 20) {
        $offset = ($page - 1) * $limit;
        $users = $this->db->fetchAll(
            "SELECT id, username, email, nickname, storage_limit, storage_used, status, is_admin, created_at, last_login_at 
             FROM users ORDER BY id DESC LIMIT ? OFFSET ?",
            [$limit, $offset]
        );

        $total = $this->db->fetch("SELECT COUNT(*) as count FROM users")['count'];

        return [
            'users' => $users,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'pages' => ceil($total / $limit)
        ];
    }
}
