(function () {
    var root = document.documentElement;
    var KEY = 'theme';

    function read() {
        try {
            var s = localStorage.getItem(KEY);
            return (s === 'light' || s === 'dark') ? s : 'auto';
        } catch (e) { return 'auto'; }
    }
    function write(mode) {
        try {
            if (mode === 'auto') localStorage.removeItem(KEY);
            else localStorage.setItem(KEY, mode);
        } catch (e) {}
    }
    function apply(mode) {
        if (mode === 'auto') root.removeAttribute('data-theme');
        else root.setAttribute('data-theme', mode);
    }

    apply(read());

    document.addEventListener('DOMContentLoaded', function () {
        var sel = document.getElementById('theme-select');
        if (!sel) return;
        sel.value = read();
        sel.addEventListener('change', function () {
            write(sel.value);
            apply(sel.value);
        });
    });
})();