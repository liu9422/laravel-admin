<?php

namespace Encore\Admin\Form\Field;

use Encore\Admin\Form;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

trait UploadField
{
    /**
     * Upload directory.
     *
     * @var string
     */
    protected $directory = '';

    /**
     * File name.
     *
     * @var null
     */
    protected $name = null;

    /**
     * Storage instance.
     *
     * @var \Illuminate\Filesystem\Filesystem
     */
    protected $storage = '';

    /**
     * If use unique name to store upload file.
     *
     * @var bool
     */
    protected $useUniqueName = false;

    /**
     * If use sequence name to store upload file.
     *
     * @var bool
     */
    protected $useSequenceName = false;

    /**
     * Retain file when delete record from DB.
     *
     * @var bool
     */
    protected $retainable = false;

    /**
     * @var bool
     */
    protected $downloadable = true;

    /**
     * Configuration for setting up file actions for newly selected file thumbnails in the preview window.
     *
     * @var array
     */
    protected $fileActionSettings = [
        'showRemove' => false,
        'showDrag'   => false,
    ];

    /**
     * Controls the storage permission. Could be 'private' or 'public'.
     *
     * @var string
     */
    protected $storagePermission;

    /**
     * @var array
     */
    protected $fileTypes = [
        'image'  => '/^(gif|png|jpe?g|svg|webp)$/i',
        'html'   => '/^(htm|html)$/i',
        'office' => '/^(docx?|xlsx?|pptx?|pps|potx?)$/i',
        'gdocs'  => '/^(docx?|xlsx?|pptx?|pps|potx?|rtf|ods|odt|pages|ai|dxf|ttf|tiff?|wmf|e?ps)$/i',
        'text'   => '/^(txt|md|csv|nfo|ini|json|php|js|css|ts|sql)$/i',
        'video'  => '/^(og?|mp4|webm|mp?g|mov|3gp)$/i',
        'audio'  => '/^(og?|mp3|mp?g|wav)$/i',
        'pdf'    => '/^(pdf)$/i',
        'flash'  => '/^(swf)$/i',
    ];

    /**
     * 默认拒绝落盘的服务器脚本扩展名(CVE-2023-24249 防护)。
     *
     * 配置 `admin.upload.forbidden_extensions` 存在时覆盖此清单;
     * 设为空数组可显式关闭防护。
     *
     * @var array
     */
    protected static $forbiddenExtensions = [
        // PHP 及衍生(PHP 主机直接执行)
        'php', 'php3', 'php4', 'php5', 'php6', 'php7', 'php8',
        'phtml', 'pht', 'phts', 'phps', 'phar',
        // 其它服务端脚本栈(同目录被其它容器/CGI 解析时)
        'asp', 'aspx', 'ascx', 'asa', 'cer', 'cdx',
        'jsp', 'jspx', 'jspa', 'jsw', 'jsv',
        'cfm', 'shtml',
    ];

    /**
     * 无论扩展名规则如何,始终拒绝写入的文件名
     * (可改变服务器/PHP 行为的配置文件)。
     *
     * 配置 `admin.upload.forbidden_filenames` 存在时覆盖此清单。
     *
     * @var array
     */
    protected static $forbiddenFilenames = ['.htaccess', '.user.ini', '.htpasswd'];

    /**
     * @var string
     */
    protected $pathColumn;

    /**
     * Initialize the storage instance.
     *
     * @return void.
     */
    protected function initStorage()
    {
        $this->disk(config('admin.upload.disk'));
    }

    /**
     * Set default options form image field.
     *
     * @return void
     */
    protected function setupDefaultOptions()
    {
        $defaults = [
            'overwriteInitial'     => false,
            'initialPreviewAsData' => true,
            'initialPreviewShowDelete' => true,
            'msgPlaceholder'       => trans('admin.choose_file'),
            'browseLabel'          => trans('admin.browse'),
            'cancelLabel'          => trans('admin.cancel'),
            'showRemove'           => false,
            'showUpload'           => false,
            'showCancel'           => false,
            'dropZoneEnabled'      => true,
            'showCaption'          => false,
            'browseOnZoneClick'    => true,
            'showBrowse'           => !($this->options['showPreview'] ?? true),
            'deleteExtraData'      => [
                $this->formatName($this->column) => static::FILE_DELETE_FLAG,
                static::FILE_DELETE_FLAG         => '',
                '_token'                         => csrf_token(),
                '_method'                        => 'PUT',
            ],
        ];

        foreach ($defaults as $key => $value) {
            if (!array_key_exists($key, $this->options)) {
                $this->options[$key] = $value;
            }
        }

        if ($this->form instanceof Form && !array_key_exists('deleteUrl', $this->options)) {
            $this->options['deleteUrl'] = $this->form->resource().'/'.$this->form->model()->getKey();
        }

        $this->options['fileActionSettings'] = $this->fileActionSettings;
    }

