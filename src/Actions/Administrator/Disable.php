<?php

namespace Encore\Admin\Actions\Administrator;

use Encore\Admin\Actions\Response;
use Encore\Admin\Actions\RowAction;
use Illuminate\Database\Eloquent\Model;

/**
 * 禁用管理员(永久封禁,status 1 → 0)。
 * 入口保护(超管/自身)在 UserController actions 闭包处理。
 */
class Disable extends RowAction
{
    public function __construct()
    {
        parent::__construct(); // 与基类一致(纯 handle 的 RowAction 无 interactor,confirm 弹窗不可用)

        $this->name = __('admin.disable');
    }

    /**
     * @param Model $model
     *
     * @return Response
     */
    public function handle(Model $model): Response
    {
        if ((int) $model->status === \Encore\Admin\Auth\Database\Administrator::STATUS_BANNED) {
            return $this->response()->error(__('admin.already_banned'));
        }

        $model->status = \Encore\Admin\Auth\Database\Administrator::STATUS_BANNED;

        if ($model->save()) {
            return $this->response()->success(__('admin.disable_succeeded'))->refresh();
        }

        return $this->response()->error(__('admin.disable_failed'));
    }
}
