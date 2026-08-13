$(document).ready(function() {
    function closeParentModal() {
        let modalElement = parent.document.getElementById('pickContainerModal');
        parent.bootstrap.Modal.getOrCreateInstance(modalElement).hide();
    }

    // Click on "Выбрать" in the IFrame - set parentId's input, toggle focus to update possible input error and close modal
    $("#btnPick").click(function() {
        parent.$('#item-parentitemid').val($(this).data('container-id')).select().change();
        closeParentModal();
    });
    // Close modal on ESC inside IFrame
    $(document).keyup(function(e) {
        if (e.key === "Escape") {
            closeParentModal();
        }
    });
});
