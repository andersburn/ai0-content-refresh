(function () {
    'use strict';
    var model = document.querySelector('[data-model-choice]');
    if (model) {
        var custom = document.querySelector('[name="custom_model"]');
        var customWrap = document.querySelector('[data-custom-model-wrap]');
        var pricing = document.querySelector('[data-custom-pricing]');
        var priceFields = document.querySelector('[data-price-fields]');
        var description = document.querySelector('[data-rate-description]');
        var prices = JSON.parse(document.getElementById('ai0r-prices').textContent);
        function chosenModel() { return model.value === '__other__' ? custom.value.trim() : model.value; }
        function updatePricing(reset) {
            var id = chosenModel();
            var own = prices.custom[id];
            var rates = own || prices.catalog[id];
            if (reset) {
                pricing.checked = !!own;
                ['input', 'output', 'cached', 'write'].forEach(function (key) {
                    document.querySelector('[name="rate_' + key + '"]').value = own ? own[key] : '';
                });
            }
            priceFields.hidden = !pricing.checked;
            priceFields.querySelectorAll('input').forEach(function (field) { field.disabled = !pricing.checked; field.required = pricing.checked; });
            description.textContent = pricing.checked ? 'Egne takster gemmes for ' + id + '.' : rates ? 'Standard: input $' + rates.input + ', output $' + rates.output + ', cache-læsning $' + rates.cached + ', cache-skrivning $' + rates.write + ' pr. million tokens.' : 'Ingen indbygget pris for denne model. Angiv egne takster for at få et prisestimat.';
        }
        function updateModel(reset) {
            customWrap.hidden = model.value !== '__other__';
            custom.required = model.value === '__other__';
            custom.disabled = model.value !== '__other__';
            updatePricing(reset);
        }
        model.addEventListener('change', function () { updateModel(true); if (model.value === '__other__') custom.focus(); });
        custom.addEventListener('input', function () { updatePricing(true); });
        pricing.addEventListener('change', function () { updatePricing(false); });
        document.querySelectorAll('[data-recommended-model]').forEach(function (button) {
            button.addEventListener('click', function () {
                model.value = button.dataset.recommendedModel;
                model.dispatchEvent(new Event('change', { bubbles: true }));
                model.focus();
            });
        });
        updateModel(false);
    }
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
