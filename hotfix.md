## ChangeLog

- 任务日常定时提示过滤锁定用户和企业 #16775
- 企业版一键导入个人版项目前端迭代 2 #16779
- [Checklist] 个人版迁移企业版迭代 1，支持用户授权/查看/筛选项目 #16668


## Diff

https://codingcorp.coding.net/p/coding-dev/git/compare/4315fd3fc03909271299577c1a6d53acd39fb85d...4d2d8b8eb2ca6d0ee9d2028488eb834bd2635fef

## Checklist

### 发布类型

常规更新

### 负责人

@彭博


### 发布服务

| 应用名称 | 发布镜像 | 执行顺序 |
| ---------- | ---------- | ---------- |
| enterprise-front | 20180917.2-enterprise-saas | 1 |
| e-coding | 20180917.2-enterprise-saas | 2 |
| e-scheduler | 20180917.2-enterprise-saas | 3 |


### 服务配置修改

e-coding/doc/mysql/migrate_script/migration-2018-09-12-import-projects.sql
```
-- ----------------------------
-- Table structure for import_project_histories
-- ----------------------------
CREATE TABLE `import_project_histories` (
  `id`                  INT(10) UNSIGNED                NOT NULL AUTO_INCREMENT,
  `platform_id`         INT(10) UNSIGNED                NOT NULL  COMMENT '平台编号',
  `project_id`          INT(10) UNSIGNED                DEFAULT NULL  COMMENT '项目编号',
  `project_name`        VARCHAR(32)                    NOT NULL  COMMENT '项目名称',
  `operator_id`         INT(10) UNSIGNED                NOT NULL  COMMENT '操作者编号',
  `team_id`             INT(10) UNSIGNED                NOT NULL  COMMENT '操作者所在企业编号',
  `import_status`       TINYINT(1)                      NOT NULL  COMMENT '项目导入状态 0-未开始 1-导入中 2-成功 3-失败',
  `import_created_at`   DATETIME                        NOT NULL  COMMENT '批次导入时间',
  `created_at`          DATETIME                        DEFAULT NULL  COMMENT '开始时间',
  `end_at`              DATETIME                        DEFAULT NULL  COMMENT '结束时间',
  `tasks_count`         INT(10) UNSIGNED                NOT NULL  COMMENT '导入任务数',
  `files_count`         INT(10) UNSIGNED                NOT NULL  COMMENT '导入文件数',
  `wikis_count`         INT(10) UNSIGNED                NOT NULL  COMMENT '导入wiki数',
  `code_status`         TINYINT(1)                      NOT NULL  COMMENT '代码仓库导入状态 0-未开始 1-导入中 2-成功 3-失败',
  `updated_at`          DATETIME                        NOT NULL COMMENT '更新时间',
  `error_message`       VARCHAR(100)                   DEFAULT NULL COMMENT '错误消息',
  `deleted_at`          DATETIME                        NOT NULL DEFAULT '1970-01-01 00:00:00',
  PRIMARY KEY (`id`),
  KEY `idx_operator_team_id` (`operator_id`, `team_id`)
)
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci
  COMMENT ='导入项目历史';

-- ----------------------------
-- Table structure for oauth_access_tokens
-- ----------------------------
CREATE TABLE `oauth_access_tokens`(
	`id` INT(11) NOT NULL AUTO_INCREMENT,
	`user_id` INT(11) NOT NULL COMMENT 'user id',
	`sid` VARCHAR(256) NOT NULL COMMENT 'cookies sid',
	`platform_id` INT(11) NOT NULL COMMENT 'oauth platform id',
	`access_token` VARCHAR(256) NOT NULL COMMENT 'oauth access_token',
	`refresh_token` VARCHAR(256) DEFAULT NULL COMMENT 'oauth refresh_token',
	`token_type` VARCHAR(32) DEFAULT NULL COMMENT 'oauth token type is bearer or mac' ,
	`scope` VARCHAR(128) DEFAULT NULL COMMENT 'oauth authorization scope' ,
	`expires_in` INT(11) DEFAULT NULL COMMENT 'oauth expires in',
	`raw_response` MEDIUMTEXT DEFAULT NULL COMMENT 'oauth raw response',
	`expires_at` DATETIME DEFAULT NULL COMMENT 'oauth expires at',
	`created_at` DATETIME NOT NULL,
	`updated_at` DATETIME NOT NULL ,
	`deleted_at` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00' ,
	PRIMARY KEY(`id`) ,
	KEY `oauth_sid_idx`(`platform_id` , `sid`),
	KEY `oauth_user_id_idx`(`platform_id` , `user_id`)

) ENGINE = INNODB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '用户获取的平台授权';

-- 新增Coding个人版平台
INSERT INTO coding.provider_platforms (created_at, updated_at, deleted_at, cn_name, en_name) VALUES ('2018-09-06 15:11:00', '2018-09-06 15:11:00', '1970-01-01 00:00:00', 'CODING个人版', 'coding');
```


### 发布后 master 指向

```
4315fd3fc03909271299577c1a6d53acd39fb85d
```
