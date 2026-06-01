$(document).ready(function() {
    $(".fancybox").fancybox({
        padding : 0,
        //closeBtn		: false,
        openEffect      : 'none',
        closeEffect     : 'none',
        prevEffect		: 'none',
        nextEffect		: 'none',
        //openOpacity     : false,
        //closeOpacity    : false,
        helpers         : {
            overlay : {
                speedOut   : 0,
                locked     : false   // if true, the content will be locked into overlay
            }
        }
    });
});
