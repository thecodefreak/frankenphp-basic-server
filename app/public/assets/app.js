const money = (value) => {
    const n = Number(value) || 0;
    if (n === 0) return '$0.00';
    return n < 0.01 ? '$' + n.toFixed(4) : '$' + n.toFixed(2);
};

document.addEventListener('click', (event) => {
    const trigger = event.target.closest('[data-confirm]');
    if (trigger && !window.confirm(trigger.dataset.confirm)) {
        event.preventDefault();
        event.stopImmediatePropagation();
    }
});

const themeButton = document.querySelector('[data-theme-toggle]');
if (themeButton) {
    const label = themeButton.querySelector('[data-theme-label]');
    const order = ['system', 'light', 'dark'];
    const icons = { system: '◐', light: '☀', dark: '☾' };

    const apply = (mode) => {
        if (mode === 'system') {
            document.documentElement.removeAttribute('data-theme');
        } else {
            document.documentElement.setAttribute('data-theme', mode);
        }
        localStorage.setItem('theme', mode);
        themeButton.querySelector('[data-theme-icon]').textContent = icons[mode];
        if (label) label.textContent = mode[0].toUpperCase() + mode.slice(1);
    };

    apply(localStorage.getItem('theme') || 'system');
    themeButton.addEventListener('click', () => {
        const next = order[(order.indexOf(localStorage.getItem('theme') || 'system') + 1) % order.length];
        apply(next);
    });
}

const navToggle = document.querySelector('[data-nav-toggle]');
if (navToggle) {
    const close = () => {
        document.body.classList.remove('nav-open');
        navToggle.setAttribute('aria-expanded', 'false');
    };
    navToggle.addEventListener('click', () => {
        const open = document.body.classList.toggle('nav-open');
        navToggle.setAttribute('aria-expanded', String(open));
    });
    document.querySelector('[data-scrim]')?.addEventListener('click', close);
    document.addEventListener('keydown', (e) => e.key === 'Escape' && close());
}

document.querySelectorAll('[data-flash]').forEach((flash) => {
    flash.querySelector('[data-flash-close]')?.addEventListener('click', () => flash.remove());
    if (flash.classList.contains('flash-success')) {
        setTimeout(() => flash.remove(), 7000);
    }
});

document.querySelectorAll('form').forEach((form) => {
    form.addEventListener('submit', (event) => {
        if (event.defaultPrevented || form.noValidate === false && !form.checkValidity()) return;

        const button = event.submitter;
        if (!button || button.dataset.noBusy !== undefined) return;

        const busyLabel = button.dataset.busy || 'Working…';
        setTimeout(() => {
            button.disabled = true;
            button.innerHTML = '<span class="spinner" aria-hidden="true"></span>' + busyLabel;
        }, 0);
    });
});

document.querySelectorAll('[data-reveal]').forEach((wrap) => {
    const input = wrap.querySelector('input');
    const button = wrap.querySelector('button');
    button.addEventListener('click', () => {
        const shown = input.type === 'text';
        input.type = shown ? 'password' : 'text';
        button.textContent = shown ? 'Show' : 'Hide';
        button.setAttribute('aria-label', shown ? 'Show value' : 'Hide value');
    });
});

