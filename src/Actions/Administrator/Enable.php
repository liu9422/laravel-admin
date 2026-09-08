<?php

namespace Encore\Admin\Actions\Administrator;

use Encore\Admin\Actions\Response;
use Encore\Admin\Actions\RowAction;
use Illuminate\Database\Eloquent\Model;

/**
 * 启用管理员(status 0 → 1)。
 */
class Enable extends RowAction
{
    public function __construct()
    {
        parent::__construct(); // 与 Disable 一致,先初始化 interactor

        $this->name = __('admin.enable');
    }

    /**
     * @param Model $model
     *
     * @return Response
     */
    public function handle(Model $model): Response
    {
        if ((int) $model->status === \Encore\Admin\Auth\Database\Administrator::STATUS_ACTIVE) {
            return $this->response()->error(__('admin.already_enabled'));
        }

        $model->status = \Encore\Admin\Auth\Database\Administrator::STATUS_ACTIVE;

        if ($model->save()) {
            return $this->response()->success(__('admin.enable_succeeded'))->refresh();
        }

        return $this->response()->error(__('admin.enable_failed'));
    }
}
