(function () {
    'use strict';

    const HOUR_PX = 56;
    const PX_PER_MIN = HOUR_PX / 60;
    const MONTHS = ['มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];
    const MONTHS_SHORT = ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
    const DAYS = ['อาทิตย์', 'จันทร์', 'อังคาร', 'พุธ', 'พฤหัสบดี', 'ศุกร์', 'เสาร์'];
    const DAYS_SHORT = ['อา', 'จ', 'อ', 'พ', 'พฤ', 'ศ', 'ส'];

    const state = {
        view: 'month',
        cursor: startOfDay(new Date()),
        miniMonth: new Date(new Date().getFullYear(), new Date().getMonth(), 1),
        selected: new Set(),
        mineOnly: false,
        events: [],
        editing: null
    };

    const grid = document.getElementById('calGrid');
    const form = document.getElementById('eventForm');
    const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('eventModal'));
    let searchTimer = null;
    let searchAbort = null;

    document.addEventListener('DOMContentLoaded', init);

    function init() {
        const savedView = localStorage.getItem('cc.view');
        state.view = ['day', 'week', 'month', 'year'].includes(savedView) ? savedView : 'month';
        state.selected = loadSelected();
        state.mineOnly = localStorage.getItem('cc.mine') === '1';
        bind();
        renderFilters();
        loadEvents();
        setInterval(moveNowLine, 30000);
    }

    function bind() {
        document.getElementById('btnToday').addEventListener('click', () => shift(0));
        document.getElementById('btnPrev').addEventListener('click', () => shift(-1));
        document.getElementById('btnNext').addEventListener('click', () => shift(1));
        document.querySelectorAll('[data-view]').forEach((button) => {
            button.addEventListener('click', () => setView(button.dataset.view));
        });
        document.querySelectorAll('[data-create]').forEach((button) => {
            button.addEventListener('click', () => {
                closeSide();
                openCreate();
            });
        });
        document.getElementById('deptAll').addEventListener('click', () => {
            state.selected = new Set(CAL.departments.map((item) => item.id));
            saveSelected();
            renderFilters();
            loadEvents();
        });
        document.getElementById('deptNone').addEventListener('click', () => {
            state.selected = new Set();
            saveSelected();
            renderFilters();
            loadEvents();
        });
        document.getElementById('mineOnly')?.addEventListener('change', (event) => {
            state.mineOnly = event.target.checked;
            localStorage.setItem('cc.mine', state.mineOnly ? '1' : '0');
            loadEvents();
        });
        document.getElementById('btnLoginToEdit').addEventListener('click', () => {
            window.location = CAL.endpoints.login;
        });
        document.getElementById('eventAllDay').addEventListener('change', syncAllDay);
        form.addEventListener('submit', saveEvent);
        document.getElementById('btnDeleteEvent').addEventListener('click', deleteEvent);
        const search = document.getElementById('searchInput');
        search.addEventListener('input', () => {
            clearTimeout(searchTimer);
            const query = search.value.trim();
            if (query.length < 2) {
                hideSearch();
                return;
            }
            searchTimer = setTimeout(() => runSearch(query), 300);
        });
        document.addEventListener('click', (event) => {
            if (!event.target.closest('.cal-search')) {
                hideSearch();
            }
        });
        document.addEventListener('keydown', (event) => {
            if (event.target.matches('input, textarea, select')) {
                return;
            }
            if (event.key === 't') {
                shift(0);
            }
            if (event.key === 'ArrowLeft') {
                shift(-1);
            }
            if (event.key === 'ArrowRight') {
                shift(1);
            }
        });
    }

    function csrf() {
        return document.querySelector('meta[name="csrf-token"]').content;
    }

    function loadSelected() {
        const all = CAL.departments.map((item) => item.id);
        const raw = localStorage.getItem('cc.depts');
        if (raw === null) {
            return new Set(all);
        }
        try {
            const ids = JSON.parse(raw).map(Number).filter((id) => all.includes(id));
            return new Set(ids);
        } catch (error) {
            return new Set(all);
        }
    }

    function saveSelected() {
        localStorage.setItem('cc.depts', JSON.stringify([...state.selected]));
    }

    function setView(view) {
        state.view = view;
        localStorage.setItem('cc.view', view);
        loadEvents();
    }

    function shift(direction) {
        if (direction === 0) {
            state.cursor = startOfDay(new Date());
        } else if (state.view === 'year') {
            const next = new Date(state.cursor);
            next.setFullYear(next.getFullYear() + direction);
            state.cursor = next;
        } else if (state.view === 'month') {
            const next = new Date(state.cursor);
            next.setMonth(next.getMonth() + direction);
            state.cursor = next;
        } else if (state.view === 'week') {
            state.cursor = addDays(state.cursor, 7 * direction);
        } else {
            state.cursor = addDays(state.cursor, direction);
        }
        state.miniMonth = new Date(state.cursor.getFullYear(), state.cursor.getMonth(), 1);
        loadEvents();
    }

    async function loadEvents() {
        setLoading(true);
        try {
            const range = visibleRange();
            const params = new URLSearchParams({
                start: range.start,
                end: range.end,
                departments: [...state.selected].join(','),
                mine: state.mineOnly ? '1' : '0'
            });
            const response = await fetch(`${CAL.endpoints.events}&${params}`, {
                headers: { Accept: 'application/json' }
            });
            if (response.status === 401) {
                window.location = CAL.endpoints.login;
                return;
            }
            const data = await response.json();
            if (!response.ok || !data.ok) {
                throw new Error(data.message || 'โหลดข้อมูลไม่สำเร็จ');
            }
            state.events = data.events;
            render();
        } catch (error) {
            Swal.fire({ icon: 'error', title: 'โหลดปฏิทินไม่สำเร็จ', text: error.message, confirmButtonColor: '#1a73e8' });
        } finally {
            setLoading(false);
        }
    }

    function render() {
        document.getElementById('calTitle').textContent = titleText();
        document.querySelectorAll('[data-view]').forEach((button) => {
            const active = button.dataset.view === state.view;
            button.classList.toggle('is-active', active);
            button.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
        renderMini();
        if (state.view === 'year') {
            renderYear();
        } else if (state.view === 'month') {
            renderMonth();
        } else {
            renderTimeGrid(state.view === 'week' ? weekDays(state.cursor) : [startOfDay(state.cursor)]);
        }
    }

    function titleText() {
        const date = state.cursor;
        if (state.view === 'year') {
            return String(date.getFullYear());
        }
        if (state.view === 'month') {
            return `${MONTHS[date.getMonth()]} ${date.getFullYear()}`;
        }
        if (state.view === 'day') {
            return `วัน${DAYS[date.getDay()]}ที่ ${date.getDate()} ${MONTHS[date.getMonth()]} ${date.getFullYear()}`;
        }
        const days = weekDays(date);
        const start = days[0];
        const end = days[6];
        const sameMonth = start.getMonth() === end.getMonth();
        const startLabel = sameMonth ? String(start.getDate()) : `${start.getDate()} ${MONTHS_SHORT[start.getMonth()]}`;
        return `${startLabel} – ${end.getDate()} ${MONTHS_SHORT[end.getMonth()]} ${end.getFullYear()}`;
    }

    function renderYear() {
        grid.className = 'cal-grid is-year';
        grid.replaceChildren();
        const wrap = document.createElement('div');
        wrap.className = 'year-wrap';
        const board = document.createElement('div');
        board.className = 'year-grid';
        const year = state.cursor.getFullYear();
        for (let month = 0; month < 12; month += 1) {
            board.append(yearMonth(year, month));
        }
        wrap.append(board);
        grid.append(wrap);
    }

    function yearMonth(year, month) {
        const section = document.createElement('section');
        section.className = 'year-month';
        const heading = document.createElement('button');
        heading.type = 'button';
        heading.className = 'year-month-name';
        heading.textContent = MONTHS[month];
        heading.addEventListener('click', () => {
            const day = Math.min(state.cursor.getDate(), new Date(year, month + 1, 0).getDate());
            state.cursor = new Date(year, month, day);
            state.miniMonth = new Date(year, month, 1);
            setView('month');
        });
        const days = document.createElement('div');
        days.className = 'year-days';
        DAYS_SHORT.forEach((name) => {
            const label = document.createElement('span');
            label.className = 'year-dow';
            label.textContent = name;
            days.append(label);
        });
        monthGrid(new Date(year, month, 1)).forEach((day) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'year-day';
            button.textContent = String(day.getDate());
            const inMonth = day.getMonth() === month;
            if (!inMonth) {
                button.classList.add('is-out');
            }
            if (sameDay(day, new Date())) {
                button.classList.add('is-today');
            }
            const count = state.events.filter((item) => coversDay(item, day)).length;
            if (count > 0 && inMonth) {
                button.classList.add('has-events');
                button.title = count === 1 ? '1 งาน' : `${count} งาน`;
            }
            button.addEventListener('click', () => {
                state.cursor = startOfDay(day);
                state.miniMonth = new Date(day.getFullYear(), day.getMonth(), 1);
                setView('day');
            });
            days.append(button);
        });
        section.append(heading, days);
        return section;
    }

    function renderFilters() {
        const box = document.getElementById('deptFilters');
        box.replaceChildren();
        CAL.departments.forEach((department) => {
            const label = document.createElement('label');
            label.className = 'dept-item';
            const input = document.createElement('input');
            input.type = 'checkbox';
            input.checked = state.selected.has(department.id);
            input.style.accentColor = department.color;
            input.addEventListener('change', () => {
                if (input.checked) {
                    state.selected.add(department.id);
                } else {
                    state.selected.delete(department.id);
                }
                saveSelected();
                loadEvents();
            });
            const swatch = document.createElement('span');
            swatch.className = 'dept-swatch';
            swatch.style.background = department.color;
            const name = document.createElement('span');
            name.textContent = department.name;
            label.append(input, swatch, name);
            box.append(label);
        });
        const mineOnly = document.getElementById('mineOnly');
        if (mineOnly) {
            mineOnly.checked = state.mineOnly;
        }
    }

    function renderMini() {
        const host = document.getElementById('miniCal');
        host.replaceChildren();
        const wrap = document.createElement('div');
        const head = document.createElement('div');
        head.className = 'mini-head';
        const prev = iconButton('bi-chevron-left', 'เดือนก่อน');
        const next = iconButton('bi-chevron-right', 'เดือนถัดไป');
        prev.addEventListener('click', () => changeMini(-1));
        next.addEventListener('click', () => changeMini(1));
        const label = document.createElement('strong');
        label.textContent = `${MONTHS[state.miniMonth.getMonth()]} ${state.miniMonth.getFullYear()}`;
        head.append(prev, label, next);
        const days = document.createElement('div');
        days.className = 'mini-grid';
        DAYS_SHORT.forEach((name) => {
            const cell = document.createElement('span');
            cell.className = 'mini-dow';
            cell.textContent = name;
            days.append(cell);
        });
        monthGrid(state.miniMonth).forEach((day) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'mini-day';
            button.textContent = String(day.getDate());
            if (day.getMonth() !== state.miniMonth.getMonth()) {
                button.classList.add('is-out');
            }
            if (sameDay(day, new Date())) {
                button.classList.add('is-today');
            }
            if (sameDay(day, state.cursor)) {
                button.classList.add('is-selected');
            }
            button.addEventListener('click', () => {
                state.cursor = startOfDay(day);
                state.miniMonth = new Date(day.getFullYear(), day.getMonth(), 1);
                closeSide();
                loadEvents();
            });
            days.append(button);
        });
        wrap.append(head, days);
        host.append(wrap);
    }

    function changeMini(direction) {
        state.miniMonth = new Date(state.miniMonth.getFullYear(), state.miniMonth.getMonth() + direction, 1);
        renderMini();
    }

    function renderMonth() {
        grid.className = 'cal-grid is-month';
        grid.replaceChildren();
        const wrap = document.createElement('div');
        wrap.className = 'month';
        DAYS_SHORT.forEach((name) => {
            const cell = document.createElement('div');
            cell.className = 'month-dow';
            cell.textContent = name;
            wrap.append(cell);
        });
        monthGrid(state.cursor).forEach((day) => {
            const cell = document.createElement('div');
            cell.className = 'month-cell';
            if (day.getMonth() !== state.cursor.getMonth()) {
                cell.classList.add('is-out');
            }
            if (sameDay(day, new Date())) {
                cell.classList.add('is-today');
            }
            const head = document.createElement('div');
            head.className = 'month-cell-head';
            const number = document.createElement('button');
            number.type = 'button';
            number.className = 'day-num';
            number.textContent = String(day.getDate());
            number.addEventListener('click', (event) => {
                event.stopPropagation();
                state.cursor = startOfDay(day);
                setView('day');
            });
            const add = document.createElement('button');
            add.type = 'button';
            add.className = 'cell-add';
            add.textContent = '+';
            add.title = 'สร้างงานวันนี้';
            add.addEventListener('click', (event) => {
                event.stopPropagation();
                openCreate({ date: day, minutes: 9 * 60 });
            });
            head.append(number, add);
            cell.append(head);
            const events = state.events.filter((item) => coversDay(item, day)).sort(compareEvents);
            events.slice(0, 3).forEach((item) => cell.append(monthChip(item, day)));
            if (events.length > 3) {
                const more = document.createElement('button');
                more.type = 'button';
                more.className = 'month-more';
                more.textContent = `+${events.length - 3} รายการ`;
                more.addEventListener('click', (event) => {
                    event.stopPropagation();
                    state.cursor = startOfDay(day);
                    setView('day');
                });
                cell.append(more);
            }
            cell.addEventListener('click', () => openCreate({ date: day, minutes: 9 * 60 }));
            wrap.append(cell);
        });
        grid.append(wrap);
    }

    function monthChip(event, day) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'month-chip';
        if (event.status === 'done') {
            button.classList.add('is-done');
        }
        button.style.background = event.color;
        button.style.color = textOn(event.color);
        const start = parseSQL(event.start);
        const prefix = event.all_day || !sameDay(start, day) ? '' : `${formatTime(start)} `;
        const mark = event.status === 'done' ? '✓ ' : '';
        button.textContent = `${mark}${prefix}${event.title}`;
        button.title = `${event.title}\n${event.user_name}`;
        button.addEventListener('click', (click) => {
            click.stopPropagation();
            openModal(event);
        });
        return button;
    }

    function renderTimeGrid(days) {
        const previousScroll = document.querySelector('.timegrid-scroll')?.scrollTop;
        grid.className = 'cal-grid is-time';
        grid.replaceChildren();
        const root = document.createElement('div');
        root.className = 'timegrid';
        root.style.setProperty('--days', String(days.length));

        const head = document.createElement('div');
        head.className = 'timegrid-head';
        head.append(document.createElement('div'));
        days.forEach((day) => head.append(dayHead(day)));

        const scroll = document.createElement('div');
        scroll.className = 'timegrid-scroll';
        const body = document.createElement('div');
        body.className = 'timegrid-body';
        const hours = document.createElement('div');
        hours.className = 'tg-hours';
        for (let hour = 0; hour < 24; hour += 1) {
            const label = document.createElement('div');
            label.className = 'hour-label';
            label.style.top = `${hour * HOUR_PX}px`;
            label.textContent = hour === 0 ? '' : `${String(hour).padStart(2, '0')}:00`;
            hours.append(label);
        }
        body.append(hours);
        days.forEach((day) => body.append(dayColumn(day)));
        scroll.append(body);
        root.append(head);

        const allDay = allDayRow(days);
        if (allDay) {
            root.append(allDay);
        }
        root.append(scroll);
        grid.append(root);
        scroll.scrollTop = previousScroll == null ? 8 * HOUR_PX : previousScroll;
        requestAnimationFrame(() => {
            const width = scroll.offsetWidth - scroll.clientWidth;
            head.style.paddingRight = `${width}px`;
            if (allDay) {
                allDay.style.paddingRight = `${width}px`;
            }
        });
    }

    function dayHead(day) {
        const cell = document.createElement('div');
        cell.className = 'tg-dayhead';
        const name = document.createElement('div');
        name.textContent = DAYS_SHORT[day.getDay()];
        const number = document.createElement('button');
        number.type = 'button';
        number.className = 'tg-num';
        number.textContent = String(day.getDate());
        if (sameDay(day, new Date())) {
            number.classList.add('is-today');
        }
        number.addEventListener('click', () => {
            state.cursor = startOfDay(day);
            setView('day');
        });
        cell.append(name, number);
        return cell;
    }

    function dayColumn(day) {
        const column = document.createElement('div');
        column.className = 'day-col';
        for (let hour = 0; hour < 24; hour += 1) {
            const line = document.createElement('div');
            line.className = 'hour-line';
            line.style.top = `${hour * HOUR_PX}px`;
            const half = document.createElement('div');
            half.className = 'hour-line half';
            half.style.top = `${hour * HOUR_PX + HOUR_PX / 2}px`;
            column.append(line, half);
        }
        const timed = state.events.filter((item) => !isMultiDay(item) && coversDay(item, day));
        placeTimed(timed, day).forEach((item) => column.append(timedEvent(item, day)));
        addNowLine(column, day);
        column.addEventListener('click', (event) => {
            if (event.target.closest('.cal-event')) {
                return;
            }
            const y = event.clientY - column.getBoundingClientRect().top;
            let minutes = Math.round(y / PX_PER_MIN / 30) * 30;
            minutes = Math.max(0, Math.min(minutes, 23 * 60 + 30));
            openCreate({ date: day, minutes });
        });
        return column;
    }

    function timedEvent(event, day) {
        const [startMin, endMin] = minuteSpan(event, day);
        const top = startMin * PX_PER_MIN;
        const height = Math.max((endMin - startMin) * PX_PER_MIN, 18);
        const button = eventButton(event);
        button.style.top = `${top}px`;
        button.style.height = `${height}px`;
        button.style.left = `calc(${(event.col / event.cols) * 100}% + 2px)`;
        button.style.width = `calc(${100 / event.cols}% - 4px)`;
        if (height < 36) {
            button.classList.add('is-compact');
        }
        const meta = document.createElement('span');
        meta.className = 'ev-meta';
        meta.textContent = `${formatTime(parseSQL(event.start))} · ${event.user_name}`;
        button.append(meta);
        return button;
    }

    function allDayRow(days) {
        const events = state.events.filter((item) => isMultiDay(item) && days.some((day) => coversDay(item, day)));
        if (events.length === 0) {
            return null;
        }
        const row = document.createElement('div');
        row.className = 'allday-row';
        const label = document.createElement('div');
        label.className = 'allday-label';
        label.textContent = 'ทั้งวัน';
        const bars = document.createElement('div');
        bars.className = 'allday-bars';
        bars.style.gridColumn = '2 / -1';
        const laid = layoutAllDay(events, days);
        bars.style.height = `${Math.max(28, laid.rows * 24 + 6)}px`;
        laid.segments.forEach((segment) => {
            const button = eventButton(segment.event);
            button.style.top = `${segment.row * 24 + 3}px`;
            button.style.height = '20px';
            button.style.left = `calc(${(segment.start / days.length) * 100}% + 2px)`;
            button.style.width = `calc(${((segment.end - segment.start + 1) / days.length) * 100}% - 4px)`;
            button.classList.add('is-compact');
            bars.append(button);
        });
        row.append(label, bars);
        return row;
    }

    function eventButton(event) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'cal-event';
        if (event.status === 'done') {
            button.classList.add('is-done');
        }
        button.style.background = event.color;
        button.style.color = textOn(event.color);
        const title = document.createElement('span');
        title.className = 'ev-title';
        title.textContent = `${event.status === 'done' ? '✓ ' : ''}${event.title}`;
        button.append(title);
        button.addEventListener('click', (click) => {
            click.stopPropagation();
            openModal(event);
        });
        return button;
    }

    function addNowLine(column, day) {
        if (!sameDay(day, new Date())) {
            return;
        }
        const now = new Date();
        const line = document.createElement('div');
        line.className = 'now-line';
        line.style.top = `${(now.getHours() * 60 + now.getMinutes()) * PX_PER_MIN}px`;
        line.append(document.createElement('span'));
        column.append(line);
    }

    function moveNowLine() {
        const now = new Date();
        document.querySelectorAll('.now-line').forEach((line) => {
            line.style.top = `${(now.getHours() * 60 + now.getMinutes()) * PX_PER_MIN}px`;
        });
    }

    function askLogin() {
        Swal.fire({
            icon: 'info',
            title: 'กรุณาเข้าสู่ระบบ',
            text: 'ต้องเข้าสู่ระบบก่อนเพิ่ม แก้ไข หรือลบงาน',
            confirmButtonText: 'เข้าสู่ระบบ',
            showCancelButton: true,
            cancelButtonText: 'ยกเลิก',
            confirmButtonColor: '#1a73e8'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location = CAL.endpoints.login;
            }
        });
    }

    function openCreate(preset) {
        if (!CAL.me) {
            askLogin();
            return;
        }
        if (!preset) {
            const now = new Date();
            let minutes = 9 * 60;
            if (sameDay(state.cursor, now)) {
                minutes = Math.min((now.getHours() + 1) * 60, 23 * 60);
            }
            const start = new Date(state.cursor.getFullYear(), state.cursor.getMonth(), state.cursor.getDate(), Math.floor(minutes / 60), minutes % 60, 0);
            preset = { start, end: new Date(start.getTime() + 60 * 60 * 1000), allDay: false };
        } else if (preset.date) {
            const start = new Date(preset.date.getFullYear(), preset.date.getMonth(), preset.date.getDate(), Math.floor(preset.minutes / 60), preset.minutes % 60, 0);
            preset = { start, end: new Date(start.getTime() + 60 * 60 * 1000), allDay: false };
        }
        openModal(null, preset);
    }

    function openModal(event, preset) {
        state.editing = event;
        const locked = Boolean(event && !event.can_edit);
        form.dataset.locked = locked ? '1' : '0';
        document.getElementById('eventModalTitle').textContent = event ? (locked ? 'รายละเอียดงาน' : 'แก้ไขงาน') : 'สร้างงาน';
        document.getElementById('btnSaveEvent').hidden = locked;
        document.getElementById('btnDeleteEvent').hidden = !(event && event.can_edit);
        document.getElementById('btnLoginToEdit').hidden = Boolean(CAL.me);
        setLocked(false);
        if (event) {
            document.getElementById('eventId').value = String(event.id);
            document.getElementById('eventTitle').value = event.title;
            document.getElementById('eventDescription').value = event.description || '';
            document.getElementById('eventLocation').value = event.location || '';
            document.getElementById('eventDepartment').value = String(event.department_id);
            document.getElementById('eventStatus').value = event.status;
            const owner = document.getElementById('eventUser');
            if (owner) {
                owner.value = String(event.user_id);
            }
            document.getElementById('eventAllDay').checked = Boolean(event.all_day);
            const start = parseSQL(event.start);
            const end = event.all_day ? inclusiveEnd(event) : parseSQL(event.end);
            document.getElementById('eventStartDate').value = dateInput(start);
            document.getElementById('eventEndDate').value = dateInput(end);
            document.getElementById('eventStartTime').value = timeInput(start);
            document.getElementById('eventEndTime').value = timeInput(parseSQL(event.end));
            document.getElementById('eventOwnerNote').textContent = `${event.user_name} · ${event.department_name} · อัปเดต ${event.updated_at}`;
        } else {
            form.reset();
            document.getElementById('eventId').value = '';
            document.getElementById('eventAllDay').checked = Boolean(preset.allDay);
            document.getElementById('eventStartDate').value = dateInput(preset.start);
            document.getElementById('eventEndDate').value = dateInput(preset.end);
            document.getElementById('eventStartTime').value = timeInput(preset.start);
            document.getElementById('eventEndTime').value = timeInput(preset.end);
            document.getElementById('eventDepartment').value = String(CAL.me.department_id);
            const owner = document.getElementById('eventUser');
            if (owner) {
                owner.value = String(CAL.me.id);
            }
            document.getElementById('eventStatus').value = 'planned';
            document.getElementById('eventOwnerNote').textContent = '';
        }
        setLocked(locked);
        syncAllDay();
        modal.show();
        if (!locked) {
            setTimeout(() => document.getElementById('eventTitle').focus(), 200);
        }
    }

    async function saveEvent(event) {
        event.preventDefault();
        if (form.dataset.locked === '1') {
            return;
        }
        const payload = buildPayload();
        if (!payload) {
            return;
        }
        const button = document.getElementById('btnSaveEvent');
        button.disabled = true;
        button.textContent = 'กำลังบันทึก';
        try {
            const response = await fetch(CAL.endpoints.save, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-Token': csrf()
                },
                body: JSON.stringify(payload)
            });
            if (response.status === 401) {
                modal.hide();
                askLogin();
                return;
            }
            const data = await response.json();
            if (!response.ok || !data.ok) {
                throw new Error(data.message || 'บันทึกไม่สำเร็จ');
            }
            modal.hide();
            Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: data.message, showConfirmButton: false, timer: 1600, timerProgressBar: true });
            loadEvents();
        } catch (error) {
            Swal.fire({ icon: 'error', title: 'บันทึกไม่สำเร็จ', text: error.message, confirmButtonColor: '#1a73e8' });
        } finally {
            button.disabled = false;
            button.textContent = 'บันทึกงาน';
        }
    }

    function buildPayload() {
        const allDay = document.getElementById('eventAllDay').checked;
        const title = document.getElementById('eventTitle').value.trim();
        const startDate = document.getElementById('eventStartDate').value;
        const endDate = document.getElementById('eventEndDate').value;
        const startTime = document.getElementById('eventStartTime').value;
        const endTime = document.getElementById('eventEndTime').value;
        if (title === '' || startDate === '' || endDate === '' || (!allDay && (startTime === '' || endTime === ''))) {
            Swal.fire({ icon: 'warning', title: 'กรอกชื่องานและวันเวลาให้ครบ', confirmButtonColor: '#1a73e8' });
            return null;
        }
        let start = `${startDate} ${clock(startTime)}`;
        let end = `${endDate} ${clock(endTime)}`;
        if (allDay) {
            start = `${startDate} 00:00:00`;
            const [year, month, day] = endDate.split('-').map(Number);
            end = formatSQL(new Date(year, month - 1, day + 1));
        }
        const payload = {
            id: document.getElementById('eventId').value || null,
            title,
            description: document.getElementById('eventDescription').value.trim(),
            location: document.getElementById('eventLocation').value.trim(),
            department_id: document.getElementById('eventDepartment').value,
            status: document.getElementById('eventStatus').value,
            all_day: allDay,
            start,
            end
        };
        const owner = document.getElementById('eventUser');
        if (owner) {
            payload.user_id = owner.value;
        }
        return payload;
    }

    async function deleteEvent() {
        if (!state.editing) {
            return;
        }
        const result = await Swal.fire({
            title: 'ลบงานนี้?',
            text: state.editing.title,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'ลบงาน',
            cancelButtonText: 'ยกเลิก',
            confirmButtonColor: '#d93025',
            reverseButtons: true,
            focusCancel: true
        });
        if (!result.isConfirmed) {
            return;
        }
        const response = await fetch(CAL.endpoints.delete, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-Token': csrf()
            },
            body: JSON.stringify({ id: state.editing.id })
        });
        if (response.status === 401) {
            modal.hide();
            askLogin();
            return;
        }
        const data = await response.json();
        if (!response.ok || !data.ok) {
            Swal.fire({ icon: 'error', title: 'ลบไม่สำเร็จ', text: data.message || '', confirmButtonColor: '#1a73e8' });
            return;
        }
        modal.hide();
        Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: data.message, showConfirmButton: false, timer: 1600 });
        loadEvents();
    }

    async function runSearch(query) {
        searchAbort?.abort();
        searchAbort = new AbortController();
        const start = formatSQL(addDays(startOfDay(new Date()), -30));
        const end = formatSQL(addDays(startOfDay(new Date()), 181));
        const params = new URLSearchParams({
            start,
            end,
            q: query,
            departments: [...state.selected].join(','),
            mine: state.mineOnly ? '1' : '0'
        });
        try {
            const response = await fetch(`${CAL.endpoints.events}&${params}`, {
                headers: { Accept: 'application/json' },
                signal: searchAbort.signal
            });
            const data = await response.json();
            showSearch(data.events || []);
        } catch (error) {
            if (error.name !== 'AbortError') {
                hideSearch();
            }
        }
    }

    function showSearch(events) {
        const box = document.getElementById('searchResults');
        box.replaceChildren();
        box.hidden = false;
        if (events.length === 0) {
            const empty = document.createElement('div');
            empty.className = 'p-2 text-muted';
            empty.textContent = 'ไม่พบงาน';
            box.append(empty);
            return;
        }
        events.forEach((event) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'search-item';
            const swatch = document.createElement('span');
            swatch.className = 'dept-swatch';
            swatch.style.background = event.color;
            const text = document.createElement('span');
            const start = parseSQL(event.start);
            text.textContent = `${start.getDate()} ${MONTHS_SHORT[start.getMonth()]} ${event.title} · ${event.user_name}`;
            button.append(swatch, text);
            button.addEventListener('click', () => {
                hideSearch();
                document.getElementById('searchInput').value = '';
                state.cursor = startOfDay(start);
                state.miniMonth = new Date(start.getFullYear(), start.getMonth(), 1);
                state.view = 'day';
                localStorage.setItem('cc.view', 'day');
                loadEvents();
                openModal(event);
            });
            box.append(button);
        });
    }

    function hideSearch() {
        const box = document.getElementById('searchResults');
        box.hidden = true;
        box.replaceChildren();
    }

    function visibleRange() {
        if (state.view === 'year') {
            const year = state.cursor.getFullYear();
            const first = monthGrid(new Date(year, 0, 1))[0];
            const last = monthGrid(new Date(year, 11, 1))[41];
            return { start: formatSQL(first), end: formatSQL(addDays(last, 1)) };
        }
        if (state.view === 'month') {
            const days = monthGrid(state.cursor);
            return { start: formatSQL(days[0]), end: formatSQL(addDays(days[41], 1)) };
        }
        if (state.view === 'week') {
            const days = weekDays(state.cursor);
            return { start: formatSQL(days[0]), end: formatSQL(addDays(days[6], 1)) };
        }
        return { start: formatSQL(startOfDay(state.cursor)), end: formatSQL(addDays(state.cursor, 1)) };
    }

    function setLocked(locked) {
        form.querySelectorAll('input, select, textarea').forEach((field) => {
            field.disabled = locked;
        });
    }

    function syncAllDay() {
        const allDay = document.getElementById('eventAllDay').checked;
        const locked = form.dataset.locked === '1';
        ['eventStartTime', 'eventEndTime'].forEach((id) => {
            const field = document.getElementById(id);
            field.disabled = locked || allDay;
            field.required = !allDay;
        });
    }

    function setLoading(active) {
        document.getElementById('calProgress').hidden = !active;
    }

    function closeSide() {
        const side = document.getElementById('calSide');
        const instance = bootstrap.Offcanvas.getInstance(side);
        if (instance && window.matchMedia('(max-width: 991.98px)').matches) {
            instance.hide();
        }
    }

    function iconButton(icon, label) {
        const button = document.createElement('button');
        button.type = 'button';
        button.setAttribute('aria-label', label);
        const mark = document.createElement('i');
        mark.className = `bi ${icon}`;
        button.append(mark);
        return button;
    }

    function placeTimed(events, day) {
        const items = events.map((event) => {
            const [startMin, endMin] = minuteSpan(event, day);
            return { ...event, startMin, endMin };
        }).sort((a, b) => a.startMin - b.startMin || b.endMin - a.endMin);
        const groups = [];
        let group = [];
        let groupEnd = -1;
        items.forEach((item) => {
            if (group.length && item.startMin >= groupEnd) {
                groups.push(group);
                group = [];
                groupEnd = -1;
            }
            group.push(item);
            groupEnd = Math.max(groupEnd, item.endMin);
        });
        if (group.length) {
            groups.push(group);
        }
        groups.forEach((cluster) => {
            const columns = [];
            cluster.forEach((item) => {
                let placed = false;
                for (let index = 0; index < columns.length; index += 1) {
                    if (columns[index] <= item.startMin) {
                        columns[index] = item.endMin;
                        item.col = index;
                        placed = true;
                        break;
                    }
                }
                if (!placed) {
                    item.col = columns.length;
                    columns.push(item.endMin);
                }
            });
            cluster.forEach((item) => {
                item.cols = columns.length;
            });
        });
        return items;
    }

    function layoutAllDay(events, days) {
        const segments = events.map((event) => {
            let start = days.length;
            let end = -1;
            days.forEach((day, index) => {
                if (coversDay(event, day)) {
                    start = Math.min(start, index);
                    end = Math.max(end, index);
                }
            });
            return { event, start, end, row: 0 };
        }).sort((a, b) => a.start - b.start || b.end - a.end);
        const rows = [];
        segments.forEach((segment) => {
            let row = rows.findIndex((value) => value <= segment.start);
            if (row === -1) {
                row = rows.length;
                rows.push(0);
            }
            rows[row] = segment.end + 1;
            segment.row = row;
        });
        return { segments, rows: Math.max(rows.length, 1) };
    }

    function minuteSpan(event, day) {
        const start = parseSQL(event.start);
        const end = parseSQL(event.end);
        const startMin = sameDay(start, day) ? start.getHours() * 60 + start.getMinutes() : 0;
        const endMin = sameDay(end, day) ? end.getHours() * 60 + end.getMinutes() : 24 * 60;
        return [startMin, Math.max(endMin, startMin + 15)];
    }

    function coversDay(event, day) {
        const start = startOfDay(day);
        const end = addDays(start, 1);
        return parseSQL(event.start) < end && parseSQL(event.end) > start;
    }

    function isMultiDay(event) {
        if (event.all_day) {
            return true;
        }
        const end = parseSQL(event.end);
        return !sameDay(parseSQL(event.start), new Date(end.getTime() - 1000));
    }

    function inclusiveEnd(event) {
        const end = parseSQL(event.end);
        if (end.getHours() === 0 && end.getMinutes() === 0 && end.getSeconds() === 0) {
            return addDays(end, -1);
        }
        return end;
    }

    function compareEvents(a, b) {
        if (a.all_day !== b.all_day) {
            return a.all_day ? -1 : 1;
        }
        return parseSQL(a.start) - parseSQL(b.start);
    }

    function textOn(hex) {
        const raw = String(hex || '#1a73e8').replace('#', '');
        if (raw.length !== 6) {
            return '#fff';
        }
        const red = parseInt(raw.slice(0, 2), 16);
        const green = parseInt(raw.slice(2, 4), 16);
        const blue = parseInt(raw.slice(4, 6), 16);
        return (red * 299 + green * 587 + blue * 114) / 1000 >= 160 ? '#202124' : '#fff';
    }

    function startOfDay(date) {
        return new Date(date.getFullYear(), date.getMonth(), date.getDate());
    }

    function addDays(date, amount) {
        return new Date(date.getFullYear(), date.getMonth(), date.getDate() + amount);
    }

    function sameDay(a, b) {
        return a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate();
    }

    function weekDays(anchor) {
        const start = addDays(anchor, -anchor.getDay());
        return Array.from({ length: 7 }, (_, index) => addDays(start, index));
    }

    function monthGrid(anchor) {
        const first = new Date(anchor.getFullYear(), anchor.getMonth(), 1);
        const start = addDays(first, -first.getDay());
        return Array.from({ length: 42 }, (_, index) => addDays(start, index));
    }

    function parseSQL(value) {
        const [date, time] = String(value).split(' ');
        const [year, month, day] = date.split('-').map(Number);
        const [hour, minute, second] = (time || '00:00:00').split(':').map(Number);
        return new Date(year, month - 1, day, hour || 0, minute || 0, second || 0);
    }

    function formatSQL(date) {
        const pad = (value) => String(value).padStart(2, '0');
        return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())} ${pad(date.getHours())}:${pad(date.getMinutes())}:${pad(date.getSeconds())}`;
    }

    function dateInput(date) {
        const pad = (value) => String(value).padStart(2, '0');
        return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
    }

    function timeInput(date) {
        const pad = (value) => String(value).padStart(2, '0');
        return `${pad(date.getHours())}:${pad(date.getMinutes())}`;
    }

    function formatTime(date) {
        return timeInput(date);
    }

    function clock(value) {
        const parts = String(value || '00:00').split(':');
        const pad = (part) => String(part || '0').padStart(2, '0');
        return `${pad(parts[0])}:${pad(parts[1])}:${pad(parts[2] || '0')}`;
    }
}());
