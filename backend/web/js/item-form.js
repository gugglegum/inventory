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

                    let htmlspecialchars = function(text) {
                        return $("<div>").text(text).html();
                    }

                    let trimIfTooLong = function(text, length, threshold) {
                        if (text.length > length + threshold) {
                            text = text.substring(0, length) + '...';
                        }
                        return text;
                    }

                    $('#divParentPreview').html('<table id="tableParentPreview" class="container-items"><tr><td class="thumbnail">'
                        + (data.primaryPhoto !== null
                            ? '<a href="' + htmlspecialchars(data.primaryPhoto.photo) + '" rel="parent-photos" class="fancybox"><img src="' + htmlspecialchars(data.primaryPhoto.thumbnail) + '" alt="Photo"></a>'
                            : '<img src="/images/no-fees-icon-B.png" alt="Photo">')
                        + '</td><td class="details">'
                        + '<div class="name"><a href="' + htmlspecialchars(data.url) + '">' + htmlspecialchars(data.name) + '</a> <sup>#' + htmlspecialchars(data.id) + '</sup></div>'
                        + (data.secondaryPhotos.length > 0 ? '<div class="secondary-photos">'
                            + (function() {
                                let secondaryPhotos = '';
                                for (let i = 0; i < data.secondaryPhotos.length; i++) {
                                    secondaryPhotos += '<a href="' + htmlspecialchars(data.secondaryPhotos[i].photo) + '" rel="parent-photos" class="fancybox"><img src="' + htmlspecialchars(data.secondaryPhotos[i].thumbnail) + '" alt="Photo"></a>'
                                }
                                return secondaryPhotos;
                            })()
                            + '</div>' : '')
                        + (data.description ? '<div class="description">' + htmlspecialchars(trimIfTooLong(data.description, 140, 10)) + '</div>' : '')
                        + (data.tags.length > 0 ? '<div class="tags">Метки: '
                            + (function() {
                                let tags = '';
                                for (let i = 0; i < data.tags.length; i++) {
                                    if (i > 0) {
                                        tags += ', ';
                                    }
                                    tags += '<a href="/items/search?q=' + encodeURIComponent(data.tags[i]) + '">' + htmlspecialchars(data.tags[i]) + '</a>';
                                }
                                return tags;
                            })()
                        + '</div>' : '')
                        + '</td></tr></table>');

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

    updateParentPreview();
});
