# Host: localhost  (Version: 5.5.29)
# Date: 2026-04-06 17:54:48
# Generator: MySQL-Front 5.3  (Build 4.234)

/*!40101 SET NAMES utf8 */;

#
# Structure for table "operation_logs"
#

DROP TABLE IF EXISTS `operation_logs`;
CREATE TABLE `operation_logs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned DEFAULT NULL COMMENT '操作用户ID',
  `action` varchar(50) NOT NULL COMMENT '操作类型',
  `target_type` varchar(50) DEFAULT NULL COMMENT '操作对象类型',
  `target_id` int(10) unsigned DEFAULT NULL COMMENT '操作对象ID',
  `details` text COMMENT '操作详情',
  `ip_address` varchar(45) DEFAULT NULL COMMENT 'IP地址',
  `user_agent` text COMMENT '用户代理',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_action` (`action`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=35 DEFAULT CHARSET=utf8mb4 COMMENT='操作日志表';

#
# Data for table "operation_logs"
#

INSERT INTO `operation_logs` VALUES (1,1,'upload_file','file',1,'{\"name\":\"\\u552e\\u540e\\u652f\\u6301.txt\",\"size\":931,\"instant\":false}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36','2026-04-04 13:35:23'),(2,1,'create_share','share',2,'{\"file_id\":1,\"type\":1}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36','2026-04-04 14:10:45'),(3,1,'create_folder','folder',2,'{\"name\":\"111\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36','2026-04-04 14:12:59'),(4,1,'move_file','file',1,'{\"target\":2}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36','2026-04-04 14:13:18'),(5,1,'create_share','share',3,'{\"file_id\":1,\"type\":1}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36','2026-04-04 14:18:56'),(6,1,'cancel_share','share',3,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36','2026-04-04 14:23:02'),(7,1,'cancel_share','share',2,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36','2026-04-04 14:23:08'),(8,1,'create_share','share',4,'{\"file_id\":1,\"type\":3}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36','2026-04-04 14:23:46'),(9,1,'create_share','share',5,'{\"file_id\":1,\"type\":3}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36','2026-04-04 14:44:00'),(10,1,'cancel_share','share',5,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36','2026-04-04 14:45:02'),(11,1,'cancel_share','share',4,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36','2026-04-04 14:45:05'),(12,1,'cancel_share','share',3,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36','2026-04-04 14:45:10'),(13,1,'cancel_share','share',2,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36','2026-04-04 14:45:14'),(14,1,'rename_file','file',1,'{\"new_name\":\"2121.txt\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36','2026-04-04 14:45:42'),(15,1,'create_share','share',6,'{\"file_id\":1,\"type\":3}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36','2026-04-04 14:45:54'),(16,1,'create_share','share',7,'{\"file_id\":1,\"type\":3}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36','2026-04-04 14:52:21'),(17,2,'upload_file','file',3,'{\"name\":\"libcurl.dll\",\"size\":1389056,\"instant\":false}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36','2026-04-04 15:18:47'),(18,2,'upload_file','file',4,'{\"name\":\"21.png\",\"size\":700083,\"instant\":false}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36','2026-04-04 18:20:42'),(19,1,'upload_file','file',5,'{\"name\":\"21.png\",\"size\":700083,\"instant\":true}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36','2026-04-04 18:22:20'),(20,2,'download_file','file',3,'{\"name\":\"libcurl.dll\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36','2026-04-04 18:27:09'),(21,2,'upload_file','file',6,'{\"name\":\"22.mp4\",\"size\":4869119,\"instant\":false}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36','2026-04-04 20:20:38'),(22,2,'create_share','share',8,'{\"file_id\":6,\"type\":3}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36','2026-04-04 20:21:22'),(23,2,'create_folder','folder',7,'{\"name\":\"1221\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36','2026-04-04 21:08:22'),(24,1,'delete_file','file',14,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36','2026-04-05 00:21:31'),(25,1,'delete_file','file',15,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36','2026-04-05 00:21:34'),(26,1,'delete_file','file',16,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36','2026-04-05 00:21:40'),(27,1,'delete_file','file',17,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36','2026-04-05 00:21:44'),(28,1,'delete_file','file',19,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36','2026-04-05 00:22:50'),(29,1,'delete_file','file',18,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36','2026-04-05 00:26:55'),(30,1,'delete_file','file',20,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36','2026-04-05 00:52:20'),(31,1,'delete_file','file',21,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36','2026-04-05 00:52:25'),(32,1,'delete_file','file',22,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36','2026-04-05 01:41:12'),(33,1,'delete_file','file',23,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36','2026-04-05 01:41:18'),(34,1,'delete_file','file',24,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36','2026-04-05 17:32:24');

#
# Structure for table "settings"
#

DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL COMMENT '设置名称',
  `value` varchar(255) NOT NULL COMMENT '设置值',
  `description` varchar(255) DEFAULT NULL COMMENT '设置描述',
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COMMENT='系统设置表';

#
# Data for table "settings"
#

INSERT INTO `settings` VALUES (1,'directory_browsing','1','启用目录化浏览'),(2,'allow_public_access','1','允许公开访问用户文件');

#
# Structure for table "users"
#

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL COMMENT '用户名',
  `email` varchar(100) NOT NULL COMMENT '邮箱',
  `password` varchar(255) NOT NULL COMMENT '密码哈希',
  `nickname` varchar(50) DEFAULT NULL COMMENT '昵称',
  `avatar` varchar(255) DEFAULT NULL COMMENT '头像URL',
  `storage_limit` bigint(20) unsigned DEFAULT '10737418240' COMMENT '存储空间限制(字节)，默认10GB',
  `storage_used` bigint(20) unsigned DEFAULT '0' COMMENT '已使用存储空间(字节)',
  `status` tinyint(4) DEFAULT '1' COMMENT '状态：0-禁用，1-正常',
  `is_admin` tinyint(4) DEFAULT '0' COMMENT '是否管理员：0-否，1-是',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL,
  `last_login_at` timestamp NULL DEFAULT NULL,
  `last_login_ip` varchar(45) DEFAULT NULL,
  `directory_browsing` tinyint(1) DEFAULT '0' COMMENT '是否启用目录化浏览',
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COMMENT='用户表';

#
# Data for table "users"
#

INSERT INTO `users` VALUES (1,'admin','admin@example.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','管理员',NULL,107374182400,701014,1,1,'2026-04-04 13:32:29',NULL,'2026-04-05 13:36:38','::1',0),(2,'yun','212112@qq.com','$2y$10$KZuxUnh/JuiRoVycgyDt3eyPRBnMv73HjXKAPpktxY3wDk/ypwj7C','yun',NULL,21474836480,13102258,1,0,'2026-04-04 15:05:44',NULL,'2026-04-04 23:01:55','::1',0);

#
# Structure for table "files"
#

DROP TABLE IF EXISTS `files`;
CREATE TABLE `files` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL COMMENT '所属用户ID',
  `parent_id` int(10) unsigned DEFAULT '0' COMMENT '父文件夹ID，0表示根目录',
  `filename` varchar(255) NOT NULL COMMENT '文件名',
  `original_name` varchar(255) NOT NULL COMMENT '原始文件名',
  `file_path` varchar(500) NOT NULL COMMENT '文件存储路径',
  `file_size` bigint(20) unsigned NOT NULL COMMENT '文件大小(字节)',
  `file_type` varchar(100) DEFAULT NULL COMMENT '文件MIME类型',
  `file_extension` varchar(20) DEFAULT NULL COMMENT '文件扩展名',
  `is_folder` tinyint(4) DEFAULT '0' COMMENT '是否为文件夹：0-文件，1-文件夹',
  `hash` varchar(64) DEFAULT NULL COMMENT '文件MD5哈希，用于秒传',
  `status` tinyint(4) DEFAULT '1' COMMENT '状态：0-删除，1-正常',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user_parent` (`user_id`,`parent_id`),
  KEY `idx_hash` (`hash`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_files_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COMMENT='文件表';

#
# Data for table "files"
#

INSERT INTO `files` VALUES (1,1,2,'2121.txt','售后支持.txt','2026/04/58b78a69fd8cd39c4b85534d85f7b532.txt',931,'text/plain','txt',0,'58b78a69fd8cd39c4b85534d85f7b532',1,'2026-04-04 13:35:23',NULL,NULL),(2,1,0,'111','111','',0,NULL,NULL,1,NULL,1,'2026-04-04 14:12:59',NULL,NULL),(3,2,0,'libcurl.dll','libcurl.dll','2026/04/2b275e2cfbe9d7d972718c0eb14238bd.dll',1389056,'application/x-msdownload','dll',0,'2b275e2cfbe9d7d972718c0eb14238bd',1,'2026-04-04 15:18:47',NULL,NULL),(4,2,0,'21.png','21.png','2026/04/2d33746e4cfd216b7ea06fc8a3d384d4.png',700083,'image/png','png',0,'2d33746e4cfd216b7ea06fc8a3d384d4',1,'2026-04-04 18:20:42',NULL,NULL),(5,1,2,'21.png','21.png','2026/04/2d33746e4cfd216b7ea06fc8a3d384d4.png',700083,'image/png','png',0,'2d33746e4cfd216b7ea06fc8a3d384d4',1,'2026-04-04 18:22:20',NULL,NULL),(6,2,0,'22.mp4','22.mp4','2026/04/e193405a811430bc91d8788d58ca6a8e.mp4',4869119,'video/mp4','mp4',0,'e193405a811430bc91d8788d58ca6a8e',1,'2026-04-04 20:20:38',NULL,NULL),(7,2,0,'1221','1221','',0,NULL,NULL,1,NULL,1,'2026-04-04 21:08:22',NULL,NULL),(8,2,7,'libcrypto-3-x64.dll','libcrypto-3-x64.dll','2026/04/9478076f4fe9977abdc0d28dff9bae73.dll',1024000,'','dll',0,'9478076f4fe9977abdc0d28dff9bae73',1,'2026-04-04 21:17:04',NULL,NULL),(9,2,7,'libcrypto-3-x64.dll','libcrypto-3-x64.dll','2026/04/ed5ffa59c8030b227c85f7147ab09c1a.dll',1024000,'','dll',0,'ed5ffa59c8030b227c85f7147ab09c1a',1,'2026-04-04 21:17:06',NULL,NULL),(10,2,7,'libcrypto-3-x64.dll','libcrypto-3-x64.dll','2026/04/8ea413a2b4079e8a5792f0be9a89633b.dll',1024000,'','dll',0,'8ea413a2b4079e8a5792f0be9a89633b',1,'2026-04-04 21:17:14',NULL,NULL),(11,2,7,'libcrypto-3-x64.dll','libcrypto-3-x64.dll','2026/04/f1fa15667bab8601fbadebbdb67b14fe.dll',1024000,'','dll',0,'f1fa15667bab8601fbadebbdb67b14fe',1,'2026-04-04 21:17:16',NULL,NULL),(12,2,7,'libcrypto-3-x64.dll','libcrypto-3-x64.dll','2026/04/184bf31b8432f14826d0b92c2debffb9.dll',1024000,'application/x-msdownload','dll',0,'184bf31b8432f14826d0b92c2debffb9',1,'2026-04-04 21:19:45',NULL,NULL),(13,2,7,'libcrypto-3-x64.dll','libcrypto-3-x64.dll','2026/04/3352db78391cdab239fd228bfb534484.dll',1024000,'application/x-msdownload','dll',0,'3352db78391cdab239fd228bfb534484',1,'2026-04-04 21:19:47',NULL,NULL),(14,1,2,'checkvm-gui-x64.exe','checkvm-gui-x64.exe','2026/04/24c6f629d69fbc56a72361c3c0c8ede1.exe',1024000,'application/x-msdownload','exe',0,'24c6f629d69fbc56a72361c3c0c8ede1',0,'2026-04-04 23:54:40',NULL,'2026-04-05 00:21:31'),(15,1,2,'checkvm-gui-x64.exe','checkvm-gui-x64.exe','2026/04/f4620a4c59a2a1eb244cedbc7fd9135a.exe',1024000,'application/x-msdownload','exe',0,'f4620a4c59a2a1eb244cedbc7fd9135a',0,'2026-04-04 23:54:42',NULL,'2026-04-05 00:21:34'),(16,1,2,'getid.exe','getid.exe','2026/04/4586c591a969c3089b7969727bdbbe95.exe',1024000,'application/x-msdownload','exe',0,'4586c591a969c3089b7969727bdbbe95',0,'2026-04-04 23:55:21',NULL,'2026-04-05 00:21:40'),(17,1,2,'getid.exe','getid.exe','2026/04/6583070322ec8b9f8253deb59d7c219c.exe',1024000,'application/x-msdownload','exe',0,'6583070322ec8b9f8253deb59d7c219c',0,'2026-04-04 23:55:24',NULL,'2026-04-05 00:21:44'),(18,1,2,'getid.exe','getid.exe','2026/04/1802aced329e9726eb82c434abc2ef8d.exe',1024000,'application/x-msdownload','exe',0,'1802aced329e9726eb82c434abc2ef8d',0,'2026-04-05 00:22:15',NULL,'2026-04-05 00:26:55'),(19,1,2,'getid.exe','getid.exe','2026/04/d8062042d240c977b973dee571b9cb51.exe',1024000,'application/x-msdownload','exe',0,'d8062042d240c977b973dee571b9cb51',0,'2026-04-05 00:22:18',NULL,'2026-04-05 00:22:50'),(20,1,2,'getid.exe','getid.exe','2026/04/4df580c4d44e42e9feaf848deb17699f.exe',1024000,'application/x-msdownload','exe',0,'4df580c4d44e42e9feaf848deb17699f',0,'2026-04-05 00:23:07',NULL,'2026-04-05 00:52:20'),(21,1,2,'getid.exe','getid.exe','2026/04/5da7f55b81c0d0200a958ed414edb0de.exe',1024000,'application/x-msdownload','exe',0,'5da7f55b81c0d0200a958ed414edb0de',0,'2026-04-05 00:23:10',NULL,'2026-04-05 00:52:25'),(22,1,2,'getid.exe','getid.exe','2026/04/c8122c4f4b429cf9228d2488d81c2b07.exe',41939666,'application/x-msdownload','exe',0,'c8122c4f4b429cf9228d2488d81c2b07',0,'2026-04-05 01:13:53',NULL,'2026-04-05 01:41:12'),(23,1,2,'getid.exe','getid.exe','2026/04/c5e14f8bc1bc9de8376c1fba7b120536.exe',41939666,'application/x-msdownload','exe',0,'c5e14f8bc1bc9de8376c1fba7b120536',0,'2026-04-05 01:14:02',NULL,'2026-04-05 01:41:18');

#
# Structure for table "shares"
#

DROP TABLE IF EXISTS `shares`;
CREATE TABLE `shares` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL COMMENT '分享者用户ID',
  `file_id` int(10) unsigned NOT NULL COMMENT '分享的文件/文件夹ID',
  `share_code` varchar(32) NOT NULL COMMENT '分享码',
  `share_type` tinyint(4) DEFAULT '1' COMMENT '分享类型：1-公开，2-密码保护',
  `share_password` varchar(255) DEFAULT NULL COMMENT '分享密码',
  `access_count` int(10) unsigned DEFAULT '0' COMMENT '访问次数',
  `download_count` int(10) unsigned DEFAULT '0' COMMENT '下载次数',
  `expire_at` datetime DEFAULT NULL COMMENT '过期时间',
  `status` tinyint(4) DEFAULT '1' COMMENT '状态：0-失效，1-有效',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `share_code` (`share_code`),
  KEY `idx_share_code` (`share_code`),
  KEY `idx_user` (`user_id`),
  KEY `idx_status` (`status`),
  KEY `fk_shares_file` (`file_id`),
  CONSTRAINT `fk_shares_file` FOREIGN KEY (`file_id`) REFERENCES `files` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_shares_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COMMENT='分享表';

#
# Data for table "shares"
#

INSERT INTO `shares` VALUES (2,1,1,'Syc6cc6C',1,NULL,2,1,'2026-04-11 14:10:45',0,'2026-04-04 14:10:45'),(3,1,1,'Ss3VyZjY',1,NULL,0,0,'2026-04-11 14:18:56',0,'2026-04-04 14:18:56'),(4,1,1,'spqoLWgm',3,NULL,0,0,'2026-04-11 14:23:46',0,'2026-04-04 14:23:46'),(5,1,1,'oFQduSIU',3,NULL,1,1,'2026-04-11 14:44:00',0,'2026-04-04 14:44:00'),(6,1,1,'EE98VU8F',3,NULL,0,0,'2026-04-11 14:45:54',1,'2026-04-04 14:45:54'),(7,1,1,'64vO20Vt',3,NULL,2,0,'2026-04-11 14:52:21',1,'2026-04-04 14:52:21'),(8,2,6,'lHrUqe5x',3,NULL,0,0,'2026-04-05 20:21:22',1,'2026-04-04 20:21:22');

#
# Structure for table "offline_tasks"
#

DROP TABLE IF EXISTS `offline_tasks`;
CREATE TABLE `offline_tasks` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL COMMENT '用户ID',
  `task_name` varchar(255) NOT NULL COMMENT '任务名称',
  `download_url` varchar(1024) NOT NULL COMMENT '下载链接',
  `save_path` int(10) unsigned DEFAULT '0' COMMENT '保存位置（文件夹ID）',
  `file_size` bigint(20) unsigned DEFAULT '0' COMMENT '文件大小',
  `downloaded_size` bigint(20) unsigned DEFAULT '0' COMMENT '已下载大小',
  `status` tinyint(1) DEFAULT '0' COMMENT '状态：0-等待中，1-下载中，2-完成，3-失败',
  `error_message` varchar(255) DEFAULT NULL COMMENT '错误信息',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `save_path` (`save_path`),
  CONSTRAINT `offline_tasks_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `offline_tasks_ibfk_2` FOREIGN KEY (`save_path`) REFERENCES `files` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='离线下载任务表';

#
# Data for table "offline_tasks"
#