    /**
     * 按应用 locale 注入 fileinput 语言(zh 需要 locale 文件与 language 选项)。
     *
     * @return void
     */
    protected function setupFileinputLocale()
    {
        $locale = config('app.locale');

        $lang = $locale === 'zh-CN' ? 'zh' : ($locale === 'zh-TW' ? 'zh-TW' : null);

        if (!$lang) {
            return;
        }

        $this->options(['language' => $lang]);

        \Encore\Admin\Admin::js('/vendor/laravel-admin/bootstrap-fileinput/js/locales/'.$lang.'.js');
    }

    /**
     * Set preview options form image field.
     *
     * @return void
     */
    protected function setupPreviewOptions()
    {
        $initialPreviewConfig = $this->initialPreviewConfig();

        $this->options(compact('initialPreviewConfig'));
    }

    /**
     * @return array|bool
     */
    protected function guessPreviewType($file)
    {
        $filetype = 'other';
        $ext = strtok(strtolower(pathinfo($file, PATHINFO_EXTENSION)), '?');

        foreach ($this->fileTypes as $type => $pattern) {
            if (preg_match($pattern, $ext) === 1) {
                $filetype = $type;
                break;
            }
        }

        $extra = ['type' => $filetype];

        if ($filetype == 'video') {
            $extra['filetype'] = "video/{$ext}";
        }

        if ($filetype == 'audio') {
            $extra['filetype'] = "audio/{$ext}";
        }

        if ($this->downloadable) {
            $extra['downloadUrl'] = $this->objectUrl($file);
        }

        return $extra;
    }

    /**
     * Indicates if the underlying field is downloadable.
     *
     * @param bool $downloadable
     *
     * @return $this
     */
    public function downloadable($downloadable = true)
    {
        $this->downloadable = $downloadable;

        return $this;
    }

    /**
     * Allow use to remove file.
     *
     * @return $this
     */
    public function removable()
    {
        $this->fileActionSettings['showRemove'] = true;

        return $this;
    }

    /**
     * Indicates if the underlying field is retainable.
     *
     * @return $this
     */
    public function retainable($retainable = true)
    {
        $this->retainable = $retainable;

        return $this;
    }

    /**
     * Set options for file-upload plugin.
     *
     * @param array $options
     *
     * @return $this
     */
    public function options($options = [])
    {
        $this->options = array_merge($options, $this->options);

        return $this;
    }

    /**
     * Set disk for storage.
     *
     * @param string $disk Disks defined in `config/filesystems.php`.
     *
     * @throws \Exception
     *
     * @return $this
     */
    public function disk($disk)
    {
        try {
            $this->storage = Storage::disk($disk);
        } catch (\Exception $exception) {
            if (!array_key_exists($disk, config('filesystems.disks'))) {
                admin_error(
                    'Config error.',
                    "Disk [$disk] not configured, please add a disk config in `config/filesystems.php`."
                );

                return $this;
            }

            throw $exception;
        }

        return $this;
    }

    /**
     * Specify the directory and name for upload file.
     *
     * @param string      $directory
     * @param null|string $name
     *
     * @return $this
     */
    public function move($directory, $name = null)
    {
        $this->dir($directory);

        $this->name($name);

        return $this;
    }

    /**
     * Specify the directory upload file.
     *
     * @param string $dir
     *
     * @return $this
     */
    public function dir($dir)
    {
        if ($dir) {
            $this->directory = $dir;
        }

        return $this;
    }

    /**
     * Set name of store name.
     *
     * @param string|callable $name
     *
     * @return $this
     */
    public function name($name)
    {
        if ($name) {
            $this->name = $name;
        }

        return $this;
    }

    /**
     * Use unique name for store upload file.
     *
     * @return $this
     */
    public function uniqueName()
    {
        $this->useUniqueName = true;

        return $this;
    }

    /**
     * Use sequence name for store upload file.
     *
     * @return $this
     */
    public function sequenceName()
    {
        $this->useSequenceName = true;

        return $this;
    }

    /**
     * Get store name of upload file.
     *
     * @param UploadedFile $file
     *
     * @return string
     */
    protected function getStoreName(UploadedFile $file)
    {
        if ($this->useUniqueName) {
            return $this->generateUniqueName($file);
        }

        if ($this->useSequenceName) {
            return $this->generateSequenceName($file);
        }

        if ($this->name instanceof \Closure) {
            return $this->name->call($this, $file);
        }

        if (is_string($this->name)) {
            return $this->name;
        }

        return $file->getClientOriginalName();
    }

    /**
     * Get directory for store file.
     *
     * @return mixed|string
     */
    public function getDirectory()
    {
        if ($this->directory instanceof \Closure) {
            return call_user_func($this->directory, $this->form);
        }

        return $this->directory ?: $this->defaultDirectory();
    }

    /**
     * Set path column in has-many related model.
     *
     * @param string $column
     *
     * @return $this
     */
    public function pathColumn($column = 'path')
    {
        $this->pathColumn = $column;

        return $this;
    }

