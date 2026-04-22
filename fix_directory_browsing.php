<?php
/**
 * 修复目录化浏览字段
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/Database.php';

$db = Database::getInstance();

try {
    // 在用户表中添加目录化浏览设置字段
    $db->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS directory_browsing TINYINT(1) DEFAULT 0 COMMENT '是否启用目录化浏览'");
    echo "目录化浏览字段添加成功！\n";
    
    // 查看表结构
    $result = $db->fetchAll("DESCRIBE users");
    echo "\n用户表结构：\n";
    foreach ($result as $field) {
        echo "{$field['Field']} - {$field['Type']} - {$field['Default']}\n";
    }
} catch (Exception $e) {
    echo "错误：" . $e->getMessage() . "\n";
}