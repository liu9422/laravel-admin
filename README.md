# liu9422/laravel-admin

基于 [encore/laravel-admin](https://github.com/z-song/laravel-admin) v1.8 的维护性 fork,面向 **PHP 8.2+ / Laravel 12+**。API、视图层、命名空间 `Encore\Admin` 与原版完全兼容,存量系统按下文步骤换装。

## 升级内容

- 运行环境升级到 **PHP 8.2 / Laravel 12**,移除 doctrine/dbal 依赖
- 修复 **CVE-2023-24249**(上传字段任意文件上传):落盘前校验最终存储文件名,拒绝 php/phtml/phar/asp/jsp 等服务器脚本扩展名及 `.htaccess`/`.user.ini`/`.htpasswd`(清单可在 `admin.upload.forbidden_extensions` 配置)
- 前端资产升级,消除 **26 个已知 CVE**(对照见下表):jQuery 3.7.1、Bootstrap 3.4.1、select2 4.0.13、moment 2.29.4、CKEditor 4.22.1(本地托管)、sweetalert2 11、bootstrap-fileinput 5.5.4
- 新增 `SecurityHeaders` 中间件,默认输出 nosniff / Referrer-Policy / X-Frame-Options 安全响应头(`admin.security_headers` 可配)
- 管理员封禁:`admin_users.status` 字段 + 登录/请求双重拦截
- 面包屑语义化:按菜单标题生成「首页 / 父菜单链 / 动作」,替代 URL 段直拼
- `belongsToMany` 大关联:批量同步性能优化(2000 关联 35s → 240ms)+ 已选区前端分页
- 内置用户/角色/权限/日志控制器:筛选与汉化优化

### 前端 CVE 修复对照

| CVE | 组件(旧 → 新) | 修复版本 | 漏洞类型 |
|---|---|---|---|
| CVE-2015-9251 | jQuery 2.1.4 → 3.7.1 | 3.0.0 | XSS(跨域 Ajax 响应直接注入) |
| CVE-2019-11358 | jQuery 2.1.4 → 3.7.1 | 3.4.0 | 原型污染(`jQuery.extend`) |
| CVE-2020-11022 | jQuery 2.1.4 → 3.7.1 | 3.5.0 | 原型污染(`jQuery.extend`) |
| CVE-2020-11023 | jQuery 2.1.4 → 3.7.1 | 3.5.0 | XSS(`htmlPrefilter` 正则绕过) |
| CVE-2016-10735 | Bootstrap 3.3.4 → 3.4.1 | 3.4.0 | XSS(`data-target` 属性) |
| CVE-2018-14040 | Bootstrap 3.3.4 → 3.4.1 | 3.4.0 | XSS(Collapse `data-parent`) |
| CVE-2018-14041 | Bootstrap 3.3.4 → 3.4.1 | 3.4.0 | XSS(ScrollSpy `data-target`) |
| CVE-2018-14042 | Bootstrap 3.3.4 → 3.4.1 | 3.4.0 | XSS(Tooltip `data-container`) |
| CVE-2018-20676 | Bootstrap 3.3.4 → 3.4.1 | 3.4.0 | XSS(Tooltip `data-viewport`) |
| CVE-2018-20677 | Bootstrap 3.3.4 → 3.4.1 | 3.4.0 | XSS(Affix `target` 配置) |
| CVE-2019-8331 | Bootstrap 3.3.4 → 3.4.1 | 3.4.1 | XSS(tooltip/popover/carousel/collapse,补全 3.4.0 的不完整修复) |
| CVE-2016-4055 | moment 2.10.6 → 2.29.4 | 2.11.2 | ReDoS |
| CVE-2017-18214 | moment 2.10.6 → 2.29.4 | 2.19.3 | ReDoS |
| CVE-2022-24785 | moment 2.10.6 → 2.29.4 | 2.29.2 | 路径穿越(动态加载 locale) |
| CVE-2022-31129 | moment 2.10.6 → 2.29.4 | 2.29.4 | ReDoS(正则复杂度) |
| CVE-2016-10744 | select2 4.0.3 → 4.0.13 | 4.0.6 | XSS |
| CVE-2018-17960 | CKEditor 4.5.10 → 4.22.1 | 4.11.0 | XSS |
| CVE-2020-9281 | CKEditor 4.5.10 → 4.22.1 | 4.14.0 | HTML Data Processor 漏洞 |
| CVE-2020-27193 | CKEditor 4.5.10 → 4.22.1 | 4.15.1 | XSS |
| CVE-2021-26272 | CKEditor 4.5.10 → 4.22.1 | 4.16.0 | ReDoS(粘贴特殊内容触发) |
| CVE-2021-32809 | CKEditor 4.5.10 → 4.22.1 | 4.16.2 | XSS(剪贴板功能) |
| CVE-2021-37695 | CKEditor 4.5.10 → 4.22.1 | 4.16.2 | XSS(Fake Objects 功能) |
| CVE-2021-41164 | CKEditor 4.5.10 → 4.22.1 | 4.17.0 | XSS(ACF 内容过滤器绕过) |
| CVE-2021-41165 | CKEditor 4.5.10 → 4.22.1 | 4.17.0 | XSS(HTML 注释) |
| CVE-2022-24728 | CKEditor 4.5.10 → 4.22.1 | 4.18.0 | XSS |
| CVE-2022-24729 | CKEditor 4.5.10 → 4.22.1 | 4.18.0 | XSS |

> 数据来源:[OSV 漏洞库](https://osv.dev)按各库旧版本逐一查询。已知边界情况:
>
> - **CVE-2024-6485**(Bootstrap button `data-loading-text` XSS):Bootstrap 3 已停止维护,官方无 3.x 修复,3.4.1 仍被标记受影响;利用前提是攻击者已能向页面注入 HTML 属性,属二次利用,风险有限(关联的 CVE-2024-6484 已被官方撤销)
> - **CVE-2024-24815**(CKEditor CDATA 检测 XSS):修复仅存在于商业授权的 4.24.0-lts 及之后;4.22.1 是 CKEditor 4 最后一个开源版本
> - CKEditor 的 CVE-2024-43407(GeSHi 插件)、CVE-2024-24816 / CVE-2023-4771(samples 示例页):本包构建未打包相关插件与示例,不适用
> - jQuery 2.x 已停止维护,漏洞仅在 3.x 修复;CKEditor 由 CDN 4.5.10(2016)改为本地托管;sweetalert2 7.26.12→11、bootstrap-fileinput 4.5.2→5.5.4、AdminLTE、flatpickr 等其余打包库经 OSV 查询无已知漏洞

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
