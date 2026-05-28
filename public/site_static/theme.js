(function () {
    function getPreferredTheme() {
        try {
            var stored = localStorage.getItem('theme');
            if (stored === 'dark' || stored === 'light') {
                return stored;
            }
        } catch (e) {}
        return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    }

    function applyTheme(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        document.querySelectorAll('.theme-toggle').forEach(function (btn) {
            var isDark = theme === 'dark';
            btn.setAttribute('aria-label', isDark ? 'Включить светлую тему' : 'Включить тёмную тему');
            btn.setAttribute('title', isDark ? 'Светлая тема' : 'Тёмная тема');
        });
    }

    function initToggle() {
        document.querySelectorAll('.theme-toggle').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var next = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
                try {
                    localStorage.setItem('theme', next);
                } catch (e) {}
                applyTheme(next);
            });
        });
    }

    applyTheme(document.documentElement.getAttribute('data-theme') || getPreferredTheme());

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initToggle);
    } else {
        initToggle();
    }

    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function (e) {
        try {
            if (localStorage.getItem('theme')) {
                return;
            }
        } catch (err) {}
        applyTheme(e.matches ? 'dark' : 'light');
    });
})();
