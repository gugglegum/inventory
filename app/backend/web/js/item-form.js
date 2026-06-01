$(document).ready(function() {
    // Click on "Выбрать..." in the item create/update form - set IFrame.src inside modal
    $('#btnTogglePickContainerModal').click(function() {
        let modal = $('#pickContainerModal');
        let modalBody = $('.modal-body', modal);
        let parentItemId = $('#item-parentitemid').val();
        let iframe = $('<iframe>', {
            id: 'pickContainerIframe',
            src: $('.modal-body', modal).data('iframe-base-src').replace(/\/0\//, '/' + encodeURIComponent(parentItemId !== '' ? parentItemId : '0') + '/')
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
        let parentItemIdValue = $('#item-parentitemid').val();
        if (parentItemIdValue === '') {
            $('#divParentPreview').html('Корневой раздел');
            return;
        }
        let id = parseInt(parentItemIdValue);

        if (isNaN(id) || !/^\d+$/.test(parentItemIdValue) || id === 0) {
            $('#divParentPreview').html('Недопустимый ID');
            return;
        }

        if (lastPreviewId !== id) {

            $('#divParentPreview').html('Загружается...');

            if (typeof xhr !== 'undefined') {
                xhr.abort();
            }

            let repoId = $('form#ItemForm').data('repoId');

            xhr = $.ajax('/repo/' + encodeURIComponent(repoId) + '/items/' + encodeURIComponent(id) + '/json-preview', {
                'success': function (data/*, textStatus, jqXHR*/) {
                    $('#divParentPreview').html(data.content);

                    $("#divParentPreview .fancybox").fancybox({
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
        lastPreviewId = parentItemIdValue;
    }

    $('#item-parentitemid').on("change paste keyup", function() {
        if (typeof updateTimeoutId !== 'undefined') {
            window.clearTimeout(updateTimeoutId);
        }
        updateTimeoutId = window.setTimeout(updateParentPreview, 300);
    });

    window.setTimeout(updateParentPreview, 0); // setTimeout() solves a bug in Chrome with old parent preview when navigating history back after selecting different parent
});
