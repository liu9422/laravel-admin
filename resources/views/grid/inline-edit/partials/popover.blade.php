$('.{{ $trigger }}').popover({
    html: true,
    // BS3.4+ popover 默认 sanitize 白名单不含 label/input,会剥光行内编辑表单内容
    sanitize: false,
    container: 'body',
    trigger: 'manual',
    placement: function (context, source) {
        var position = $(source).position();
        if (position.left < 100) return "right";
        if (position.top < 110) return "bottom";
        if ($(window).height() - $(source).offset().top < 370) {
            return 'top';
        }
        return "bottom";
    },
    content: function () {
        var $trigger = $(this);
        var $template = $($('template#'+$(this).data('target')).html());
        {{ $content }}
        return $template.prop("outerHTML");
    }
}).on('shown.bs.popover', function (e) {

    var $popover = $($(this).data('bs.popover').$tip[0]).find('.ie-content');
    var $display = $(this).parents('.ie-wrap').find('.ie-display');
    var $trigger = $(this);

    $popover.data('display', $display);
    $popover.data('trigger', $trigger);

    {{ $shown ?? '' }}

}).click(function () {
    $('[data-toggle="popover"]').popover('hide');
    $(this).popover('toggle');
});
