# 更新日志

## v1.1.0 (2026-10-08)

### 新增功能：安装统计与版本推送

#### 安装统计
- 新增 `ca_installations` 安装记录表：每次安装独立计数（含已删除）
- 统计维度：总安装次数、存活安装、已删除、今日/本周新增、最后上线时间
- 支持按项目汇总查看安装情况
- 管理端页面：`安装统计`（列表 / 筛选 / 详情 / 软删除 / 恢复 / 批量删除 / 导出 CSV）
- 权限：超级管理员、项目管理员（限绑定项目）、代理（限本人卡密相关）

#### 版本推送（安装包分发）
- 新增 `ca_project_versions` 项目版本表
- 管理端上传安装包（zip/tar/gz/7z/exe/dmg/apk/msi/pkg/rar/bin，≤200MB）
- 支持更新说明、设为最新版本、禁用/启用、删除版本及文件
- 管理端页面：`版本推送`
- 权限：发布/修改/删除仅超级管理员与项目管理员；**代理仅只读**

#### 强制推送
- 版本可开启 `is_force` 或设置 `min_client_version`
- 客户端检查更新时返回 `update_required=true`，提示强制更新
- **仅客户端提示，不阻断** `verify` 授权结果

#### 客户端接口（API Key）
| 接口 | 说明 |
|------|------|
| `POST /api/public/installations/check-update` | 检查更新 + 登记安装/更新在线时间 |
| `POST /api/public/installations/heartbeat` | 轻量心跳，更新 `last_online_at` |
| `POST /api/public/verify` | 成功响应附带更新字段，并同步安装记录 |

`check-update` / `verify` 成功时返回：`update_available`、`update_required`、`latest_version`、`download_url`、`file_size`、`checksum`、`changelog`、`is_force`、`install_id` 等。

#### 管理端接口（JWT）
| 方法 | 路径 | 说明 |
|------|------|------|
| GET | `/api/installations` | 安装列表 |
| GET | `/api/installations/stats` | 安装统计（含已删除） |
| GET | `/api/installations/export` | 导出 CSV |
| DELETE | `/api/installations/{id}` | 标记已删除 |
| POST | `/api/installations/{id}/restore` | 恢复安装 |
| GET | `/api/projects/{projectId}/versions` | 版本列表 |
| POST | `/api/projects/{projectId}/versions` | 发布版本（multipart） |
| PUT | `/api/versions/{id}/force` | 强制推送开关 |
| PUT | `/api/versions/{id}/latest` | 设为最新 |
| DELETE | `/api/versions/{id}` | 删除版本 |

#### 文档
- README 补充安装统计与版本推送 API 文档
- 公开文档页 `/docs` 增加功能介绍、后台使用方法、客户端对接说明与 CURL 示例

#### 数据库升级
- 已有库执行：`backend/database/migrations/20261008_add_installations_and_versions.sql`
- 全新安装：`backend/database/schema.sql` 已包含新表

---

## v1.0.0

初始版本：卡密授权、项目/套餐/卡密管理、代理分销、在线支付商城、授权验证 API、黑名单、SMTP 到期提醒、Docker 部署。
