(function () {
    'use strict';

    var form = document.getElementById('itemSearchForm');
    if (!form) {
        return;
    }

    var details = form.querySelector('#advancedSearchOptions');
    if (!details) {
        return;
    }

    var queryInput = form.querySelector('input[name="q"]');
    var advancedInputs = Array.prototype.slice.call(
        details.querySelectorAll('input[name="description"], input[name="notes"]')
    );

    function syncAdvancedInputs() {
        advancedInputs.forEach(function (input) {
            input.disabled = !details.open;
        });
    }

    details.addEventListener('toggle', syncAdvancedInputs);
    form.addEventListener('formdata', function (event) {
        if (queryInput && queryInput.value.trim() === '') {
            event.formData.delete(queryInput.name);
        }

        advancedInputs.forEach(function (input) {
            if (!details.open || input.value.trim() === '') {
                event.formData.delete(input.name);
            }
        });
    });

    window.addEventListener('pageshow', syncAdvancedInputs);
    syncAdvancedInputs();
}());
