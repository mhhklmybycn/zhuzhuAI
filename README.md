# AI 创作平台

![PHP](https://img.shields.io/badge/PHP-%E2%89%A57.4-777BB4?logo=php&logoColor=fff)
![MySQL](https://img.shields.io/badge/MySQL-%E2%89%A55.7-4479A1?logo=mysql&logoColor=fff)
![License](https://img.shields.io/badge/License-MIT-green)

基于 **PHP + MySQL + 原生 HTML/CSS/JS** 的 [猪猪 AI（pigsec.cn）](https://www.pigsec.cn/) 统一图像与视频 API 调用平台。内置一键安装向导、计费预览、幂等提交、任务状态轮询等完整生产级功能。

---

## ✨ 功能特性

- 🔧 **一键安装向导** — 自动检测 PHP/PDO/cURL/权限，3 步完成部署
- 🖼 **图像生成** — 简单 / 专业两种模式，支持参考图、蒙版、自定义分辨率
- 🎬 **视频生成** — 简单 / 专业两种模式，支持首/尾帧、关键帧、分段转场、预设音色
- 💰 **计费预览** — 提交前预估积分消耗，retail 模型自动携带报价单号
- 🔄 **幂等提交** — 自动生成 `Idempotency-Key`，重试不重复扣费
- 📊 **用量统计** — 后台实时查看今日 / 历史按模型消耗量
- 👥 **多用户** — 管理员可分配 / 禁用 / 重置前台登录账号
- 🔒 **服务端代理** — API 密钥只存服务器，浏览器不接触
- 🛡 **安全加固** — PDO 预处理防 SQL 注入，统一输出转义防 XSS

## 🧰 技术栈

| 层 | 技术 |
|---|---|
| 后端 | PHP 7.4+（面向对象，PDO 单例） |
| 数据库 | MySQL 5.7+ / MariaDB 10.2+（UTF-8mb4） |
| 前端 | 原生 HTML / CSS / JavaScript（无构建工具） |
| HTTP | cURL（上游 API 调用） |
| Web | Apache（推荐，含 .htaccess）/ Nginx |

## 📁 目录结构

```
├── index.php              前台首页（模型列表与能力信息）
├── login.php              前台用户登录
├── logout.php             前台用户退出
├── image.php              图像生成页（简单/专业模式 + 计费预览）
├── video.php              视频生成页（简单/专业模式 + 计费预览）
├── tasks.php              任务管理页（进行中 / 已完成）
├── _header.php            前台公共导航
│
├── admin/                 管理后台
│   ├── login.php          管理员登录
│   ├── index.php          用量概览仪表盘
│   ├── users.php          前台用户管理
│   ├── settings.php       站点设置（名称 + API 密钥）
│   ├── logout.php
│   └── _header.php
│
├── install/
│   └── index.php          🔨 一键安装向导（首次访问自动进入）
│
├── includes/              公共代码
│   ├── bootstrap.php      全局引导（安装检测 + DB 容错 + 自动跳转）
│   ├── db.php             PDO 数据库单例
│   ├── functions.php      会话 / 权限 / 输出 / 工具函数
│   └── apiclient.php      猪猪 AI 统一 API 客户端
│
├── api/                   🛡 前端 Ajax 代理（隐藏 API 密钥）
│   ├── models.php         获取模型能力列表
│   ├── billing.php        计费预览
│   ├── generate_image.php 提交图像生成（multipart）
│   ├── generate_video.php 提交视频生成（multipart）
│   ├── task_status.php    查询单任务状态
│   ├── tasks.php          当前用户任务列表
│   └── result.php         结果内容代理（鉴权 + 本地缓存）
│
├── assets/
│   ├── css/style.css
│   └── js/{common,image,video,tasks}.js
│
├── config/                📦 安装后生成 config.php（禁止外部访问）
└── storage/results/       💾 结果文件缓存（禁止外部直接访问）
```

## 🚀 快速部署

### 1. 环境准备

| 依赖 | 最低版本 | 必需扩展 |
|---|---|---|
| PHP | 7.4 | `pdo_mysql` · `curl` · `mbstring` · `fileinfo` · `json` · `openssl` |
| MySQL | 5.7 | — |
| Web | Apache 2.4+ / Nginx 1.18+ | — |

PHP 扩展可在 `php.ini` 中取消注释：

```ini
extension_dir = "ext"
extension = pdo_mysql
extension = curl
extension = mbstring
extension = fileinfo
extension = openssl
```

### 2. 上传并赋权

```bash
# 将项目上传到网站根目录后执行
chmod -R 775 config install storage
```

### 3. 运行安装向导

浏览器访问域名，若未安装会自动跳转到 `install/index.php`：

1. **环境检测** — 自动校验 PHP 版本、扩展、目录权限，全部通过后点「下一步」
2. **填写信息** — 数据库连接（库不存在会自动创建）、网站名称、管理员账号密码、猪猪 AI API 密钥（X-API-Key）
3. **完成** — 向导自动建库建表、写入初始数据

> 🛡 **安全提醒**：安装成功后建议删除或限制 `install/` 目录访问。

### 4. 访问入口

| 入口 | URL |
|---|---|
| 前台首页 | `http://你的域名/` |
| 后台管理 | `http://你的域名/admin/login.php` |

## 📖 使用指南

### 🔑 后台（管理员）

| 功能 | 说明 |
|---|---|
| **用量概览** | 今日任务数 / 图像 / 视频 / 积分消耗；今日按模型统计；最近 30 天历史按天统计；模型累计排行 |
| **用户管理** | 添加 / 删除前台登录账号、启用禁用、重置密码 |
| **站点设置** | 修改网站名称、更新 API 密钥 |

### 👤 前台（用户）

| 页面 | 说明 |
|---|---|
| **模型列表** | 查看已启用的图像 / 视频模型及能力边界 |
| **图像生成 · 简单模式** | 选择模型 · 提示词 · 生成模式（文生图 / 图生图） · 数量 1~4 · 比例 |
| **图像生成 · 专业模式** | 额外支持：分辨率档位 · 质量 · 自定义宽高 · 参考图（≤ 4 张、每张 ≤ 12 MiB） · 蒙版（≤ 4 MiB） |
| **视频生成 · 简单模式** | 选择模型 · 提示词 · 比例 · 时长 **≤ 15 秒** · 分辨率 |
| **视频生成 · 专业模式** | 额外支持：首 / 尾帧 · 参考图（2~7 张） · 关键帧（≤ 4 个、严格递增、1/3 秒网格） · 预设音色（AUDIO_0/1/2） · 分段转场（≤ 19 段、累计 ≤ 15 秒） |
| **计费预览** | 提交前必须先获取；retail_pricing 模型返回 `quote_id` 并自动携带；若上游返回 409 `quote_stale`（未扣费），需重新预览 |
| **任务管理** | 进行中任务每 5 秒自动轮询；成功任务可在线查看 / 下载结果 |

## 🤔 常见问题

<details>
<summary><b>Q: 怎么重新跑安装向导？</b></summary>

删除以下两个文件后访问首页即可：

```bash
rm config/config.php install/install.lock
```

> 系统内置容错：即使标记为已安装，**只要数据库连不上就会自动删除锁文件并跳回安装向导**，不会再出现白屏。

</details>

<details>
<summary><b>Q: API 调用失败怎么办？</b></summary>

1. 后台「站点设置」里确认 API 密钥正确
2. 服务器能访问 `https://www.pigsec.cn`（`curl https://www.pigsec.cn/openapi/v1/ai/models -H "X-API-Key: 你的密钥"` 测试）
3. 如果出现 SSL 错误 `unable to get local issuer certificate`，需要配置 PHP 的 `curl.cainfo` 指向 CA 证书包（[cacert.pem](https://curl.se/ca/cacert.pem)）

</details>

<details>
<summary><b>Q: 结果图片 / 视频显示不出来？</b></summary>

- 确认 `storage/results` 目录可写
- 结果通过 `api/result.php` 鉴权代理输出，确保浏览器未禁用 Cookie
- 临时 `.htaccess` 禁止了 `storage/` 直访（必须经由代理），属正常行为

</details>

<details>
<summary><b>Q: Nginx 需要额外配置吗？</b></summary>

Apache 用户无需改动（`config/.htaccess` 已内置禁止直访）。**Nginx 用户**请在站点配置里追加：

```nginx
location ~* /(config|storage)/ {
    deny all;
    return 404;
}
```

</details>

## 🔐 安全架构

```
浏览器  ──(无密钥)──▶  api/*.php  ──(服务端持 X-API-Key)──▶  pigsec.cn
                         │
                         ├── 白名单字段校验（防未知参数注入）
                         ├── Idempotency-Key 自动生成（防重放扣费）
                         ├── Multipart 文件校验（大小 / 数量 / 类型）
                         └── API 响应 → 本地入库 → 前端轮询
```

- 密钥只存 `settings` 表，**永不暴露给前端**
- 用户密码 `password_hash`（bcrypt）存储
- 全部 SQL 走 PDO 预处理
- 所有输出 `htmlspecialchars` 转义（防 XSS）
- 结果文件 `.htaccess` 禁止直访，统一经 `api/result.php` 鉴权代理

## 📝 许可证

MIT
