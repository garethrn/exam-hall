(function () {
    Array.prototype.forEach.call(document.querySelectorAll('.eh-file input[type="file"]'), function (file) {
        var label = file.parentNode ? file.parentNode.querySelector('span') : null;
        if (!label) {
            return;
        }
        var fallback = label.textContent;
        file.addEventListener('change', function () {
            label.textContent = file.files && file.files[0] ? file.files[0].name : fallback;
        });
    });

    Array.prototype.forEach.call(document.querySelectorAll('form[data-confirm]'), function (form) {
        form.addEventListener('submit', function (event) {
            if (!window.confirm(form.getAttribute('data-confirm'))) {
                event.preventDefault();
            }
        });
    });

    Array.prototype.forEach.call(document.querySelectorAll('form[data-confirm-replace]'), function (form) {
        form.addEventListener('submit', function (event) {
            var box = form.querySelector('input[name="replace"]');
            if (box && box.checked && !window.confirm(form.getAttribute('data-confirm-replace'))) {
                event.preventDefault();
            }
        });
    });

    var distinction = document.getElementById('eh-band-d');
    var merit = document.getElementById('eh-band-m');
    var pass = document.getElementById('eh-band-p');
    var legend = document.getElementById('eh-band-legend');

    function paintLegend() {
        if (!distinction || !merit || !pass || !legend) {
            return;
        }
        var high = parseInt(distinction.value, 10);
        var mid = parseInt(merit.value, 10);
        var low = parseInt(pass.value, 10);
        if (isNaN(high) || isNaN(mid) || isNaN(low)) {
            return;
        }
        legend.textContent = high + '–100 distinction, ' + mid + '–' + (high - 1) + ' merit, ' + low + '–' + (mid - 1) + ' pass, below ' + low + ' fail.';
    }

    [distinction, merit, pass].forEach(function (input) {
        if (input) {
            input.addEventListener('input', paintLegend);
        }
    });
    paintLegend();

    var assignAll = document.getElementById('eh-assign-all');
    var assignFilter = document.getElementById('eh-assign-filter');
    if (assignAll) {
        assignAll.addEventListener('change', function () {
            Array.prototype.forEach.call(document.querySelectorAll('.eh-assign-list input[type="checkbox"]'), function (box) {
                var row = box.closest('[data-student]');
                if (!row || !row.hidden) {
                    box.checked = assignAll.checked;
                }
            });
        });
    }
    if (assignFilter) {
        assignFilter.addEventListener('input', function () {
            var query = assignFilter.value.toLowerCase();
            Array.prototype.forEach.call(document.querySelectorAll('[data-student]'), function (row) {
                row.hidden = query !== '' && row.getAttribute('data-student').indexOf(query) === -1;
            });
        });
    }

    var stage = document.getElementById('eh-cert-stage');
    if (stage) {
        Array.prototype.forEach.call(stage.querySelectorAll('.eh-cert-field'), function (field) {
            var key = field.getAttribute('data-field');
            var xInput = document.getElementById('eh-cert-' + key + '-x');
            var yInput = document.getElementById('eh-cert-' + key + '-y');
            function place(x, y) {
                x = Math.max(0, Math.min(100, x));
                y = Math.max(0, Math.min(100, y));
                field.style.left = x + '%';
                field.style.top = y + '%';
                if (xInput) {
                    xInput.value = x.toFixed(1);
                }
                if (yInput) {
                    yInput.value = y.toFixed(1);
                }
            }
            field.addEventListener('pointerdown', function (event) {
                field.setPointerCapture(event.pointerId);
                function move(ev) {
                    var rect = stage.getBoundingClientRect();
                    place(((ev.clientX - rect.left) / rect.width) * 100, ((ev.clientY - rect.top) / rect.height) * 100);
                }
                function stop() {
                    field.removeEventListener('pointermove', move);
                    field.removeEventListener('pointerup', stop);
                }
                field.addEventListener('pointermove', move);
                field.addEventListener('pointerup', stop);
            });
            [xInput, yInput].forEach(function (input) {
                if (!input) {
                    return;
                }
                input.addEventListener('input', function () {
                    place(parseFloat(xInput.value), parseFloat(yInput.value));
                });
            });
        });
    }
}());
