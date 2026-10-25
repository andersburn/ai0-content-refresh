(function () {
    'use strict';
    document.querySelectorAll('[data-recommended-model]').forEach(function (button) {
        button.addEventListener('click', function () {
            var input = document.querySelector('input[name="model"]');
            if (input) {
                input.value = button.dataset.recommendedModel;
                input.dispatchEvent(new Event('change', { bubbles: true }));
                input.focus();
            }
        });
    });
    document.querySelectorAll('[data-exclusions]').forEach(function (picker) {
        var search = picker.querySelector('[data-exclusion-search]');
        var rows = Array.from(picker.querySelectorAll('[data-exclusion-option]'));
        var count = picker.querySelector('[data-selection-count]');
        var empty = picker.querySelector('[data-no-matches]');
        var selected = picker.nextElementSibling;
        function update() {
            var query = search.value.trim().toLocaleLowerCase('da');
            var visible = 0;
            var names = [];
            rows.forEach(function (row) {
                var input = row.querySelector('input');
                var label = row.querySelector('span').textContent;
                row.hidden = query !== '' && !label.toLocaleLowerCase('da').includes(query);
                if (!row.hidden) visible++;
                if (input.checked) names.push(label);
            });
            count.textContent = '(' + names.length + ' valgt)';
            empty.hidden = visible > 0;
            if (selected) selected.textContent = names.length ? 'Valgt: ' + names.join('; ') : 'Ingen sider eller indlæg er manuelt udelukket.';
        }
        search.addEventListener('input', update);
        picker.addEventListener('change', update);
        picker.addEventListener('toggle', function () { if (picker.open) search.focus(); });
        picker.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') { picker.open = false; picker.querySelector('summary').focus(); }
        });
        picker.querySelector('[data-clear-exclusions]').addEventListener('click', function () {
            rows.forEach(function (row) { row.querySelector('input').checked = false; });
            update();
        });
        update();
    });
}());
