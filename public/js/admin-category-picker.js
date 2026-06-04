/**
 * Multi-select category picker (no external deps). Enhances #item_categories.
 */
(function () {
    const select = document.getElementById('item_categories');
    if (!select || select.multiple !== true || select.dataset.pickerReady === '1') {
        return;
    }
    select.dataset.pickerReady = '1';

    const wrap = document.createElement('div');
    wrap.className = 'cat-picker';
    select.parentNode.insertBefore(wrap, select);
    wrap.appendChild(select);
    select.classList.add('cat-picker-native');

    const trigger = document.createElement('button');
    trigger.type = 'button';
    trigger.className = 'cat-picker-trigger';
    trigger.setAttribute('aria-haspopup', 'listbox');
    trigger.setAttribute('aria-expanded', 'false');
    wrap.insertBefore(trigger, select);

    const panel = document.createElement('div');
    panel.className = 'cat-picker-panel';
    panel.hidden = true;
    wrap.appendChild(panel);

    const search = document.createElement('input');
    search.type = 'search';
    search.className = 'cat-picker-search';
    search.placeholder = 'Search categories…';
    search.autocomplete = 'off';
    panel.appendChild(search);

    const list = document.createElement('ul');
    list.className = 'cat-picker-list';
    list.setAttribute('role', 'listbox');
    panel.appendChild(list);

    const options = [];
    Array.from(select.options).forEach((opt) => {
        if (!opt.value) {
            return;
        }
        const li = document.createElement('li');
        li.className = 'cat-picker-option';
        li.dataset.name = opt.text.toLowerCase();

        const label = document.createElement('label');
        const cb = document.createElement('input');
        cb.type = 'checkbox';
        cb.value = opt.value;
        cb.checked = opt.selected;
        cb.addEventListener('change', () => {
            opt.selected = cb.checked;
            updateTrigger();
        });

        label.appendChild(cb);
        label.appendChild(document.createTextNode(opt.text));
        li.appendChild(label);
        list.appendChild(li);
        options.push({ li, cb, opt });
    });

    function selectedLabels() {
        return options.filter((o) => o.cb.checked).map((o) => o.opt.text);
    }

    function updateTrigger() {
        const labels = selectedLabels();
        if (labels.length === 0) {
            trigger.textContent = 'Select categories…';
            trigger.classList.remove('has-value');
        } else if (labels.length <= 2) {
            trigger.textContent = labels.join(', ');
            trigger.classList.add('has-value');
        } else {
            trigger.textContent = labels.length + ' categories: ' + labels.slice(0, 2).join(', ') + '…';
            trigger.classList.add('has-value');
        }
    }

    function setOpen(open) {
        panel.hidden = !open;
        trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
        if (open) {
            search.focus();
        }
    }

    trigger.addEventListener('click', () => setOpen(panel.hidden));
    document.addEventListener('click', (e) => {
        if (!wrap.contains(e.target)) {
            setOpen(false);
        }
    });

    search.addEventListener('input', () => {
        const q = search.value.trim().toLowerCase();
        options.forEach(({ li }) => {
            li.hidden = q !== '' && !li.dataset.name.includes(q);
        });
    });

    updateTrigger();
})();