document.querySelectorAll('[data-counter]').forEach((field) => {
    const input = field.querySelector('textarea, input');
    const output = field.querySelector('[data-counter-output]');
    const max = Number(field.dataset.counter);

    const update = () => {
        const length = input.value.length;
        const tags = (input.value.match(/#[\p{L}\p{N}_]+/gu) || []).length;
        output.textContent = `${length}/${max} characters · ${tags}/30 hashtags`;
        output.classList.toggle('counter-over', length > max || tags > 30);
        output.classList.toggle('counter-warn', length <= max && length > max * 0.9);
    };

    input.addEventListener('input', update);
    update();
});

document.querySelectorAll('[data-lightbox]').forEach((grid) => {
    const dialog = document.createElement('dialog');
    dialog.className = 'lightbox';
    dialog.innerHTML = '<img alt="">';
    document.body.appendChild(dialog);

    grid.addEventListener('click', (event) => {
        const button = event.target.closest('button[data-full]');
        if (!button) return;
        dialog.querySelector('img').src = button.dataset.full;
        dialog.showModal();
    });

    dialog.addEventListener('click', () => dialog.close());
});

document.querySelectorAll('[data-times]').forEach((widget) => {
    const hidden = widget.querySelector('input[type="hidden"]');
    const list = widget.querySelector('[data-times-list]');
    const picker = widget.querySelector('input[type="time"]');
    const addButton = widget.querySelector('[data-times-add]');

    const values = () => hidden.value.split(',').map((v) => v.trim()).filter(Boolean);

    const write = (times) => {
        const unique = [...new Set(times)].sort();
        hidden.value = unique.join(',');
        render(unique);
        hidden.dispatchEvent(new Event('change', { bubbles: true }));
    };

    const render = (times) => {
        list.innerHTML = '';
        if (times.length === 0) {
            list.innerHTML = '<span class="field-hint">No times yet — add at least one.</span>';
            return;
        }
        times.forEach((time) => {
            const chip = document.createElement('span');
            chip.className = 'chip';
            chip.textContent = time;
            const remove = document.createElement('button');
            remove.type = 'button';
            remove.textContent = '×';
            remove.setAttribute('aria-label', 'Remove ' + time);
            remove.addEventListener('click', () => write(values().filter((t) => t !== time)));
            chip.appendChild(remove);
            list.appendChild(chip);
        });
    };

    const add = () => {
        if (!picker.value) return;
        write([...values(), picker.value]);
        picker.value = '';
    };

    addButton.addEventListener('click', add);
    picker.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            add();
        }
    });

    render(values());
});

document.querySelectorAll('[data-weekday-preset]').forEach((button) => {
    button.addEventListener('click', () => {
        const form = button.closest('form');
        const wanted = button.dataset.weekdayPreset.split(',').filter(Boolean);
        form.querySelectorAll('input[name="weekdays[]"]').forEach((cb) => {
            cb.checked = wanted.includes(cb.value);
        });
        form.querySelector('input[name="weekdays[]"]').dispatchEvent(new Event('change', { bubbles: true }));
    });
});

document.querySelectorAll('[data-schedule-form]').forEach((form) => {
    const preview = form.querySelector('[data-schedule-preview]');
    if (!preview) return;
    let timer = null;

    const refresh = () => {
        const body = new URLSearchParams();
        body.set('timezone', form.timezone.value);
        body.set('times', form.times.value);
        form.querySelectorAll('input[name="weekdays[]"]:checked').forEach((cb) => body.append('weekdays[]', cb.value));

        fetch('/templates/preview-slots', { method: 'POST', body })
            .then((res) => res.json())
            .then((data) => {
                preview.innerHTML = '';
                if (!data.slots || data.slots.length === 0) {
                    preview.innerHTML = '<li>No upcoming occurrences — pick at least one time and one day.</li>';
                    return;
                }
                data.slots.forEach((slot) => {
                    const li = document.createElement('li');
                    li.textContent = slot.local + '  ·  ' + slot.utc + ' UTC';
                    preview.appendChild(li);
                });
            })
            .catch(() => {
                preview.innerHTML = '<li>Could not load preview.</li>';
            });
    };

    form.addEventListener('change', (event) => {
        if (!event.target.matches('[data-schedule-field], input[name="weekdays[]"]')) return;
        clearTimeout(timer);
        timer = setTimeout(refresh, 180);
    });

    refresh();
});

document.querySelectorAll('[data-kind-select]').forEach((kindSelect) => {
    const types = JSON.parse(kindSelect.dataset.types || '{}');
    const typeSelect = document.getElementById('type');
    const form = kindSelect.closest('form');

    const syncPricing = () => {
        form.querySelectorAll('[data-price-for]').forEach((field) => {
            field.hidden = field.dataset.priceFor !== kindSelect.value;
        });
    };

    kindSelect.addEventListener('change', () => {
        const options = types[kindSelect.value] || {};
        typeSelect.innerHTML = '';
        Object.entries(options).forEach(([value, label]) => {
            const option = document.createElement('option');
            option.value = value;
            option.textContent = label;
            typeSelect.appendChild(option);
        });
        syncPricing();
    });

    syncPricing();
});

