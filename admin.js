(function () {
    'use strict';
    document.querySelectorAll('.la-admin [data-toggle-password]').forEach(function (button) {
        button.addEventListener('click', function () {
            var input = document.getElementById(button.getAttribute('data-toggle-password'));
            if (!input) return;
            var visible = input.type === 'password';
            input.type = visible ? 'text' : 'password';
            button.textContent = visible ? 'پنهان' : 'نمایش';
            button.setAttribute('aria-pressed', visible ? 'true' : 'false');
        });
    });

    function oldCopy(value) {
        var field = document.createElement('textarea');
        field.value = value;
        field.readOnly = true;
        field.style.position = 'fixed';
        field.style.opacity = '0';
        document.body.appendChild(field);
        field.select();
        var copied = false;
        try { copied = document.execCommand('copy'); } catch (ignored) { /* no clipboard */ }
        document.body.removeChild(field);
        return copied;
    }

    document.querySelectorAll('.la-admin [data-copy]').forEach(function (button) {
        button.addEventListener('click', function () {
            var value = button.getAttribute('data-copy');
            var clipboard = navigator.clipboard && window.isSecureContext
                ? navigator.clipboard.writeText(value).then(function () { return true; }, function () { return oldCopy(value); })
                : Promise.resolve(oldCopy(value));
            clipboard.then(function (copied) {
                if (!copied) return;
                button.textContent = 'کپی شد ✓';
                button.classList.add('is-copied');
                setTimeout(function () {
                    button.textContent = 'کپی';
                    button.classList.remove('is-copied');
                }, 1600);
            });
        });
    });
}());
