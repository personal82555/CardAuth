-- 安装记录表 + 项目版本/安装包表
-- 适用于已有数据库的增量升级；新库直接使用 schema.sql

-- ============================================
-- 安装记录表
-- ============================================
CREATE TABLE IF NOT EXISTS `ca_installations` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `project_id` BIGINT UNSIGNED NOT NULL COMMENT '所属项目ID',
    `card_id` BIGINT UNSIGNED DEFAULT NULL COMMENT '关联卡密ID(可空)',
    `card_key` VARCHAR(64) DEFAULT '' COMMENT '卡密(冗余,便于查询)',
    `machine_id` VARCHAR(128) NOT NULL COMMENT '机器指纹',
    `ip` VARCHAR(45) DEFAULT '' COMMENT '最后IP',
    `device_info` TEXT DEFAULT NULL COMMENT '设备信息',
    `client_version` VARCHAR(50) DEFAULT '' COMMENT '客户端版本',
    `status` ENUM('active','deleted') NOT NULL DEFAULT 'active' COMMENT '状态: active=存活, deleted=已删除',
    `install_count` INT UNSIGNED NOT NULL DEFAULT 1 COMMENT '该机器累计激活次数',
    `last_online_at` DATETIME DEFAULT NULL COMMENT '最后上线时间',
    `installed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '安装时间',
    `deleted_at` DATETIME DEFAULT NULL COMMENT '删除时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_project_id` (`project_id`),
    KEY `idx_status` (`status`),
    KEY `idx_project_machine` (`project_id`,`machine_id`,`status`),
    KEY `idx_card_id` (`card_id`),
    KEY `idx_last_online` (`last_online_at`),
    KEY `idx_deleted_at` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='安装记录表';

-- ============================================
-- 项目版本/安装包表
-- ============================================
CREATE TABLE IF NOT EXISTS `ca_project_versions` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `project_id` BIGINT UNSIGNED NOT NULL COMMENT '所属项目ID',
    `version` VARCHAR(50) NOT NULL COMMENT '版本号(如 1.2.0)',
    `file_name` VARCHAR(255) NOT NULL COMMENT '原始文件名',
    `file_path` VARCHAR(500) NOT NULL COMMENT '存储路径(相对public)',
    `file_size` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '文件大小(字节)',
    `checksum` VARCHAR(64) DEFAULT '' COMMENT 'SHA256',
    `changelog` TEXT DEFAULT NULL COMMENT '更新说明',
    `min_client_version` VARCHAR(50) DEFAULT '' COMMENT '强制更新最低版本(可空)',
    `is_force` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '是否强制推送: 0=否, 1=是',
    `is_latest` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '是否最新版本',
    `download_count` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '下载次数',
    `status` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '状态: 0=禁用, 1=正常',
    `created_by` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '发布人ID',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_project_version` (`project_id`,`version`),
    KEY `idx_project_id` (`project_id`),
    KEY `idx_is_latest` (`project_id`,`is_latest`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='项目版本/安装包表';
