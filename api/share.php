<?php
/**
 * 分享管理API
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/FileManager.php';
require_once __DIR__ . '/../includes/ShareManager.php';
require_once __DIR__ . '/../includes/functions.php';

$auth = new Auth();
$shareManager = new ShareManager();
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'create':
        if (!$auth->check()) {
            error('请先登录', 401);
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            error('请求方式错误', 405);
        }

        $data = json_decode(file_get_contents('php://input'), true);
        $fileId = intval($data['file_id'] ?? 0);
        $shareType = intval($data['share_type'] ?? 1);
        $password = $data['password'] ?? '';
        $expireDays = intval($data['expire_days'] ?? 7);

        if ($fileId <= 0) {
            error('请选择要分享的文件');
        }

        $result = $shareManager->createShare($auth->id(), $fileId, $shareType, $password, $expireDays);
        if ($result['success']) {
            logAction('create_share', 'share', $result['share_id'], [
                'file_id' => $fileId,
                'type' => $shareType
            ]);
            success([
                'share_id' => $result['share_id'],
                'share_code' => $result['share_code'],
                'share_url' => $result['share_url'],
                'expire_at' => $result['expire_at'],
                'direct_link' => $result['direct_link'] ?? null
            ], $result['message']);
        } else {
            error($result['message']);
        }
        break;

    case 'list':
        if (!$auth->check()) {
            error('请先登录', 401);
        }

        $page = intval($_GET['page'] ?? 1);
        $limit = intval($_GET['limit'] ?? 20);

        $result = $shareManager->getUserShares($auth->id(), $page, $limit);

        // 格式化数据
        foreach ($result['shares'] as &$share) {
            $share['size_formatted'] = $share['is_folder'] ? '-' : formatFileSize($share['file_size']);
            $share['is_expired'] = $share['expire_at'] && strtotime($share['expire_at']) < time();
        }

        success($result);
        break;

    case 'cancel':
        if (!$auth->check()) {
            error('请先登录', 401);
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
            error('请求方式错误', 405);
        }

        $data = json_decode(file_get_contents('php://input'), true);
        $shareId = intval($data['share_id'] ?? 0);

        $result = $shareManager->cancelShare($auth->id(), $shareId);
        if ($result['success']) {
            logAction('cancel_share', 'share', $shareId);
            success(null, $result['message']);
        } else {
            error($result['message']);
        }
        break;

    case 'info':
        // 获取分享信息（公开接口，不需要登录）
        $shareCode = $_GET['code'] ?? '';

        if (empty($shareCode)) {
            error('分享码不能为空');
        }

        $share = $shareManager->getShare($shareCode);
        if (!$share) {
            error('分享不存在或已过期', 404);
        }

        // 如果需要密码，不返回完整信息
        if ($share['share_type'] == 2) {
            $shareInfo = [
                'share_code' => $share['share_code'],
                'share_type' => $share['share_type'],
                'filename' => $share['filename'],
                'is_folder' => $share['is_folder'],
                'file_size' => $share['file_size'],
                'size_formatted' => $share['is_folder'] ? '-' : formatFileSize($share['file_size']),
                'created_at' => $share['created_at'],
                'expire_at' => $share['expire_at'],
                'access_count' => $share['access_count'],
                'uploader' => $share['nickname'] ?: $share['username']
            ];
        } else {
            $shareInfo = [
                'share_code' => $share['share_code'],
                'share_type' => $share['share_type'],
                'filename' => $share['filename'],
                'is_folder' => $share['is_folder'],
                'file_size' => $share['file_size'],
                'size_formatted' => $share['is_folder'] ? '-' : formatFileSize($share['file_size']),
                'created_at' => $share['created_at'],
                'expire_at' => $share['expire_at'],
                'access_count' => $share['access_count'],
                'uploader' => $share['nickname'] ?: $share['username']
            ];

            // 增加访问次数
            $shareManager->accessShare($shareCode);

            // 如果是文件夹，获取内容
            if ($share['is_folder']) {
                $contents = $shareManager->getShareContents($shareCode);
                $shareInfo['files'] = $contents['files'];
            }
        }

        success($shareInfo);
        break;

    case 'verify':
        // 验证分享密码
        $shareCode = $_GET['code'] ?? '';
        $data = json_decode(file_get_contents('php://input'), true);
        $password = $data['password'] ?? '';

        if (empty($shareCode)) {
            error('分享码不能为空');
        }

        if (!$shareManager->verifyPassword($shareCode, $password)) {
            error('密码错误', 403);
        }

        // 密码正确，返回分享内容
        $share = $shareManager->getShare($shareCode);
        $shareInfo = [
            'share_code' => $share['share_code'],
            'share_type' => $share['share_type'],
            'filename' => $share['filename'],
            'is_folder' => $share['is_folder'],
            'file_size' => $share['file_size'],
            'size_formatted' => $share['is_folder'] ? '-' : formatFileSize($share['file_size']),
            'created_at' => $share['created_at'],
            'expire_at' => $share['expire_at'],
            'access_count' => $share['access_count'],
            'uploader' => $share['nickname'] ?: $share['username']
        ];

        // 增加访问次数
        $shareManager->accessShare($shareCode);

        // 如果是文件夹，获取内容
        if ($share['is_folder']) {
            $contents = $shareManager->getShareContents($shareCode);
            $shareInfo['files'] = $contents['files'];
        }

        success($shareInfo);
        break;

    case 'download':
        // 下载分享的文件
        $shareCode = $_GET['code'] ?? '';
        $password = $_GET['password'] ?? '';

        if (empty($shareCode)) {
            error('分享码不能为空');
        }

        $share = $shareManager->getShare($shareCode);
        if (!$share) {
            error('分享不存在或已过期', 404);
        }

        // 验证密码
        if ($share['share_type'] == 2 && !$shareManager->verifyPassword($shareCode, $password)) {
            error('密码错误', 403);
        }

        if ($share['is_folder']) {
            error('暂不支持下载文件夹');
        }

        $fileManager = new FileManager();
        $filePath = $fileManager->getFilePath($share);

        if (!file_exists($filePath)) {
            error('文件不存在或已被删除', 404);
        }

        // 增加下载次数
        $shareManager->downloadShare($shareCode);

        downloadFile($filePath, $share['original_name']);
        break;

    case 'direct':
        // 直链下载
        $shareCode = $_GET['code'] ?? '';
        $token = $_GET['token'] ?? '';

        if (empty($shareCode) || empty($token)) {
            error('直链参数不完整', 400);
        }

        // 验证直链令牌
        if (!$shareManager->verifyDirectLink($shareCode, $token)) {
            error('直链已过期或无效', 403);
        }

        $share = $shareManager->getShare($shareCode);
        if (!$share) {
            error('分享不存在或已过期', 404);
        }

        if ($share['is_folder']) {
            error('暂不支持下载文件夹');
        }

        $fileManager = new FileManager();
        $filePath = $fileManager->getFilePath($share);

        if (!file_exists($filePath)) {
            error('文件不存在或已被删除', 404);
        }

        // 增加下载次数
        $shareManager->downloadShare($shareCode);

        downloadFile($filePath, $share['original_name']);
        break;

    case 'generateDirectLink':
        // 生成直链
        if (!$auth->check()) {
            error('请先登录', 401);
        }

        $shareCode = $_GET['code'] ?? '';
        if (empty($shareCode)) {
            error('分享码不能为空');
        }

        $directLink = $shareManager->generateDirectLink($shareCode);
        if ($directLink) {
            success(['direct_link' => $directLink], '直链生成成功');
        } else {
            error('直链生成失败');
        }
        break;

    case 'save':
        // 转存文件到自己的网盘
        if (!$auth->check()) {
            error('请先登录', 401);
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            error('请求方式错误', 405);
        }

        $data = json_decode(file_get_contents('php://input'), true);
        $shareCode = $data['share_code'] ?? '';
        $parentId = intval($data['parent_id'] ?? 0);

        if (empty($shareCode)) {
            error('分享码不能为空');
        }

        $share = $shareManager->getShare($shareCode);
        if (!$share) {
            error('分享不存在或已过期', 404);
        }

        $fileManager = new FileManager();
        $targetUserId = $auth->id();

        // 检查用户存储空间
        $user = $fileManager->db->fetch("SELECT storage_limit, storage_used FROM users WHERE id = ?", [$targetUserId]);
        if (!$user) {
            error('用户不存在');
        }

        // 转存文件或文件夹
        if ($share['is_folder']) {
            // 转存文件夹
            $result = saveSharedFolder($fileManager, $targetUserId, $share['user_id'], $share['file_id'], $parentId, $share['filename']);
        } else {
            // 转存单个文件
            $result = saveSharedFile($fileManager, $targetUserId, $share, $parentId);
        }

        if ($result['success']) {
            logAction('save_shared_file', 'file', $result['file_id'], ['share_code' => $shareCode]);
            success($result, $result['message']);
        } else {
            error($result['message']);
        }
        break;

    default:
        error('未知操作', 404);
}

/**
 * 转存单个文件
 */
