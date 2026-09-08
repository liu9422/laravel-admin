<div class="{{$viewClass['form-group']}} {!! !$errors->has($errorKey) ? '' : 'has-error' !!}">

<label for="{{$id}}" class="{{$viewClass['label']}} control-label">{{$label}}</label>

    <div class="{{$viewClass['field']}}">

        @include('admin::form.error')

        <select class="form-control {{$class}} hide" style="width: 100%;" name="{{$name}}[]" multiple="multiple" data-placeholder="{{ $placeholder }}" {!! $attributes !!} >
            @foreach($options as $select => $option)
                <option value="{{$select}}" {{  in_array($select, (array)old($column, $value)) ?'selected':'' }}>{{$option}}</option>
            @endforeach
        </select>
        <input type="hidden" name="{{$name}}[]" />

        <div class="belongstomany-{{ $class }}">
            {{-- 已选区:前端分页展示(关联再多也只渲染当前页,页面不被拉长) --}}
            <div class="btm-view" style="border:1px solid #e4e4e4;border-radius:3px;margin-bottom:6px">
                <div class="btm-toolbar clearfix" style="padding:6px 10px;background:#f9f9f9;border-bottom:1px solid #e4e4e4">
                    <a href="javascript:void(0)" class="btn btn-primary btn-sm select-relation">
                        <i class="fa fa-plus"></i>&nbsp;{{ trans('admin.choose') }}
                    </a>
                    <span style="line-height:30px;margin-left:12px">已选 <b class="btm-count">0</b> 项</span>
                    <span class="btm-pager pull-right" style="line-height:30px"></span>
                </div>
                <table class="table table-hover btm-table" style="margin-bottom:0">
                    <tbody class="btm-body"></tbody>
                </table>
            </div>
            {{-- 数据源:服务端已选行整表(隐藏,不参与布局,仅作分页行数据来源) --}}
            <div class="btm-source" style="display:none">
                {!! $grid->render() !!}
            </div>
            <template class="empty">
                @include('admin::grid.empty-grid')
            </template>
        </div>

        @include('admin::form.help-block')

    </div>
</div>
