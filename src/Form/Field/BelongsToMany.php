<?php

namespace Encore\Admin\Form\Field;

use Encore\Admin\Admin;

class BelongsToMany extends MultipleSelect
{
    use BelongsToRelation;

    protected function addScript()
    {
        $script = <<<SCRIPT
;(function () {

    var grid = $('.belongstomany-{$this->column()}');
    var modal = $('#{$this->modalID}');
    var viewBody = grid.find('.btm-body');
    var pagerBox = grid.find('.btm-pager');
    var countBox = grid.find('.btm-count');
    var sourceBody = grid.find('.btm-source .grid-table tbody');
    var selectEl = $("{$this->getElementClassSelector()}");
    var emptyHtml = grid.find('template.empty').html();

    var PAGE_SIZE = 10;
    var selected = [];
    var rows = {};   // id(String) -> 行元素(来自服务端隐藏源或弹窗勾选行)
    var page = 0;

    // 行进入已选区前的统一处理(弹窗勾选行含勾选列/隐藏移除按钮;幂等)
    var cleanRow = function (tr) {
        if (tr.data('btm-clean')) {
            return tr;
        }

        tr.data('btm-clean', 1);
        tr.find('td.column-__modal_selector__').remove();
        tr.find('.grid-row-remove').removeClass('hide');

        return tr;
    };

    // 同步已选值到原生多选 select(无 select2 UI,大关联不渲染标签)
    var syncSelect = function () {
        var known = {};

        selectEl.find('option').each(function () {
            known[this.value] = 1;
        });

        selected.forEach(function (id) {
            if (!known[id]) {
                selectEl.append($('<option>').val(id).text(id));
                known[id] = 1;
            }
        });

        selectEl.val(selected);
        countBox.text(selected.length);
    };

    // 渲染当前页(只挂 20 行 DOM,其余行保留在内存)
    var renderPage = function () {
        viewBody.empty();

        if (selected.length === 0) {
            viewBody.append(emptyHtml);
            pagerBox.empty();
            return;
        }

        var totalPages = Math.max(1, Math.ceil(selected.length / PAGE_SIZE));

        if (page >= totalPages) {
            page = totalPages - 1;
        }

        var slice = selected.slice(page * PAGE_SIZE, (page + 1) * PAGE_SIZE);
        var frag = document.createDocumentFragment();

        slice.forEach(function (id) {
            var tr = rows[id];

            if (!tr) {
                return;
            }

            frag.appendChild(cleanRow(tr)[0]);
        });

        viewBody[0].appendChild(frag);

        var pager = '<span style="margin-right:10px">第 ' + (page + 1) + ' / ' + totalPages + ' 页</span>' +
            '<a href="javascript:void(0)" class="btm-prev" style="margin-right:6px">上一页</a>' +
            '<a href="javascript:void(0)" class="btm-next">下一页</a>';

        pagerBox.html(pager);
        pagerBox.find('.btm-prev').toggleClass('disabled', page === 0);
        pagerBox.find('.btm-next').toggleClass('disabled', page >= totalPages - 1);
    };

    // 初始:编辑回显 —— 服务端隐藏源整表行收进内存(不渲染);值取已选行 key
    sourceBody.find('tr').each(function () {
        var tr = $(this);
        var rm = tr.find('.grid-row-remove');

        if (rm.length > 0) {
            var key = rm.data('key').toString();

            rows[key] = tr;
            selected.push(key);
            tr.detach();
        }
    });

    renderPage();
    syncSelect();

    // open modal
    grid.on('click', '.select-relation', function (e) {
        modal.modal('show');
        e.preventDefault();
    });

    // 分页
    pagerBox.on('click', '.btm-prev', function () {
        if (page > 0) {
            page--;
            renderPage();
        }
    });
    pagerBox.on('click', '.btm-next', function () {
        if ((page + 1) * PAGE_SIZE < selected.length) {
            page++;
            renderPage();
        }
    });

    // remove row(移除后重绘当前页,保持分页状态自洽)
    viewBody.on('click', '.grid-row-remove', function () {
        var key = $(this).data('key').toString();
        var index = selected.indexOf(key);

        if (index !== -1) {
            selected.splice(index, 1);
            delete rows[key];
        }

        renderPage();
        syncSelect();
    });

    var load = function (url) {
        $.get(url, function (data) {
            modal.find('.modal-body').html(data);
            modal.find('.select').iCheck({
                radioClass:'iradio_minimal-blue',
                checkboxClass:'icheckbox_minimal-blue'
            });
            modal.find('.box-header:first').hide();

            modal.find('input.select').each(function (index, el) {
                if ($.inArray($(el).val().toString(), selected) >=0 ) {
                    $(el).iCheck('toggle');
                }
            });
        });
    };

    // 弹窗确定:同步值并回到第一页(行已存内存,不再整批 append 进 DOM)
    var update = function (callback) {
        syncSelect();
        page = 0;
        renderPage();

        if (callback) {
            callback();
        }
    };

    modal.on('show.bs.modal', function (e) {
        load("{$this->getLoadUrl(1)}");
    }).on('click', '.page-item a, .filter-box a', function (e) {
        load($(this).attr('href'));
        e.preventDefault();
    }).on('click', 'tr', function (e) {
        $(this).find('input.select').iCheck('toggle');
        e.preventDefault();
    }).on('submit', '.box-header form', function (e) {
        load($(this).attr('action')+'&'+$(this).serialize());
        e.preventDefault();
        return false;
    }).on('ifChecked', 'input.select', function (e) {
        if (selected.indexOf($(this).val()) < 0) {
            selected.push($(this).val());
            rows[$(e.target).val()] = $(e.target).parents('tr');
        }
    }).on('ifUnchecked', 'input.select', function (e) {
           var val = $(this).val();
           var index = selected.indexOf(val);
           if (index !== -1) {
               selected.splice(index, 1);
               delete rows[$(e.target).val()];
           }
    }).find('.modal-footer .submit').click(function () {
        update(function () {
            modal.modal('toggle');
        });
    });
})();
SCRIPT;

        Admin::script($script);

        return $this;
    }

    protected function getOptions()
    {
        $options = [];

        if ($this->value()) {
            $options = array_combine($this->value(), $this->value());
        }

        return $options;
    }
}
