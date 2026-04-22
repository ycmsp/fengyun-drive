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
