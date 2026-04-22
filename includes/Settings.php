<?php
/**
 * 设置管理类
 */
class Settings {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
        $this->initSettingsTable();
    }

    /**
     * 初始化设置表
     */
    private function initSettingsTable() {
        // 创建设置表
        $this->db->query("CREATE TABLE IF NOT EXISTS settings (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(50) NOT NULL UNIQUE COMMENT '设置名称',
            value VARCHAR(255) NOT NULL COMMENT '设置值',
            description VARCHAR(255) DEFAULT NULL COMMENT '设置描述'
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='系统设置表'");

        // 插入默认设置
        $defaultSettings = [
            ['name' => 'allow_public_access', 'value' => '0', 'description' => '允许公开访问用户文件'],
            ['name' => 'opcache_enable', 'value' => '1', 'description' => '启用 OPcache'],
            ['name' => 'opcache_memory_consumption', 'value' => '128', 'description' => 'OPcache 内存使用量 (MB)'],
            ['name' => 'opcache_max_accelerated_files', 'value' => '10000', 'description' => 'OPcache 最大文件数'],
            ['name' => 'opcache_revalidate_freq', 'value' => '60', 'description' => 'OPcache 检查频率 (秒)']
        ];

        foreach ($defaultSettings as $setting) {
            $exists = $this->db->fetch("SELECT id FROM settings WHERE name = ?", [$setting['name']]);
            if (!$exists) {
                $this->db->insert(
                    "INSERT INTO settings (name, value, description) VALUES (?, ?, ?)",
                    [$setting['name'], $setting['value'], $setting['description']]
                );
            }
        }

        // 在用户表中添加目录化浏览设置字段
        try {
            // 检查字段是否存在
            $columnExists = $this->db->fetch("SHOW COLUMNS FROM users LIKE 'directory_browsing'");
            if (!$columnExists) {
                $this->db->query("ALTER TABLE users ADD COLUMN directory_browsing TINYINT(1) DEFAULT 0 COMMENT '是否启用目录化浏览'");
            }
        } catch (Exception $e) {
            // 忽略错误
        }

        // 创建离线下载任务表
        try {
            $this->db->query("CREATE TABLE IF NOT EXISTS offline_tasks (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL COMMENT '用户ID',
                task_name VARCHAR(255) NOT NULL COMMENT '任务名称',
                download_url VARCHAR(1024) NOT NULL COMMENT '下载链接',
                save_path INT UNSIGNED DEFAULT 0 COMMENT '保存位置（文件夹ID）',
                file_size BIGINT UNSIGNED DEFAULT 0 COMMENT '文件大小',
                downloaded_size BIGINT UNSIGNED DEFAULT 0 COMMENT '已下载大小',
                status TINYINT(1) DEFAULT 0 COMMENT '状态：0-等待中，1-下载中，2-完成，3-失败',
                error_message VARCHAR(255) DEFAULT NULL COMMENT '错误信息',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
                updated_at DATETIME DEFAULT NULL COMMENT '更新时间',
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (save_path) REFERENCES files(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='离线下载任务表'");
        } catch (Exception $e) {
            // 忽略错误
        }
    }

    /**
     * 获取所有设置
     */
    public function getAll() {
        $settings = $this->db->fetchAll("SELECT * FROM settings");
        $result = [];
        foreach ($settings as $setting) {
            $result[$setting['name']] = $setting['value'];
        }
        return $result;
    }

    /**
     * 获取单个设置
     */
    public function get($name, $default = null) {
        $setting = $this->db->fetch("SELECT value FROM settings WHERE name = ?", [$name]);
        return $setting ? $setting['value'] : $default;
    }

    /**
     * 设置设置值
     */
    public function set($name, $value) {
        $exists = $this->db->fetch("SELECT id FROM settings WHERE name = ?", [$name]);
        if ($exists) {
            return $this->db->update(
                "UPDATE settings SET value = ? WHERE name = ?",
                [$value, $name]
            );
        } else {
            return $this->db->insert(
                "INSERT INTO settings (name, value) VALUES (?, ?)",
                [$name, $value]
            );
        }
    }

    /**
     * 检查目录化浏览是否启用
     */
    public function isDirectoryBrowsingEnabled() {
        return (bool)$this->get('directory_browsing', 0);
    }

    /**
     * 检查公开访问是否启用
     */
    public function isPublicAccessAllowed() {
        return (bool)$this->get('allow_public_access', 0);
    }
}
