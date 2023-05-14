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
});
