<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 直接在脚本中设置配置
ini_set('upload_max_filesize', '100M');
ini_set('post_max_size', '100M');
ini_set('max_execution_time', '600');
ini_set('max_input_time', '600');
ini_set('memory_limit', '512M');

// 确保临时目录存在
$tempDir = __DIR__ . '/temp';
if (!is_dir($tempDir)) {
    mkdir($tempDir, 0755, true);
}
ini_set('upload_tmp_dir', $tempDir);

echo "<html><body>";
echo "<h1>详细上传测试</h1>";

echo "<h2>当前配置</h2>";
echo "<p>upload_max_filesize: " . ini_get('upload_max_filesize') . "</p>";
echo "<p>post_max_size: " . ini_get('post_max_size') . "</p>";
echo "<p>max_execution_time: " . ini_get('max_execution_time') . "</p>";
echo "<p>max_input_time: " . ini_get('max_input_time') . "</p>";
echo "<p>memory_limit: " . ini_get('memory_limit') . "</p>";
echo "<p>upload_tmp_dir: " . ini_get('upload_tmp_dir') . "</p>";
echo "<p>临时目录存在: " . (is_dir(ini_get('upload_tmp_dir')) ? '是' : '否') . "</p>";
echo "<p>临时目录可写: " . (is_writable(ini_get('upload_tmp_dir')) ? '是' : '否') . "</p>";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<h2>上传结果</h2>";
    
    if (isset($_FILES['file'])) {
        $file = $_FILES['file'];
        echo "<p>文件名称: " . $file['name'] . "</p>";
        echo "<p>文件大小: " . ($file['size'] / 1024 / 1024) . " MB</p>";
        echo "<p>错误代码: " . $file['error'] . "</p>";
        echo "<p>临时文件: " . $file['tmp_name'] . "</p>";
        echo "<p>临时文件存在: " . (file_exists($file['tmp_name']) ? '是' : '否') . "</p>";
        echo "<p>临时文件可读: " . (is_readable($file['tmp_name']) ? '是' : '否') . "</p>";
        
        if ($file['error'] === UPLOAD_ERR_OK) {
            echo "<p style='color:green;'>文件上传成功到临时目录！</p>";
            
            // 尝试复制到上传目录
            $uploadDir = __DIR__ . '/uploads';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $dest = $uploadDir . '/' . basename($file['name']);
            if (copy($file['tmp_name'], $dest)) {
                echo "<p style='color:green;'>文件复制成功到: " . $dest . "</p>";
            } else {
                echo "<p style='color:red;'>文件复制失败</p>";
            }
        } else {
            $errorMessages = [
                UPLOAD_ERR_INI_SIZE => '文件大小超过 upload_max_filesize 限制',
                UPLOAD_ERR_FORM_SIZE => '文件大小超过表单限制',
                UPLOAD_ERR_PARTIAL => '文件只有部分被上传',
                UPLOAD_ERR_NO_FILE => '没有文件被上传',
                UPLOAD_ERR_NO_TMP_DIR => '找不到临时文件夹',
                UPLOAD_ERR_CANT_WRITE => '文件写入失败',
                UPLOAD_ERR_EXTENSION => '上传被扩展程序中断'
            ];
            $errorMsg = isset($errorMessages[$file['error']]) ? $errorMessages[$file['error']] : '未知错误';
            echo "<p style='color:red;'>上传失败: " . $errorMsg . "</p>";
        }
    } else {
        echo "<p>没有接收到文件</p>";
    }
} else {
    echo "<h2>上传表单</h2>";
    echo "<form method='post' enctype='multipart/form-data'>";
    echo "<input type='hidden' name='MAX_FILE_SIZE' value='104857600'>"; // 100MB
    echo "<input type='file' name='file' size='50'>";
    echo "<button type='submit'>上传</button>";
    echo "</form>";
}

echo "</body></html>";