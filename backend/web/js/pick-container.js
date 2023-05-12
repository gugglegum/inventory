$(document).ready(function() {
    // Click on "Выбрать" in the IFrame - set parentId's input, toggle focus to update possible input error and close modal
    $("#btnPick").click(function() {
        parent.$('#item-parentid').val($(this).data('container-id'));
        parent.$('#item-parentid').focus();
        parent.$('#item-parentid').blur();
        parent.$('#pickContainerModal').modal('hide');
    });
    // Close modal on ESC inside IFrame
    $(document).keyup(function(e) {
        if (e.key === "Escape") {
            parent.$('#pickContainerModal').modal('hide');
        }
    });
});
