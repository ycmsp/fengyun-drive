<?php
// 检查PHP配置
echo "PHP版本: " . phpversion() . "\n";
echo "上传文件最大值: " . ini_get('upload_max_filesize') . "\n";
echo "POST数据最大值: " . ini_get('post_max_size') . "\n";
echo "最大文件上传数量: " . ini_get('max_file_uploads') . "\n";
echo "临时目录: " . ini_get('upload_tmp_dir') . "\n";
echo "内存限制: " . ini_get('memory_limit') . "\n";

// 检查临时目录
$tempDir = ini_get('upload_tmp_dir');
echo "\n临时目录检查:\n";
echo "临时目录: " . $tempDir . "\n";
echo "目录存在: " . (is_dir($tempDir) ? '是' : '否') . "\n";
echo "目录可写: " . (is_writable($tempDir) ? '是' : '否') . "\n";

// 检查当前脚本所在目录
$currentDir = dirname(__FILE__);
echo "\n当前目录: " . $currentDir . "\n";

// 检查TEMP环境变量
echo "\n环境变量:\n";
echo "TEMP: " . getenv('TEMP') . "\n";
echo "TMP: " . getenv('TMP') . "\n";

// 尝试创建临时文件
echo "\n创建临时文件测试:\n";
try {
    $tempFile = tempnam($tempDir, 'test');
    echo "创建临时文件: " . ($tempFile ? '成功' : '失败') . "\n";
    if ($tempFile) {
        echo "临时文件路径: " . $tempFile . "\n";
        unlink($tempFile);
    }
} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
}
