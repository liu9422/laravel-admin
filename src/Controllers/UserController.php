<?php

namespace Encore\Admin\Controllers;

use Encore\Admin\Actions\Administrator\Disable;
use Encore\Admin\Actions\Administrator\Enable;
use Encore\Admin\Auth\Database\Administrator;
use Encore\Admin\Facades\Admin;
use Encore\Admin\Form;
use Encore\Admin\Grid;
use Encore\Admin\Show;
use Illuminate\Support\Facades\Hash;

class UserController extends AdminController
{
    /**
     * {@inheritdoc}
     */
    protected function title()
    {
        return trans('admin.administrator');
    }

    /**
     * Make a grid builder.
     *
     * @return Grid
     */
    protected function grid()
    {
        $userModel = config('admin.database.users_model');
        $roleModel = config('admin.database.roles_model');

        $grid = new Grid(new $userModel());

        $grid->disableExport();
        $grid->expandFilter();

        $grid->filter(function (Grid\Filter $filter) use ($roleModel) {
            $filter->disableIdFilter();

            $filter->column(0.5, function (Grid\Filter $filter) {
                $filter->like('username', trans('admin.username'));
                $filter->like('name', trans('admin.name'));
            });

            $filter->column(0.5, function (Grid\Filter $filter) use ($roleModel) {
                $filter->where(function ($query) use ($roleModel) {
                    // 角色表名可配置(admin.database.roles_table),不能写死
                    $table = (new $roleModel)->getTable();
                    $query->whereHas('roles', function ($query) use ($table) {
                        $query->where($table.'.id', $this->input);
                    });
                }, trans('admin.roles'))->select($roleModel::all()->pluck('name', 'id'));
            });
        });

        $grid->column('id', 'ID')->sortable();
        $grid->column('username', trans('admin.username'));
        $grid->column('name', trans('admin.name'));
        $grid->column('roles', trans('admin.roles'))->pluck('name')->label();
        $grid->column('status', trans('admin.status'))->display(function ($status) {
            if ($status === null || (int) $status === Administrator::STATUS_ACTIVE) {
                return '<span class="label label-success">'.trans('admin.enabled').'</span>';
            }else{
                return '<span class="label label-danger">'.trans('admin.disabled').'</span>';
            }
        });
        $grid->column('created_at', trans('admin.created_at'));
        $grid->column('updated_at', trans('admin.updated_at'));

        $grid->actions(function (Grid\Displayers\Actions $actions) {
            $key = (int) $actions->getKey();

            if ($key == 1) {
                $actions->disableDelete();
            }

            $status = $actions->row->status;
            if ($status !== null && (int) $status === Administrator::STATUS_BANNED) {
                $actions->add(new Enable());
            } elseif ($key !== 1 && $key !== (int) Admin::user()->id) {
                $actions->add(new Disable());
            }
        });

        $grid->tools(function (Grid\Tools $tools) {
            $tools->batch(function (Grid\Tools\BatchActions $actions) {
                $actions->disableDelete();
            });
        });

        return $grid;
    }

    /**
     * Make a show builder.
     *
     * @param mixed $id
     *
     * @return Show
     */
    protected function detail($id)
    {
        $userModel = config('admin.database.users_model');

        $show = new Show($userModel::findOrFail($id));

        $show->field('id', 'ID');
        $show->field('username', trans('admin.username'));
        $show->field('name', trans('admin.name'));
        $show->field('roles', trans('admin.roles'))->as(function ($roles) {
            return $roles->pluck('name');
        })->label();
        $show->field('permissions', trans('admin.permissions'))->as(function ($permission) {
            return $permission->pluck('name');
        })->label();
        $show->field('created_at', trans('admin.created_at'));
        $show->field('updated_at', trans('admin.updated_at'));

        return $show;
    }

    /**
     * Make a form builder.
     *
     * @return Form
     */
    public function form()
    {
        $userModel = config('admin.database.users_model');
        $permissionModel = config('admin.database.permissions_model');
        $roleModel = config('admin.database.roles_model');

        $form = new Form(new $userModel());

        $userTable = config('admin.database.users_table');
        $connection = config('admin.database.connection');

        $form->text('username', trans('admin.username'))
            ->creationRules(['required', "unique:{$connection}.{$userTable}"])
            ->updateRules(['required', "unique:{$connection}.{$userTable},username,{{id}}"]);

        $form->text('name', trans('admin.name'))->rules('required');
        $form->image('avatar', trans('admin.avatar'));
        $form->password('password', trans('admin.password'))->rules('required|confirmed');
        $form->password('password_confirmation', trans('admin.password_confirmation'))->rules('required')
            ->default(function ($form) {
                return $form->model()->password;
            });

        $form->ignore(['password_confirmation']);

        $form->multipleSelect('roles', trans('admin.roles'))->options($roleModel::all()->pluck('name', 'id'));
        $form->multipleSelect('permissions', trans('admin.permissions'))->options($permissionModel::all()->pluck('name', 'id'));

        $form->display('created_at', trans('admin.created_at'));
        $form->display('updated_at', trans('admin.updated_at'));

        $form->saving(function (Form $form) {
            if ($form->password && $form->model()->password != $form->password) {
                $form->password = Hash::make($form->password);
            }
        });

        return $form;
    }
}
