<?php
/**
 * 网盘系统配置文件
 */

// 数据库配置
define('DB_HOST', 'localhost');
define('DB_NAME', 'cloud_drive');
define('DB_USER', 'root');
define('DB_PASS', 'root');
define('DB_CHARSET', 'utf8mb4');

// 应用配置
define('APP_NAME', 'Cloud Drive');
define('APP_URL', (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST']);
#define('APP_URL', 'http://localhost');
define('APP_ROOT', dirname(__DIR__));
define('UPLOAD_PATH', APP_ROOT . '\uploads');
define('MAX_UPLOAD_SIZE', 2 * 1024 * 1024 * 1024); // 2GB

// 安全配置
define('SECRET_KEY', 'your-secret-key-here-change-in-production');
define('SESSION_LIFETIME', 7200); // 2小时
define('ALLOWED_EXTENSIONS', []); // 空数组表示允许所有文件类型

// 分享配置
define('SHARE_CODE_LENGTH', 8);
define('DEFAULT_SHARE_EXPIRE', 7 * 24 * 3600); // 默认7天过期

// 错误报告（生产环境建议关闭）
error_reporting(E_ALL);
ini_set('display_errors', '1');

// 时区设置
date_default_timezone_set('Asia/Shanghai');

// 临时目录设置
// 使用项目内的临时目录，确保在open_basedir允许范围内
define('TEMP_DIR', APP_ROOT . '/temp');
// 确保目录存在
if (!is_dir(TEMP_DIR)) {
    @mkdir(TEMP_DIR, 0777, true);
}
// 强制设置 PHP 临时目录
ini_set('upload_tmp_dir', TEMP_DIR);
// 确保设置生效
ini_set('upload_tmp_dir', TEMP_DIR);
// 输出临时目录信息（调试用）
error_log('临时目录: ' . TEMP_DIR);
error_log('是否可写: ' . (is_writable(TEMP_DIR) ? '是' : '否'));
error_log('upload_tmp_dir: ' . ini_get('upload_tmp_dir'));

// 启动会话
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
