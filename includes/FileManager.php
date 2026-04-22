<?php
/**
 * 文件管理类
 */
class FileManager {
    private $db;
    private $uploadPath;

    public function __construct() {
        $this->db = Database::getInstance();
        $this->uploadPath = UPLOAD_PATH;

        // 确保上传目录存在
        if (!is_dir($this->uploadPath)) {
            mkdir($this->uploadPath, 0755, true);
        }
    }

    /**
     * 获取文件列表
     */
    public function getFiles($userId, $parentId = 0, $page = 1, $limit = 50) {
        $offset = ($page - 1) * $limit;

        $files = $this->db->fetchAll(
            "SELECT * FROM files 
             WHERE user_id = ? AND parent_id = ? AND status = 1 
             ORDER BY is_folder DESC, filename ASC 
             LIMIT ? OFFSET ?",
            [$userId, $parentId, $limit, $offset]
        );

        $total = $this->db->fetch(
            "SELECT COUNT(*) as count FROM files WHERE user_id = ? AND parent_id = ? AND status = 1",
            [$userId, $parentId]
        )['count'];

        // 获取面包屑导航
        $breadcrumbs = $this->getBreadcrumbs($userId, $parentId);

        return [
            'files' => $files,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'pages' => ceil($total / $limit),
            'breadcrumbs' => $breadcrumbs
        ];
    }

    /**
     * 获取面包屑导航
     */
    public function getBreadcrumbs($userId, $folderId) {
        $breadcrumbs = [];

        // 一次性查询所有父文件夹，减少数据库查询次数
        $folderPath = [];
        $currentId = $folderId;
        
        // 构建文件夹路径
        while ($currentId > 0) {
            $folder = $this->db->fetch(
                "SELECT id, parent_id, filename FROM files WHERE id = ? AND user_id = ? AND is_folder = 1",
                [$currentId, $userId]
            );

            if (!$folder) break;

            $folderPath[] = [
                'id' => $folder['id'],
                'name' => $folder['filename']
            ];

            $currentId = $folder['parent_id'];
        }
        
        // 反转路径，构建面包屑
        for ($i = count($folderPath) - 1; $i >= 0; $i--) {
            $breadcrumbs[] = $folderPath[$i];
        }

        // 添加根目录
        array_unshift($breadcrumbs, ['id' => 0, 'name' => '根目录']);

        return $breadcrumbs;
    }

    /**
     * 创建文件夹
     */
    public function createFolder($userId, $folderName, $parentId = 0) {
        // 验证文件夹名
        if (empty($folderName) || strlen($folderName) > 255) {
            return ['success' => false, 'message' => '文件夹名称无效'];
        }

        // 检查是否已存在
        $existing = $this->db->fetch(
            "SELECT id FROM files WHERE user_id = ? AND parent_id = ? AND filename = ? AND is_folder = 1 AND status = 1",
            [$userId, $parentId, $folderName]
        );

        if ($existing) {
            return ['success' => false, 'message' => '该文件夹已存在'];
        }

        // 检查父文件夹是否存在
        if ($parentId > 0) {
            $parent = $this->db->fetch(
                "SELECT id FROM files WHERE id = ? AND user_id = ? AND is_folder = 1 AND status = 1",
                [$parentId, $userId]
            );
            if (!$parent) {
                return ['success' => false, 'message' => '父文件夹不存在'];
            }
        }

        // 创建文件夹记录
        $fileId = $this->db->insert(
            "INSERT INTO files (user_id, parent_id, filename, original_name, file_path, file_size, is_folder, created_at, updated_at) 
             VALUES (?, ?, ?, ?, '', 0, 1, NOW(), NOW())",
            [$userId, $parentId, $folderName, $folderName]
        );

        if ($fileId) {
            return ['success' => true, 'message' => '文件夹创建成功', 'file_id' => $fileId];
        }

        return ['success' => false, 'message' => '文件夹创建失败'];
    }

