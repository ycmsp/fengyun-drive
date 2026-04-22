<?php
/**
 * 分享管理类
 */
class ShareManager {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * 创建分享
     */
    public function createShare($userId, $fileId, $shareType = 1, $password = '', $expireDays = 7) {
        try {
            // 检查文件是否存在且属于该用户
            $file = $this->db->fetch(
                "SELECT * FROM files WHERE id = ? AND user_id = ? AND status = 1",
                [$fileId, $userId]
            );

            if (!$file) {
                return ['success' => false, 'message' => '文件不存在或无权访问'];
            }

            // 生成分享码
            $shareCode = $this->generateShareCode();

            // 计算过期时间
            $expireAt = null;
            if ($expireDays > 0) {
                $expireAt = date('Y-m-d H:i:s', time() + $expireDays * 24 * 3600);
            }

            // 密码处理
            $sharePassword = null;
            if ($shareType == 2 && !empty($password)) {
                $sharePassword = password_hash($password, PASSWORD_BCRYPT);
            }

            // 调试信息
            error_log("Creating share: userId=$userId, fileId=$fileId, shareCode=$shareCode, shareType=$shareType");

            $shareId = $this->db->insert(
                "INSERT INTO shares (user_id, file_id, share_code, share_type, share_password, expire_at) 
                 VALUES (?, ?, ?, ?, ?, ?)",
                [$userId, $fileId, $shareCode, $shareType, $sharePassword, $expireAt]
            );

            error_log("Share created with ID: " . var_export($shareId, true));

            if ($shareId && $shareId > 0) {
                $result = [
                    'success' => true,
                    'message' => '分享创建成功',
                    'share_id' => $shareId,
                    'share_code' => $shareCode,
                    'share_url' => APP_URL . '/share.php?code=' . $shareCode,
                    'expire_at' => $expireAt
                ];

                // 如果是直链分享，生成直链URL
                if ($shareType == 3) {
                    $directLink = APP_URL . '/direct.php?path=' . urlencode($file['file_path']);
                    $result['direct_link'] = $directLink;
                }

                return $result;
            }

            return ['success' => false, 'message' => '分享创建失败，无法获取分享ID'];
        } catch (PDOException $e) {
            error_log("Share creation error: " . $e->getMessage());
            return ['success' => false, 'message' => '数据库错误: ' . $e->getMessage()];
        } catch (Exception $e) {
            error_log("Share creation error: " . $e->getMessage());
            return ['success' => false, 'message' => '系统错误: ' . $e->getMessage()];
        }
    }

    /**
     * 生成分享码
     */
    private function generateShareCode() {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        $code = '';
        for ($i = 0; $i < SHARE_CODE_LENGTH; $i++) {
            $code .= $chars[random_int(0, strlen($chars) - 1)];
        }

        // 检查是否已存在
        $existing = $this->db->fetch("SELECT id FROM shares WHERE share_code = ?", [$code]);
        if ($existing) {
            return $this->generateShareCode();
        }

        return $code;
    }

    /**
     * 获取分享信息
     */
    public function getShare($shareCode) {
        $share = $this->db->fetch(
            "SELECT s.*, u.username, u.nickname, f.filename, f.original_name, f.file_size, 
                    f.file_type, f.is_folder, f.file_path
             FROM shares s
             JOIN users u ON s.user_id = u.id
             JOIN files f ON s.file_id = f.id
             WHERE s.share_code = ? AND s.status = 1",
            [$shareCode]
        );

        if (!$share) {
            return null;
        }

        // 检查是否过期
        if ($share['expire_at'] && strtotime($share['expire_at']) < time()) {
            $this->cancelShare($share['user_id'], $share['id']);
            return null;
        }

        return $share;
    }

