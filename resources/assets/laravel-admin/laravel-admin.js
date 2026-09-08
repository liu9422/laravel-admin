// jQuery 3 兼容垫片(2026-09-07 安全升级 jQ2.1→3.7):
// jQ3 已删除 .size()(1.8 起废弃);停更组件(eonasdan datetimepicker、
// bootstrap3-editable)仍调用,行为与 jQ2 一致
if (!$.fn.size) {
    $.fn.size = function () { return this.length; };
}

(function ($) {
    var _offset = $.fn.offset;

    $.fn.offset = function (options) {
        var el = this[0];

        if (options === undefined && el && (el === window || el.nodeType === 9)) {
            return { top: 0, left: 0 };
        }

        return _offset.apply(this, arguments);
    };
})(jQuery);
var laSwalCompat = function () {
    if (typeof Swal !== 'function') {
        return $.Deferred().reject('sweetalert2 not loaded');
    }
    var args = Array.prototype.slice.call(arguments), opt = {};
    if (args.length === 1 && typeof args[0] === 'object' && args[0]) {
        opt = $.extend({}, args[0]);
        if ('type' in opt) { opt.icon = opt.type; delete opt.type; }
        // 维持 v7 观感:确认按钮默认红色(v11 默认蓝),未显式指定时补回
        if (typeof opt.confirmButtonColor === 'undefined') { opt.confirmButtonColor = '#DD6B55'; }
    } else {
        if (typeof args[0] === 'string') { opt.title = args[0]; }
        if (typeof args[1] === 'string') { opt.html = args[1]; }
        if (typeof args[2] === 'string') { opt.icon = args[2]; }
    }
    return Swal.fire(opt);
};
window.swal = laSwalCompat;

var laToastr = {
    options: {
        closeButton: true,
        progressBar: true,
        showMethod: 'slideDown',
        timeOut: 4000
    },
    _ret: {
        css: function () { return laToastr._ret; },
        remove: function () { return laToastr._ret; }
    },
    _fire: function (type, message, title, options) {
        options = options || {};
        var posMap = {
            'toast-top-right': 'top-end', 'toast-top-left': 'top-start',
            'toast-top-center': 'top', 'toast-bottom-right': 'bottom-end',
            'toast-bottom-left': 'bottom-start', 'toast-bottom-center': 'bottom'
        };
        var conf = {
            toast: true,
            position: posMap[options.positionClass] || 'top-end',
            icon: type,
            html: message,
            showConfirmButton: false,
            timer: options.timeOut || laToastr.options.timeOut || 4000,
            showCloseButton: options.closeButton === true || laToastr.options.closeButton === true,
            // 类型 class 供彩底醒目样式(la-toast-{success|error|warning|info})
            customClass: { popup: 'la-toast-' + type }
        };
        if (title) { conf.title = title; }
        if (typeof Swal === 'function') { Swal.fire(conf); }
        return laToastr._ret;
    },
    success: function (m, t, o) { return laToastr._fire('success', m, t, o); },
    error: function (m, t, o) { return laToastr._fire('error', m, t, o); },
    warning: function (m, t, o) { return laToastr._fire('warning', m, t, o); },
    info: function (m, t, o) { return laToastr._fire('info', m, t, o); }
};
window.toastr = laToastr;

$.fn.editable.defaults.params = function (params) {
    params._token = LA.token;
    params._editable = 1;
    params._method = 'PUT';
    return params;
};

$.fn.editable.defaults.error = function (data) {
    var msg = '';
    if (data.responseJSON.errors) {
        $.each(data.responseJSON.errors, function (k, v) {
            msg += v + "\n";
        });
    }
    return msg
};

toastr.options = {
    closeButton: true,
    progressBar: true,
    showMethod: 'slideDown',
    timeOut: 4000
};

$.pjax.defaults.timeout = 5000;
$.pjax.defaults.maxCacheLength = 0;
$(document).pjax('a:not(a[target="_blank"])', {
    container: '#pjax-container'
});

NProgress.configure({parent: '#app'});

$(document).on('pjax:timeout', function (event) {
    event.preventDefault();
})

$(document).on('submit', 'form[pjax-container]', function (event) {
    $.pjax.submit(event, '#pjax-container')
});

