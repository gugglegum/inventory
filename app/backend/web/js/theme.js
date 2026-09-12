(function () {
    'use strict';

    var key = 'stockhub.theme';
    var systemTheme = window.matchMedia('(prefers-color-scheme: dark)');
    var preference = 'auto';

    function normalize(value) {
        return value === 'light' || value === 'dark' ? value : 'auto';
    }

    try {
        preference = normalize(window.localStorage.getItem(key));
    } catch (error) {
        // Theme selection still works when browser storage is unavailable.
    }

    function apply() {
        var theme = preference === 'auto' ? (systemTheme.matches ? 'dark' : 'light') : preference;
        document.documentElement.setAttribute('data-bs-theme', theme);
        var select = document.getElementById('theme-select');
        if (select) {
            select.value = preference;
        }
    }

    apply();
    systemTheme.addEventListener('change', apply);
    window.addEventListener('storage', function (event) {
        if (event.key === key || event.key === null) {
            preference = normalize(event.newValue);
            apply();
        }
    });
    document.addEventListener('DOMContentLoaded', apply);
    document.addEventListener('change', function (event) {
        if (event.target.id !== 'theme-select') {
            return;
        }
        preference = normalize(event.target.value);
        try {
            if (preference === 'auto') {
                window.localStorage.removeItem(key);
            } else {
                window.localStorage.setItem(key, preference);
            }
        } catch (error) {
            // Keep the selected theme for the current page.
        }
        apply();
    });
}());
