<?php
/**
 * 离线下载API
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/FileManager.php';
require_once __DIR__ . '/../includes/functions.php';

$auth = new Auth();
$fileManager = new FileManager();
$db = Database::getInstance();
$action = $_GET['action'] ?? '';

// 检查登录状态
if (!$auth->check()) {
    error('请先登录', 401);
}

$userId = $auth->id();

switch ($action) {
    case 'list':
        // 获取离线下载任务列表
        $page = intval($_GET['page'] ?? 1);
        $limit = intval($_GET['limit'] ?? 20);
        $offset = ($page - 1) * $limit;

        $tasks = $db->fetchAll(
            "SELECT * FROM offline_tasks WHERE user_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?",
            [$userId, $limit, $offset]
        );

        $total = $db->fetch(
            "SELECT COUNT(*) as count FROM offline_tasks WHERE user_id = ?",
            [$userId]
        )['count'];

        // 格式化任务数据
        foreach ($tasks as &$task) {
            $task['size_formatted'] = formatFileSize($task['file_size']);
            $task['downloaded_formatted'] = formatFileSize($task['downloaded_size']);
            $task['progress'] = $task['file_size'] > 0 ? round($task['downloaded_size'] / $task['file_size'] * 100, 2) : 0;
            
            // 状态文本
            $statusText = [
                0 => '等待中',
                1 => '下载中',
                2 => '完成',
                3 => '失败'
            ];
            $task['status_text'] = $statusText[$task['status']] ?? '未知';
        }

        success([
            'tasks' => $tasks,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'pages' => ceil($total / $limit)
        ]);
        break;

    case 'add':
        // 添加离线下载任务
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            error('请求方式错误', 405);
        }

        $data = json_decode(file_get_contents('php://input'), true);
        $url = $data['url'] ?? '';
        $taskName = $data['task_name'] ?? '';
        $savePath = intval($data['save_path'] ?? 0);

        if (empty($url)) {
            error('下载链接不能为空');
        }

        // 验证保存位置
        if ($savePath > 0) {
            $folder = $fileManager->getFile($userId, $savePath);
            if (!$folder || !$folder['is_folder']) {
                error('保存位置不存在');
            }
        }

        // 生成任务名称
        if (empty($taskName)) {
            $taskName = basename(parse_url($url, PHP_URL_PATH)) ?: '离线下载任务';
        }

        // 检查是否存在相同的正在进行中的任务（等待中或下载中）
        $existingTask = $db->fetch(
            "SELECT id FROM offline_tasks WHERE user_id = ? AND download_url = ? AND status IN (0, 1)",
            [$userId, $url]
        );

        if ($existingTask) {
            error('该下载任务已经存在');
        }

        // 创建任务
        try {
            // 直接执行插入操作
            $stmt = $db->query(
                "INSERT INTO offline_tasks (user_id, task_name, download_url, save_path) VALUES (?, ?, ?, ?)",
                [$userId, $taskName, $url, $savePath]
            );
            
            // 获取插入的行数
            $rowCount = $stmt->rowCount();
            
            // 获取最后插入的ID
            $lastId = $db->query("SELECT LAST_INSERT_ID() as id")->fetch()['id'];
            
            error_log('插入操作：行数=' . $rowCount . ', 最后ID=' . $lastId);
            
            if ($rowCount > 0) {
                $taskId = intval($lastId);
            } else {
                $taskId = 0;
            }
        } catch (Exception $e) {
            error_log('插入操作异常：' . $e->getMessage());
            $taskId = 0;
        }

        error_log('任务ID：' . $taskId);

        if ($taskId) {
            // 尝试获取文件大小
            $fileSize = 0;
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HEADER, true);
            curl_setopt($ch, CURLOPT_NOBODY, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // 跟随重定向
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'); // 设置用户代理
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // 禁用SSL证书验证
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); // 禁用SSL主机验证
            $response = curl_exec($ch);
            
            if ($response) {
                $headers = curl_getinfo($ch);
                $fileSize = isset($headers['content_length']) ? (int)$headers['content_length'] : 0;
                
                // 尝试从响应头中提取文件大小
                if ($fileSize == 0) {
                    $headerLines = explode("\n", $response);
                    foreach ($headerLines as $line) {
                        if (stripos($line, 'Content-Length:') === 0) {
                            $fileSize = (int)trim(substr($line, 16));
                            break;
                        }
                    }
                }
            }
            curl_close($ch);
            
            // 如果无法获取文件大小，使用默认值
            if ($fileSize == 0) {
                $fileSize = 1024000; // 1MB
            }

            // 更新任务状态为下载中
            $db->update(
                "UPDATE offline_tasks SET status = 1, file_size = ? WHERE id = ?",
                [$fileSize, $taskId]
            );

            // 初始化下载进度
            $db->update(
                "UPDATE offline_tasks SET downloaded_size = 0 WHERE id = ?",
                [$taskId]
            );

            // 继续处理下载任务
            try {
                // 实际下载文件
                $fileName = $taskName;
                $extension = pathinfo($fileName, PATHINFO_EXTENSION);
                
                // 根据文件扩展名设置 MIME 类型
                $mimeTypes = [
                    'txt' => 'text/plain',
                    'jpg' => 'image/jpeg',
                    'jpeg' => 'image/jpeg',
                    'png' => 'image/png',
                    'gif' => 'image/gif',
                    'pdf' => 'application/pdf',
                    'doc' => 'application/msword',
                    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'xls' => 'application/vnd.ms-excel',
                    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'ppt' => 'application/vnd.ms-powerpoint',
                    'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                    'zip' => 'application/zip',
                    'rar' => 'application/x-rar-compressed',
                    '7z' => 'application/x-7z-compressed',
                    'exe' => 'application/x-msdownload',
                    'dll' => 'application/x-msdownload',
                    'mp3' => 'audio/mpeg',
                    'mp4' => 'video/mp4',
                    'avi' => 'video/x-msvideo',
                    'mov' => 'video/quicktime',
                    'wmv' => 'video/x-ms-wmv'
                ];
                
                $fileType = isset($mimeTypes[strtolower($extension)]) ? $mimeTypes[strtolower($extension)] : 'application/octet-stream';
                
                // 生成存储路径
                $fileHash = md5($url . time() . rand(1000, 9999));
                $relativePath = $userId . '/' . $fileHash . ($extension ? '.' . $extension : '');
                $fullPath = UPLOAD_PATH . '/' . $relativePath;
                
                // 确保目录存在
                $dir = dirname($fullPath);
                if (!is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }
                
                // 更新任务状态为下载中
                $db->update(
                    "UPDATE offline_tasks SET status = 1 WHERE id = ?",
                    [$taskId]
                );
                
                // 初始化下载进度
                $db->update(
                    "UPDATE offline_tasks SET downloaded_size = 0 WHERE id = ?",
                    [$taskId]
                );
                
                // 下载文件
                $ch = curl_init($url);
                $fp = fopen($fullPath, 'w');
                
                curl_setopt($ch, CURLOPT_FILE, $fp);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
                curl_setopt($ch, CURLOPT_TIMEOUT, 3600); // 1小时超时
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // 禁用SSL证书验证
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); // 禁用SSL主机验证
                
                // 进度更新回调
                $lastUpdateTime = time();
                curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, function($ch, $downloadSize, $downloaded, $uploadSize, $uploaded) use ($taskId, $db, &$lastUpdateTime) {
                    // 每1秒更新一次进度，避免频繁数据库操作
                    if (time() - $lastUpdateTime >= 1 && $downloadSize > 0) {
                        $db->update(
                            "UPDATE offline_tasks SET downloaded_size = ? WHERE id = ?",
                            [$downloaded, $taskId]
                        );
                        $lastUpdateTime = time();
                    }
                });
                curl_setopt($ch, CURLOPT_NOPROGRESS, false);
                
                $downloadSuccess = curl_exec($ch);
                $curlError = curl_error($ch);
                curl_close($ch);
                fclose($fp);
                
                if (!$downloadSuccess) {
                    // 下载失败
                    $db->update(
                        "UPDATE offline_tasks SET status = 3, error_message = ? WHERE id = ?",
                        [$curlError, $taskId]
                    );
                    error_log('文件下载失败: ' . $curlError);
                    return;
                }
                
                // 获取实际文件大小
                $actualFileSize = filesize($fullPath);
                
                // 创建文件记录
                $fileId = $db->insert(
                    "INSERT INTO files (user_id, parent_id, filename, original_name, file_path, file_size, file_type, file_extension, hash, status) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    [
                        $userId, $savePath, $fileName, $fileName,
                        $relativePath, $actualFileSize, $fileType, $extension, $fileHash, 1
                    ]
                );

                if ($fileId) {
                    // 更新用户已用空间
                    $db->update(
                        "UPDATE users SET storage_used = storage_used + ? WHERE id = ?",
                        [$actualFileSize, $userId]
                    );
                    
                    // 更新任务状态为完成
                    $db->update(
                        "UPDATE offline_tasks SET status = 2, downloaded_size = ?, file_size = ? WHERE id = ?",
                        [$actualFileSize, $actualFileSize, $taskId]
                    );

                    success(['task_id' => $taskId, 'file_id' => $fileId], '任务添加成功');
                } else {
                    // 删除下载的文件
                    unlink($fullPath);
                    // 更新任务状态为失败
                    $db->update(
                        "UPDATE offline_tasks SET status = 3, error_message = '文件记录创建失败' WHERE id = ?",
                        [$taskId]
                    );
                    error('文件记录创建失败');
                }
            } catch (Exception $e) {
                // 记录错误并更新任务状态为失败
                error_log('下载文件时出错: ' . $e->getMessage());
                $db->update(
                    "UPDATE offline_tasks SET status = 3, error_message = ? WHERE id = ?",
                    [$e->getMessage(), $taskId]
                );
                error('下载文件时出错: ' . $e->getMessage());
            }
        } else {
            error('任务添加失败');
        }
        break;

    case 'cancel':
        // 取消离线下载任务
        if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
            error('请求方式错误', 405);
        }

        $data = json_decode(file_get_contents('php://input'), true);
        $taskId = intval($data['task_id'] ?? 0);

        if ($taskId <= 0) {
            error('任务ID不能为空');
        }

        // 检查任务是否存在且属于当前用户
        $task = $db->fetch(
            "SELECT id FROM offline_tasks WHERE id = ? AND user_id = ?",
            [$taskId, $userId]
        );

        if (!$task) {
            error('任务不存在');
        }

        // 删除任务
        $db->delete(
            "DELETE FROM offline_tasks WHERE id = ?",
            [$taskId]
        );

        success(null, '任务已取消');
        break;

    case 'delete':
        // 删除离线下载任务
        if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
            error('请求方式错误', 405);
        }

        $data = json_decode(file_get_contents('php://input'), true);
        $taskId = intval($data['task_id'] ?? 0);

        if ($taskId <= 0) {
            error('任务ID不能为空');
        }

        // 检查任务是否存在且属于当前用户
        $task = $db->fetch(
            "SELECT id FROM offline_tasks WHERE id = ? AND user_id = ?",
            [$taskId, $userId]
        );

        if (!$task) {
            error('任务不存在');
        }

        // 删除任务
        $db->delete(
            "DELETE FROM offline_tasks WHERE id = ?",
            [$taskId]
        );

        success(null, '任务已删除');
        break;

    default:
        error('未知操作', 404);
}
