(function($){
    'use strict';
    function initJapurSuiteMenu(){
        var $parent=$('#toplevel_page_japur-suite');
        if(!$parent.length) return;
        var $link=$parent.children('a');
        if(!$link.length || $link.data('japurToggleReady')) return;
        $link.data('japurToggleReady',true);
        $link.on('click.japurSuiteToggle',function(e){
            var href=$(this).attr('href')||'';
            var current=window.location.href;
            var isDashboard=current.indexOf('page=japur-suite')!==-1 || current.indexOf('page%3Djapur-suite')!==-1;
            if(!isDashboard && href){ return; }
            e.preventDefault();
            var $menu=$parent.children('.wp-submenu');
            if($menu.length){
                $menu.toggleClass('japur-suite-collapsed');
                $parent.toggleClass('japur-suite-submenu-closed');
            }
        });
    }
    $(initJapurSuiteMenu);
})(jQuery);
