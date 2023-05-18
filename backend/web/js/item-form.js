$(document).ready(function() {
    // Click on "Выбрать..." in the item create/update form - set IFrame.src inside modal
    $('#btnTogglePickContainerModal').click(function() {
        let modal = $('#pickContainerModal');
        let modalBody = $('.modal-body', modal);
        let iframe = $('<iframe>', {
            id: 'pickContainerIframe',
            src: $('.modal-body', modal).data('iframe-base-src') + encodeURIComponent($('#item-parentid').val())
        });
        modalBody.html(iframe);
    });
    // When modal closes - remove iframe to clear navigation history (although works only in Firefox)
    $('#pickContainerModal').on('hidden.bs.modal', function () {
        $('.modal-body', $('#pickContainerModal')).html('');
    });

    let updateTimeoutId = undefined;
    let lastPreviewId = undefined;
    let xhr = undefined;

    let updateParentPreview = function() {
        let parentIdValue = $('#item-parentid').val();
        if (parentIdValue === '') {
            $('#divParentPreview').html('Корневой раздел');
            return;
        }
        let id = parseInt(parentIdValue);

        if (isNaN(id) || !/^\d+$/.test(parentIdValue) || id === 0) {
            $('#divParentPreview').html('Недопустимый ID');
            return;
        }

        if (lastPreviewId !== id) {

            $('#divParentPreview').html('Загружается...');

            if (typeof xhr !== 'undefined') {
                xhr.abort();
            }

            xhr = $.ajax('/items/json-preview?id=' + encodeURIComponent(id), {
                'success': function (data/*, textStatus, jqXHR*/) {
                    $('#divParentPreview').html(data.content);

                    $("#tableParentPreview .fancybox").fancybox({
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
                },
                'error' : function(jqXHR, textStatus, errorThrown) {
                    console.log(jqXHR, textStatus, errorThrown);
                    if (jqXHR.status === 404) {
                        $('#divParentPreview').html('Предмет не существует');
                    }
                }
            });
        }
        lastPreviewId = parentIdValue;
    }

    $('#item-parentid').on("change paste keyup", function() {
        if (typeof updateTimeoutId !== 'undefined') {
            window.clearTimeout(updateTimeoutId);
        }
        updateTimeoutId = window.setTimeout(updateParentPreview, 300);
    });

    window.setTimeout(updateParentPreview, 0); // setTimeout() solves a bug in Chrome with old parent preview when navigating history back after selecting different parent
});
