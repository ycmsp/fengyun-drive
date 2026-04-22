-- 在用户表中添加目录化浏览设置字段
ALTER TABLE users ADD COLUMN directory_browsing TINYINT(1) DEFAULT 0 COMMENT '是否启用目录化浏览';
