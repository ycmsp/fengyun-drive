<?php
// 检查数据库连接和表结构
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/Database.php';

echo "=== 数据库连接检查 ===\n";
try {
    $db = Database::getInstance();
    echo "数据库连接成功！\n";
    
    // 检查必要的表
    $tables = ['users', 'files', 'shares', 'offline_tasks'];
    
    foreach ($tables as $table) {
        $result = $db->query("SHOW TABLES LIKE '$table'");
        $exists = $result->rowCount() > 0;
        echo "表 $table: " . ($exists ? '存在' : '不存在') . "\n";
    }
    
    // 检查 shares 表结构
    echo "\n=== shares 表结构 ===\n";
    $result = $db->query("DESCRIBE shares");
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        echo "{$row['Field']} ({$row['Type']}) {$row['Null']} {$row['Key']} {$row['Default']} {$row['Extra']}\n";
    }
    
    // 检查 offline_tasks 表结构
    echo "\n=== offline_tasks 表结构 ===\n";
    $result = $db->query("DESCRIBE offline_tasks");
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        echo "{$row['Field']} ({$row['Type']}) {$row['Null']} {$row['Key']} {$row['Default']} {$row['Extra']}\n";
    }
    
} catch (Exception $e) {
    echo "数据库连接失败: " . $e->getMessage() . "\n";
}
