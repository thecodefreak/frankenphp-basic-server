document.addEventListener('click', (event) => {
    const trigger = event.target.closest('[data-confirm]');
    if (trigger && !window.confirm(trigger.dataset.confirm)) {
        event.preventDefault();
    }
});

document.querySelectorAll('[data-calendar]').forEach((calendar) => {
    const title = calendar.querySelector('[data-cal-title]');
    const grid = calendar.querySelector('[data-cal-grid]');
    const params = new URLSearchParams(location.search);
    let current = params.get('month') || new Date().toISOString().slice(0, 7);

    const monthLabel = (month) => new Date(month + '-02T00:00:00Z').toLocaleDateString(undefined, { year: 'numeric', month: 'long', timeZone: 'UTC' });

    const load = (month) => {
        current = month;
        title.textContent = monthLabel(month);
        history.replaceState(null, '', location.pathname + '?month=' + month);

        fetch('/calendar/data?month=' + month)
            .then((res) => res.json())
            .then((data) => render(month, data.days || {}));
    };

    const render = (month, days) => {
        grid.innerHTML = '';

        const weekdayNames = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        weekdayNames.forEach((name) => {
            const el = document.createElement('div');
            el.className = 'cal-weekday';
            el.textContent = name;
            grid.appendChild(el);
        });

        const [year, mon] = month.split('-').map(Number);
        const firstOfMonth = new Date(Date.UTC(year, mon - 1, 1));
        const firstWeekday = (firstOfMonth.getUTCDay() + 6) % 7; // Monday = 0
        const daysInMonth = new Date(Date.UTC(year, mon, 0)).getUTCDate();
        const todayIso = new Date().toISOString().slice(0, 10);

        for (let i = 0; i < firstWeekday; i++) {
            const el = document.createElement('div');
            el.className = 'cal-cell cal-cell-empty';
            grid.appendChild(el);
        }

        for (let day = 1; day <= daysInMonth; day++) {
            const iso = month + '-' + String(day).padStart(2, '0');
            const cell = document.createElement('div');
            cell.className = 'cal-cell' + (iso === todayIso ? ' cal-cell-today' : '');

            const number = document.createElement('div');
            number.className = 'cal-day-number';
            number.textContent = String(day);
            cell.appendChild(number);

            (days[iso] || []).forEach((post) => {
                const link = document.createElement('a');
                link.className = 'cal-post pill-' + post.status;
                link.href = '/posts/' + post.id;
                link.textContent = post.time + ' ' + (post.template_name || 'Untitled');
                link.title = post.caption || '';
                cell.appendChild(link);
            });

            grid.appendChild(cell);
        }
    };

    calendar.querySelector('[data-cal-prev]').addEventListener('click', () => {
        const [y, m] = current.split('-').map(Number);
        const prev = new Date(Date.UTC(y, m - 2, 1));
        load(prev.toISOString().slice(0, 7));
    });

    calendar.querySelector('[data-cal-next]').addEventListener('click', () => {
        const [y, m] = current.split('-').map(Number);
        const next = new Date(Date.UTC(y, m, 1));
        load(next.toISOString().slice(0, 7));
    });

    load(current);
});

document.querySelectorAll('[data-schedule-form]').forEach((form) => {
    const preview = form.querySelector('[data-schedule-preview]');
    const fields = form.querySelectorAll('[data-schedule-field]');
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
                    preview.innerHTML = '<li class="empty">No upcoming occurrences for this schedule.</li>';
                    return;
                }
                data.slots.forEach((slot) => {
                    const li = document.createElement('li');
                    li.textContent = slot.local + ' (' + slot.utc + ' UTC)';
                    preview.appendChild(li);
                });
            })
            .catch(() => {
                preview.innerHTML = '<li class="empty">Could not load preview.</li>';
            });
    };

    fields.forEach((field) => field.addEventListener('change', () => {
        clearTimeout(timer);
        timer = setTimeout(refresh, 200);
    }));

    if (preview) {
        refresh();
    }
});

document.querySelectorAll('[data-kind-select]').forEach((kindSelect) => {
    const types = JSON.parse(kindSelect.dataset.types || '{}');
    const typeSelect = document.getElementById('type');
    const kindLabel = document.getElementById('kind-label');

    kindSelect.addEventListener('change', () => {
        const options = types[kindSelect.value] || {};
        typeSelect.innerHTML = '';
        Object.entries(options).forEach(([value, label]) => {
            const option = document.createElement('option');
            option.value = value;
            option.textContent = label;
            typeSelect.appendChild(option);
        });
        if (kindLabel) {
            kindLabel.textContent = kindSelect.value;
        }
    });
});