$(document).on("pjax:popstate", function () {

    $(document).one("pjax:end", function (event) {
        $(event.target).find("script[data-exec-on-popstate]").each(function () {
            $.globalEval(this.text || this.textContent || this.innerHTML || '');
        });
    });
});

$(document).on('pjax:send', function (xhr) {
    if (xhr.relatedTarget && xhr.relatedTarget.tagName && xhr.relatedTarget.tagName.toLowerCase() === 'form') {
        $submit_btn = $('form[pjax-container] :submit');
        if ($submit_btn) {
            $submit_btn.button('loading')
        }
    }
    NProgress.start();
});

$(document).on('pjax:complete', function (xhr) {
    if (xhr.relatedTarget && xhr.relatedTarget.tagName && xhr.relatedTarget.tagName.toLowerCase() === 'form') {
        $submit_btn = $('form[pjax-container] :submit');
        if ($submit_btn) {
            $submit_btn.button('reset')
        }
    }
    NProgress.done();
    $.admin.grid.selects = {};
});

$(document).click(function () {
    $('.sidebar-form .dropdown-menu').hide();
});

$(function () {
    $('.sidebar-menu li:not(.treeview) > a').on('click', function () {
        var $parent = $(this).parent().addClass('active');
        $parent.siblings('.treeview.active').find('> a').trigger('click');
        $parent.siblings().removeClass('active').find('li').removeClass('active');
    });
    var menu = $('.sidebar-menu li > a[href$="' + (location.pathname + location.search + location.hash) + '"]').parent().addClass('active');
    menu.parents('ul.treeview-menu').addClass('menu-open');
    menu.parents('li.treeview').addClass('active');

    $('[data-toggle="popover"]').popover();

    // Sidebar form autocomplete
    $('.sidebar-form .autocomplete').on('keyup focus', function () {
        var $menu = $('.sidebar-form .dropdown-menu');
        var text = $(this).val();

        if (text === '') {
            $menu.hide();
            return;
        }

        var regex = new RegExp(text, 'i');
        var matched = false;

        $menu.find('li').each(function () {
            if (!regex.test($(this).find('a').text())) {
                $(this).hide();
            } else {
                $(this).show();
                matched = true;
            }
        });

        if (matched) {
            $menu.show();
        }
    }).click(function(event){
        event.stopPropagation();
    });

    $('.sidebar-form .dropdown-menu li a').click(function (){
        $('.sidebar-form .autocomplete').val($(this).text());
    });
});

$(window).scroll(function() {
    if (document.body.scrollTop > 100 || document.documentElement.scrollTop > 100) {
        $('#totop').fadeIn(500);
    } else {
        $('#totop').fadeOut(500);
    }
});

$('#totop').on('click', function (e) {
    e.preventDefault();
    $('html,body').animate({scrollTop: 0}, 500);
});

(function ($) {

    var Grid = function () {
        this.selects = {};
    };

    Grid.prototype.select = function (id) {
        this.selects[id] = id;
    };

    Grid.prototype.unselect = function (id) {
        delete this.selects[id];
    };

    Grid.prototype.selected = function () {
        var rows = [];
        $.each(this.selects, function (key, val) {
            rows.push(key);
        });

        return rows;
    };

    $.fn.admin = LA;
    $.admin = LA;
    $.admin.swal = laSwalCompat;
    $.admin.toastr = laToastr;
    $.admin.grid = new Grid();

    $.admin.reload = function () {
        $.pjax.reload('#pjax-container');
        $.admin.grid = new Grid();
    };

    $.admin.redirect = function (url) {
        $.pjax({container:'#pjax-container', url: url });
        $.admin.grid = new Grid();
    };

    $.admin.getToken = function () {
        return $('meta[name="csrf-token"]').attr('content');
    };

    $.admin.loadedScripts = [];

    $.admin.loadScripts = function(arr) {
        var _arr = $.map(arr, function(src) {

            if ($.inArray(src, $.admin.loadedScripts)) {
                return;
            }

            $.admin.loadedScripts.push(src);

            return $.getScript(src);
        });

        _arr.push($.Deferred(function(deferred){
            $(deferred.resolve);
        }));

        return $.when.apply($, _arr);
    }

})(jQuery);
