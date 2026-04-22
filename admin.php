<?php
/**
 * 管理员后台
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/Database.php';
require_once __DIR__ . '/includes/Settings.php';
require_once __DIR__ . '/includes/Auth.php';
require_once __DIR__ . '/includes/functions.php';

// 初始化设置
$settings = new Settings();

$auth = new Auth();

// 检查登录状态和管理员权限
if (!$auth->check()) {
    header('Location: ' . APP_URL . '/login.php');
    exit;
}

if (!$auth->isAdmin()) {
    header('Location: ' . APP_URL . '/drive.php');
    exit;
}

$db = Database::getInstance();

// 获取统计数据
$stats = [
    'total_users' => $db->fetch("SELECT COUNT(*) as count FROM users")['count'],
    'total_files' => $db->fetch("SELECT COUNT(*) as count FROM files WHERE status = 1")['count'],
    'total_shares' => $db->fetch("SELECT COUNT(*) as count FROM shares WHERE status = 1")['count'],
    'total_storage' => $db->fetch("SELECT SUM(storage_used) as total FROM users")['total'] ?? 0
];

// 获取用户列表
$page = intval($_GET['user_page'] ?? 1);
$limit = 20;
$offset = ($page - 1) * $limit;

$users = $db->fetchAll(
    "SELECT id, username, email, nickname, storage_limit, storage_used, status, is_admin, created_at, last_login_at 
     FROM users ORDER BY id DESC LIMIT ? OFFSET ?",
    [$limit, $offset]
);

$totalUsers = $db->fetch("SELECT COUNT(*) as count FROM users")['count'];
$totalPages = ceil($totalUsers / $limit);

$user = $auth->user();

// 处理 AJAX 请求
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    
    switch ($action) {
        case 'getUser':
            // 获取用户信息
            $userId = intval($_GET['userId'] ?? 0);
            if ($userId) {
                $userInfo = $db->fetch("SELECT * FROM users WHERE id = ?", [$userId]);
                if ($userInfo) {
                    echo json_encode(['success' => true, 'data' => $userInfo]);
                } else {
                    echo json_encode(['success' => false, 'message' => '用户不存在']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => '参数错误']);
            }
            exit;
            
        case 'toggleStatus':
            // 切换用户状态
            $userId = intval($_GET['userId'] ?? 0);
            $status = intval($_GET['status'] ?? 0);
            if ($userId && $userId != $user['id']) { // 不能禁用自己
                $db->update("UPDATE users SET status = ? WHERE id = ?", [$status, $userId]);
                echo json_encode(['success' => true, 'message' => '状态更新成功']);
            } else {
                echo json_encode(['success' => false, 'message' => '参数错误或不能操作自己']);
            }
            exit;
            
        case 'toggleAdmin':
            // 切换管理员状态
            $userId = intval($_GET['userId'] ?? 0);
            $isAdmin = intval($_GET['isAdmin'] ?? 0);
            if ($userId && $userId != $user['id']) { // 不能修改自己的管理员权限
                $db->update("UPDATE users SET is_admin = ? WHERE id = ?", [$isAdmin, $userId]);
                echo json_encode(['success' => true, 'message' => '权限更新成功']);
            } else {
                echo json_encode(['success' => false, 'message' => '参数错误或不能操作自己']);
            }
            exit;
            
        case 'updateUser':
            // 更新用户信息
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $data = json_decode(file_get_contents('php://input'), true);
                $userId = intval($data['userId'] ?? 0);
                $email = $data['email'] ?? '';
                $nickname = $data['nickname'] ?? '';
                $storageLimit = intval($data['storage_limit'] ?? 0);
                $directoryBrowsing = intval($data['directory_browsing'] ?? 0);
                
                if ($userId) {
                    $db->update(
                        "UPDATE users SET email = ?, nickname = ?, storage_limit = ?, directory_browsing = ? WHERE id = ?",
                        [$email, $nickname, $storageLimit, $directoryBrowsing, $userId]
                    );
                    echo json_encode(['success' => true, 'message' => '用户信息更新成功']);
                } else {
                    echo json_encode(['success' => false, 'message' => '参数错误']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => '请求方法错误']);
            }
            exit;
            
        case 'deleteUser':
            // 删除用户
            $userId = intval($_GET['userId'] ?? 0);
            if ($userId && $userId != $user['id']) { // 不能删除自己
                // 开始事务
                $db->beginTransaction();
                
                try {
                    // 删除用户的文件
                    $db->delete("DELETE FROM files WHERE user_id = ?", [$userId]);
                    // 删除用户的分享
                    $db->delete("DELETE FROM shares WHERE user_id = ?", [$userId]);
                    // 检查 share_direct_links 表是否存在
                    $tableExists = $db->fetch("SHOW TABLES LIKE 'share_direct_links'");
                    if ($tableExists) {
                        // 删除用户的直链
                        $db->delete("DELETE FROM share_direct_links WHERE share_code IN (SELECT share_code FROM shares WHERE user_id = ?)", [$userId]);
                    }
                    // 删除用户
                    $db->delete("DELETE FROM users WHERE id = ?", [$userId]);
                    
                    // 提交事务
                    $db->commit();
                    echo json_encode(['success' => true, 'message' => '用户删除成功']);
                } catch (Exception $e) {
                    // 回滚事务
                    $db->rollback();
                    echo json_encode(['success' => false, 'message' => '删除失败: ' . $e->getMessage()]);
                }
            } else {
                echo json_encode(['success' => false, 'message' => '参数错误或不能删除自己']);
            }
            exit;
            
        case 'deleteFile':
            // 删除文件
            $fileId = intval($_GET['fileId'] ?? 0);
            if ($fileId) {
                // 开始事务
                $db->beginTransaction();
                
                try {
                    // 获取文件信息
                    $file = $db->fetch("SELECT file_path, user_id, file_size FROM files WHERE id = ?", [$fileId]);
                    if ($file) {
                        $fileSize = $file['file_size'];
                        $userId = $file['user_id'];
                        // 删除文件的分享
                        $db->delete("DELETE FROM shares WHERE file_id = ?", [$fileId]);
                        // 检查 share_direct_links 表是否存在
                        $tableExists = $db->fetch("SHOW TABLES LIKE 'share_direct_links'");
                        if ($tableExists) {
                            // 删除文件的直链
                            $db->delete("DELETE FROM share_direct_links WHERE share_code IN (SELECT share_code FROM shares WHERE file_id = ?)", [$fileId]);
                        }
                        // 删除文件记录
                        $db->delete("DELETE FROM files WHERE id = ?", [$fileId]);
                        // 更新用户存储空间
                        $db->update("UPDATE users SET storage_used = GREATEST(0, storage_used - ?) WHERE id = ?", [$fileSize, $userId]);
                        // 删除物理文件
                        $filePath = UPLOAD_PATH . '/' . $file['file_path'];
                        if (file_exists($filePath)) {
                            unlink($filePath);
                        }
                        
                        // 提交事务
                        $db->commit();
                        echo json_encode(['success' => true, 'message' => '文件删除成功']);
                    } else {
                        echo json_encode(['success' => false, 'message' => '文件不存在']);
                    }
                } catch (Exception $e) {
                    // 回滚事务
                    $db->rollback();
                    echo json_encode(['success' => false, 'message' => '删除失败: ' . $e->getMessage()]);
                }
            } else {
                echo json_encode(['success' => false, 'message' => '参数错误']);
            }
            exit;
            
        case 'saveSettings':
            // 保存设置
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $data = json_decode(file_get_contents('php://input'), true);
                require_once __DIR__ . '/includes/Settings.php';
                $settings = new Settings();
                
                foreach ($data as $name => $value) {
                    $settings->set($name, $value);
                }
                
                echo json_encode(['success' => true, 'message' => '设置保存成功']);
            } else {
                echo json_encode(['success' => false, 'message' => '请求方法错误']);
            }
            exit;
            
        case 'generateCache':
            // 生成文件缓存
            function scanPHPFiles($directory) {
                $phpFiles = [];
                $files = scandir($directory);
                
                foreach ($files as $file) {
                    if ($file === '.' || $file === '..') {
                        continue;
                    }
                    
                    $path = $directory . '/' . $file;
                    if (is_dir($path)) {
                        $phpFiles = array_merge($phpFiles, scanPHPFiles($path));
                    } elseif (pathinfo($path, PATHINFO_EXTENSION) === 'php') {
                        $phpFiles[] = $path;
                    }
                }
                
                return $phpFiles;
            }
            
            $phpFiles = scanPHPFiles(__DIR__);
            $count = 0;
            
            foreach ($phpFiles as $file) {
                // 尝试加载文件以生成缓存
                @include_once $file;
                $count++;
            }
            
            echo json_encode(['success' => true, 'message' => "成功生成 {$count} 个文件的缓存"]);
            exit;
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理后台 - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="/assets/css/all.min.css">
    <style>
        .admin-container {
            display: flex;
            min-height: 100vh;
        }
        .admin-sidebar {
            width: 220px;
            background: #2c3e50;
            color: white;
            position: fixed;
            height: 100vh;
        }
        .admin-sidebar-header {
            padding: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .admin-sidebar-header h1 {
            font-size: 18px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .admin-nav {
            padding: 20px 0;
        }
        .admin-nav-item {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            transition: all 0.3s;
            gap: 10px;
        }
        .admin-nav-item:hover,
        .admin-nav-item.active {
            background: rgba(255,255,255,0.1);
            color: white;
        }
        .admin-main {
            flex: 1;
            margin-left: 220px;
            padding: 20px;
            background: #f5f6f7;
            min-height: 100vh;
        }
        .admin-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #e0e0e0;
        }
        .admin-header h2 {
            font-size: 24px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .stat-card h3 {
            font-size: 14px;
            color: #666;
            margin-bottom: 10px;
        }
        .stat-card .number {
            font-size: 32px;
            font-weight: bold;
            color: #4a90d9;
        }
        .admin-section {
            background: white;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        .admin-section h3 {
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e0e0e0;
        }
        .user-table {
            width: 100%;
            border-collapse: collapse;
        }
        .user-table th,
        .user-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }
        .user-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #666;
            font-size: 13px;
        }
        .user-table tr:hover {
            background: #f8f9fa;
        }
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
        }
        .badge-success {
            background: #d4edda;
            color: #155724;
        }
        .badge-danger {
            background: #f8d7da;
            color: #721c24;
        }
        .badge-primary {
            background: #cce5ff;
            color: #004085;
        }
        .pagination {
            display: flex;
            justify-content: center;
            gap: 5px;
            margin-top: 20px;
        }
        .pagination a,
        .pagination span {
            padding: 8px 12px;
            border: 1px solid #e0e0e0;
            border-radius: 4px;
            text-decoration: none;
            color: #333;
        }
        .pagination a:hover {
            background: #f8f9fa;
        }
        .pagination .current {
            background: #4a90d9;
            color: white;
            border-color: #4a90d9;
        }
        .btn-sm {
            padding: 5px 10px;
            font-size: 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.3s;
            margin-right: 5px;
            margin-bottom: 5px;
        }
        .btn-primary {
            background: #4a90d9;
            color: white;
        }
        .btn-primary:hover {
            background: #357abd;
        }
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        .btn-danger:hover {
            background: #c82333;
        }
        .btn-success {
            background: #28a745;
            color: white;
        }
        .btn-success:hover {
            background: #218838;
        }
        .btn-warning {
            background: #ffc107;
            color: #212529;
        }
        .btn-warning:hover {
            background: #e0a800;
        }
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        .btn-secondary:hover {
            background: #5a6268;
        }
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.4);
        }
        .modal-content {
            background-color: #fefefe;
            margin: 15% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 400px;
            border-radius: 8px;
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e0e0e0;
        }
        .modal-header h3 {
            margin: 0;
        }
        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        .close:hover {
            color: black;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
        }
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }
        .form-help {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <!-- 侧边栏 -->
        <aside class="admin-sidebar">
            <div class="admin-sidebar-header">
                <h1><i class="fas fa-cog"></i> 管理后台</h1>
            </div>
            <nav class="admin-nav">
                <a href="admin.php" class="admin-nav-item <?php echo empty($_GET['page'] ?? '') || ($_GET['page'] ?? '') == 'dashboard' ? 'active' : ''; ?>">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>仪表盘</span>
                </a>
                <a href="admin.php?page=users" class="admin-nav-item <?php echo ($_GET['page'] ?? '') == 'users' ? 'active' : ''; ?>">
                    <i class="fas fa-users"></i>
                    <span>用户管理</span>
                </a>
                <a href="admin.php?page=files" class="admin-nav-item <?php echo ($_GET['page'] ?? '') == 'files' ? 'active' : ''; ?>">
                    <i class="fas fa-file"></i>
                    <span>文件管理</span>
                </a>
                <a href="admin.php?page=settings" class="admin-nav-item <?php echo ($_GET['page'] ?? '') == 'settings' ? 'active' : ''; ?>">
                    <i class="fas fa-cog"></i>
                    <span>系统设置</span>
                </a>
                <a href="drive.php" class="admin-nav-item">
                    <i class="fas fa-cloud"></i>
                    <span>返回网盘</span>
                </a>
                <a href="logout.php" class="admin-nav-item">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>退出登录</span>
                </a>
            </nav>
        </aside>

        <!-- 主内容区 -->
        <main class="admin-main">
            <div class="admin-header">
                <h2><?php 
                    $currentPage = $_GET['page'] ?? 'dashboard';
                    switch ($currentPage) {
                        case 'users': echo '用户管理'; break;
                        case 'files': echo '文件管理'; break;
                        case 'settings': echo '系统设置'; break;
                        default: echo '仪表盘';
                    }
                ?></h2>
                <div class="user-info">
                    欢迎，<?php echo htmlspecialchars($user['nickname'] ?: $user['username']); ?> (管理员)
                </div>
            </div>

            <?php
                $currentPage = $_GET['page'] ?? 'dashboard';
            ?>

            <?php if ($currentPage == 'dashboard'): ?>
            <!-- 统计卡片 -->
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>总用户数</h3>
                    <div class="number"><?php echo $stats['total_users']; ?></div>
                </div>
                <div class="stat-card">
                    <h3>总文件数</h3>
                    <div class="number"><?php echo $stats['total_files']; ?></div>
                </div>
                <div class="stat-card">
                    <h3>活跃分享</h3>
                    <div class="number"><?php echo $stats['total_shares']; ?></div>
                </div>
                <div class="stat-card">
                    <h3>存储使用</h3>
                    <div class="number"><?php echo formatFileSize($stats['total_storage']); ?></div>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($currentPage == 'users' || $currentPage == 'dashboard'): ?>
            <!-- 用户列表 -->
            <div class="admin-section">
                <h3>用户管理</h3>
                <table class="user-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>用户名</th>
                            <th>邮箱</th>
                            <th>昵称</th>
                            <th>存储空间</th>
                            <th>已用空间</th>
                            <th>角色</th>
                            <th>状态</th>
                            <th>注册时间</th>
                            <th>最后登录</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                        <tr>
                            <td><?php echo $u['id']; ?></td>
                            <td><?php echo htmlspecialchars($u['username']); ?></td>
                            <td><?php echo htmlspecialchars($u['email']); ?></td>
                            <td><?php echo htmlspecialchars($u['nickname'] ?? '-'); ?></td>
                            <td><?php echo formatFileSize($u['storage_limit']); ?></td>
                            <td><?php echo formatFileSize($u['storage_used']); ?></td>
                            <td>
                                <?php if ($u['is_admin']): ?>
                                    <span class="badge badge-primary">管理员</span>
                                <?php else: ?>
                                    <span class="badge badge-success">普通用户</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($u['status']): ?>
                                    <span class="badge badge-success">正常</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">禁用</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $u['created_at']; ?></td>
                            <td><?php echo $u['last_login_at'] ?? '从未登录'; ?></td>
                            <td>
                                <button class="btn-sm btn-primary" onclick="editUser(<?php echo $u['id']; ?>)">编辑</button>
                                <?php if ($u['status']): ?>
                                    <button class="btn-sm btn-danger" onclick="toggleUserStatus(<?php echo $u['id']; ?>, 0)">禁用</button>
                                <?php else: ?>
                                    <button class="btn-sm btn-success" onclick="toggleUserStatus(<?php echo $u['id']; ?>, 1)">启用</button>
                                <?php endif; ?>
                                <?php if (!$u['is_admin']): ?>
                                    <button class="btn-sm btn-warning" onclick="toggleAdminStatus(<?php echo $u['id']; ?>, 1)">设为管理员</button>
                                <?php else: ?>
                                    <button class="btn-sm btn-secondary" onclick="toggleAdminStatus(<?php echo $u['id']; ?>, 0)">取消管理员</button>
                                <?php endif; ?>
                                <?php if (!$u['is_admin'] && $u['id'] != $user['id']): ?>
                                    <button class="btn-sm btn-danger" onclick="deleteUser(<?php echo $u['id']; ?>)">删除</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- 分页 -->
                <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=users&user_page=<?php echo $page - 1; ?>">上一页</a>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <?php if ($i == $page): ?>
                            <span class="current"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="?page=users&user_page=<?php echo $i; ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=users&user_page=<?php echo $page + 1; ?>">下一页</a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if ($currentPage == 'files'): ?>
            <!-- 文件管理 -->
            <div class="admin-section">
                <h3>文件管理</h3>
                <table class="user-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>文件名</th>
                            <th>大小</th>
                            <th>所属用户</th>
                            <th>上传时间</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                            // 获取文件列表
                            $filePage = intval($_GET['file_page'] ?? 1);
                            $fileLimit = 20;
                            $fileOffset = ($filePage - 1) * $fileLimit;
                            
                            $files = $db->fetchAll(
                                "SELECT f.id, f.filename, f.original_name, f.file_size, f.file_path, f.created_at, u.username 
                                 FROM files f 
                                 JOIN users u ON f.user_id = u.id 
                                 WHERE f.status = 1 
                                 ORDER BY f.created_at DESC 
                                 LIMIT ? OFFSET ?",
                                [$fileLimit, $fileOffset]
                            );
                            
                            $totalFiles = $db->fetch("SELECT COUNT(*) as count FROM files WHERE status = 1")['count'];
                            $totalFilePages = ceil($totalFiles / $fileLimit);
                        ?>
                        <?php foreach ($files as $file): ?>
                        <tr>
                            <td><?php echo $file['id']; ?></td>
                            <td><?php echo htmlspecialchars($file['original_name']); ?></td>
                            <td><?php echo formatFileSize($file['file_size']); ?></td>
                            <td><?php echo htmlspecialchars($file['username']); ?></td>
                            <td><?php echo $file['created_at']; ?></td>
                            <td>
                                <a href="<?php echo APP_URL; ?>/direct.php?path=<?php echo urlencode($file['file_path']); ?>" class="btn-sm btn-primary" target="_blank">下载</a>
                                <button class="btn-sm btn-danger" onclick="deleteFile(<?php echo $file['id']; ?>)">删除</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- 分页 -->
                <?php if ($totalFilePages > 1): ?>
                <div class="pagination">
                    <?php if ($filePage > 1): ?>
                        <a href="?page=files&file_page=<?php echo $filePage - 1; ?>">上一页</a>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $totalFilePages; $i++): ?>
                        <?php if ($i == $filePage): ?>
                            <span class="current"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="?page=files&file_page=<?php echo $i; ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($filePage < $totalFilePages): ?>
                        <a href="?page=files&file_page=<?php echo $filePage + 1; ?>">下一页</a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if ($currentPage == 'settings'): ?>
            <!-- 系统设置 -->
            <div class="admin-section">
                <h3>系统设置</h3>
                <form id="settings-form">
                    <?php
                        require_once __DIR__ . '/includes/Settings.php';
                        $settings = new Settings();
                        $allSettings = $settings->getAll();
                    ?>

                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="allow_public_access" value="1" <?php echo $allSettings['allow_public_access'] ? 'checked' : ''; ?>>
                            允许公开访问用户文件
                        </label>
                        <p class="form-help">启用后，无需登录即可访问用户文件</p>
                    </div>
                    
                    <div class="form-group">
                        <label for="directory_browsing_per_page">目录化每页显示条数</label>
                        <input type="number" id="directory_browsing_per_page" name="directory_browsing_per_page" min="1" max="100" value="<?php echo $allSettings['directory_browsing_per_page'] ?? 20; ?>">
                        <p class="form-help">设置目录化浏览页面每页显示的文件数量</p>
                    </div>
                    
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="enable_office_preview" value="1" <?php echo ($allSettings['enable_office_preview'] ?? 0) ? 'checked' : ''; ?>>
                            启用 Office 在线预览
                        </label>
                        <p class="form-help">启用后，支持在线预览 Office 文件（Word、Excel、PowerPoint）</p>
                    </div>
                    
                    <div class="form-group">
                        <label for="office_preview_service">Office 预览服务</label>
                        <select id="office_preview_service" name="office_preview_service">
                            <option value="microsoft" <?php echo ($allSettings['office_preview_service'] ?? 'microsoft') == 'microsoft' ? 'selected' : ''; ?>>Microsoft Office Online</option>
                        </select>
                        <p class="form-help">选择使用的 Office 预览服务</p>
                    </div>
                    
                    <div class="form-group">
                        <label for="chunk_size">分块上传大小 (MB)</label>
                        <input type="number" id="chunk_size" name="chunk_size" min="1" max="100" value="<?php echo $allSettings['chunk_size'] ?? 10; ?>">
                        <p class="form-help">设置文件分块上传的大小，单位为 MB，建议设置为 5-20MB</p>
                    </div>
                    
                    <div class="form-group">
                        <label for="parallel_uploads">并行上传数量</label>
                        <input type="number" id="parallel_uploads" name="parallel_uploads" min="1" max="10" value="<?php echo $allSettings['parallel_uploads'] ?? 3; ?>">
                        <p class="form-help">设置同时上传的分块数量，建议设置为 2-5 个</p>
                    </div>
                    
                    <div class="form-group">
                        <button type="button" id="generate-cache" class="btn btn-success">生成文件缓存</button>
                        <p class="form-help">预编译项目中的所有PHP文件，提高首次访问速度</p>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">保存设置</button>
                    </div>
                </form>
            </div>
            <?php endif; ?>
        </main>
    </div>

    <!-- 编辑用户模态框 -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>编辑用户</h3>
                <span class="close">&times;</span>
            </div>
            <form id="editForm">
                <input type="hidden" id="user-id">
                <div class="form-group">
                    <label>用户名</label>
                    <input type="text" id="user-username" disabled>
                </div>
                <div class="form-group">
                    <label>邮箱</label>
                    <input type="email" id="user-email">
                </div>
                <div class="form-group">
                    <label>昵称</label>
                    <input type="text" id="user-nickname">
                </div>
                <div class="form-group">
                    <label>存储空间 (GB)</label>
                    <input type="number" id="user-storage" min="1" max="1024">
                </div>
                <div class="form-group">
                    <label>
                        <input type="checkbox" id="user-directory-browsing">
                        启用目录化浏览
                    </label>
                    <p class="form-help">启用后，用户可以通过 URL 直接浏览和下载文件</p>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn-sm btn-secondary" onclick="closeModal()">取消</button>
                    <button type="submit" class="btn-sm btn-primary">保存</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // 编辑用户
        function editUser(userId) {
            fetch('admin.php?action=getUser&userId=' + userId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const user = data.data;
                        document.getElementById('user-id').value = user.id;
                        document.getElementById('user-username').value = user.username;
                        document.getElementById('user-email').value = user.email;
                        document.getElementById('user-nickname').value = user.nickname || '';
                        document.getElementById('user-storage').value = Math.round(user.storage_limit / (1024 * 1024 * 1024));
                        document.getElementById('user-directory-browsing').checked = user.directory_browsing || false;
                        document.getElementById('editModal').style.display = 'block';
                    }
                });
        }

        // 切换用户状态
        function toggleUserStatus(userId, status) {
            if (confirm(status ? '确定要启用该用户吗？' : '确定要禁用该用户吗？')) {
                fetch('admin.php?action=toggleStatus&userId=' + userId + '&status=' + status)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            location.reload();
                        } else {
                            alert(data.message);
                        }
                    });
            }
        }

        // 切换管理员状态
        function toggleAdminStatus(userId, isAdmin) {
            if (confirm(isAdmin ? '确定要将该用户设为管理员吗？' : '确定要取消该用户的管理员权限吗？')) {
                fetch('admin.php?action=toggleAdmin&userId=' + userId + '&isAdmin=' + isAdmin)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            location.reload();
                        } else {
                            alert(data.message);
                        }
                    });
            }
        }

        // 删除用户
        function deleteUser(userId) {
            if (confirm('确定要删除该用户吗？此操作不可恢复！')) {
                fetch('admin.php?action=deleteUser&userId=' + userId)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            location.reload();
                        } else {
                            alert(data.message);
                        }
                    });
            }
        }

        // 删除文件
        function deleteFile(fileId) {
            if (confirm('确定要删除该文件吗？此操作不可恢复！')) {
                fetch('admin.php?action=deleteFile&fileId=' + fileId)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            location.reload();
                        } else {
                            alert(data.message);
                        }
                    });
            }
        }

        // 关闭模态框
        function closeModal() {
            document.getElementById('editModal').style.display = 'none';
        }

        // 关闭按钮点击事件
        document.querySelector('.close').addEventListener('click', closeModal);

        // 点击模态框外部关闭
        window.onclick = function(event) {
            const modal = document.getElementById('editModal');
            if (event.target == modal) {
                closeModal();
            }
        }

        // 表单提交
        document.getElementById('editForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const userId = document.getElementById('user-id').value;
            const email = document.getElementById('user-email').value;
            const nickname = document.getElementById('user-nickname').value;
            const storage = document.getElementById('user-storage').value * 1024 * 1024 * 1024;
            const directoryBrowsing = document.getElementById('user-directory-browsing').checked ? 1 : 0;

            fetch('admin.php?action=updateUser', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    userId: userId,
                    email: email,
                    nickname: nickname,
                    storage_limit: storage,
                    directory_browsing: directoryBrowsing
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    closeModal();
                    location.reload();
                } else {
                    alert(data.message);
                }
            });
        });

        // 保存设置
        const settingsForm = document.getElementById('settings-form');
        if (settingsForm) {
            settingsForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(settingsForm);
                const data = {};
                formData.forEach((value, key) => {
                    data[key] = value;
                });

                fetch('admin.php?action=saveSettings', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(data)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('设置保存成功');
                        location.reload();
                    } else {
                        alert(data.message);
                    }
                });
            });
        }
        
        // 生成文件缓存
        const generateCacheBtn = document.getElementById('generate-cache');
        if (generateCacheBtn) {
            generateCacheBtn.addEventListener('click', function() {
                if (confirm('确定要生成文件缓存吗？这可能需要一些时间。')) {
                    fetch('admin.php?action=generateCache')
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert(data.message);
                        } else {
                            alert('生成缓存失败: ' + data.message);
                        }
                    });
                }
            });
        }
    </script>
</body>
</html>