    /**
     * 上传文件
     */
    public function uploadFile($userId, $fileData, $parentId = 0) {
        // 检查用户存储空间
        $user = $this->db->fetch("SELECT storage_limit, storage_used FROM users WHERE id = ?", [$userId]);
        if (!$user) {
            return ['success' => false, 'message' => '用户不存在'];
        }

        if ($user['storage_used'] + $fileData['size'] > $user['storage_limit']) {
            return ['success' => false, 'message' => '存储空间不足'];
        }

        // 检查父文件夹
        if ($parentId > 0) {
            $parent = $this->db->fetch(
                "SELECT id FROM files WHERE id = ? AND user_id = ? AND is_folder = 1 AND status = 1",
                [$parentId, $userId]
            );
            if (!$parent) {
                return ['success' => false, 'message' => '目标文件夹不存在'];
            }
        }

        // 生成存储路径
        $fileHash = md5_file($fileData['tmp_name']);
        $extension = strtolower(pathinfo($fileData['name'], PATHINFO_EXTENSION));
        $newFilename = $fileHash . '.' . $extension;
        $relativePath = $userId . '/' . $newFilename;
        $fullPath = $this->uploadPath . '/' . $relativePath;

        // 检查文件是否已存在（秒传）
        $existingFile = $this->db->fetch(
            "SELECT * FROM files WHERE hash = ? AND status = 1 LIMIT 1",
            [$fileHash]
        );

        if ($existingFile) {
            // 秒传：只创建记录，不复制文件
            $fileId = $this->db->insert(
                "INSERT INTO files (user_id, parent_id, filename, original_name, file_path, file_size, file_type, file_extension, hash, created_at, updated_at) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())",
                [
                    $userId, $parentId, $fileData['name'], $fileData['name'],
                    $existingFile['file_path'], $fileData['size'],
                    $fileData['type'], $extension, $fileHash
                ]
            );
        } else {
            // 确保目录存在
            $dir = dirname($fullPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            // 移动上传文件
            if (!move_uploaded_file($fileData['tmp_name'], $fullPath)) {
                return ['success' => false, 'message' => '文件上传失败'];
            }

            // 创建文件记录
            $fileId = $this->db->insert(
                "INSERT INTO files (user_id, parent_id, filename, original_name, file_path, file_size, file_type, file_extension, hash, created_at, updated_at) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())",
                [
                    $userId, $parentId, $fileData['name'], $fileData['name'],
                    $relativePath, $fileData['size'],
                    $fileData['type'], $extension, $fileHash
                ]
            );
        }

        if ($fileId) {
            // 更新用户已用空间
            $this->db->update(
                "UPDATE users SET storage_used = storage_used + ? WHERE id = ?",
                [$fileData['size'], $userId]
            );

            return [
                'success' => true,
                'message' => '文件上传成功',
                'file_id' => $fileId,
                'is_instant' => $existingFile ? true : false
            ];
        }

        return ['success' => false, 'message' => '文件保存失败'];
    }

    /**
     * 从文件路径上传文件（用于分块上传合并后）
     */
    public function uploadFileFromPath($userId, $fileInfo, $filePath, $parentId = 0) {
        // 检查用户存储空间
        $user = $this->db->fetch("SELECT storage_limit, storage_used FROM users WHERE id = ?", [$userId]);
        if (!$user) {
            return ['success' => false, 'message' => '用户不存在'];
        }

        if ($user['storage_used'] + $fileInfo['size'] > $user['storage_limit']) {
            return ['success' => false, 'message' => '存储空间不足'];
        }

        // 检查父文件夹
        if ($parentId > 0) {
            $parent = $this->db->fetch(
                "SELECT id FROM files WHERE id = ? AND user_id = ? AND is_folder = 1 AND status = 1",
                [$parentId, $userId]
            );
            if (!$parent) {
                return ['success' => false, 'message' => '目标文件夹不存在'];
            }
        }

        // 生成哈希值
        $timestamp = time();
        $random = mt_rand(100000, 999999);
        $fileHash = md5($timestamp . $random . $fileInfo['name']);
        $extension = strtolower(pathinfo($fileInfo['name'], PATHINFO_EXTENSION));

        // 创建文件记录
        $fileId = $this->db->insert(
            "INSERT INTO files (user_id, parent_id, filename, original_name, file_path, file_size, file_type, file_extension, hash, created_at, updated_at) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())",
            [
                $userId, $parentId, $fileInfo['name'], $fileInfo['name'],
                $filePath, $fileInfo['size'],
                $fileInfo['type'], $extension, $fileHash
            ]
        );

        if ($fileId) {
            // 更新用户已用空间
            $this->db->update(
                "UPDATE users SET storage_used = storage_used + ? WHERE id = ?",
                [$fileInfo['size'], $userId]
            );

            return [
                'success' => true,
                'message' => '文件上传成功',
                'file_id' => $fileId,
                'is_instant' => false
            ];
        }

        return ['success' => false, 'message' => '文件保存失败'];
    }

    /**
     * 重命名文件/文件夹
     */
    public function rename($userId, $fileId, $newName) {
        $file = $this->db->fetch(
            "SELECT * FROM files WHERE id = ? AND user_id = ? AND status = 1",
            [$fileId, $userId]
        );

        if (!$file) {
            return ['success' => false, 'message' => '文件不存在'];
        }

        // 检查新名称是否已存在
        $existing = $this->db->fetch(
            "SELECT id FROM files WHERE user_id = ? AND parent_id = ? AND filename = ? AND id != ? AND status = 1",
            [$userId, $file['parent_id'], $newName, $fileId]
        );

        if ($existing) {
            return ['success' => false, 'message' => '该名称已被使用'];
        }

        $this->db->update(
            "UPDATE files SET filename = ?, updated_at = NOW() WHERE id = ?",
            [$newName, $fileId]
        );

        return ['success' => true, 'message' => '重命名成功'];
    }

    /**
     * 移动文件/文件夹
     */
    public function move($userId, $fileId, $targetParentId) {
        $file = $this->db->fetch(
            "SELECT * FROM files WHERE id = ? AND user_id = ? AND status = 1",
            [$fileId, $userId]
        );

        if (!$file) {
            return ['success' => false, 'message' => '文件不存在'];
        }

        if ($file['parent_id'] == $targetParentId) {
            return ['success' => false, 'message' => '文件已在目标位置'];
        }

        // 检查目标文件夹
        if ($targetParentId > 0) {
            $target = $this->db->fetch(
                "SELECT id FROM files WHERE id = ? AND user_id = ? AND is_folder = 1 AND status = 1",
                [$targetParentId, $userId]
            );
            if (!$target) {
                return ['success' => false, 'message' => '目标文件夹不存在'];
            }

            // 检查是否移动到子文件夹中
            if ($file['is_folder'] && $this->isDescendant($userId, $fileId, $targetParentId)) {
                return ['success' => false, 'message' => '不能将文件夹移动到其子文件夹中'];
            }
        }

        // 检查目标位置是否已有同名文件
        $existing = $this->db->fetch(
            "SELECT id FROM files WHERE user_id = ? AND parent_id = ? AND filename = ? AND id != ? AND status = 1",
            [$userId, $targetParentId, $file['filename'], $fileId]
        );

        if ($existing) {
            return ['success' => false, 'message' => '目标位置已存在同名文件'];
        }

        $this->db->update(
            "UPDATE files SET parent_id = ?, updated_at = NOW() WHERE id = ?",
            [$targetParentId, $fileId]
        );

        return ['success' => true, 'message' => '移动成功'];
    }

    /**
     * 检查是否是后代文件夹
     */
    private function isDescendant($userId, $folderId, $targetId) {
        $currentId = $targetId;
        while ($currentId > 0) {
            $folder = $this->db->fetch(
                "SELECT parent_id FROM files WHERE id = ? AND user_id = ? AND is_folder = 1",
                [$currentId, $userId]
            );
            if (!$folder) break;
            if ($folder['parent_id'] == $folderId) {
                return true;
            }
            $currentId = $folder['parent_id'];
        }
        return false;
    }

    /**
     * 删除文件/文件夹
     */
    public function delete($userId, $fileId) {
        $file = $this->db->fetch(
            "SELECT * FROM files WHERE id = ? AND user_id = ? AND status = 1",
            [$fileId, $userId]
        );

        if (!$file) {
            return ['success' => false, 'message' => '文件不存在'];
        }

        $this->db->beginTransaction();

        try {
            if ($file['is_folder']) {
                // 递归删除文件夹内容
                $this->deleteFolderContents($userId, $fileId);
            } else {
                // 删除物理文件
                $filePath = $this->uploadPath . '/' . $file['file_path'];
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
                
                // 更新用户存储空间
                $this->db->update(
                    "UPDATE users SET storage_used = GREATEST(0, storage_used - ?) WHERE id = ?",
                    [$file['file_size'], $userId]
                );
            }

            // 从数据库中删除记录
            $this->db->delete(
                "DELETE FROM files WHERE id = ?",
                [$fileId]
            );

            $this->db->commit();
            return ['success' => true, 'message' => '删除成功'];
        } catch (Exception $e) {
            $this->db->rollback();
            return ['success' => false, 'message' => '删除失败：' . $e->getMessage()];
        }
    }

    /**
     * 递归删除文件夹内容
     */
    private function deleteFolderContents($userId, $folderId) {
        // 使用单次查询获取所有子文件和文件夹
        $items = $this->db->fetchAll(
            "SELECT * FROM files WHERE user_id = ? AND parent_id = ? AND status = 1",
            [$userId, $folderId]
        );

        // 计算总文件大小，用于一次性更新存储空间
        $totalFileSize = 0;
        $filePaths = [];
        $fileIds = [];

        foreach ($items as $item) {
            if ($item['is_folder']) {
                // 递归处理子文件夹
                $this->deleteFolderContents($userId, $item['id']);
            } else {
                // 收集文件路径和大小
                $filePath = $this->uploadPath . '/' . $item['file_path'];
                if (file_exists($filePath)) {
                    $filePaths[] = $filePath;
                }
                $totalFileSize += $item['file_size'];
            }
            // 收集文件ID
            $fileIds[] = $item['id'];
        }

        // 批量删除物理文件
        foreach ($filePaths as $filePath) {
            @unlink($filePath); // 使用@抑制错误
        }

        // 一次性更新用户存储空间
        if ($totalFileSize > 0) {
            $this->db->update(
                "UPDATE users SET storage_used = GREATEST(0, storage_used - ?) WHERE id = ?",
                [$totalFileSize, $userId]
            );
        }

        // 批量删除数据库记录
        if (!empty($fileIds)) {
            $placeholders = implode(',', array_fill(0, count($fileIds), '?'));
            $this->db->delete(
                "DELETE FROM files WHERE id IN ($placeholders)",
                $fileIds
            );
        }
    }

    /**
     * 获取文件信息
     */
    public function getFile($userId, $fileId) {
        return $this->db->fetch(
            "SELECT * FROM files WHERE id = ? AND user_id = ? AND status = 1",
            [$fileId, $userId]
        );
    }

    /**
     * 搜索文件
     */
    public function search($userId, $keyword, $page = 1, $limit = 20) {
        $offset = ($page - 1) * $limit;
        $keyword = "%$keyword%";

        $files = $this->db->fetchAll(
            "SELECT * FROM files 
             WHERE user_id = ? AND status = 1 AND filename LIKE ? 
             ORDER BY is_folder DESC, filename ASC 
             LIMIT ? OFFSET ?",
            [$userId, $keyword, $limit, $offset]
        );

        $total = $this->db->fetch(
            "SELECT COUNT(*) as count FROM files WHERE user_id = ? AND status = 1 AND filename LIKE ?",
            [$userId, $keyword]
        )['count'];

        return [
            'files' => $files,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'pages' => ceil($total / $limit)
        ];
    }

    /**
     * 获取文件物理路径
     */
    public function getFilePath($file) {
        return $this->uploadPath . '/' . $file['file_path'];
    }
    
    /**
     * 获取数据库实例
     */
    public function getDb() {
        return $this->db;
    }
}
