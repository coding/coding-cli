#Coding Release 发布工具


## 简介

用于创建 Coding 发布使用的 Release Checklist 文档

## 命令

### 登录用户

示例命令：`coding-cli login -u username` 

在用户目录下创建一个 ~/.coding_release_rc 文件保存 session

### 生成 Release 文件

在当前目录生成 Markdown 格式的 Release 文件

示例命令：`coding-cli release master enterprise-saas  -o release-20181030.1-enterprise.md -p enterprise-saas -t normal -n 1 -c ~/.coding_release.yml`

查看帮助：`coding-cli release --h`

![图片](https://dn-coding-net-production-pp.codehub.cn/f8f39bb8-a3f9-44ba-b6b4-747e8aa2f8d0.png)


### 创建环境变量文件

示例命令：`coding-cli env add -c "redis.host=17.0.0.1" -f add_redis_host`

![图片](https://dn-coding-net-production-pp.codehub.cn/36e64407-5d03-4764-b2c3-a0775e2e6777.png)

### 创建 pt-online-schema-change 数据库表结构更新文件

示例命令：`coding-cli pt -t sample -a "add column nickname varchar(32) default null comment '昵称' after id" -f sample_table_add_nickname_col`

![图片](https://dn-coding-net-production-pp.codehub.cn/993f59be-f40c-40c3-bba0-9a06a80d94d6.png)

### 创建数据库数据更新 SQL 文件

示例命令：`coding-cli sql -c " UPDATE sample SET nickname='tom' WHERE id = 1 " -f update_sample_nickname`

![图片](https://dn-coding-net-production-pp.codehub.cn/81123b37-fcd1-476a-9a47-0a5c46e749f4.png)


### .coding_release.yml 文件示例

```yml
eid: empty
service:
- name: e-coding
  migrate: enterprise/app/e-coding/doc/mysql/migrate_script
  source:
  - enterprise/app/e-coding
- name: e-front
  migrate:
  source:
  - frontend/coding-front-v2
```