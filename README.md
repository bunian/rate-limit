<div align="center">

# 🚦 PHP Rate Limit & Queue

**一个即插即用的 PHP 7.4+ 游客请求限速 + 智能排队系统**

基于令牌桶算法与 SQLite 存储，零依赖、零配置，一行代码接入。

[![PHP](https://img.shields.io/badge/PHP-%3E%3D7.4-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![SQLite](https://img.shields.io/badge/SQLite-3.x-003B57?logo=sqlite&logoColor=white)](https://www.sqlite.org/)
[![License](https://img.shields.io/badge/License-MIT-green.svg)](#license)
[![Version](https://img.shields.io/badge/Version-1.0.0-blue.svg)](#更新日志)
[![PRs Welcome](https://img.shields.io/badge/PRs-welcome-brightgreen.svg)](#贡献指南)

[快速开始](#快速开始) · [文档](#文档) · [管理后台](#管理后台) · [工作原理](#工作原理) · [FAQ](#常见问题)

</div>

---

## 📋 目录

- [项目简介](#项目简介)
- [核心特性](#核心特性)
- [环境要求](#环境要求)
- [安装部署](#安装部署)
- [快速开始](#快速开始)
- [使用文档](#使用文档)
- [配置参考](#配置参考)
- [管理后台](#管理后台)
- [工作原理](#工作原理)
- [API 接口](#api-接口)
- [性能调优](#性能调优)
- [安全建议](#安全建议)
- [Web 服务器配置](#web-服务器配置)
- [常见问题](#常见问题)
- [项目结构](#项目结构)
- [贡献指南](#贡献指南)
- [更新日志](#更新日志)
- [License](#license)

---

## 项目简介

`php-rate-limit` 是一个轻量级、零依赖的 PHP 应用层限速排队解决方案。专为**未登录游客（Guest）**的提交请求设计，能够有效防止表单刷取、接口滥用、恶意爬虫等行为，同时通过智能排队机制保障正常用户的访问体验。

与传统的 `sleep()` 或固定窗口限流不同，本系统采用**令牌桶算法**实现平滑限速，并在超限后自动将请求转入 **FIFO 队列**，前端页面自动轮询排队状态，排到后无缝继续业务流程——用户几乎无感知。

> 💡 **设计哲学**：业务零侵入。只需在目标 PHP 文件顶部 `require` 一个文件，限速、排队、日志、后台全部自动生效。

---

## 核心特性

| 特性 | 说明 |
|---|---|
| ⚡ **即插即用** | 业务文件顶部一行 `require` 即可生效，无需修改业务代码 |
| 🪣 **令牌桶限速** | 支持全局限速 + 单IP限速 + 接口级限速，三层独立令牌桶 |
| 📊 **智能排队** | 超限请求自动入队，前端自动轮询，排到后自动重放请求 |
| 🎛️ **管理后台** | 实时仪表盘、参数热配置、黑白名单、日志查询，无需改代码 |
| 📦 **零依赖** | 纯 PHP + SQLite（PDO），无需 Redis、MQ 等任何额外服务 |
| 🔒 **并发安全** | SQLite `BEGIN IMMEDIATE` 写锁 + 事务，令牌消耗原子操作 |
| 🧩 **接口隔离** | 通过 `RL_ACTION` 常量为不同接口设置独立限速策略 |
| 🌐 **双模式输出** | 网页场景返回精美排队页，API 场景返回 JSON 状态 |
| 📝 **完整日志** | 所有限速/排队/放行事件可追溯，支持多维度筛选查询 |
| 🚫 **黑白名单** | 按 IP 或访客指纹精准管控，白名单完全跳过限速 |

---

## 环境要求

- **PHP**：>= 7.4（兼容 7.4 / 8.0 / 8.1 / 8.2 / 8.3）
- **扩展**：PDO + pdo_sqlite（PHP 默认编译开启）
- **存储**：SQLite 3.x（随 PHP 扩展自带）
- **权限**：`data/` 目录需要写入权限
- **Web 服务器**：Nginx / Apache / IIS 均可

### 检查环境

```bash
# 检查 PHP 版本
php -v

# 检查 PDO SQLite 扩展
php -m | grep sqlite

# 如果未开启，在 php.ini 中取消注释：
# extension=pdo_sqlite
```

---

## 安装部署

### 方式一：直接下载

下载最新 Release 包，解压到你的网站目录：

```bash
wget https://github.com/bunian/rate-limit/archive/refs/tags/down.zip
unzip down.zip
mv down rate-limit
```

### 方式二：Git 克隆

```bash
cd /path/to/your/project
git clone https://github.com/bunian/rate-limit.git rate-limit
```


### 目录权限

确保 `data/` 目录可写（SQLite 数据库文件自动创建在此）：

```bash
chmod -R 755 rate-limit/data
chown -R www-data:www-data rate-limit/data   # Nginx
# 或
chown -R apache:apache rate-limit/data       # Apache
```

---

## 快速开始

### 三步接入

**第 1 步**：将 `rate-limit` 目录放入项目

**第 2 步**：在需要限速的 PHP 文件顶部加入一行

```php
<?php
// 只需这一行，后续业务代码无需任何修改
require_once __DIR__ . '/rate-limit/rate_limit.php';

// ===== 以下是正常业务代码 =====
echo "表单提交成功！";
// ... 数据库操作、邮件发送等
```

**第 3 步**：访问管理后台配置参数

```
http://your-domain.com/rate-limit/admin/
```

默认账号：`admin` / `admin123`（首次登录后请立即修改）

### 验证是否生效

快速刷新页面（连续按 F5），如果出现排队页面，说明限速已生效。

---

## 使用文档

### 基础用法

最简单的接入方式，适用于大多数表单提交场景：

```php
<?php
require_once __DIR__ . '/rate-limit/rate_limit.php';

// 业务代码...
```

### 接口级限速

不同接口使用独立的令牌桶，互不影响。通过 `RL_ACTION` 常量区分：

```php
<?php
// 评论提交接口
define('RL_ACTION', 'comment_submit');
require_once __DIR__ . '/rate-limit/rate_limit.php';

// 处理评论...
```

```php
<?php
// 用户注册接口
define('RL_ACTION', 'user_register');
require_once __DIR__ . '/rate-limit/rate_limit.php';

// 处理注册...
```

在管理后台可以为不同 `RL_ACTION` 分别配置速率（当前版本全局统一配置，多速率支持在规划中）。

### API / 前后端分离场景

定义 `RL_JSON_RESPONSE` 常量后，超限时返回 JSON 而非 HTML 排队页：

```php
<?php
define('RL_JSON_RESPONSE', true);
require_once __DIR__ . '/rate-limit/rate_limit.php';

// 正常响应
header('Content-Type: application/json');
echo json_encode(['code' => 0, 'message' => 'success']);
```

**限速时返回示例：**

```json
{
  "code": 429,
  "message": "当前访问人数较多，请耐心等待...",
  "request_id": "a1b2c3d4e5f6...",
  "position": 3,
  "wait_estimate": 5
}
```

**队列满时返回：**

```json
{
  "code": 503,
  "message": "系统繁忙，请稍后再试"
}
```

前端拿到 `request_id` 后，轮询 `?rl_poll={request_id}` 即可获取排队状态，排到后带 `rl_request_id` 重新请求原接口。

### 整站自动接入（推荐）

通过 `.htaccess` 或 `php.ini` 的 `auto_prepend_file`，让指定目录下所有 PHP 文件自动加载限速：

**Apache (.htaccess)：**

```apache
# 在需要限速的目录下创建 .htaccess
php_value auto_prepend_file "/var/www/html/rate-limit/rate_limit.php"
```

**Nginx + PHP-FPM：**

在 Nginx 配置中添加：

```nginx
location ~ \.php$ {
    fastcgi_param PHP_VALUE "auto_prepend_file=/var/www/html/rate-limit/rate_limit.php";
    # ... 其他配置
}
```

**php.ini（全局）：**

```ini
auto_prepend_file = /var/www/html/rate-limit/rate_limit.php
```

> ⚠️ 使用 `auto_prepend_file` 时，注意管理后台目录（`admin/`）会被重复加载，建议在 `rate_limit.php` 中判断当前路径排除后台目录，或为后台单独配置。

### 自定义排队页面

排队页面的文案可在管理后台「参数配置」中修改。如需完全自定义页面样式，可修改 `src/Guard.php` 中的 `outputQueuePage()` 方法，或在业务层捕获 429 状态码后自定义处理。

---

## 配置参考

所有参数均可在**管理后台 → 参数配置**中动态修改，保存后立即生效，无需重启服务。

### 限速参数

| 参数 | 默认值 | 类型 | 说明 |
|---|---|---|---|
| `global_rate` | `10` | int/float | 全局每秒请求数（所有游客总和的平稳处理速率） |
| `global_burst` | `20` | int/float | 全局令牌桶容量（允许的瞬时突发请求数） |
| `ip_rate` | `2` | int/float | 单 IP 每秒请求数 |
| `ip_burst` | `5` | int/float | 单 IP 令牌桶容量 |

### 排队参数

| 参数 | 默认值 | 类型 | 说明 |
|---|---|---|---|
| `queue_max_size` | `100` | int | 队列最大长度，超出返回 503 系统繁忙 |
| `queue_timeout` | `30` | int | 排队超时时间（秒），超时自动移除 |
| `poll_interval` | `1` | int | 前端排队页轮询间隔（秒），建议 1~3 |

### 文案参数

| 参数 | 默认值 | 说明 |
|---|---|---|
| `queue_title` | `系统繁忙` | 排队页面标题 |
| `queue_message` | `当前访问人数较多，请耐心等待...` | 排队页面提示语 |
| `queue_full` | `系统繁忙，请稍后再试` | 队列满时的提示 |
| `queue_timeout_msg` | `排队超时，请重新提交` | 排队超时提示 |

### 其他参数

| 参数 | 默认值 | 说明 |
|---|---|---|
| `admin_user` | `admin` | 管理后台用户名 |
| `admin_pass` | `admin123` | 管理后台密码（建议首次登录后修改） |
| `log_enabled` | `true` | 是否启用日志记录 |
| `log_retention_days` | `30` | 日志保留天数 |
| `json_response` | `false` | 默认是否返回 JSON（可被 `RL_JSON_RESPONSE` 常量覆盖） |

### 配置文件位置

默认配置在 `config.php` 中，管理后台修改的配置存储在 SQLite 的 `config` 表中，**数据库配置优先级高于文件配置**。

---

## 管理后台

访问地址：`http://your-domain.com/rate-limit/admin/`

### 仪表盘

实时监控系统运行状态，每 5 秒自动刷新：

- **统计卡片**：活跃访客数（5分钟内）、当前排队数、今日放行/限速/排队/拒绝数
- **当前队列**：实时显示排队中的请求列表，包含位置、指纹、接口、状态、剩余时间
- **最近日志**：最新 10 条事件日志，颜色区分事件类型
- **配置概览**：当前生效的限速参数一览

### 参数配置

可视化修改所有限速、排队、文案参数，支持管理账号密码修改。保存后立即生效。

### 黑白名单

- **黑名单**：命中后直接返回 403，完全拒绝访问
- **白名单**：命中后完全跳过限速，直接放行
- 支持按 **IP 地址** 或 **访客指纹** 两种维度添加
- 可添加备注说明原因

### 日志查询

- 按事件类型（放行/限速/排队/超时/拒绝/redeem放行）筛选
- 按 IP 模糊搜索
- 按日期范围查询
- 分页浏览（每页 50 条）
- 一键清理 30 天前的过期日志

---

## 工作原理

### 整体架构

```
┌─────────────────────────────────────────────────────────┐
│                    业务 PHP 文件                        │
│         require_once 'rate-limit/rate_limit.php'        │
└──────────────────────────┬──────────────────────────────┘
                           │
                           ▼
┌─────────────────────────────────────────────────────────┐
│                    Guard 调度器                         │
│  ┌──────────┐  ┌──────────┐  ┌──────────┐  ┌─────────┐  │
│  │ Visitor  │→ │Blacklist │→ │RateLimit │→ │  Queue  │  │
│  │ 访客识别  │  │ 黑白名单 │  │ 令牌桶    │  │排队管理 │  │
│  └──────────┘  └──────────┘  └──────────┘  └─────────┘  │
│                           │                             │
│                    ┌──────┴──────┐                      │
│                    │   Logger    │                      │
│                    │  事件日志    │                      │
│                    └─────────────┘                      │
└──────────────────────────┬──────────────────────────────┘
                           │
                           ▼
┌─────────────────────────────────────────────────────────┐
│              SQLite (PDO) 数据存储                       │
│  visitors │ queue │ config │ blacklist │ logs           │
└─────────────────────────────────────────────────────────┘
```

### 请求处理流程

```
请求到达
  │
  ├─ 1. 轮询请求？(rl_poll) ──→ 返回排队状态 JSON
  │
  ├─ 2. 带放行令牌？(rl_request_id) ──→ redeem 验证 ──→ 放行
  │
  ├─ 3. 黑名单检查 ──→ 命中 ──→ 403 拒绝
  │
  ├─ 4. 白名单检查 ──→ 命中 ──→ 直接放行
  │
  ├─ 5. 单IP令牌桶 ──→ 有令牌？
  │     │
  │     ├─ 无 ──→ 入队
  │     │
  │     └─ 有 ──→ 消耗令牌 ──→ 6. 全局令牌桶
  │                    │
  │                    ├─ 无 ──→ 退还IP令牌 ──→ 入队
  │                    │
  │                    └─ 有 ──→ 消耗令牌 ──→ 放行（推动队列前进）
  │
  └─ 入队处理
        │
        ├─ 队列满？──→ 503 系统繁忙
        │
        └─ 入队成功 ──→ 返回排队页/JSON（含 request_id）
                    │
                    └─ 前端轮询 rl_poll
                          │
                          ├─ 等待中 ──→ 返回 position + eta
                          │
                          └─ 已放行(processing) ──→ ready=true
                                              │
                                              └─ 前端带 rl_request_id 重新请求
                                                        │
                                                        └─ 回到步骤 2 → 放行
```

### 令牌桶算法详解

每个限速维度（单IP / 全局 / 接口）维护独立的令牌桶：

```
时间 →   ──────────────────────────────────────→

令牌补充:  +rate/秒  +rate/秒  +rate/秒  ...
           │        │        │
桶容量:    ┌─────────────────────┐ burst
           │                     │
           │   当前令牌数 tokens  │  ← 每次请求 -1
           │                     │
           └─────────────────────┘
           ↑ 超过 burst 的令牌溢出丢弃
```

- **补充**：每次请求时，根据距上次补充的时间差 × rate 计算应补充的令牌数
- **消耗**：有足够令牌则消耗 1 个并放行，否则触发排队
- **突发**：空闲一段时间后令牌攒满，允许用户瞬时连续提交 burst 次

### 排队机制详解

1. **入队**：限速不通过时，生成唯一 `request_id`（32位十六进制），写入 `queue` 表，状态为 `waiting`
2. **轮询**：前端每 `poll_interval` 秒请求 `?rl_poll={request_id}`，后端返回当前位置和预估等待时间
3. **放行推进**：每次有请求成功通过限速时，调用 `advanceQueue(1)` 将队首 1 个 `waiting` 请求标记为 `processing`
4. **重放**：被标记为 `processing` 的请求轮询时得到 `ready=true`，前端自动在 URL 后追加 `rl_request_id` 并跳转
5. **兑换**：后端收到 `rl_request_id`，验证状态为 `processing` 后标记为 `done` 并直接放行（不再消耗令牌）
6. **超时清理**：每次操作时自动清理 `expires_at < now` 的 `waiting` 请求，标记为 `timeout`
7. **历史清理**：`done` 和 `timeout` 状态的记录保留 1 小时后自动删除

### 并发安全保障

- 所有令牌消耗操作在 `BEGIN IMMEDIATE` 事务中执行，确保原子性
- `BEGIN IMMEDIATE` 立即获取数据库写锁，避免多个请求同时修改令牌数
- 队列出队使用 `UPDATE ... WHERE status='waiting'` + 行计数判断，防止重复放行
- 全局不通过时退还已消耗的 IP 令牌，避免双重消耗

---

## API 接口

本系统对外暴露以下 HTTP 接口（均为自动处理，无需手动调用）：

### 排队状态轮询

**请求：**

```
GET /your-page.php?rl_poll={request_id}
```

**响应（等待中）：**

```json
{
  "ready": false,
  "status": "waiting",
  "position": 3,
  "wait_estimate": 5
}
```

**响应（已放行）：**

```json
{
  "ready": true,
  "status": "ready"
}
```

**响应（超时）：**

```json
{
  "ready": false,
  "status": "timeout",
  "message": "排队超时，请重新提交"
}
```

**响应（不存在）：** HTTP 404

```json
{
  "ready": false,
  "status": "not_found",
  "message": "请求不存在或已过期"
}
```

### 放行令牌兑换

**请求：**

```
GET /your-page.php?rl_request_id={request_id}
```

验证成功后直接执行业务代码（HTTP 200），验证失败则继续走正常限速流程。

### 管理后台 AJAX 接口

所有后台接口通过 `POST /admin/index.php` 调用，需带 `X-Requested-With: XMLHttpRequest` 请求头，且需登录态。

| API 参数 | 说明 |
|---|---|
| `api=dashboard` | 获取仪表盘数据 |
| `api=save_settings` | 保存配置参数 |
| `api=clear_queue` | 清空排队队列 |
| `api=cleanup_logs` | 清理过期日志 |
| `api=add_blacklist` | 添加黑白名单规则 |
| `api=remove_blacklist` | 删除黑白名单规则 |

---

## 性能调优

### 按并发量级选择方案

| 并发量级 | 推荐方案 | 说明 |
|---|---|---|
| < 100 QPS | 默认 SQLite | 完全够用，无需任何调整 |
| 100~500 QPS | SQLite + WAL 模式 | 已默认开启 WAL，建议放在 SSD 上 |
| 500~2000 QPS | 替换 Storage 为 Redis | 实现 `StorageInterface`，用 Redis 替换 SQLite |
| > 2000 QPS | Nginx 层限流 + 本系统 | Nginx `limit_req_zone` 做第一层，本系统做精细控制 |

### 令牌桶参数调优指南

**rate（速率）设置：**

```
合理 rate ≈ 服务器每秒能处理的请求数 × 0.7
```

留出 30% 余量应对波动。例如服务器每秒能处理 20 次提交，`global_rate` 设为 14 左右。

**burst（突发）设置：**

```
burst ≈ rate × (2 ~ 5)
```

- 用户体验优先：取大值（×5），允许用户快速连点
- 防刷优先：取小值（×2），严格控制突发

**单IP速率经验值：**

| 场景 | ip_rate | ip_burst |
|---|---|---|
| 表单提交（注册/留言/询盘） | 1 | 3 |
| 评论/发帖 | 1~2 | 3~5 |
| API 查询接口 | 5~10 | 10~20 |
| 搜索接口 | 3~5 | 5~10 |
| 文件上传/导出 | 0.5~1 | 1~2 |

### 队列参数调优

```
queue_max_size ≈ global_rate × 用户可接受等待秒数
```

例如 `global_rate=10`，用户最多等 10 秒 → `queue_max_size=100`。

- 队列太小：稍微有点并发就返回 503，用户体验差
- 队列太大：用户排很久才超时，不如早拒绝
- `queue_timeout` 建议与 `queue_max_size / global_rate` 相当

### SQLite 性能优化

已默认启用的优化：

```sql
PRAGMA journal_mode = WAL;      -- 写前日志，提升并发读性能
PRAGMA busy_timeout = 5000;     -- 锁等待超时 5 秒
```

额外建议：

- 将 `data/` 目录放在 SSD 或 tmpfs（内存文件系统）上
- 定期执行 `VACUUM` 清理数据库碎片（可通过 cron 定时任务）
- 高并发时考虑添加 `PRAGMA synchronous = NORMAL`（牺牲一点安全性换性能）

---

## 安全建议

### 必做项

1. **修改默认密码**：首次登录管理后台后立即修改 `admin` / `admin123`
2. **保护 data 目录**：禁止通过 Web 访问 `data/` 目录（SQLite 数据库文件）
3. **限制后台访问 IP**：管理后台仅允许特定 IP 段访问
4. **使用 HTTPS**：生产环境强制 HTTPS，保护 Cookie 和管理后台登录态

### 推荐项

5. **修改后台路径**：将 `admin/` 目录重命名为不易猜测的名称
6. **设置 Session 安全**：在 `php.ini` 中配置 `session.cookie_httponly = On` 和 `session.cookie_secure = On`
7. **定期清理日志**：在管理后台或通过 cron 清理过期日志，避免数据库膨胀
8. **监控异常流量**：关注仪表盘的"今日拒绝"数，突然飙升可能意味着攻击

### 访客指纹说明

系统通过 `IP + User-Agent + 随机盐` 生成 SHA-256 指纹，并通过 `rl_visitor_id` Cookie 持久化（有效期 1 年）。

- 同一访客更换 IP 但保留 Cookie → 识别为同一人
- 清除 Cookie 后 → 生成新指纹（但 IP 限速仍然生效）
- 指纹仅用于限速统计，不存储任何个人身份信息

---

## Web 服务器配置

### Nginx 配置

```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /var/www/html;

    # 禁止访问 data 目录（SQLite 数据库）
    location /rate-limit/data/ {
        deny all;
        return 404;
    }

    # 限制管理后台访问 IP
    location /rate-limit/admin/ {
        allow 192.168.1.0/24;   # 允许的内网段
        allow 10.0.0.5;         # 允许的特定IP
        deny all;

        # PHP 处理
        location ~ \.php$ {
            fastcgi_pass unix:/run/php/php7.4-fpm.sock;
            fastcgi_index index.php;
            include fastcgi_params;
            fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        }
    }

    # 普通 PHP 处理
    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php7.4-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
}
```

### Nginx 层限流（与本系统配合）

在 Nginx 配置第一层限流，本系统做第二层精细控制：

```nginx
http {
    # 定义限流区域（按 IP）
    limit_req_zone $binary_remote_addr zone=rl_zone:10m rate=5r/s;

    server {
        location /submit/ {
            # 突发 10，nodelay 不延迟直接处理
            limit_req zone=rl_zone burst=10 nodelay;
            limit_req_status 429;

            # ... PHP 处理
        }
    }
}
```

### Apache 配置

**.htaccess（放在 rate-limit 目录下）：**

```apache
# 禁止访问 data 目录
<Directory "data">
    Require all denied
</Directory>

# 限制 admin 目录访问 IP
<Directory "admin">
    Require ip 192.168.1.0/24
    Require ip 10.0.0.5
</Directory>

# 自动接入限速（可选）
<FilesMatch "\.php$">
    php_value auto_prepend_file "/var/www/html/rate-limit/rate_limit.php"
</FilesMatch>
```

---

## 常见问题

### Q1: 接入后页面空白或 500 错误？

**A:** 检查以下几点：
1. PHP 版本是否 >= 7.4：`php -v`
2. pdo_sqlite 扩展是否开启：`php -m | grep sqlite`
3. `data/` 目录是否有写入权限
4. 查看 PHP 错误日志获取具体错误信息

### Q2: 管理后台登录不了？

**A:** 
1. 确认使用默认账号 `admin` / `admin123`
2. 检查浏览器是否禁用了 Cookie（后台登录依赖 Session）
3. 如果修改过密码但忘记了，删除 `data/rate_limit.db` 重置为默认配置

### Q3: 限速不生效，刷新多少次都能通过？

**A:**
1. 确认 `rate_limit.php` 是在业务代码**之前**加载的
2. 检查是否在白名单中
3. 查看 `config.php` 中的 `global_rate` 和 `ip_rate` 是否设得过大
4. 确认访客指纹 Cookie `rl_visitor_id` 正常设置（检查浏览器开发者工具）

### Q4: 排队页面不自动跳转？

**A:**
1. 检查浏览器控制台是否有 JS 报错
2. 确认轮询请求 `?rl_poll=xxx` 是否正常返回 JSON
3. 如果页面是 HTTPS，确保轮询请求也是 HTTPS（混合内容会被拦截）
4. 检查 `poll_interval` 是否设置过大

### Q5: SQLite 数据库文件越来越大？

**A:**
1. 在管理后台「日志查询」页面点击「清理30天前」
2. 或定期执行 SQLite 的 `VACUUM` 命令整理碎片
3. 可以通过 cron 定时任务自动清理：
   ```bash
   sqlite3 /path/to/data/rate_limit.db "DELETE FROM logs WHERE created_at < strftime('%s', 'now', '-30 days'); VACUUM;"
   ```

### Q6: 可以限制已登录用户吗？

**A:** 当前版本针对游客设计。如需对登录用户限速，可以：
- 在登录后将用户标识加入白名单（完全跳过）
- 或修改 `Visitor.php`，将用户 ID 纳入指纹生成逻辑
- 多用户组限速支持在规划中

### Q7: 支持 Redis 吗？

**A:** 当前版本默认使用 SQLite。如需 Redis，可以实现 `Storage` 类的接口，用 Redis 替换底层存储。核心逻辑（RateLimiter / Queue / Guard）无需修改。Redis 版本在规划中。

### Q8: 如何在命令行/CLI 模式下使用？

**A:** CLI 模式下没有 `$_SERVER` 和 Cookie，访客识别会退化。可以通过环境变量传入 IP：
```php
$_SERVER['REMOTE_ADDR'] = getenv('CLIENT_IP') ?? '127.0.0.1';
require_once 'rate-limit/rate_limit.php';
```

### Q9: 多个网站可以共用一个 rate-limit 吗？

**A:** 可以，但建议每个网站使用独立的 `data/` 目录（通过修改 `config.php` 中的 `db_path`），避免数据混淆。

### Q10: 会影响 SEO 吗？

**A:** 正常爬虫访问频率很低，不会触发限速。如果担心，可以将搜索引擎爬虫的 IP 段加入白名单。

---

## 项目结构

```
rate-limit/
├── rate_limit.php              # ★ 介入入口（业务方只需 require 这一个文件）
├── config.php                  # 默认配置文件
├── test.php                    # 测试示例文件
├── README.md                   # 项目文档
│
├── src/                        # 核心源码
│   ├── Autoloader.php          # PSR-4 自动加载器
│   ├── Visitor.php             # 访客识别（IP+UA+Cookie 指纹）
│   ├── Storage.php             # SQLite 存储层（PDO 封装 + 事务锁）
│   ├── RateLimiter.php         # 令牌桶限速器（全局+单IP双桶）
│   ├── Queue.php               # FIFO 排队管理器
│   ├── Guard.php               # 总调度器（限速→排队→放行/拒绝）
│   ├── Blacklist.php           # 黑白名单管理
│   ├── Logger.php              # 事件日志记录与查询
│   └── Admin.php               # 管理后台逻辑（认证+数据接口）
│
├── admin/                      # 管理后台
│   ├── index.php               # 后台入口（路由分发 + AJAX 处理）
│   ├── login.php               # 登录页面
│   ├── dashboard.php           # 仪表盘（实时监控）
│   ├── settings.php            # 参数配置页面
│   ├── blacklist.php           # 黑白名单管理页面
│   ├── logs.php                # 日志查询页面
│   ├── _header.php             # 后台公共头部
│   └── _footer.php             # 后台公共底部
│
├── assets/                     # 静态资源
│   ├── admin.css               # 后台样式
│   └── admin.js                # 后台脚本
│
└── data/                       # 数据目录（需写入权限）
    └── rate_limit.db           # SQLite 数据库（自动创建）
```

### 核心类关系

```
rate_limit.php (入口)
    │
    └── Guard (调度器)
          ├── Visitor          → 生成访客指纹
          ├── Blacklist        → 黑白名单检查
          ├── RateLimiter      → 令牌桶限速
          │     └── Storage    → 数据持久化
          ├── Queue            → 排队管理
          │     └── Storage
          └── Logger           → 事件日志
                └── Storage

Admin (后台)
    ├── Storage
    ├── Queue
    └── Logger
```

---

## 贡献指南

欢迎提交 Issue 和 Pull Request！

### 开发环境搭建

```bash
# 克隆仓库
git clone https://github.com/yourname/php-rate-limit.git
cd php-rate-limit

# 启动本地 PHP 开发服务器
php -S localhost:8080

# 访问测试
# http://localhost:8080/test.php
# http://localhost:8080/admin/
```

### 代码规范

- 遵循 [PSR-12](https://www.php-fig.org/psr/psr-12/) 编码规范
- PHP 7.4 语法，不使用 PHP 8.x 独有特性
- 类名使用 PascalCase，方法名使用 camelCase
- 所有公共方法必须有类型声明（参数和返回值）
- 提交前确保无语法错误：`find . -name "*.php" -exec php -l {} \;`

### 提交 PR 流程

1. Fork 本仓库
2. 创建特性分支：`git checkout -b feature/your-feature`
3. 提交更改：`git commit -m 'Add some feature'`
4. 推送分支：`git push origin feature/your-feature`
5. 提交 Pull Request

### 待实现功能（Roadmap）

- [ ] Redis 存储驱动支持
- [ ] 多接口独立速率配置
- [ ] 登录用户组限速
- [ ] 图形化流量趋势图表
- [ ] 邮件/钉钉告警（异常流量通知）
- [ ] Composer 包发布
- [ ] 单元测试覆盖

---

## 更新日志

### v1.0.0 (2026-08-17)

- 🎉 首次发布
- ✅ 令牌桶限速（全局 + 单IP双桶）
- ✅ FIFO 智能排队（自动轮询 + 无缝重放）
- ✅ 管理后台（仪表盘 + 参数配置 + 黑白名单 + 日志查询）
- ✅ SQLite 存储（零依赖，WAL 模式）
- ✅ 访客指纹识别（IP + UA + Cookie）
- ✅ API 模式（JSON 响应）
- ✅ 接口级隔离（RL_ACTION 常量）

---

## License

[MIT License](LICENSE) © 2026

```
MIT License

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
```

---

<div align="center">

如果这个项目对你有帮助，欢迎给个 ⭐ Star 支持！

[⬆ 回到顶部](#-php-rate-limit--queue)

</div>
