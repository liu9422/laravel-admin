<?php

namespace Encore\Admin\Controllers;

use Encore\Admin\Auth\Database\OperationLog;
use Encore\Admin\Grid;
use Illuminate\Support\Arr;

class LogController extends AdminController
{
    /**
     * {@inheritdoc}
     */
    protected function title()
    {
        return trans('admin.operation_log');
    }

    /**
     * @return Grid
     */
    protected function grid()
    {
        $grid = new Grid(new OperationLog());

        $grid->disableExport();
        $grid->expandFilter();

        $grid->model()->orderBy('id', 'DESC');

        $grid->column('id', 'ID')->sortable();
        $grid->column('user.name', trans('admin.user'));
        $grid->column('method', trans('admin.http.method'))->display(function ($method) {
            $color = Arr::get(OperationLog::$methodColors, $method, 'grey');

            return "<span class=\"badge bg-$color\">$method</span>";
        });
        $grid->column('path', trans('admin.http.path'))->label('info');
        $grid->column('ip', trans('admin.ip'))->label('primary');
        $grid->column('input', trans('admin.input'))->display(function ($input) {
            $input = json_decode($input, true);
            $input = Arr::except($input, ['_pjax', '_token', '_method', '_previous_']);
            if (empty($input)) {
                return '<code>{}</code>';
            }
            $json = json_encode($input, JSON_PRETTY_PRINT | JSON_HEX_TAG | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return sprintf(
                '<pre title="点击展开/收起" style="cursor:pointer;white-space:pre-wrap;word-break:break-all;max-width:380px;max-height:64px;overflow:hidden;margin:0;padding:4px 6px;border:1px solid #eee;border-radius:3px;background:#f9f9f9;font-size:12px" onclick="if (this.style.maxHeight === \'360px\') { this.style.maxHeight = \'64px\'; this.style.overflow = \'hidden\'; } else { this.style.maxHeight = \'360px\'; this.style.overflow = \'auto\'; }">%s</pre>',
                $json
            );
        });

        $grid->column('created_at', trans('admin.created_at'));

        $grid->actions(function (Grid\Displayers\Actions $actions) {
            $actions->disableEdit();
            $actions->disableView();
        });

        $grid->disableCreateButton();

        $grid->filter(function (Grid\Filter $filter) {
            $userModel = config('admin.database.users_model');

            $filter->disableIdFilter();

            $filter->column(0.5, function (Grid\Filter $filter) use ($userModel) {
                $filter->equal('user_id', trans('admin.user'))->select($userModel::all()->pluck('name', 'id'));
                $filter->equal('method', trans('admin.http.method'))->select(array_combine(OperationLog::$methods, OperationLog::$methods));
            });

            $filter->column(0.5, function (Grid\Filter $filter) {
                $filter->like('path', trans('admin.http.path'));
                $filter->equal('ip', trans('admin.ip'));
            });
        });

        return $grid;
    }

    /**
     * @param mixed $id
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        $ids = explode(',', $id);

        if (OperationLog::destroy(array_filter($ids))) {
            $data = [
                'status'  => true,
                'message' => trans('admin.delete_succeeded'),
            ];
        } else {
            $data = [
                'status'  => false,
                'message' => trans('admin.delete_failed'),
            ];
        }

        return response()->json($data);
    }
}
