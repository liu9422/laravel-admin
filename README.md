# liu9422/laravel-admin

基于 [encore/laravel-admin](https://github.com/z-song/laravel-admin) v1.8 的维护性 fork:面向 **PHP 8.2+ / Laravel 11+**(验证于 Laravel 12)持续做安全加固与缺陷修复。**API、视图层、命名空间 `Encore\Admin` 与原版完全兼容**,存量系统改 require 即可一键换装。

## 与原版的主要差异

**运行环境**
- PHP >= 8.2、Laravel >= 11;移除 doctrine/dbal 依赖(资源生成器改用 Laravel Schema 建表信息 + 内置类型映射)
- 常驻内存适配(Octane/FrankenPHP 方向):`Admin::flushState()` 请求级状态清理体系,静态状态零泄漏、多请求不串页

**安全**
- **CVE-2023-24249**(任意文件上传 → RCE,上游弃维护无补丁):上传字段落盘前校验**最终存储文件名**,内置服务器脚本扩展名黑名单 + `.htaccess/.user.ini` 恒拒,`admin.upload.forbidden_extensions` 可配可关
- 前端资产安全升级(观感不变):jQuery 3.7.1、Bootstrap 3.4.1、select2 4.0.13、moment 2.29.4、CKEditor 4.22.1(本地托管)、sweetalert2 11、bootstrap-fileinput 5.5.4
- `SecurityHeaders` 中间器(nosniff / Referrer-Policy / X-Frame-Options,`admin.security_headers` 可配)

**功能**
- 管理员封禁(status 字段 + 登录/请求双重拦截)
- 面包屑语义化(按菜单标题生成,替代 URL 段直拼)
- belongsToMany 大关联批量同步(2000 关联 35s → 240ms)+ 已选区前端分页
- 内置用户/角色/权限/日志控制器筛选与汉化优化

## 存量系统接入

**方式一:CNB Composer 制品库(推荐,生产用)**——本仓库推送 tag(如 `1.8.99`)后由 `.cnb.yml` 流水线自动打包发布,存量系统配置后 `composer update` 即换装:

```bash
# 认证(全局,一次):访问令牌见 CNB「创建访问令牌」
composer config http-basic.composer.cnb.cool cnb <CNB_TOKEN> -g
```

```json
"repositories": [{ "type": "composer", "url": "<CNB_COMPOSER_URL>" }],
"require":      { "liu9422/laravel-admin": "1.8.99" }
```

**方式二:path 仓库(开发联调用)**

```json
"repositories": [{ "type": "path", "url": "../laravel-admin" }],
"require":      { "liu9422/laravel-admin": "*" }
```

- `replace` 已顶替 `encore/laravel-admin`,`laravel-admin-ext/*` 扩展的依赖解析不受影响;**不要**按 CNB 文档禁用 packagist(`repos.packgist false`)——扩展包仍需从 packagist 解析
- 需 Composer 2.x(CNB 制品库不再支持 1.x)
- 新增的 config / lang 键均有内置回退,不重新 `vendor:publish` 也能生效;需要发布时:`vendor:publish --tag=laravel-admin-assets --tag=laravel-admin-config --tag=laravel-admin-lang`

## License

MIT。基于 z-song/laravel-admin(MIT)修改,原作者著作权保留。