    /**
     * 验证分享密码
     */
    public function verifyPassword($shareCode, $password) {
        $share = $this->db->fetch(
            "SELECT share_password FROM shares WHERE share_code = ? AND status = 1",
            [$shareCode]
        );

        if (!$share) {
            return false;
        }

        if ($share['share_type'] == 1) {
            return true;
        }

        return password_verify($password, $share['share_password']);
    }

    /**
     * 访问分享（增加访问次数）
     */
    public function accessShare($shareCode) {
        $this->db->update(
            "UPDATE shares SET access_count = access_count + 1 WHERE share_code = ?",
            [$shareCode]
        );
    }

    /**
     * 下载分享（增加下载次数）
     */
    public function downloadShare($shareCode) {
        $this->db->update(
            "UPDATE shares SET download_count = download_count + 1 WHERE share_code = ?",
            [$shareCode]
        );
    }

    /**
     * 生成直链
     */
    public function generateDirectLink($shareCode) {
        $share = $this->getShare($shareCode);
        if (!$share) {
            return null;
        }

        // 生成直链令牌
        $token = md5($shareCode . time() . SECRET_KEY);
        $expireTime = time() + 24 * 3600; // 24小时过期

        // 存储直链信息到数据库
        $this->db->insert(
            "INSERT INTO share_direct_links (share_code, token, expire_at) VALUES (?, ?, ?)",
            [$shareCode, $token, date('Y-m-d H:i:s', $expireTime)]
        );

        // 生成直链URL
        return APP_URL . '/api/share.php?action=direct&code=' . $shareCode . '&token=' . $token;
    }

    /**
     * 验证直链令牌
     */
    public function verifyDirectLink($shareCode, $token) {
        $link = $this->db->fetch(
            "SELECT * FROM share_direct_links WHERE share_code = ? AND token = ? AND expire_at > NOW()",
            [$shareCode, $token]
        );

        return $link !== false;
    }

    /**
     * 获取用户的分享列表
     */
    public function getUserShares($userId, $page = 1, $limit = 20) {
        $offset = ($page - 1) * $limit;

        $shares = $this->db->fetchAll(
            "SELECT s.*, f.filename, f.original_name, f.is_folder, f.file_size
             FROM shares s
             JOIN files f ON s.file_id = f.id
             WHERE s.user_id = ? 
             ORDER BY s.created_at DESC 
             LIMIT ? OFFSET ?",
            [$userId, $limit, $offset]
        );

        $total = $this->db->fetch(
            "SELECT COUNT(*) as count FROM shares WHERE user_id = ?",
            [$userId]
        )['count'];

        return [
            'shares' => $shares,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'pages' => ceil($total / $limit)
        ];
    }

    /**
     * 取消分享
     */
    public function cancelShare($userId, $shareId) {
        $share = $this->db->fetch(
            "SELECT * FROM shares WHERE id = ? AND user_id = ?",
            [$shareId, $userId]
        );

        if (!$share) {
            return ['success' => false, 'message' => '分享不存在'];
        }

        $this->db->update(
            "UPDATE shares SET status = 0 WHERE id = ?",
            [$shareId]
        );

        return ['success' => true, 'message' => '分享已取消'];
    }

    /**
     * 获取分享的文件内容（如果是文件夹）
     */
    public function getShareContents($shareCode) {
        $share = $this->getShare($shareCode);

        if (!$share) {
            return null;
        }

        if ($share['is_folder']) {
            // 获取文件夹内容
            $files = $this->db->fetchAll(
                "SELECT id, filename, original_name, file_size, file_type, is_folder, created_at 
                 FROM files 
                 WHERE user_id = ? AND parent_id = ? AND status = 1 
                 ORDER BY is_folder DESC, filename ASC",
                [$share['user_id'], $share['file_id']]
            );

            return [
                'share' => $share,
                'files' => $files
            ];
        } else {
            return [
                'share' => $share,
                'files' => []
            ];
        }
    }

    /**
     * 检查分享是否有效
     */
    public function isValid($shareCode) {
        $share = $this->getShare($shareCode);
        return $share !== null;
    }
}
