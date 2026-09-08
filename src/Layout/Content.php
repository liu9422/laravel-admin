<?php

namespace Encore\Admin\Layout;

use Closure;
use Encore\Admin\Facades\Admin;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Support\Arr;

class Content implements Renderable
{
    /**
     * Content title.
     *
     * @var string
     */
    protected $title = ' ';

    /**
     * Content description.
     *
     * @var string
     */
    protected $description = ' ';

    /**
     * Page breadcrumb.
     *
     * @var array
     */
    protected $breadcrumb = [];

    /**
     * @var Row[]
     */
    protected $rows = [];

    /**
     * @var array
     */
    protected $view;

    /**
     * Content constructor.
     *
     * @param Closure|null $callback
     */
    public function __construct(\Closure $callback = null)
    {
        if ($callback instanceof Closure) {
            $callback($this);
        }
    }

    /**
     * Alias of method `title`.
     *
     * @param string $header
     *
     * @return $this
     */
    public function header($header = '')
    {
        return $this->title($header);
    }

    /**
     * @param string $title
     *
     * @return $this
     */
    public function title($title)
    {
        $this->title = $title;

        return $this;
    }

    /**
     * Set description of content.
     *
     * @param string $description
     *
     * @return $this
     */
    public function description($description = '')
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Set breadcrumb of content.
     *
     * @param array ...$breadcrumb
     *
     * @return $this
     */
    public function breadcrumb(...$breadcrumb)
    {
        $this->validateBreadcrumb($breadcrumb);

        $this->breadcrumb = (array) $breadcrumb;

        return $this;
    }

    /**
     * Validate content breadcrumb.
     *
     * @param array $breadcrumb
     *
     * @throws \Exception
     *
     * @return bool
     */
    protected function validateBreadcrumb(array $breadcrumb)
    {
        foreach ($breadcrumb as $item) {
            if (!is_array($item) || !Arr::has($item, 'text')) {
                throw new  \Exception('Breadcrumb format error!');
            }
        }

        return true;
    }

    /**
     * Alias of method row.
     *
     * @param mixed $content
     *
     * @return $this
     */
    public function body($content)
    {
        return $this->row($content);
    }

    /**
     * Add one row for content body.
     *
     * @param $content
     *
     * @return $this
     */
    public function row($content)
    {
        if ($content instanceof Closure) {
            $row = new Row();
            call_user_func($content, $row);
            $this->addRow($row);
        } else {
            $this->addRow(new Row($content));
        }

        return $this;
    }

    /**
     * Render giving view as content body.
     *
     * @param string $view
     * @param array  $data
     *
     * @return $this
     */
    public function view($view, $data = [])
    {
        $this->view = compact('view', 'data');

        return $this;
    }

    /**
     * @param string $view
     * @param array  $data
     */
    public function component($view, $data = [])
    {
        return $this->body(Admin::component($view, $data));
    }

    /**
     * @param $var
     *
     * @return $this
     */
    public function dump($var)
    {
        return $this->row(admin_dump(...func_get_args()));
    }

    /**
     * Add Row.
     *
     * @param Row $row
     */
    protected function addRow(Row $row)
    {
        $this->rows[] = $row;
    }

    /**
     * Build html of content.
     *
     * @return string
     */
    public function build()
    {
        ob_start();

        foreach ($this->rows as $row) {
            $row->build();
        }

        $contents = ob_get_contents();

        ob_end_clean();

        return $contents;
    }

    /**
     * Set success message for content.
     *
     * @param string $title
     * @param string $message
     *
     * @return $this
     */
    public function withSuccess($title = '', $message = '')
    {
        admin_success($title, $message);

        return $this;
    }

    /**
     * Set error message for content.
     *
     * @param string $title
     * @param string $message
     *
     * @return $this
     */
    public function withError($title = '', $message = '')
    {
        admin_error($title, $message);

        return $this;
    }

    /**
     * Set warning message for content.
     *
     * @param string $title
     * @param string $message
     *
     * @return $this
     */
    public function withWarning($title = '', $message = '')
    {
        admin_warning($title, $message);

        return $this;
    }

