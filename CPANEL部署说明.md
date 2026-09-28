# cPanel 上线清单（简体）

把本项目放到 cPanel / 共享主机时按这个顺序做即可。

## 1. 上传文件

1. 使用项目里的 `ai-ar-cpanel-upload.zip`（或自行压缩整个项目文件夹）。
2. 登录 cPanel → **File Manager** → 进入 `public_html`。
3. 新建目录，例如：`ai-ar`。
4. 上传 zip 到 `ai-ar`，右键 **Extract**。
5. 确认能看到 `index.php`、`settings.php`、`api/`、`assets/` 等。

最终路径示例：

```text
public_html/ai-ar/index.php
```

网站地址示例：

```text
https://你的域名.com/ai-ar/
```

## 2. PHP 版本

cPanel → **MultiPHP Manager** / **Select PHP Version**：

- 选 **PHP 8.2 或更高**（推荐 8.3）
- 打开：`curl`、`json`、`openssl`、`mbstring`
- 若要用扫描历史：再开 `pdo_mysql`

## 3. 目录权限

给下面目录写权限（一般 `755`；若 Settings 无法保存再试 `775`）：

```text
storage
storage/settings
storage/logs
```

## 4. HTTPS（手机摄像头必须）

1. cPanel → **SSL/TLS Status** → 为域名开启 AutoSSL。
2. 用 `https://` 打开网站。
3. 可选：编辑根目录 `.htaccess`，取消 HTTPS 强制跳转那几行的注释。

## 5. 配置 AI

打开：

```text
https://你的域名.com/ai-ar/settings.php
```

建议：

| 项 | 值 |
| --- | --- |
| AI Provider | Auto |
| Gemini Model | gemini-flash-latest |
| Agnes Model | agnes-3.0-flash |
| Agnes Endpoint | https://apihub.agnes-ai.com/v1 |
| Gemini / Agnes API Key | 填入你的密钥后 Save |

然后点 **Test Connection**，两边都成功后再测扫描。

> 若你上传的 zip 已包含本地调通的 `storage/settings/runtime.json`，密钥可能已带上；到 Settings 再测一次即可。不要把含密钥的 zip 发给别人。

## 6. 可选：扫描历史

1. cPanel 创建 MySQL 数据库与用户。
2. phpMyAdmin 导入 `database/database.sql`。
3. 编辑 `config/config.php` 填入数据库信息。
4. Settings 开启 Scan history。

## 7. 上线后自测

1. 电脑打开 `https://域名/ai-ar/`  
2. Settings 页扫二维码用手机打开  
3. 允许摄像头 → Scan → 看 AR 框与结果  
4. Provider 分别测 Gemini / Agnes / Auto  

## 8. 常见问题

| 问题 | 处理 |
| --- | --- |
| 摄像头不能开 | 必须 HTTPS；检查浏览器权限 |
| Settings 保存失败 | `storage/settings` 权限不够 |
| AI 失败 | Test Connection；检查 outbound 外网是否被主机禁用 |
| 404 | 确认解压路径，不要多套一层文件夹 |
| 500 | 看 `storage/logs/app.log`；确认 PHP 版本 |

## 9. 安全提醒

- 不要公开分享带 API Key 的压缩包
- `config/`、`includes/`、`storage/` 已有 `.htaccess` 禁止直链
- 密钥只放服务器，不要写进前端 JS
