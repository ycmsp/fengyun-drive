<?php
/**
 * 文件直链访问
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/ShareManager.php';
require_once __DIR__ . '/../includes/FileManager.php';
require_once __DIR__ . '/../includes/functions.php';

// 调试信息
error_log('Direct link access: ' . $_SERVER['REQUEST_URI']);

// 获取文件路径
$requestUri = $_SERVER['REQUEST_URI'];
// 处理不同的路径格式
if (strpos($requestUri, '/files/') === 0) {
    $filePath = substr($requestUri, strlen('/files/'));
} else if (strpos($requestUri, 'files/') === 0) {
    $filePath = substr($requestUri, strlen('files/'));
} else {
    // 直接获取路径部分
    $pathParts = explode('/', ltrim($requestUri, '/'));
    if (isset($pathParts[0]) && $pathParts[0] == 'files') {
        array_shift($pathParts);
        $filePath = implode('/', $pathParts);
    } else {
        header('HTTP/1.1 404 Not Found');
        echo '文件不存在';
        exit;
    }
}

if (empty($filePath)) {
    header('HTTP/1.1 404 Not Found');
    echo '文件不存在';
    exit;
}

// 解码URL编码的文件名
$filePath = urldecode($filePath);

// 安全检查，防止目录遍历
if (strpos($filePath, '..') !== false) {
    header('HTTP/1.1 403 Forbidden');
    echo '访问被拒绝';
    exit;
}

// 检查文件是否存在
$fullPath = UPLOAD_PATH . '/' . $filePath;
error_log('Full path: ' . $fullPath);

if (!file_exists($fullPath)) {
    header('HTTP/1.1 404 Not Found');
    echo '文件不存在: ' . $fullPath;
    exit;
}

// 获取文件信息
$fileInfo = pathinfo($fullPath);
$fileName = $fileInfo['basename'];
$fileSize = filesize($fullPath);
$mimeType = mime_content_type($fullPath);

// 设置响应头
header('Content-Type: ' . $mimeType);
header('Content-Disposition: inline; filename="' . $fileName . '"');
header('Content-Length: ' . $fileSize);
header('Cache-Control: public, max-age=86400');

// 输出文件内容
error_log('Serving file: ' . $fileName);
readfile($fullPath);
