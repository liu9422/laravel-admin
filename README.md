# liu9422/laravel-admin

基于 [encore/laravel-admin](https://github.com/z-song/laravel-admin) v1.8 的维护性 fork,面向 **PHP 8.2+ / Laravel 12+**。API、视图层、命名空间 `Encore\Admin` 与原版完全兼容,存量系统按下文步骤换装。

## 升级内容

- 运行环境升级到 **PHP 8.2 / Laravel 12**,移除 doctrine/dbal 依赖
- 修复 **CVE-2023-24249**(上传字段任意文件上传):落盘前校验最终存储文件名,拒绝 php/phtml/phar/asp/jsp 等服务器脚本扩展名及 `.htaccess`/`.user.ini`/`.htpasswd`(清单可在 `admin.upload.forbidden_extensions` 配置)
- 前端资产升级,消除 **10 个已知 CVE**(对照见下表):jQuery 3.7.1、Bootstrap 3.4.1、select2 4.0.13、moment 2.29.4、CKEditor 4.22.1(本地托管)、sweetalert2 11、bootstrap-fileinput 5.5.4
- 新增 `SecurityHeaders` 中间件,默认输出 nosniff / Referrer-Policy / X-Frame-Options 安全响应头(`admin.security_headers` 可配)
- 管理员封禁:`admin_users.status` 字段 + 登录/请求双重拦截
- 面包屑语义化:按菜单标题生成「首页 / 父菜单链 / 动作」,替代 URL 段直拼
- `belongsToMany` 大关联:批量同步性能优化(2000 关联 35s → 240ms)+ 已选区前端分页
- 内置用户/角色/权限/日志控制器:筛选与汉化优化

### 前端 CVE 修复对照

| CVE | 组件 | 旧版本(受影响) | 修复版本 | 漏洞类型 |
|---|---|---|---|---|
| CVE-2019-11358 | jQuery | 2.1.4 | 3.4.0 | 原型污染(`jQuery.extend`) |
| CVE-2020-11022 | jQuery | 2.1.4 | 3.5.0 | 原型污染(`jQuery.extend`) |
| CVE-2020-11023 | jQuery | 2.1.4 | 3.5.0 | XSS(`htmlPrefilter` 正则绕过) |
| CVE-2016-10735 | Bootstrap | 3.3.4 | 3.4.0 | XSS(Collapse `data-parent`) |
| CVE-2018-14041 | Bootstrap | 3.3.4 | 3.4.0 | XSS(ScrollSpy `data-target`) |
| CVE-2018-20676 | Bootstrap | 3.3.4 | 3.4.0 | XSS(Tooltip `template`) |
| CVE-2018-20677 | Bootstrap | 3.3.4 | 3.4.0 | XSS(Collapse/Affix 组件配置) |
| CVE-2019-8331 | Bootstrap | 3.3.4 | 3.4.1 | XSS(tooltip/popover/carousel/collapse,补全 3.4.0 的不完整修复) |
| CVE-2022-24785 | moment | 2.10.6 | 2.29.2 | 路径穿越(动态加载 locale,服务端场景) |
| CVE-2022-31129 | moment | 2.10.6 | 2.29.4 | ReDoS(正则拒绝服务) |

> jQuery 2.x 早已停止维护,以上漏洞只在 3.x 修复。CKEditor 由 CDN 4.5.10(2016)改为本地托管 **4.22.1**(LTS 安全版),涵盖七年累积安全修复;select2 4.0.3→4.0.13、sweetalert2 7.26.12→11、bootstrap-fileinput 4.5.2→5.5.4 为常规升级,无已知 CVE。

## 项目升级步骤

### 1. 替换依赖

```json
"require":      { "liu9422/laravel-admin": "1.9.0" }
```

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

- 需 Composer 2.x

## License

MIT。基于 z-song/laravel-admin(MIT)修改,原作者著作权保留。
