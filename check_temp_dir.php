<?php
// 检查临时目录设置
echo "PHP临时目录设置：" . ini_get('upload_tmp_dir') . "\n";
echo "临时目录是否存在：" . (is_dir(sys_get_temp_dir()) ? '是' : '否') . "\n";
echo "临时目录是否可写：" . (is_writable(sys_get_temp_dir()) ? '是' : '否') . "\n";
echo "上传文件最大值：" . ini_get('upload_max_filesize') . "\n";
echo "POST数据最大值：" . ini_get('post_max_size') . "\n";
echo "最大文件上传数量：" . ini_get('max_file_uploads') . "\n";

// 尝试创建临时文件
$tempFile = tempnam(sys_get_temp_dir(), 'test');
echo "创建临时文件：" . ($tempFile ? '成功' : '失败') . "\n";
if ($tempFile) {
    echo "临时文件路径：" . $tempFile . "\n";
    unlink($tempFile);
}
