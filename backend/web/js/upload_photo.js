$(document).ready(function() {
    var onFileInputChanged = function(e) {
        if (! hasEmptyInput()) {
            addFileInput().find('input[type=file]').change(onFileInputChanged);
        }

        var file = $(e.target).closest('.field-item-photos');

        /*if (this.files && this.files[0]) {
            var reader = new FileReader();
            reader.onload = function(e) {
                var selectedImage = e.target.result;
                file.find('.block').show();
                file.find('img').attr('src', selectedImage);
            };
            reader.readAsDataURL(this.files[0]);
        } else {
            file.find('.block').hide();
            file.find('img').attr('src', '');
        }*/
    };
    var addFileInput = function() {
        var container = $('#PhotosContainer');
        var file = container.find('.field-item-photos').first().clone();
        file.find('.block').hide();
        file.find('img').attr('src', '');
        container.append(file);
        return file;
    };
    var hasEmptyInput = function() {
        var hasEmptyInput = false;
        $('#PhotosContainer input[type=file]').each(function(index, element) {
            if ($(element).val() == '') {
                hasEmptyInput = true;
                return false;
            }
        });
        return hasEmptyInput;
    };
    $('#PhotosContainer input[type=file]').change(onFileInputChanged);

    // ----------

    $('.uploaded-photos .btn-delete').click(function(e) {
        if (! confirm("Действительно удалить?")) {
            return;
        }

        var button = $(e.target).closest('button');
        var url = button.data('action');
        var id = button.data('id');
        var wrapper = button.closest('.photo-wrapper');

        $.ajax(url, {
            type : 'post',
            data : {
                id : id
            },
            success : function() {
                wrapper.remove();
            },
            error : function(jqXHR, textStatus, errorThrown) {
                var msg = '';
                if (textStatus != null) {
                    msg += '[' + textStatus.toUpperCase() + ']';
                }
                if (errorThrown != '') {
                    if (msg != '') {
                        msg += ' ';
                    }
                    msg += errorThrown;
                }
                alert(msg);
            }
        });
    });

    // -------------

    $('.uploaded-photos .btn-sort-up').click(function(e) {
        var button = $(e.target).closest('button');
        var url = button.data('action');
        var id = button.data('id');
        var wrapper = button.closest('.photo-wrapper');

        $.ajax(url, {
            type : 'post',
            data : {
                id : id
            },
            success : function() {
                wrapper.after(wrapper.prev('.photo-wrapper'));
            },
            error : function(jqXHR, textStatus, errorThrown) {
                var msg = '';
                if (textStatus != null) {
                    msg += '[' + textStatus.toUpperCase() + ']';
                }
                if (errorThrown != '') {
                    if (msg != '') {
                        msg += ' ';
                    }
                    msg += errorThrown;
                }
                alert(msg);
            }
        });
    });

    $('.uploaded-photos .btn-sort-down').click(function(e) {
        var button = $(e.target).closest('button');
        var url = button.data('action');
        var id = button.data('id');
        var wrapper = button.closest('.photo-wrapper');

        $.ajax(url, {
            type : 'post',
            data : {
                id : id
            },
            success : function() {
                wrapper.before(wrapper.next('.photo-wrapper'));
            },
            error : function(jqXHR, textStatus, errorThrown) {
                var msg = '';
                if (textStatus != null) {
                    msg += '[' + textStatus.toUpperCase() + ']';
                }
                if (errorThrown != '') {
                    if (msg != '') {
                        msg += ' ';
                    }
                    msg += errorThrown;
                }
                alert(msg);
            }
        });
    });

});
