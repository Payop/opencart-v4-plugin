(() => {
    'use strict';
    const config = JSON.parse(document.getElementById('payop-button-settings').textContent);
    const labels = config.labels;
    const form = document.getElementById('form-payment');
    const rows = document.getElementById('payop-button-rows');
    const json = document.getElementById('payop-buttons-json');
    const message = document.getElementById('payop-methods-message');
    const loadButton = document.getElementById('payop-load-methods');
    let methods = [];
    const el = (tag, className, text) => {
        const node = document.createElement(tag);
        if (className) node.className = className;
        if (text !== undefined) node.textContent = text;
        return node;
    };
    const option = (select, value, title) => {
        const item = el('option', '', title);
        item.value = value;
        select.append(item);
    };
    function field(row, title, control) {
        const label = el('label', 'd-block mb-3');
        label.append(el('span', 'form-label d-block', title), control);
        row.append(label);
        return label;
    }
    function fillMethods(select, selected) {
        select.replaceChildren();
        option(select, '', labels.text_select_method);
        if (selected && !methods.some(method => method.identifier === selected)) {
            option(select, selected, labels.text_saved_method + ' ' + selected);
        }
        methods.forEach(method => option(select, method.identifier, method.title + ' (' + method.identifier + ')'));
        select.value = selected;
    }
    function sync() {
        const buttons = {};
        rows.querySelectorAll('.payop-button-row').forEach(row => {
            const language = {};
            row.querySelectorAll('[data-language]').forEach(group => {
                language[group.dataset.language] = {
                    name: group.querySelector('.payop-name').value,
                    description: group.querySelector('.payop-description').value
                };
            });
            buttons[row.dataset.id] = {
                enabled: Number(row.querySelector('.payop-enabled').value),
                integration_type: row.querySelector('.payop-type').value,
                payment_method: row.querySelector('.payop-method').value,
                revolut_fields: Number(row.querySelector('.payop-revolut').value),
                language
            };
        });
        json.value = JSON.stringify(buttons);
    }
    function addRow(id, settings = {}) {
        if (!settings || typeof settings !== 'object') settings = {};
        const row = el('div', 'payop-button-row border rounded p-3 mb-3');
        row.dataset.id = id;
        const enabled = el('select', 'form-select payop-enabled');
        option(enabled, '1', labels.text_enabled);
        option(enabled, '0', labels.text_disabled);
        enabled.value = String(settings.enabled ?? 1);
        field(row, labels.entry_status, enabled);
        const type = el('select', 'form-select payop-type');
        option(type, 'hosted_page', labels.text_hosted_page);
        option(type, 'payment_method', labels.text_payment_method);
        type.value = settings.integration_type || 'hosted_page';
        field(row, labels.entry_integration_type, type);
        const method = el('select', 'form-select payop-method');
        fillMethods(method, String(settings.payment_method || ''));
        const methodField = field(row, labels.entry_payment_method, method);
        const revolut = el('select', 'form-select payop-revolut');
        option(revolut, '0', labels.text_disabled);
        option(revolut, '1', labels.text_enabled);
        revolut.value = String(settings.revolut_fields || 0);
        const revolutField = field(row, labels.entry_revolut_fields, revolut);
        config.languages.forEach(language => {
            const group = el('fieldset', 'mb-2');
            group.dataset.language = String(language.language_id);
            group.append(el('legend', 'fs-6', language.name));
            const text = settings.language?.[language.language_id] || {};
            const name = el('input', 'form-control payop-name');
            name.type = 'text'; name.value = text.name || '';
            field(group, labels.entry_button_name, name);
            const description = el('textarea', 'form-control payop-description');
            description.rows = 2; description.value = text.description || '';
            field(group, labels.entry_button_description, description);
            row.append(group);
        });
        const remove = el('button', 'btn btn-danger', labels.button_remove);
        remove.type = 'button';
        remove.addEventListener('click', () => { row.remove(); sync(); });
        row.append(remove);
        const update = () => {
            methodField.hidden = type.value !== 'payment_method';
            methodField.classList.toggle('d-none', methodField.hidden);
            revolutField.hidden = type.value !== 'payment_method';
            revolutField.classList.toggle('d-none', revolutField.hidden);
            method.required = type.value === 'payment_method';
            row.querySelectorAll('.payop-name').forEach(name => { name.required = enabled.value === '1'; });
            sync();
        };
        row.addEventListener('change', update);
        row.addEventListener('input', sync);
        rows.append(row);
        update();
    }
    Object.entries(config.buttons).forEach(([id, settings]) => addRow(id, settings));
    document.getElementById('payop-add-button').addEventListener('click', () => {
        // Stable identifiers prevent an old session selecting a different row after deletion.
        const random = new Uint8Array(8);
        crypto.getRandomValues(random);
        addRow('button_' + Array.from(random, byte => byte.toString(16).padStart(2, '0')).join(''));
    });
    form.addEventListener('submit', sync);
    loadButton.addEventListener('click', async () => {
        loadButton.disabled = true;
        message.textContent = '';
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), 25000);
        try {
            const body = new URLSearchParams({
                payment_payop_project_id: document.getElementById('entry-project-id').value,
                payment_payop_jwt_token: document.getElementById('entry-jwt-token').value
            });
            const response = await fetch(config.methodsUrl, { method: 'POST', body, signal: controller.signal });
            if (!response.ok) throw new Error(labels.error_methods_network);
            const result = await response.json();
            if (result.error) throw new Error(result.error);
            if (!Array.isArray(result.methods)) throw new Error(labels.error_methods_network);
            methods = result.methods;
            rows.querySelectorAll('.payop-method').forEach(select => fillMethods(select, select.value));
            message.className = 'alert alert-success';
            message.textContent = labels.text_methods_loaded;
        } catch (error) {
            message.className = 'alert alert-warning';
            message.textContent = error.name === 'AbortError' ? labels.error_methods_network : error.message;
            // Keep every row and its saved selection when API access fails.
        } finally {
            clearTimeout(timeout);
            loadButton.disabled = false;
        }
    });
})();