document.querySelectorAll('[data-calendar]').forEach((calendar) => {
    const title = calendar.querySelector('[data-cal-title]');
    const grid = calendar.querySelector('[data-cal-grid]');
    const summary = calendar.querySelector('[data-cal-summary]');
    const monthInput = calendar.querySelector('[data-cal-month]');
    const params = new URLSearchParams(location.search);
    let current = params.get('month') || new Date().toISOString().slice(0, 7);

    const monthLabel = (month) =>
        new Date(month + '-02T00:00:00Z').toLocaleDateString(undefined, { year: 'numeric', month: 'long', timeZone: 'UTC' });

    const load = (month) => {
        current = month;
        title.textContent = monthLabel(month);
        if (monthInput) monthInput.value = month;
        history.replaceState(null, '', location.pathname + '?month=' + month);
        grid.classList.add('is-loading');

        fetch('/calendar/data?month=' + month)
            .then((res) => res.json())
            .then((data) => {
                renderSummary(data.totals || {});
                render(month, data.days || {});
            })
            .finally(() => grid.classList.remove('is-loading'));
    };

    const renderSummary = (totals) => {
        const items = [
            ['Scheduled', totals.posts || 0],
            ['Published', totals.published || 0],
            ['Failed / skipped', totals.failed || 0],
            ['AI cost', money(totals.cost_usd)],
        ];
        summary.innerHTML = '';
        items.forEach(([label, value]) => {
            const item = document.createElement('div');
            item.className = 'summary-item';
            item.innerHTML = `<span class="summary-value">${value}</span><span class="summary-label">${label}</span>`;
            summary.appendChild(item);
        });
    };

    const render = (month, days) => {
        grid.innerHTML = '';

        ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'].forEach((name) => {
            const el = document.createElement('div');
            el.className = 'cal-weekday';
            el.textContent = name;
            grid.appendChild(el);
        });

        const [year, mon] = month.split('-').map(Number);
        const firstWeekday = (new Date(Date.UTC(year, mon - 1, 1)).getUTCDay() + 6) % 7;
        const daysInMonth = new Date(Date.UTC(year, mon, 0)).getUTCDate();
        const todayIso = new Date().toISOString().slice(0, 10);

        for (let i = 0; i < firstWeekday; i++) {
            const el = document.createElement('div');
            el.className = 'cal-cell cal-cell-empty';
            grid.appendChild(el);
        }

        for (let day = 1; day <= daysInMonth; day++) {
            const iso = month + '-' + String(day).padStart(2, '0');
            const info = days[iso];
            const cell = document.createElement('div');
            cell.className = 'cal-cell'
                + (iso === todayIso ? ' cal-cell-today' : '')
                + (info ? ' cal-cell-has' : '');

            const head = document.createElement('div');
            head.className = 'cal-day-head';
            head.innerHTML = `<span class="cal-day-number">${day}</span>`;
            if (info && info.cost_usd > 0) {
                head.insertAdjacentHTML('beforeend', `<span class="cal-day-cost">${money(info.cost_usd)}</span>`);
            }
            cell.appendChild(head);

            if (info) {
                const published = info.published;
                const failed = info.posts.filter((p) => p.status === 'failed' || p.status === 'skipped').length;
                const other = info.count - published - failed;

                const counts = document.createElement('div');
                counts.className = 'cal-counts';
                if (published) counts.insertAdjacentHTML('beforeend', `<span class="cal-count cal-count-published">${published} posted</span>`);
                if (other) counts.insertAdjacentHTML('beforeend', `<span class="cal-count cal-count-pending">${other} planned</span>`);
                if (failed) counts.insertAdjacentHTML('beforeend', `<span class="cal-count cal-count-failed">${failed} failed</span>`);
                cell.appendChild(counts);

                info.posts.slice(0, 2).forEach((post) => {
                    const link = document.createElement('a');
                    link.className = 'cal-post pill-' + post.status;
                    link.href = '/posts/' + post.id;
                    link.textContent = post.time + ' ' + (post.template_name || 'Untitled');
                    link.title = (post.caption || '') + (post.cost_usd ? ` — ${money(post.cost_usd)}` : '');
                    cell.appendChild(link);
                });

                if (info.count > 2) {
                    cell.insertAdjacentHTML('beforeend', `<span class="cal-more">+${info.count - 2} more</span>`);
                }
            }

            grid.appendChild(cell);
        }
    };

    const shift = (delta) => {
        const [y, m] = current.split('-').map(Number);
        load(new Date(Date.UTC(y, m - 1 + delta, 1)).toISOString().slice(0, 7));
    };

    calendar.querySelector('[data-cal-prev]').addEventListener('click', () => shift(-1));
    calendar.querySelector('[data-cal-next]').addEventListener('click', () => shift(1));
    calendar.querySelector('[data-cal-today]')?.addEventListener('click', () => load(new Date().toISOString().slice(0, 7)));
    monthInput?.addEventListener('change', () => monthInput.value && load(monthInput.value));

    load(current);
});
