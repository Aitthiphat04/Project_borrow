/**
 * theme.js - Global Theme Manager for PEA ITEMFLOW
 */
(function() {
    function getSavedTheme() {
        return localStorage.getItem('itemflow_theme') || 'light';
    }

    function applyTheme(theme) {
        if (theme === 'dark') {
            document.documentElement.setAttribute('data-theme', 'dark');
        } else {
            document.documentElement.removeAttribute('data-theme');
        }
        localStorage.setItem('itemflow_theme', theme);
    }

    // Apply immediately to avoid flash of light theme
    applyTheme(getSavedTheme());

    // Listen to storage changes across tabs/windows
    window.addEventListener('storage', function(e) {
        if (e.key === 'itemflow_theme') {
            applyTheme(e.newValue || 'light');
        }
    });

    // Global helper
    window.itemflowTheme = {
        get: getSavedTheme,
        set: applyTheme,
        toggle: function() {
            var current = getSavedTheme();
            var next = current === 'dark' ? 'light' : 'dark';
            applyTheme(next);
            return next;
        }
    };
})();
