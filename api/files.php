<?php
/**
 * 文件管理API
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/FileManager.php';
require_once __DIR__ . '/../includes/Settings.php';
require_once __DIR__ . '/../includes/functions.php';

// 初始化设置
$settings = new Settings();

$auth = new Auth();
$fileManager = new FileManager();

// 检查登录状态
if (!$auth->check()) {
    error('请先登录', 401);
}

$userId = $auth->id();
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'list':
        $parentId = intval($_GET['parent_id'] ?? 0);
        $page = intval($_GET['page'] ?? 1);
        $limit = intval($_GET['limit'] ?? 50);

        $result = $fileManager->getFiles($userId, $parentId, $page, $limit);

        // 添加文件图标信息
        foreach ($result['files'] as &$file) {
            $file['icon'] = getFileIcon($file);
            $file['size_formatted'] = $file['is_folder'] ? '-' : formatFileSize($file['file_size']);
        }

        success($result);
        break;

    case 'mkdir':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            error('请求方式错误', 405);
        }

        $data = json_decode(file_get_contents('php://input'), true);
        $folderName = trim($data['name'] ?? '');
        $parentId = intval($data['parent_id'] ?? 0);

        if (empty($folderName)) {
            error('文件夹名称不能为空');
        }

        $result = $fileManager->createFolder($userId, $folderName, $parentId);
        if ($result['success']) {
            logAction('create_folder', 'folder', $result['file_id'], ['name' => $folderName]);
            success(['file_id' => $result['file_id']], $result['message']);
        } else {
            error($result['message']);
        }
        break;

    case 'get_settings':
        // 获取系统设置
        require_once __DIR__ . '/../includes/Settings.php';
        $settings = new Settings();
        $allSettings = $settings->getAll();
        success([
            'chunk_size' => $allSettings['chunk_size'] ?? 10,
            'parallel_uploads' => $allSettings['parallel_uploads'] ?? 3
        ]);
        break;

    case 'upload':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            error('请求方式错误', 405);
        }

        $parentId = intval($_POST['parent_id'] ?? 0);

        if (!isset($_FILES['file'])) {
            error('没有上传文件');
        }

        $file = $_FILES['file'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            // 错误代码解释
            $errorMessages = [
                UPLOAD_ERR_INI_SIZE => '上传文件超过了 php.ini 中 upload_max_filesize 限制',
                UPLOAD_ERR_FORM_SIZE => '上传文件超过了 HTML 表单中 MAX_FILE_SIZE 限制',
                UPLOAD_ERR_PARTIAL => '上传文件只有部分被上传',
                UPLOAD_ERR_NO_FILE => '没有文件被上传',
                UPLOAD_ERR_NO_TMP_DIR => '找不到临时文件夹',
                UPLOAD_ERR_CANT_WRITE => '文件写入失败',
                UPLOAD_ERR_EXTENSION => '上传被扩展程序中断'
            ];
            
            $errorMessage = isset($errorMessages[$file['error']]) ? $errorMessages[$file['error']] : '未知错误';
            error('上传失败：' . $file['error'] . ' - ' . $errorMessage);
        }
        
        // 检查临时文件是否存在
        if (!file_exists($file['tmp_name'])) {
            error('临时文件不存在：' . $file['tmp_name']);
        }
        
        // 检查临时文件是否可读
        if (!is_readable($file['tmp_name'])) {
            error('临时文件不可读：' . $file['tmp_name']);
        }

        // 检查文件扩展名
        if (!isAllowedExtension($file['name'])) {
            error('不支持的文件类型');
        }

        $result = $fileManager->uploadFile($userId, $file, $parentId);
        if ($result['success']) {
            logAction('upload_file', 'file', $result['file_id'], [
                'name' => $file['name'],
                'size' => $file['size'],
                'instant' => $result['is_instant']
            ]);
            success([
                'file_id' => $result['file_id'],
                'is_instant' => $result['is_instant']
            ], $result['message']);
        } else {
            error($result['message']);
        }
        break;

    case 'upload_chunk':
        // 上传文件块
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            error('请求方式错误', 405);
        }

        $sessionId = $_POST['session_id'] ?? '';
        $chunkIndex = intval($_POST['index'] ?? 0);
        $fileName = $_POST['file_name'] ?? '';

        if (!isset($_FILES['chunk'])) {
            error('没有上传文件块');
        }

        $chunk = $_FILES['chunk'];

        if ($chunk['error'] !== UPLOAD_ERR_OK) {
            error('文件块上传失败');
        }

        // 获取临时目录设置
        $tempDirectory = $settings->get('temp_directory');
        if (empty($tempDirectory)) {
            // 使用默认临时目录
            $tempDir = TEMP_DIR . '/' . $sessionId;
        } else {
            // 使用设置的临时目录
            $tempDir = rtrim($tempDirectory, '/') . '/' . $sessionId;
        }
        
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        // 保存块
        $chunkPath = $tempDir . '/' . $chunkIndex;
        if (move_uploaded_file($chunk['tmp_name'], $chunkPath)) {
            // 记录块信息
            $info = [
                'file_name' => $fileName,
                'chunk_index' => $chunkIndex
            ];
            file_put_contents($tempDir . '/info.json', json_encode($info, JSON_UNESCAPED_UNICODE));
            success([]);
        } else {
            error('文件块保存失败');
        }
        break;

    case 'merge_chunks':
        // 合并文件块
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            error('请求方式错误', 405);
        }

        $sessionId = $_POST['session_id'] ?? '';
        $fileName = $_POST['file_name'] ?? '';
        $fileSize = intval($_POST['file_size'] ?? 0);
        $parentId = intval($_POST['parent_id'] ?? 0);

        if (empty($sessionId)) {
            error('会话ID不能为空');
        }

        // 获取临时目录设置
        $tempDirectory = $settings->get('temp_directory');
        if (empty($tempDirectory)) {
            // 使用默认临时目录
            $tempDir = TEMP_DIR . '/' . $sessionId;
        } else {
            // 使用设置的临时目录
            $tempDir = rtrim($tempDirectory, '/') . '/' . $sessionId;
        }
        
        // 检查临时目录是否存在
        if (!is_dir($tempDir)) {
            error('临时目录不存在');
        }

        // 获取所有块
        $chunks = [];
        $dir = opendir($tempDir);
        while (($file = readdir($dir)) !== false) {
            if (is_numeric($file)) {
                $chunks[] = intval($file);
            }
        }
        closedir($dir);

        if (empty($chunks)) {
            error('没有找到文件块');
        }

        // 按索引排序
        sort($chunks);

        // 生成唯一文件名
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $timestamp = time();
        $random = mt_rand(100000, 999999);
        $fileHash = md5($timestamp . $random . $fileName);
        $newFilename = $fileHash . '.' . $extension;
        $relativePath = $userId . '/' . $newFilename;
        $fullPath = UPLOAD_PATH . '/' . $relativePath;

        // 确保目标目录存在
        $dir = dirname($fullPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        // 合并块
        $output = fopen($fullPath, 'wb');
        foreach ($chunks as $chunkIndex) {
            $chunkPath = $tempDir . '/' . $chunkIndex;
            if (file_exists($chunkPath)) {
                $chunk = fopen($chunkPath, 'rb');
                stream_copy_to_stream($chunk, $output);
                fclose($chunk);
                unlink($chunkPath); // 删除临时块
            }
        }
        fclose($output);

        // 清理临时目录
        if (file_exists($tempDir . '/info.json')) {
            unlink($tempDir . '/info.json');
        }
        rmdir($tempDir);

        // 创建文件记录
        $result = $fileManager->uploadFileFromPath($userId, [
            'name' => $fileName,
            'size' => $fileSize,
            'type' => mime_content_type($fullPath) ?: 'application/octet-stream'
        ], $relativePath, $parentId);

        if ($result['success']) {
            logAction('upload_file', 'file', $result['file_id'], [
                'name' => $fileName,
                'size' => $fileSize,
                'parent_id' => $parentId
            ]);
            success([
                'file_id' => $result['file_id'],
                'hash' => $fileHash
            ]);
        } else {
            // 删除已合并的文件
            if (file_exists($fullPath)) {
                unlink($fullPath);
            }
            error($result['message']);
        }
        break;

    case 'rename':
        if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
            error('请求方式错误', 405);
        }

        $data = json_decode(file_get_contents('php://input'), true);
        $fileId = intval($data['file_id'] ?? 0);
        $newName = trim($data['new_name'] ?? '');

        if (empty($newName)) {
            error('新名称不能为空');
        }

        $result = $fileManager->rename($userId, $fileId, $newName);
        if ($result['success']) {
            logAction('rename_file', 'file', $fileId, ['new_name' => $newName]);
            success(null, $result['message']);
        } else {
            error($result['message']);
        }
        break;

    case 'move':
        if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
            error('请求方式错误', 405);
        }

        $data = json_decode(file_get_contents('php://input'), true);
        $fileId = intval($data['file_id'] ?? 0);
        $targetParentId = intval($data['target_parent_id'] ?? 0);

        $result = $fileManager->move($userId, $fileId, $targetParentId);
        if ($result['success']) {
            logAction('move_file', 'file', $fileId, ['target' => $targetParentId]);
            success(null, $result['message']);
        } else {
            error($result['message']);
        }
        break;

    case 'delete':
        if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
            error('请求方式错误', 405);
        }

        $data = json_decode(file_get_contents('php://input'), true);
        $fileId = intval($data['file_id'] ?? 0);

        $result = $fileManager->delete($userId, $fileId);
        if ($result['success']) {
            logAction('delete_file', 'file', $fileId);
            success(null, $result['message']);
        } else {
            error($result['message']);
        }
        break;

    case 'search':
        $keyword = trim($_GET['keyword'] ?? '');
        $page = intval($_GET['page'] ?? 1);
        $limit = intval($_GET['limit'] ?? 20);

        if (empty($keyword)) {
            error('搜索关键词不能为空');
        }

        $result = $fileManager->search($userId, $keyword, $page, $limit);

        foreach ($result['files'] as &$file) {
            $file['icon'] = getFileIcon($file);
            $file['size_formatted'] = $file['is_folder'] ? '-' : formatFileSize($file['file_size']);
        }

        success($result);
        break;

    case 'download':
        $fileId = intval($_GET['id'] ?? $_GET['file_id'] ?? 0);

        $file = $fileManager->getFile($userId, $fileId);
        if (!$file) {
            error('文件不存在', 404);
        }

        if ($file['is_folder']) {
            error('不能下载文件夹');
        }

        $filePath = $fileManager->getFilePath($file);
        if (!file_exists($filePath)) {
            error('文件不存在或已被删除', 404);
        }

        logAction('download_file', 'file', $fileId, ['name' => $file['filename']]);
        downloadFile($filePath, $file['original_name']);
        break;

    case 'info':
        $fileId = intval($_GET['id'] ?? $_GET['file_id'] ?? 0);

        $file = $fileManager->getFile($userId, $fileId);
        if (!$file) {
            error('文件不存在', 404);
        }

        $file['icon'] = getFileIcon($file);
        $file['size_formatted'] = $file['is_folder'] ? '-' : formatFileSize($file['file_size']);

        success($file);
        break;

    case 'direct':
        $fileId = intval($_GET['id'] ?? $_GET['file_id'] ?? 0);

        // 检查是否是前台目录化页面的访问
        $isDirectoryBrowsing = isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], '/index.php') !== false;
        
        if ($isDirectoryBrowsing) {
            // 前台目录化页面访问，不验证用户登录
            $file = $fileManager->getDb()->fetch(
                "SELECT * FROM files WHERE id = ? AND status = 1",
                [$fileId]
            );
        } else {
            // 普通访问，验证用户登录
            $file = $fileManager->getFile($userId, $fileId);
        }
        
        if (!$file) {
            error('文件不存在', 404);
        }

        if ($file['is_folder']) {
            error('不能直接访问文件夹', 400);
        }

        $filePath = $fileManager->getFilePath($file);
        if (!file_exists($filePath)) {
            error('文件不存在或已被删除', 404);
        }

        // 设置正确的 Content-Type
        $mimeType = mime_content_type($filePath);
        header('Content-Type: ' . $mimeType);
        
        // 对于文本文件，设置编码
        if (substr($mimeType, 0, 5) === 'text/') {
            header('Content-Type: ' . $mimeType . '; charset=utf-8');
        }
        
        // 输出文件内容
        readfile($filePath);
        exit;
        break;

    default:
        error('未知操作', 404);
}
