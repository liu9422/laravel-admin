<?php

namespace Encore\Admin\Form\Field;

use Encore\Admin\Form\Field;

class Editor extends Textarea
{
    protected static $js = [
        '/vendor/laravel-admin/ckeditor/ckeditor.js',
    ];

    public function render()
    {
        $this->rows = 18;
        $this->script = "CKEDITOR.config.versionCheck = false; CKEDITOR.replace('{$this->id}');";
        return parent::render();
    }
}
