-- 数据库索引优化脚本
-- 执行此脚本以添加必要的复合索引，提高查询性能

-- 优化文件列表查询
ALTER TABLE files ADD INDEX idx_user_status_parent (user_id, status, parent_id);

-- 优化文件夹查询
ALTER TABLE files ADD INDEX idx_user_status_folder (user_id, status, is_folder);

-- 优化搜索查询
ALTER TABLE files ADD INDEX idx_user_status_filename (user_id, status, filename);

-- 优化分享查询
ALTER TABLE shares ADD INDEX idx_user_file (user_id, file_id);
ALTER TABLE shares ADD INDEX idx_code_status (share_code, status);

-- 优化离线下载任务查询
ALTER TABLE offline_tasks ADD INDEX idx_user_status (user_id, status);

-- 优化用户查询
ALTER TABLE users ADD INDEX idx_username_email (username, email);

-- 查看所有索引
SHOW INDEX FROM files;
SHOW INDEX FROM shares;
SHOW INDEX FROM offline_tasks;
SHOW INDEX FROM users;