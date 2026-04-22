<?php
/**
 * 通用函数库
 */

/**
 * JSON响应
 */
function jsonResponse($data, $httpCode = 200) {
    http_response_code($httpCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

/**
 * 成功响应
 */
function success($data = null, $message = '操作成功') {
    jsonResponse([
        'success' => true,
        'message' => $message,
        'data' => $data
    ]);
}

/**
 * 错误响应
 */
function error($message = '操作失败', $code = 400) {
    jsonResponse([
        'success' => false,
        'message' => $message
    ], $code);
}

/**
 * 格式化文件大小
 */
function formatFileSize($bytes) {
    if ($bytes < 1024) {
        return $bytes . ' B';
    } elseif ($bytes < 1024 * 1024) {
        return round($bytes / 1024, 2) . ' KB';
    } elseif ($bytes < 1024 * 1024 * 1024) {
        return round($bytes / (1024 * 1024), 2) . ' MB';
    } else {
        return round($bytes / (1024 * 1024 * 1024), 2) . ' GB';
    }
}

/**
 * 获取文件图标
 */
function getFileIcon($file) {
    if ($file['is_folder']) {
        return 'folder';
    }

    $extension = strtolower($file['file_extension'] ?? '');

    $iconMap = [
        'image' => ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg'],
        'video' => ['mp4', 'avi', 'mkv', 'mov', 'wmv', 'flv', 'webm'],
        'audio' => ['mp3', 'wav', 'flac', 'aac', 'ogg', 'wma'],
        'pdf' => ['pdf'],
        'word' => ['doc', 'docx'],
        'excel' => ['xls', 'xlsx', 'csv'],
        'ppt' => ['ppt', 'pptx'],
        'zip' => ['zip', 'rar', '7z', 'tar', 'gz', 'bz2'],
        'code' => ['js', 'css', 'html', 'php', 'py', 'java', 'c', 'cpp', 'h', 'json', 'xml'],
        'text' => ['txt', 'md', 'log']
    ];

    foreach ($iconMap as $icon => $extensions) {
        if (in_array($extension, $extensions)) {
            return $icon;
        }
    }

    return 'file';
}

/**
 * 生成随机字符串
 */
function randomString($length = 16) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $str = '';
    for ($i = 0; $i < $length; $i++) {
        $str .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $str;
}

/**
 * 安全过滤
 */
function sanitize($data) {
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

/**
 * 获取客户端IP
 */
function getClientIP() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } elseif (!empty($_SERVER['HTTP_X_REAL_IP'])) {
        $ip = $_SERVER['HTTP_X_REAL_IP'];
    }
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
}

/**
 * 记录日志
 */
function logAction($action, $targetType = null, $targetId = null, $details = null) {
    $db = Database::getInstance();
    $userId = $_SESSION['user_id'] ?? null;

    $db->insert(
        "INSERT INTO operation_logs (user_id, action, target_type, target_id, details, ip_address, user_agent) 
         VALUES (?, ?, ?, ?, ?, ?, ?)",
        [
            $userId,
            $action,
            $targetType,
            $targetId,
            $details ? json_encode($details) : null,
            getClientIP(),
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]
    );
}

/**
 * 验证CSRF Token
 */
function verifyCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * 生成CSRF Token
 */
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * 下载文件
 */
function downloadFile($filePath, $fileName) {
    if (!file_exists($filePath)) {
        return false;
    }

    $fileSize = filesize($filePath);
    $fileExt = pathinfo($fileName, PATHINFO_EXTENSION);

    // 设置MIME类型
    $mimeTypes = [
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'ppt' => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'zip' => 'application/zip',
        'rar' => 'application/x-rar-compressed',
        '7z' => 'application/x-7z-compressed',
        'txt' => 'text/plain',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'mp3' => 'audio/mpeg',
        'mp4' => 'video/mp4'
    ];

    $contentType = $mimeTypes[$fileExt] ?? 'application/octet-stream';

    // 清除缓冲区
    ob_clean();
    flush();

    // 设置头信息
    header('Content-Type: ' . $contentType);
    header('Content-Disposition: attachment; filename="' . urlencode($fileName) . '"');
    header('Content-Length: ' . $fileSize);
    header('Cache-Control: no-cache, must-revalidate');

    // 输出文件
    readfile($filePath);
    exit;
}

/**
 * 检查文件扩展名是否允许
 */
function isAllowedExtension($filename) {
    // 如果 ALLOWED_EXTENSIONS 为空数组，允许所有文件类型
    if (empty(ALLOWED_EXTENSIONS)) {
        return true;
    }
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return in_array($extension, ALLOWED_EXTENSIONS);
}

/**
 * 获取文件扩展名
 */
function getFileExtension($filename) {
    return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
}
