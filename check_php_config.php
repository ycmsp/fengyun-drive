<?php
// 检查PHP配置
echo "上传文件最大值：" . ini_get('upload_max_filesize') . "\n";
echo "POST数据最大值：" . ini_get('post_max_size') . "\n";
echo "最大文件上传数量：" . ini_get('max_file_uploads') . "\n";
echo "临时目录：" . ini_get('upload_tmp_dir') . "\n";
echo "内存限制：" . ini_get('memory_limit') . "\n";

// 检查临时目录是否存在且可写
$tempDir = ini_get('upload_tmp_dir');
echo "临时目录存在：" . (is_dir($tempDir) ? '是' : '否') . "\n";
echo "临时目录可写：" . (is_writable($tempDir) ? '是' : '否') . "\n";

// 尝试创建临时文件
try {
    $tempFile = tempnam($tempDir, 'test');
    echo "创建临时文件：" . ($tempFile ? '成功' : '失败') . "\n";
    if ($tempFile) {
        echo "临时文件路径：" . $tempFile . "\n";
        // 尝试写入文件
        $written = file_put_contents($tempFile, 'test content');
        echo "写入临时文件：" . ($written !== false ? '成功' : '失败') . "\n";
        unlink($tempFile);
    }
} catch (Exception $e) {
    echo "错误：" . $e->getMessage() . "\n";
}

// 检查上传目录
$uploadDir = dirname(__DIR__) . '/uploads';
echo "上传目录存在：" . (is_dir($uploadDir) ? '是' : '否') . "\n";
echo "上传目录可写：" . (is_writable($uploadDir) ? '是' : '否') . "\n";
