<?php
/**
 * 直链访问脚本
 * 使用 ?path= 参数来指定文件路径
 */
error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';

// 获取文件路径
$filePath = $_GET['path'] ?? '';

if (empty($filePath)) {
    header('HTTP/1.1 404 Not Found');
    echo '文件路径不能为空';
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
readfile($fullPath);
