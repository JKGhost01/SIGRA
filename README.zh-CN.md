# SIGRA · 全球地震信息与公民网络

**v1.4.0.0 · 全球地图与移动体验** · `20261120.1` · 2026-11-20

> 稳定版本，提供移动端地图、全球地震危险性分区和统一目录。 [1.4.0.0](docs/PRODUCCION.md)

[Español](README.md) · [English](README.en.md) · [Português](README.pt.md) · [Français](README.fr.md) · [Deutsch](README.de.md) · [Italiano](README.it.md) · [日本語](README.ja.md) · [简体中文](README.zh-CN.md) · [العربية](README.ar.md)

本指南以简体中文介绍项目及安装步骤。[完整文档](README.md)和链接的技术指南以西班牙语维护。命令、路径和 API 契约名称保持不变。网站界面目前支持西班牙语和英语。

## 项目简介

SIGRA 是一个开源 PHP 应用，将 **SGC、USGS、EMSC、GeoNet、GEOFON/GFZ 和 INGV** 发布的地震数据汇集到可搜索的地图中，并提供历史记录、筛选器、JSON API 和非公开的公众报告。距离筛选器可以查找指定位置附近的地震。

手机传感器网络处于**实验阶段**。SIGRA 不预测地震，不保证提前预警，也不能替代主管部门或官方预警系统。Android 客户端在独立仓库 [SIGRA_MOBILE](https://github.com/JKGhost01/SIGRA_MOBILE) 中开发。

## 功能与数据来源

- Leaflet 地图、历史记录、详情页，以及按地点、来源、震级和距离筛选。
- SGC 主要覆盖哥伦比亚；USGS、EMSC 和 GEOFON 提供各有局限的全球数据。
- GeoNet 主要覆盖新西兰；INGV 主要覆盖意大利及周边地区。
- Web 和 Android API、非公开公众报告、可安装的 PWA。
- 数据标准化与跨来源关联；某个来源连接失败时保留已存储的地震目录。

各来源的覆盖范围和更新频率不同。HTTP 响应正常并不意味着数据在科学意义上足够及时或完整。请参阅[数据来源与限制](docs/FUENTES_SISMICAS.md)。

## 安装

需要 PHP 8.2 或更高版本并启用 `curl`、`intl`、`mbstring`、`mysqli`，以及 Composer 2 和 MySQL/MariaDB。CodeIgniter 已包含在 `system/` 中；依赖按照 `composer.lock` 安装。验证还需要 Node 22 或更高版本、Python 3 和 PHP-CGI。

```bash
git clone https://github.com/JKGhost01/SIGRA.git
cd SIGRA
composer install
cp .env.example .env
php tools/setup-local.php
```

创建空数据库，在 `.env` 中配置 `database.default.*` 和 `app.baseURL`，然后执行：

```bash
php spark migrate --all
php -S 127.0.0.1:8080 -t public
```

打开 <http://127.0.0.1:8080/index.php/sismos>。新安装应仅公开 `public/` 目录。密钥和权限配置请按照[完整安装指南](docs/INSTALLATION.md)操作。

## 配置与同步

| 配置项 | 用途 |
|---|---|
| `CI_ENVIRONMENT` | 本地使用 `development`，服务器使用 `production`。 |
| `app.baseURL` | 以斜杠结尾的公开 URL。 |
| `APP_API_TOKEN` | 受保护路由使用的 Bearer 令牌。 |
| `database.default.*` | 数据库连接。 |
| `encryption.key` | 私有加密密钥。 |
| `quakes.*` | 数据来源、筛选条件与更新间隔。 |
| `sigra.version`, `sigra.build` | 可选的版本信息覆盖值。 |

地图会自动请求更新。一般同步间隔至少为5分钟；SGC 每个主机的间隔为15分钟。缓存、锁和出错后的暂停状态由各进程共享。cron 为可选项。可以使用以下命令启动普通同步：

```bash
php spark sigra:sync
```

该命令遵守更新间隔，并可能将事件写入数据库。`/acerca-de#sources-health` 显示已记录的连接状态；刷新该面板不会向数据提供方发起请求。部署时请保留 `writable/cache/providers`。

## API、隐私与验证

受保护路由使用 `Authorization: Bearer <APP_API_TOKEN>`。请查看[地震 API](docs/API_SISMOS.md)、[公民网络 API](docs/API_RED.md)和 [OpenAPI](docs/api/openapi.yaml) 契约。契约版本仍为 `v1`。

共享位置是可选的。作为替代方式的 IP 粗略定位使用 `ipapi.co`。不要在公开 issue 中发布令牌、凭据或非公开报告内容。

```bash
composer test
composer analyse
python3 tools/check-git.py
```

MySQL 测试必须使用明确指定、可以丢弃的测试数据库，不得使用生产数据库。

## 更新与相关文档

1.3.1.3 恢复了与 SGC 的 HTTP 兼容性。本地实际连接测试收到 HTTP 200，并标准化了100条事件；随后用户确认部署修复后运行正常。此修订不增加数据库迁移。请查看[需要上传的文件及回退步骤](docs/SGC_CONEXION_2026-11-19.md)。

- [运行与恢复](docs/PRODUCCION.md)
- [架构](docs/architecture/README.md)
- [版本](docs/VERSIONES.md)与[变更记录](CHANGELOG.md)
- [贡献指南](CONTRIBUTING.md)与[行为准则](CODE_OF_CONDUCT.md)
- [MIT 许可证](LICENSE)与[第三方声明](THIRD_PARTY_NOTICES.md)