function saveSharedFile($fileManager, $targetUserId, $share, $parentId) {
    $db = $fileManager->getDb();
    
    // 检查目标位置是否已存在同名文件
    $existing = $db->fetch(
        "SELECT id FROM files WHERE user_id = ? AND parent_id = ? AND filename = ? AND status = 1",
        [$targetUserId, $parentId, $share['filename']]
    );
    
    if ($existing) {
        return ['success' => false, 'message' => '目标位置已存在同名文件'];
    }
    
    // 检查存储空间
    $user = $db->fetch("SELECT storage_limit, storage_used FROM users WHERE id = ?", [$targetUserId]);
    if ($user['storage_used'] + $share['file_size'] > $user['storage_limit']) {
        return ['success' => false, 'message' => '存储空间不足'];
    }
    
    // 直接复制文件记录（秒传）
    $fileId = $db->insert(
        "INSERT INTO files (user_id, parent_id, filename, original_name, file_path, file_size, file_type, file_extension, hash) 
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
        [
            $targetUserId, $parentId, $share['filename'], $share['original_name'],
            $share['file_path'], $share['file_size'],
            $share['file_type'], pathinfo($share['filename'], PATHINFO_EXTENSION), $share['hash']
        ]
    );
    
    if ($fileId) {
        // 更新用户存储空间
        $db->update(
            "UPDATE users SET storage_used = storage_used + ? WHERE id = ?",
            [$share['file_size'], $targetUserId]
        );
        
        return ['success' => true, 'message' => '文件转存成功', 'file_id' => $fileId];
    }
    
    return ['success' => false, 'message' => '文件转存失败'];
}

