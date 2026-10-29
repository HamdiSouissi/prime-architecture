import { gantt } from 'dhtmlx-gantt';
import 'dhtmlx-gantt/codebase/dhtmlxgantt.css';

const element = document.getElementById('planning-gantt');
if (element) {
    const message = document.getElementById('gantt-message');
    gantt.config.readonly = true;
    gantt.config.rtl = element.dataset.locale === 'ar';
    gantt.config.date_format = '%Y-%m-%d';
    gantt.config.columns = [
        { name: 'text', label: element.dataset.title, tree: true, width: 220 },
        { name: 'start_date', label: element.dataset.start, align: 'center', width: 110 },
    ];
    const scales = {
        day: [{ unit: 'month', step: 1, format: '%F %Y' }, { unit: 'day', step: 1, format: '%d' }],
        week: [{ unit: 'month', step: 1, format: '%F %Y' }, { unit: 'week', step: 1, format: '%d %M' }],
        month: [{ unit: 'year', step: 1, format: '%Y' }, { unit: 'month', step: 1, format: '%M' }],
    };
    let zoom = 'day';
    gantt.config.scales = scales[zoom];
    gantt.templates.timeline_cell_class = (task, date) => {
        const now = new Date();
        const start = new Date(date.getFullYear(), date.getMonth(), date.getDate());
        const end = new Date(start);
        if (zoom === 'day') end.setDate(end.getDate() + 1);
        if (zoom === 'week') end.setDate(end.getDate() + 7);
        if (zoom === 'month') end.setMonth(end.getMonth() + 1);
        return now >= start && now < end ? 'planning-today' : '';
    };
    gantt.attachEvent('onTaskDblClick', (id) => {
        if (/^t\d+$/.test(String(id))) window.location.assign(`${element.dataset.taskBase}/${String(id).slice(1)}`);
        return false;
    });
    gantt.init(element);
    document.querySelectorAll('[data-gantt-zoom]').forEach((button) => {
        button.addEventListener('click', () => {
            zoom = button.dataset.ganttZoom;
            gantt.config.scales = scales[zoom];
            gantt.render();
        });
    });
    fetch(element.dataset.url, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
        .then((response) => { if (!response.ok) throw new Error('Gantt request failed'); return response.json(); })
        .then((data) => { gantt.parse(data); if (data.truncated) message.textContent = element.dataset.truncated; })
        .catch(() => { message.textContent = element.dataset.failed; });
}
