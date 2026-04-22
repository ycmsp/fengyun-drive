-- 网盘系统数据库结构
-- 创建数据库
CREATE DATABASE IF NOT EXISTS cloud_drive DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE cloud_drive;

-- 删除已存在的表（按依赖顺序）
DROP TABLE IF EXISTS operation_logs;
DROP TABLE IF EXISTS shares;
DROP TABLE IF EXISTS files;
DROP TABLE IF EXISTS users;

-- 用户表
CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE COMMENT '用户名',
    email VARCHAR(100) NOT NULL UNIQUE COMMENT '邮箱',
    password VARCHAR(255) NOT NULL COMMENT '密码哈希',
    nickname VARCHAR(50) DEFAULT NULL COMMENT '昵称',
    avatar VARCHAR(255) DEFAULT NULL COMMENT '头像URL',
    storage_limit BIGINT UNSIGNED DEFAULT 10737418240 COMMENT '存储空间限制(字节)，默认10GB',
    storage_used BIGINT UNSIGNED DEFAULT 0 COMMENT '已使用存储空间(字节)',
    status TINYINT DEFAULT 1 COMMENT '状态：0-禁用，1-正常',
    is_admin TINYINT DEFAULT 0 COMMENT '是否管理员：0-否，1-是',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    last_login_at TIMESTAMP NULL DEFAULT NULL,
    last_login_ip VARCHAR(45) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户表';

-- 文件表
CREATE TABLE files (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL COMMENT '所属用户ID',
    parent_id INT UNSIGNED DEFAULT 0 COMMENT '父文件夹ID，0表示根目录',
    filename VARCHAR(255) NOT NULL COMMENT '文件名',
    original_name VARCHAR(255) NOT NULL COMMENT '原始文件名',
    file_path VARCHAR(500) NOT NULL COMMENT '文件存储路径',
    file_size BIGINT UNSIGNED NOT NULL COMMENT '文件大小(字节)',
    file_type VARCHAR(100) DEFAULT NULL COMMENT '文件MIME类型',
    file_extension VARCHAR(20) DEFAULT NULL COMMENT '文件扩展名',
    is_folder TINYINT DEFAULT 0 COMMENT '是否为文件夹：0-文件，1-文件夹',
    hash VARCHAR(64) DEFAULT NULL COMMENT '文件MD5哈希，用于秒传',
    status TINYINT DEFAULT 1 COMMENT '状态：0-删除，1-正常',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    INDEX idx_user_parent (user_id, parent_id),
    INDEX idx_hash (hash),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='文件表';

-- 添加外键（在表创建后）
ALTER TABLE files ADD CONSTRAINT fk_files_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;

-- 分享表
CREATE TABLE shares (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL COMMENT '分享者用户ID',
    file_id INT UNSIGNED NOT NULL COMMENT '分享的文件/文件夹ID',
    share_code VARCHAR(32) NOT NULL UNIQUE COMMENT '分享码',
    share_type TINYINT DEFAULT 1 COMMENT '分享类型：1-公开，2-密码保护',
    share_password VARCHAR(255) DEFAULT NULL COMMENT '分享密码',
    access_count INT UNSIGNED DEFAULT 0 COMMENT '访问次数',
    download_count INT UNSIGNED DEFAULT 0 COMMENT '下载次数',
    expire_at DATETIME NULL DEFAULT NULL COMMENT '过期时间',
    status TINYINT DEFAULT 1 COMMENT '状态：0-失效，1-有效',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_share_code (share_code),
    INDEX idx_user (user_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='分享表';

-- 添加外键
ALTER TABLE shares ADD CONSTRAINT fk_shares_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;
ALTER TABLE shares ADD CONSTRAINT fk_shares_file FOREIGN KEY (file_id) REFERENCES files(id) ON DELETE CASCADE;

-- 操作日志表
CREATE TABLE operation_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED DEFAULT NULL COMMENT '操作用户ID',
    action VARCHAR(50) NOT NULL COMMENT '操作类型',
    target_type VARCHAR(50) DEFAULT NULL COMMENT '操作对象类型',
    target_id INT UNSIGNED DEFAULT NULL COMMENT '操作对象ID',
    details TEXT DEFAULT NULL COMMENT '操作详情',
    ip_address VARCHAR(45) DEFAULT NULL COMMENT 'IP地址',
    user_agent TEXT DEFAULT NULL COMMENT '用户代理',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_action (action),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='操作日志表';

CREATE TABLE IF NOT EXISTS offline_tasks (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='离线下载任务表';


-- 直链分享表
CREATE TABLE share_direct_links (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    share_code VARCHAR(32) NOT NULL COMMENT '分享码',
    token VARCHAR(32) NOT NULL UNIQUE COMMENT '直链令牌',
    expire_at DATETIME NOT NULL COMMENT '过期时间',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_share_code (share_code),
    INDEX idx_token (token),
    INDEX idx_expire (expire_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='直链分享表';

-- 系统设置表
CREATE TABLE settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE COMMENT '设置名称',
    value VARCHAR(255) NOT NULL COMMENT '设置值',
    description VARCHAR(255) DEFAULT NULL COMMENT '设置描述',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='系统设置表';

-- 插入默认设置
INSERT INTO settings (name, value, description) VALUES
('directory_browsing', '0', '启用目录化浏览'),
('allow_public_access', '0', '允许公开访问用户文件');

-- 插入默认管理员账号（密码：admin123）
INSERT INTO users (username, email, password, nickname, is_admin, storage_limit) VALUES
('admin', 'admin@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '管理员', 1, 107374182400);
