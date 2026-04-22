<?php
/**
 * 数据库性能优化脚本
 * 添加必要的索引以提高查询性能
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/Database.php';

try {
    $db = Database::getInstance();
    
    // 添加复合索引
    $queries = [
        // 优化文件列表查询
        "ALTER TABLE files ADD INDEX idx_user_status_parent (user_id, status, parent_id)",
        // 优化文件夹查询
        "ALTER TABLE files ADD INDEX idx_user_status_folder (user_id, status, is_folder)",
        // 优化搜索查询
        "ALTER TABLE files ADD INDEX idx_user_status_filename (user_id, status, filename)",
        // 优化分享查询
        "ALTER TABLE shares ADD INDEX idx_user_file (user_id, file_id)",
        "ALTER TABLE shares ADD INDEX idx_code_status (share_code, status)"
    ];
    
    foreach ($queries as $query) {
        try {
            $db->query($query);
            echo "执行成功: $query<br>";
        } catch (Exception $e) {
            echo "执行失败: $query<br>错误: " . $e->getMessage() . "<br>";
        }
    }
    
    echo "<br>数据库索引优化完成！";
    
} catch (Exception $e) {
    echo "错误: " . $e->getMessage();
}
?>