    /**
     * 拒绝把服务器可执行脚本写入磁盘(CVE-2023-24249 防护)。
     *
     * 校验的是"最终存储文件名"(经过 renameIfExists/uniqueName/sequenceName/
     * 业务自定义 name 之后),因此覆盖全部存储路径,并天然拦截重名改写时
     * 注入客户端扩展名的边缘情形。校验失败抛 ValidationException,走框架
     * 标准的错误回显(表单字段红框 + 错误消息,与业务规则校验一致)。
     *
     * @param string|null $name
     *
     * @throws ValidationException
     *
     * @return void
     */
    protected function assertSafeStoreName($name)
    {
        $forbidden = config('admin.upload.forbidden_extensions');

        if (!is_array($forbidden)) {
            $forbidden = static::$forbiddenExtensions;
        }

        if (empty($forbidden)) {
            return;
        }

        $forbiddenFilenames = config('admin.upload.forbidden_filenames');

        if (!is_array($forbiddenFilenames)) {
            $forbiddenFilenames = static::$forbiddenFilenames;
        }

        $forbidden = array_map('strtolower', $forbidden);
        $forbiddenFilenames = array_map('strtolower', $forbiddenFilenames);

        // Windows 语义的尾部 NUL/点/空格绕过:"shell.php\x00"、"shell.php."、"shell.php "
        $basename = strtolower(basename(str_replace('\\', '/', (string) $name)));
        $basename = rtrim($basename, "\x00\t .");

        $extension = (string) pathinfo($basename, PATHINFO_EXTENSION);

        if (in_array($extension, $forbidden, true)
            || in_array($basename, $forbiddenFilenames, true)) {
            $key = 'admin.upload_forbidden_extension';

            $message = trans($key, ['name' => $basename]);

            if ($message === $key) {
                // 语言文件按需发布、不会随包升级覆盖:未发布新键的存量系统
                // 在此回退到内置文案,避免用户看到原始键名
                $message = str_starts_with(app()->getLocale(), 'zh')
                    ? "不允许上传 {$basename}(禁止的服务器脚本类型)"
                    : "Uploading {$basename} is not allowed (server script type)";
            }

            throw ValidationException::withMessages([
                $this->getErrorKey() => $message,
            ]);
        }
    }

    /**
     * Upload file and delete original file.
     *
     * @param UploadedFile $file
     *
     * @return mixed
     */
    protected function upload(UploadedFile $file)
    {
        $this->renameIfExists($file);

        $this->assertSafeStoreName($this->name);

        if (!is_null($this->storagePermission)) {
            return $this->storage->putFileAs($this->getDirectory(), $file, $this->name, $this->storagePermission);
        }

        return $this->storage->putFileAs($this->getDirectory(), $file, $this->name);
    }

    /**
     * If name already exists, rename it.
     *
     * @param $file
     *
     * @return void
     */
    public function renameIfExists(UploadedFile $file)
    {
        if ($this->storage->exists("{$this->getDirectory()}/$this->name")) {
            $this->name = $this->generateUniqueName($file);
        }
    }

    /**
     * Get file visit url.
     *
     * @param $path
     *
     * @return string
     */
    public function objectUrl($path)
    {
        if ($this->pathColumn && is_array($path)) {
            $path = Arr::get($path, $this->pathColumn);
        }

        if (URL::isValidUrl($path)) {
            return $path;
        }

        if ($this->storage) {
            return $this->storage->url($path);
        }

        return Storage::disk(config('admin.upload.disk'))->url($path);
    }

    /**
     * Generate a unique name for uploaded file.
     *
     * @param UploadedFile $file
     *
     * @return string
     */
    protected function generateUniqueName(UploadedFile $file)
    {
        return md5(uniqid()).'.'.$file->getClientOriginalExtension();
    }

    /**
     * Generate a sequence name for uploaded file.
     *
     * @param UploadedFile $file
     *
     * @return string
     */
    protected function generateSequenceName(UploadedFile $file)
    {
        $index = 1;
        $extension = $file->getClientOriginalExtension();
        $original = $file->getClientOriginalName();
        $new = sprintf('%s_%s.%s', $original, $index, $extension);

        while ($this->storage->exists("{$this->getDirectory()}/$new")) {
            $index++;
            $new = sprintf('%s_%s.%s', $original, $index, $extension);
        }

        return $new;
    }

    /**
     * Destroy original files.
     *
     * @return void.
     */
    public function destroy()
    {
        if ($this->retainable) {
            return;
        }

        if (method_exists($this, 'destroyThumbnail')) {
            $this->destroyThumbnail();
        }

        if (!empty($this->original) && $this->storage->exists($this->original)) {
            $this->storage->delete($this->original);
        }
    }

    /**
     * Set file permission when stored into storage.
     *
     * @param string $permission
     *
     * @return $this
     */
    public function storagePermission($permission)
    {
        $this->storagePermission = $permission;

        return $this;
    }
}