    /**
     * Set info message for content.
     *
     * @param string $title
     * @param string $message
     *
     * @return $this
     */
    public function withInfo($title = '', $message = '')
    {
        admin_info($title, $message);

        return $this;
    }

    /**
     * @return array
     */
    protected function getUserData()
    {
        if (!$user = Admin::user()) {
            return [];
        }

        return Arr::only($user->toArray(), ['id', 'username', 'email', 'name', 'avatar']);
    }

    /**
     * Render this content.
     *
     * @return string
     */
    public function render()
    {
        $items = [
            'header'      => $this->title,
            'description' => $this->description,
            'breadcrumb'  => $this->breadcrumb ?: $this->defaultBreadcrumb(),
            '_content_'   => $this->build(),
            '_view_'      => $this->view,
            '_user_'      => $this->getUserData(),
        ];

        return view('admin::content', $items)->render();
    }

    /**
     * 默认面包屑(替代旧的"URL 段直拼",产出可读语义):
     * 首页 / [父菜单…] 菜单标题 / 动作(编辑|创建|显示)。
     * 纯数字 id 段一律剔除;动作取自 URL 尾段(edit/create)或详情页(id 结尾)。
     *
     * @return array
     */
    protected function defaultBreadcrumb()
    {
        if (!config('admin.enable_default_breadcrumb', true)) {
            return [];
        }

        $breadcrumb = [[
            'icon' => 'fa-dashboard',
            'text' => trans('admin.home'),
            'url'  => '/',
        ]];

        $segments = request()->segments();
        array_shift($segments); // 去掉 admin 路由前缀

        $action = null;

        if ($segments && in_array(end($segments), ['edit', 'create'], true)) {
            $action = array_pop($segments);
        } elseif ($segments && ctype_digit(end($segments))) {
            // 详情页 URL 以记录 id 结尾
            $action = 'show';
            array_pop($segments);
        }

        // 剔除残留的纯数字段(嵌套资源的记录 id)
        $segments = array_values(array_filter($segments, function ($s) {
            return !ctype_digit($s);
        }));

        if (empty($segments)) {
            return $breadcrumb;
        }

        $path = implode('/', $segments);

        // 按菜单 uri 匹配当前资源,标题与父级链取自业务菜单(中文系统即中文)
        $menuClass = config('admin.database.menu_model');

        $menu = $menuClass::query()
            ->where(function ($query) use ($path) {
                $query->where('uri', $path)->orWhere('uri', '/'.$path);
            })
            ->first();

        if ($menu) {
            $chain = [];
            $parentId = $menu->parent_id;
            $guard = 0;

            while ($parentId && $guard++ < 10) {
                $parent = $menuClass::query()->find($parentId);

                if (!$parent) {
                    break;
                }

                // 父级与当前菜单归一后同名(业务常见"分组名=子菜单名")则跳过,防冗余重复
                if (trim(strtolower((string) $parent->title)) !== trim(strtolower((string) $menu->title))) {
                    array_unshift($chain, $parent->title);
                }

                $parentId = $parent->parent_id;
            }

            foreach ($chain as $title) {
                $breadcrumb[] = ['text' => $this->menuTitle($title)];
            }

            $breadcrumb[] = ['text' => $this->menuTitle($menu->title), 'url' => $menu->uri];
        } else {
            $breadcrumb[] = ['text' => ucfirst(end($segments))];
        }

        if ($action) {
            $breadcrumb[] = ['text' => trans('admin.'.$action)];
        }

        return $breadcrumb;
    }

    /**
     * 菜单标题转译:与侧栏菜单一致(admin.menu_titles.{title 归一}),无映射则原样。
     *
     * @param string $title
     *
     * @return string
     */
    protected function menuTitle(string $title): string
    {
        $key = trim(str_replace(' ', '_', strtolower($title)));

        return \Illuminate\Support\Facades\Lang::has('admin.menu_titles.'.$key)
            ? trans('admin.menu_titles.'.$key)
            : $title;
    }
}