/**
 * 转存文件夹
 */
function saveSharedFolder($fileManager, $targetUserId, $sourceUserId, $folderId, $parentId, $folderName) {
    $db = $fileManager->getDb();
    
    // 检查目标位置是否已存在同名文件夹
    $existing = $db->fetch(
        "SELECT id FROM files WHERE user_id = ? AND parent_id = ? AND filename = ? AND is_folder = 1 AND status = 1",
        [$targetUserId, $parentId, $folderName]
    );
    
    if ($existing) {
        return ['success' => false, 'message' => '目标位置已存在同名文件夹'];
    }
    
    // 创建目标文件夹
    $targetFolderId = $db->insert(
        "INSERT INTO files (user_id, parent_id, filename, original_name, file_path, file_size, is_folder) 
         VALUES (?, ?, ?, ?, '', 0, 1)",
        [$targetUserId, $parentId, $folderName, $folderName]
    );
    
    if (!$targetFolderId) {
        return ['success' => false, 'message' => '文件夹创建失败'];
    }
    
    // 获取源文件夹内容
    $contents = $db->fetchAll(
        "SELECT * FROM files WHERE user_id = ? AND parent_id = ? AND status = 1",
        [$sourceUserId, $folderId]
    );
    
    // 转存文件夹内容
    foreach ($contents as $item) {
        if ($item['is_folder']) {
            // 递归转存子文件夹
            $result = saveSharedFolder($fileManager, $targetUserId, $sourceUserId, $item['id'], $targetFolderId, $item['filename']);
            if (!$result['success']) {
                return $result;
            }
        } else {
            // 转存文件
            $result = saveSharedFile($fileManager, $targetUserId, $item, $targetFolderId);
            if (!$result['success']) {
                return $result;
            }
        }
    }
    
    return ['success' => true, 'message' => '文件夹转存成功', 'file_id' => $targetFolderId];
}
