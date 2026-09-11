# liu9422/laravel-admin

基于 [encore/laravel-admin](https://github.com/z-song/laravel-admin) v1.8 的维护性 fork,面向 **PHP 8.2+ / Laravel 12+**。API、视图层、命名空间 `Encore\Admin` 与原版完全兼容,存量系统按下文步骤换装。

## 升级内容

- 运行环境升级到 **PHP 8.2 / Laravel 12**,移除 doctrine/dbal 依赖
- 修复 **CVE-2023-24249**(上传字段任意文件上传):落盘前校验最终存储文件名,拒绝 php/phtml/phar/asp/jsp 等服务器脚本扩展名及 `.htaccess`/`.user.ini`/`.htpasswd`(清单可在 `admin.upload.forbidden_extensions` 配置)
- 前端资产升级:jQuery 3.7.1、Bootstrap 3.4.1、select2 4.0.13、moment 2.29.4、CKEditor 4.22.1(本地托管)、sweetalert2 11、bootstrap-fileinput 5.5.4
- 新增 `SecurityHeaders` 中间件,默认输出 nosniff / Referrer-Policy / X-Frame-Options 安全响应头(`admin.security_headers` 可配)
- 管理员封禁:`admin_users.status` 字段 + 登录/请求双重拦截
- 面包屑语义化:按菜单标题生成「首页 / 父菜单链 / 动作」,替代 URL 段直拼
- `belongsToMany` 大关联:批量同步性能优化(2000 关联 35s → 240ms)+ 已选区前端分页
- 内置用户/角色/权限/日志控制器:筛选与汉化优化

## 母项目升级步骤

### 1. 替换依赖

**方式一:CNB Composer 制品库(推荐,生产用)**——本仓库推送 tag(如 `1.9.0`)后由 `.cnb.yml` 流水线自动打包发布:

```bash
# 认证(全局,一次):访问令牌见 CNB「创建访问令牌」
composer config http-basic.composer.cnb.cool cnb <CNB_TOKEN> -g
```

```json
"repositories": [{ "type": "composer", "url": "https://composer.cnb.cool/vosbyte.com/lcj/common.service/laravel-admin-frankenphp/-/packages" }],
"require":      { "liu9422/laravel-admin": "1.9.0" }
```

**方式二:path 仓库(本地联调用)**

```json
"repositories": [{ "type": "path", "url": "../laravel-admin" }],
"require":      { "liu9422/laravel-admin": "*" }
```

记得同时从 `require` 中删除原有的 `encore/laravel-admin` 条目。

### 2. 更新依赖

```bash
composer update liu9422/laravel-admin
```

`replace` 已顶替 `encore/laravel-admin`,`laravel-admin-ext/*` 扩展的依赖解析不受影响。

### 3. 发布并执行迁移(管理员封禁 status 字段)

```bash
php artisan vendor:publish --tag=laravel-admin-migrations
php artisan migrate
```

### 4. 重新发布前端资产(必须)

```bash
php artisan vendor:publish --tag=laravel-admin-assets --force
```

不带 `--force` 时同名旧文件不会被覆盖,升级不生效;若对 `public/vendor/laravel-admin` 下资产有本地改动,请先备份比对。

### 5. 配置与语言(可选)

新增的 config / lang 键均有内置回退,不重新发布也能生效;需要发布时:

```bash
php artisan vendor:publish --tag=laravel-admin-config --tag=laravel-admin-lang
```

发布 config 会覆盖本地 `config/admin.php` 的已有改动,如有自定义请先备份。

### 注意事项

- 需 Composer 2.x(CNB 制品库不再支持 1.x)
- **不要**按 CNB 文档禁用 packagist——`laravel-admin-ext/*` 扩展包仍需从 packagist 解析

## License

MIT。基于 z-song/laravel-admin(MIT)修改,原作者著作权保留。
