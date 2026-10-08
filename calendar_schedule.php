<?php
require_once __DIR__ . '/include/auth.php';
require_once __DIR__ . '/config/db.php';

if (!isAdmin()) {
    header('Location: ./index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Calendar Schedule - Administration</title>
  <link rel="stylesheet" href="css/style.css">
  <style>
    .schedule-form {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
      gap: 0.75rem 1rem;
      align-items: end;
      max-width: none;
      margin: 0 0 1rem;
      padding: 0;
      background: none;
      box-shadow: none;
    }
    .schedule-form label { display: block; font-weight: 600; margin-bottom: 0.25rem; }
    .schedule-form input, .schedule-form select { width: 100%; box-sizing: border-box; }
    .schedule-form .form-actions { display: flex; gap: 0.5rem; flex-wrap: wrap; }
    .schedule-form .checkbox-field label { display: flex; align-items: center; gap: 0.4rem; }
    .schedule-form .checkbox-field input { width: auto; }
    .schedule-actions { display: flex; gap: 0.4rem; flex-wrap: wrap; }
    .schedule-status { margin-bottom: 1rem; display: none; }
    .schedule-status.success { display: block; color: #166534; }
    .schedule-status.error { display: block; color: #991b1b; }
    .muted { color: #6b7280; font-size: 0.9em; }
    .filter-row { display: flex; gap: 0.5rem; align-items: center; margin-bottom: 0.75rem; }
    .filter-row select { width: auto; }
    tr.inactive td { color: #9ca3af; }
    tr.past td { color: #9ca3af; }
    .schedule-form [hidden] { display: none; }
  </style>
</head>
<body>
<?php include __DIR__ . '/include/header.php'; ?>
<main class="dashboard-container">
  <h2>Calendar Schedule</h2>
  <p class="muted">Program calendar days, recurring services, one-time exceptions, and reminders shown on the Calendar page and the Three Month Calendar report.
    A monthly service or timed reminder that starts at the same time as a weekly service is shown in parentheses with it, e.g. "Sunday Worship (Communion)".</p>

  <div id="statusMessage" class="card schedule-status" role="status"></div>

  <div class="card" id="programCalendar" style="margin-bottom:1.5rem;">
    <h3>Program / Event Calendar Days</h3>
    <p class="muted">Create activities and set their date range in <a href="events.php">Event Management</a>, then choose their calendar days here.
      Continuous activities keep showing every day as before. Selected weekdays repeat from the start date through the end date, inclusive,
      using the start and end times on each occurrence. Equal times show a point-in-time entry; an earlier end time runs overnight.
      With no end date, only the start date is eligible. These settings do not change registration or event details.</p>
    <form id="programScheduleForm" class="schedule-form">
      <div style="grid-column: span 2;">
        <label for="program_schedule">Program activity</label>
        <select id="program_schedule" name="schedule_id" required></select>
      </div>
      <div>
        <label for="program_mode">Calendar display</label>
        <select id="program_mode" name="calendar_mode">
          <option value="continuous">Continuous date range (existing behavior)</option>
          <option value="weekdays">Selected weekdays only</option>
        </select>
      </div>
      <fieldset id="program_weekdays" class="checkbox-field" style="grid-column: 1 / -1;" hidden>
        <legend>Show on</legend>
      </fieldset>
      <div class="form-actions"><button type="submit" class="btn btn-primary">Save Calendar Days</button></div>
    </form>
    <table class="data-table">
      <thead><tr><th>Program / Activity</th><th>Date range</th><th>Calendar days</th><th>Exceptions</th><th>Actions</th></tr></thead>
      <tbody id="programSchedulesBody"><tr><td colspan="5">Loading…</td></tr></tbody>
    </table>

    <h3 id="programExceptionTitle">Add Program Exception</h3>
    <form id="programExceptionForm" class="schedule-form">
      <input type="hidden" name="id" id="program_exception_id">
      <div style="grid-column: span 2;">
        <label for="program_exception_schedule">Program activity</label>
        <select id="program_exception_schedule" name="schedule_id" required></select>
      </div>
      <div>
        <label for="program_exception_date">Occurrence date</label>
        <input id="program_exception_date" name="occurrence_date" type="date" required>
      </div>
      <div>
        <label for="program_exception_type">Exception type</label>
        <select id="program_exception_type" name="exception_type">
          <option value="cancel">Cancelled</option><option value="reschedule">Rescheduled</option>
        </select>
      </div>
      <div class="program-replacement-field" hidden>
        <label for="program_replacement_start">Replacement start</label>
        <input id="program_replacement_start" name="replacement_start" type="datetime-local">
      </div>
      <div class="program-replacement-field" hidden>
        <label for="program_replacement_end">Replacement end <span class="muted">(optional)</span></label>
        <input id="program_replacement_end" name="replacement_end" type="datetime-local">
      </div>
      <div class="form-actions">
        <button type="submit" class="btn btn-primary" id="programExceptionSubmit">Add Exception</button>
        <button type="button" class="btn btn-secondary" id="programExceptionCancel" hidden>Cancel</button>
      </div>
    </form>
    <p class="muted">Set selected weekdays first. The occurrence date must be one of those weekdays within the activity's date range.
      A replacement can be on a different weekday or outside that range. Delete an exception to restore the regular occurrence.</p>
    <table class="data-table">
      <thead><tr><th>Program / Activity</th><th>Occurrence</th><th>Type</th><th>Replacement</th><th>Actions</th></tr></thead>
      <tbody id="programExceptionsBody"><tr><td colspan="5">Loading…</td></tr></tbody>
    </table>
  </div>

  <div class="card" style="margin-bottom:1.5rem;">
    <h3 id="serviceFormTitle">Add Service</h3>
    <form id="serviceForm" class="schedule-form" autocomplete="off">
      <input type="hidden" name="id" id="service_id" value="">
      <div style="grid-column: span 2;">
        <label for="service_name">Service name</label>
        <input id="service_name" name="name" maxlength="255" required>
      </div>
      <div>
        <label for="service_recurrence">Repeats</label>
        <select id="service_recurrence" name="recurrence">
          <option value="weekly">Every week</option>
          <option value="monthly_weekday">Monthly on a weekday (e.g. 1st Sunday)</option>
          <option value="monthly_date">Monthly on a date (e.g. the 15th)</option>
        </select>
      </div>
      <div id="week_of_month_field" hidden>
        <label for="service_week_of_month">Week of month</label>
        <select id="service_week_of_month" name="week_of_month">
          <option value="1">1st</option>
          <option value="2">2nd</option>
          <option value="3">3rd</option>
          <option value="4">4th</option>
          <option value="5">5th</option>
          <option value="-1">Last</option>
        </select>
      </div>
      <div id="weekday_field">
        <label for="service_weekday">Weekday</label>
        <select id="service_weekday" name="weekday"></select>
      </div>
      <div id="day_of_month_field" hidden>
        <label for="service_day_of_month">Day of month</label>
        <input id="service_day_of_month" name="day_of_month" type="number" min="1" max="31">
      </div>
      <div>
        <label for="service_start_time">Start time</label>
        <input id="service_start_time" name="start_time" type="time" required>
      </div>
      <div>
        <label for="service_end_time">End time <span class="muted">(optional)</span></label>
        <input id="service_end_time" name="end_time" type="time">
      </div>
      <div>
        <label for="service_effective_start">Effective start</label>
        <input id="service_effective_start" name="effective_start" type="date" required>
      </div>
      <div>
        <label for="service_effective_end">Effective end <span class="muted">(optional)</span></label>
        <input id="service_effective_end" name="effective_end" type="date">
      </div>
      <div class="checkbox-field">
        <label><input id="service_active" name="active" type="checkbox" value="1" checked> Active</label>
      </div>
      <div class="checkbox-field">
        <label><input id="service_is_public" name="is_public" type="checkbox" value="1" checked> Show on public calendar</label>
      </div>
      <div class="form-actions">
        <button type="submit" class="btn btn-primary" id="serviceSubmit">Add Service</button>
        <button type="button" class="btn btn-secondary" id="serviceCancel" hidden>Cancel</button>
      </div>
    </form>
    <p class="muted">Leave end time blank for a point-in-time entry. An end time earlier than the start time is treated as overnight.
      A day of month past the end of a shorter month falls on that month's last day; a "5th" weekday only appears in months that have one.</p>

    <table class="data-table">
      <thead>
        <tr><th>Name</th><th>Schedule</th><th>Time</th><th>Effective</th><th>Active</th><th>Public</th><th>Exceptions</th><th>Actions</th></tr>
      </thead>
      <tbody id="servicesBody"><tr><td colspan="8">Loading…</td></tr></tbody>
    </table>
  </div>

  <div class="card">
    <h3 id="exceptionFormTitle">Add Exception</h3>
    <form id="exceptionForm" class="schedule-form" autocomplete="off">
      <input type="hidden" name="id" id="exception_id" value="">
      <div style="grid-column: span 2;">
        <label for="exception_service">Service</label>
        <select id="exception_service" name="service_id" required></select>
      </div>
      <div>
        <label for="exception_occurrence">Occurrence date</label>
        <input id="exception_occurrence" name="occurrence_date" type="date" required>
      </div>
      <div>
        <label for="exception_type">Exception type</label>
        <select id="exception_type" name="exception_type">
          <option value="cancel">Cancelled</option>
          <option value="reschedule">Rescheduled</option>
        </select>
      </div>
      <div class="replacement-field">
        <label for="exception_replacement_start">Replacement start</label>
        <input id="exception_replacement_start" name="replacement_start" type="datetime-local">
      </div>
      <div class="replacement-field">
        <label for="exception_replacement_end">Replacement end <span class="muted">(optional)</span></label>
        <input id="exception_replacement_end" name="replacement_end" type="datetime-local">
      </div>
      <div class="form-actions">
        <button type="submit" class="btn btn-primary" id="exceptionSubmit">Add Exception</button>
        <button type="button" class="btn btn-secondary" id="exceptionCancel" hidden>Cancel</button>
      </div>
    </form>
    <p class="muted">The occurrence date is the regularly scheduled date being changed and must be one of the service's scheduled dates within its effective dates.</p>

    <div class="filter-row">
      <label for="exceptionFilter">Show exceptions for</label>
      <select id="exceptionFilter"><option value="">All services</option></select>
    </div>
    <table class="data-table">
      <thead>
        <tr><th>Service</th><th>Occurrence</th><th>Type</th><th>Replacement</th><th>Actions</th></tr>
      </thead>
      <tbody id="exceptionsBody"><tr><td colspan="5">Loading…</td></tr></tbody>
    </table>
  </div>

  <div class="card" style="margin-top:1.5rem;">
    <h3 id="reminderFormTitle">Add Reminder / Important Date</h3>
    <form id="reminderForm" class="schedule-form" autocomplete="off">
      <input type="hidden" name="id" id="reminder_id" value="">
      <div style="grid-column: span 2;">
        <label for="reminder_title">Title</label>
        <input id="reminder_title" name="title" maxlength="255" required>
      </div>
      <div>
        <label for="reminder_start_date">Date</label>
        <input id="reminder_start_date" name="start_date" type="date" required>
      </div>
      <div>
        <label for="reminder_end_date">Through <span class="muted">(optional)</span></label>
        <input id="reminder_end_date" name="end_date" type="date">
      </div>
      <div>
        <label for="reminder_start_time">Start time <span class="muted">(blank = all day)</span></label>
        <input id="reminder_start_time" name="start_time" type="time">
      </div>
      <div>
        <label for="reminder_end_time">End time <span class="muted">(optional)</span></label>
        <input id="reminder_end_time" name="end_time" type="time">
      </div>
      <div class="checkbox-field">
        <label><input id="reminder_is_public" name="is_public" type="checkbox" value="1"> Show on public calendar</label>
      </div>
      <div class="form-actions">
        <button type="submit" class="btn btn-primary" id="reminderSubmit">Add Reminder</button>
        <button type="button" class="btn btn-secondary" id="reminderCancel" hidden>Cancel</button>
      </div>
    </form>
    <p class="muted">Use reminders for one-time or multi-day dates outside the regular schedule. A reminder with a time appears at that time on each day of its range,
      and is shown in parentheses with a weekly service that starts at the same time.</p>

    <div class="filter-row">
      <label for="reminderScope">Show</label>
      <select id="reminderScope">
        <option value="upcoming">Upcoming</option>
        <option value="all">All</option>
      </select>
    </div>
    <table class="data-table">
      <thead>
        <tr><th>Title</th><th>Dates</th><th>Time</th><th>Public</th><th>Actions</th></tr>
      </thead>
      <tbody id="remindersBody"><tr><td colspan="5">Loading…</td></tr></tbody>
    </table>
  </div>
</main>

<script>
(() => {
  const API = 'calendar_schedule_api.php';
  const WEEKDAYS = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
  let services = [];
  let exceptions = [];
  let reminders = [];
  let programSchedules = [];
  let programExceptions = [];
  const ORDINALS = { '1': '1st', '2': '2nd', '3': '3rd', '4': '4th', '5': '5th', '-1': 'Last' };

  const $ = (id) => document.getElementById(id);
  const esc = (v) => String(v ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

  function showStatus(message, ok = true) {
    const el = $('statusMessage');
    el.textContent = message;
    el.className = 'card schedule-status ' + (ok ? 'success' : 'error');
    el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }

  async function api(action, data = null, params = {}) {
    const url = new URL(API, window.location.href);
    url.searchParams.set('action', action);
    Object.entries(params).forEach(([k, v]) => url.searchParams.set(k, v));
    const options = { credentials: 'same-origin' };
    if (data) {
      options.method = 'POST';
      options.body = data;
      data.set('action', action);
    }
    const res = await fetch(url, options);
    let json;
    try { json = await res.json(); } catch { throw new Error('Unexpected server response.'); }
    if (!res.ok || json.status !== 'success') throw new Error(json.message || 'Request failed.');
    return json;
  }

  const fmtTime = (t) => {
    if (!t) return '';
    const [h, m] = t.split(':').map(Number);
    return `${((h + 11) % 12) + 1}:${String(m).padStart(2, '0')} ${h < 12 ? 'AM' : 'PM'}`;
  };
  const fmtDateTime = (dt) => dt ? `${dt.slice(0, 10)} ${fmtTime(dt.slice(11, 16))}` : '';
  const toLocalInput = (dt) => dt ? dt.slice(0, 16).replace(' ', 'T') : '';

  const programTitle = (s) => s.prg_evnt_name + (s.activity_scheduled ? ' - ' + s.activity_scheduled : '');
  const programDays = (s) => s.calendar_weekdays === null ? 'Continuous'
    : WEEKDAYS.filter((_, i) => Number(s.calendar_weekdays) & (1 << i)).join(', ');

  function toggleProgramDays() {
    const weekly = $('program_mode').value === 'weekdays';
    $('program_weekdays').hidden = !weekly;
    $('program_weekdays').disabled = !weekly;
  }

  function editProgramSchedule(id) {
    const s = programSchedules.find((x) => String(x.schedule_id) === String(id));
    $('program_mode').value = s && s.calendar_weekdays !== null ? 'weekdays' : 'continuous';
    document.querySelectorAll('#program_weekdays input').forEach((input) => {
      input.checked = Boolean(s && (Number(s.calendar_weekdays) & (1 << Number(input.value))));
    });
    toggleProgramDays();
  }

  async function loadProgramSchedules() {
    programSchedules = (await api('list_program_schedules')).data;
    $('programSchedulesBody').innerHTML = programSchedules.length ? programSchedules.map((s) => `<tr>
      <td>${esc(programTitle(s))}</td>
      <td>${esc(fmtDateTime(s.start_datetime))}${s.end_datetime ? ' → ' + esc(fmtDateTime(s.end_datetime)) : ''}</td>
      <td>${esc(programDays(s))}</td><td>${Number(s.exception_count)}</td>
      <td><button type="button" class="btn btn-sm btn-secondary" data-edit-program="${s.schedule_id}">Edit Days</button></td>
      </tr>`).join('') : '<tr><td colspan="5">No program activities found. Add one in Event Management.</td></tr>';
    const options = (s) => `<option value="${s.schedule_id}">${esc(programTitle(s))} (${esc(s.start_datetime.slice(0, 10))} → ${esc((s.end_datetime || s.start_datetime).slice(0, 10))}; ${esc(programDays(s))})</option>`;
    for (const id of ['program_schedule', 'program_exception_schedule']) {
      const previous = $(id).value;
      const eligible = id === 'program_schedule' ? programSchedules : programSchedules.filter((s) => s.calendar_weekdays !== null);
      $(id).innerHTML = '<option value="">Select a program activity…</option>' + eligible.map(options).join('');
      $(id).value = eligible.some((s) => String(s.schedule_id) === previous) ? previous : '';
    }
    editProgramSchedule($('program_schedule').value);
  }

  async function loadProgramExceptions() {
    programExceptions = (await api('list_program_exceptions')).data;
    $('programExceptionsBody').innerHTML = programExceptions.length ? programExceptions.map((e) => `<tr>
      <td>${esc(programTitle(e))}</td><td>${esc(e.occurrence_date)}</td>
      <td>${Number(e.is_cancelled) ? 'Cancelled' : 'Rescheduled'}</td>
      <td>${Number(e.is_cancelled) ? '—' : esc(fmtDateTime(e.replacement_start)) + (e.replacement_end ? ' – ' + esc(fmtDateTime(e.replacement_end)) : '')}</td>
      <td class="schedule-actions">
        <button type="button" class="btn btn-sm btn-secondary" data-edit-program-exception="${e.id}">Edit</button>
        <button type="button" class="btn btn-sm btn-danger" data-delete-program-exception="${e.id}">Delete</button>
      </td></tr>`).join('') : '<tr><td colspan="5">No program exceptions found.</td></tr>';
  }

  function toggleProgramReplacement() {
    const reschedule = $('program_exception_type').value === 'reschedule';
    document.querySelectorAll('.program-replacement-field').forEach((el) => { el.hidden = !reschedule; });
    $('program_replacement_start').required = reschedule;
  }

  function resetProgramException() {
    $('programExceptionForm').reset();
    $('program_exception_id').value = '';
    $('programExceptionTitle').textContent = 'Add Program Exception';
    $('programExceptionSubmit').textContent = 'Add Exception';
    $('programExceptionCancel').hidden = true;
    toggleProgramReplacement();
  }

  $('program_weekdays').insertAdjacentHTML('beforeend', WEEKDAYS.map((day, i) =>
    `<label><input type="checkbox" name="weekdays[]" value="${i}"> ${day}</label>`).join(''));
  $('program_mode').addEventListener('change', toggleProgramDays);
  $('program_schedule').addEventListener('change', () => editProgramSchedule($('program_schedule').value));
  $('programSchedulesBody').addEventListener('click', (ev) => {
    const id = ev.target.dataset.editProgram;
    if (!id) return;
    $('program_schedule').value = id;
    editProgramSchedule(id);
    $('programScheduleForm').scrollIntoView({ behavior: 'smooth', block: 'center' });
  });
  $('programScheduleForm').addEventListener('submit', async (ev) => {
    ev.preventDefault();
    try {
      const res = await api('save_program_schedule', new FormData(ev.target));
      showStatus(res.message);
      await loadProgramSchedules();
    } catch (err) { showStatus(err.message, false); }
  });
  $('program_exception_type').addEventListener('change', toggleProgramReplacement);
  $('programExceptionCancel').addEventListener('click', resetProgramException);
  $('programExceptionForm').addEventListener('submit', async (ev) => {
    ev.preventDefault();
    try {
      const res = await api('save_program_exception', new FormData(ev.target));
      showStatus(res.message);
      resetProgramException();
      await Promise.all([loadProgramSchedules(), loadProgramExceptions()]);
    } catch (err) { showStatus(err.message, false); }
  });
  $('programExceptionsBody').addEventListener('click', async (ev) => {
    const editId = ev.target.dataset.editProgramException;
    const deleteId = ev.target.dataset.deleteProgramException;
    if (editId) {
      const e = programExceptions.find((x) => String(x.id) === editId);
      $('program_exception_id').value = e.id;
      $('program_exception_schedule').value = e.schedule_id;
      $('program_exception_date').value = e.occurrence_date;
      $('program_exception_type').value = Number(e.is_cancelled) ? 'cancel' : 'reschedule';
      $('program_replacement_start').value = toLocalInput(e.replacement_start);
      $('program_replacement_end').value = toLocalInput(e.replacement_end);
      $('programExceptionTitle').textContent = 'Edit Program Exception';
      $('programExceptionSubmit').textContent = 'Save Changes';
      $('programExceptionCancel').hidden = false;
      toggleProgramReplacement();
      $('programExceptionForm').scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
    if (deleteId && confirm('Delete this program exception? The regular occurrence will appear again.')) {
      const data = new FormData();
      data.set('id', deleteId);
      try {
        const res = await api('delete_program_exception', data);
        showStatus(res.message);
        if ($('program_exception_id').value === deleteId) resetProgramException();
        await Promise.all([loadProgramSchedules(), loadProgramExceptions()]);
      } catch (err) { showStatus(err.message, false); }
    }
  });

  // ---------- Weekly services ----------
  function renderServices() {
    const body = $('servicesBody');
    if (!services.length) {
      body.innerHTML = '<tr><td colspan="8">No services have been added.</td></tr>';
    } else {
      body.innerHTML = services.map((s) => {
        const time = fmtTime(s.start_time) + (s.end_time ? ' – ' + fmtTime(s.end_time) : '');
        const eff = esc(s.effective_start) + ' → ' + (s.effective_end ? esc(s.effective_end) : 'ongoing');
        return `<tr class="${Number(s.active) ? '' : 'inactive'}">
          <td>${esc(s.name)}</td>
          <td>${esc(s.schedule)}</td>
          <td>${esc(time)}</td>
          <td>${eff}</td>
          <td>${Number(s.active) ? 'Yes' : 'No'}</td>
          <td>${Number(s.is_public) ? 'Yes' : 'No'}</td>
          <td>${Number(s.exception_count)}</td>
          <td class="schedule-actions">
            <button type="button" class="btn btn-sm btn-secondary" data-edit-service="${s.id}">Edit</button>
            <button type="button" class="btn btn-sm btn-danger" data-delete-service="${s.id}">Delete</button>
          </td></tr>`;
      }).join('');
    }

    const options = services.map((s) =>
      `<option value="${s.id}">${esc(s.name)} (${esc(s.schedule)} ${esc(fmtTime(s.start_time))})${Number(s.active) ? '' : ' – inactive'}</option>`).join('');
    const svcSelect = $('exception_service');
    const prevSvc = svcSelect.value;
    svcSelect.innerHTML = '<option value="">Select a service…</option>' + options;
    svcSelect.value = services.some((s) => String(s.id) === prevSvc) ? prevSvc : '';
    const filter = $('exceptionFilter');
    const prevFilter = filter.value;
    filter.innerHTML = '<option value="">All services</option>' + options;
    filter.value = services.some((s) => String(s.id) === prevFilter) ? prevFilter : '';
  }

  async function loadServices() {
    services = (await api('list_services')).data;
    renderServices();
  }

  function resetServiceForm() {
    $('serviceForm').reset();
    $('service_id').value = '';
    $('service_active').checked = true;
    $('service_is_public').checked = true;
    toggleRecurrenceFields();
    $('serviceFormTitle').textContent = 'Add Service';
    $('serviceSubmit').textContent = 'Add Service';
    $('serviceCancel').hidden = true;
  }

  function editService(id) {
    const s = services.find((x) => String(x.id) === String(id));
    if (!s) return;
    $('service_id').value = s.id;
    $('service_name').value = s.name;
    $('service_recurrence').value = s.recurrence || 'weekly';
    $('service_weekday').value = s.weekday ?? '0';
    $('service_week_of_month').value = s.week_of_month ?? '1';
    $('service_day_of_month').value = s.day_of_month ?? '';
    toggleRecurrenceFields();
    $('service_start_time').value = (s.start_time || '').slice(0, 5);
    $('service_end_time').value = (s.end_time || '').slice(0, 5);
    $('service_effective_start').value = s.effective_start || '';
    $('service_effective_end').value = s.effective_end || '';
    $('service_active').checked = Number(s.active) === 1;
    $('service_is_public').checked = Number(s.is_public) === 1;
    $('serviceFormTitle').textContent = 'Edit Service';
    $('serviceSubmit').textContent = 'Save Changes';
    $('serviceCancel').hidden = false;
    $('serviceForm').scrollIntoView({ behavior: 'smooth', block: 'center' });
  }

  function toggleRecurrenceFields() {
    const r = $('service_recurrence').value;
    $('weekday_field').hidden = r === 'monthly_date';
    $('week_of_month_field').hidden = r !== 'monthly_weekday';
    $('day_of_month_field').hidden = r !== 'monthly_date';
    $('service_day_of_month').required = r === 'monthly_date';
  }

  // ---------- Exceptions ----------
  function renderExceptions() {
    const body = $('exceptionsBody');
    if (!exceptions.length) {
      body.innerHTML = '<tr><td colspan="5">No exceptions found.</td></tr>';
      return;
    }
    body.innerHTML = exceptions.map((e) => {
      const cancelled = Number(e.is_cancelled) === 1;
      const replacement = cancelled ? '—'
        : esc(fmtDateTime(e.replacement_start)) + (e.replacement_end ? ' – ' + esc(fmtDateTime(e.replacement_end)) : '');
      return `<tr>
        <td>${esc(e.service_name)}</td>
        <td>${esc(e.occurrence_date)}</td>
        <td>${cancelled ? 'Cancelled' : 'Rescheduled'}</td>
        <td>${replacement}</td>
        <td class="schedule-actions">
          <button type="button" class="btn btn-sm btn-secondary" data-edit-exception="${e.id}">Edit</button>
          <button type="button" class="btn btn-sm btn-danger" data-delete-exception="${e.id}">Delete</button>
        </td></tr>`;
    }).join('');
  }

  async function loadExceptions() {
    const serviceId = $('exceptionFilter').value;
    exceptions = (await api('list_exceptions', null, serviceId ? { service_id: serviceId } : {})).data;
    renderExceptions();
  }

  function toggleReplacementFields() {
    const reschedule = $('exception_type').value === 'reschedule';
    document.querySelectorAll('.replacement-field').forEach((el) => { el.hidden = !reschedule; });
    $('exception_replacement_start').required = reschedule;
  }

  function resetExceptionForm() {
    $('exceptionForm').reset();
    $('exception_id').value = '';
    $('exceptionFormTitle').textContent = 'Add Exception';
    $('exceptionSubmit').textContent = 'Add Exception';
    $('exceptionCancel').hidden = true;
    toggleReplacementFields();
  }

  function editException(id) {
    const e = exceptions.find((x) => String(x.id) === String(id));
    if (!e) return;
    $('exception_id').value = e.id;
    $('exception_service').value = e.service_id;
    $('exception_occurrence').value = e.occurrence_date;
    $('exception_type').value = Number(e.is_cancelled) === 1 ? 'cancel' : 'reschedule';
    $('exception_replacement_start').value = toLocalInput(e.replacement_start);
    $('exception_replacement_end').value = toLocalInput(e.replacement_end);
    toggleReplacementFields();
    $('exceptionFormTitle').textContent = 'Edit Exception';
    $('exceptionSubmit').textContent = 'Save Changes';
    $('exceptionCancel').hidden = false;
    $('exceptionForm').scrollIntoView({ behavior: 'smooth', block: 'center' });
  }

  // Default a reschedule to the original occurrence date at the service's usual times.
  function prefillReplacement() {
    if ($('exception_type').value !== 'reschedule' || $('exception_replacement_start').value) return;
    const s = services.find((x) => String(x.id) === $('exception_service').value);
    const d = $('exception_occurrence').value;
    if (!s || !d) return;
    $('exception_replacement_start').value = `${d}T${s.start_time.slice(0, 5)}`;
    if (s.end_time && s.end_time >= s.start_time) {
      $('exception_replacement_end').value = `${d}T${s.end_time.slice(0, 5)}`;
    }
  }

  // ---------- Reminders ----------
  const today = new Date().toISOString().slice(0, 10);

  function renderReminders() {
    const body = $('remindersBody');
    if (!reminders.length) {
      body.innerHTML = '<tr><td colspan="5">No reminders found.</td></tr>';
      return;
    }
    body.innerHTML = reminders.map((r) => {
      const dates = esc(r.start_date) + (r.end_date ? ' → ' + esc(r.end_date) : '');
      const time = r.start_time ? fmtTime(r.start_time) + (r.end_time ? ' – ' + fmtTime(r.end_time) : '') : 'All day';
      return `<tr class="${(r.end_date || r.start_date) < today ? 'past' : ''}">
        <td>${esc(r.title)}</td>
        <td>${dates}</td>
        <td>${esc(time)}</td>
        <td>${Number(r.is_public) ? 'Yes' : 'No'}</td>
        <td class="schedule-actions">
          <button type="button" class="btn btn-sm btn-secondary" data-edit-reminder="${r.id}">Edit</button>
          <button type="button" class="btn btn-sm btn-danger" data-delete-reminder="${r.id}">Delete</button>
        </td></tr>`;
    }).join('');
  }

  async function loadReminders() {
    reminders = (await api('list_reminders', null, { scope: $('reminderScope').value })).data;
    renderReminders();
  }

  function resetReminderForm() {
    $('reminderForm').reset();
    $('reminder_id').value = '';
    $('reminderFormTitle').textContent = 'Add Reminder / Important Date';
    $('reminderSubmit').textContent = 'Add Reminder';
    $('reminderCancel').hidden = true;
  }

  function editReminder(id) {
    const r = reminders.find((x) => String(x.id) === String(id));
    if (!r) return;
    $('reminder_id').value = r.id;
    $('reminder_title').value = r.title;
    $('reminder_start_date').value = r.start_date;
    $('reminder_end_date').value = r.end_date || '';
    $('reminder_start_time').value = (r.start_time || '').slice(0, 5);
    $('reminder_end_time').value = (r.end_time || '').slice(0, 5);
    $('reminder_is_public').checked = Number(r.is_public) === 1;
    $('reminderFormTitle').textContent = 'Edit Reminder';
    $('reminderSubmit').textContent = 'Save Changes';
    $('reminderCancel').hidden = false;
    $('reminderForm').scrollIntoView({ behavior: 'smooth', block: 'center' });
  }

  $('reminderForm').addEventListener('submit', async (ev) => {
    ev.preventDefault();
    const data = new FormData(ev.target);
    try {
      const res = await api('save_reminder', data);
      showStatus(res.message);
      resetReminderForm();
      await loadReminders();
    } catch (err) { showStatus(err.message, false); }
  });
  $('reminderCancel').addEventListener('click', resetReminderForm);
  $('reminderScope').addEventListener('change', () => loadReminders().catch((err) => showStatus(err.message, false)));
  $('remindersBody').addEventListener('click', async (ev) => {
    const editId = ev.target.dataset.editReminder;
    const delId = ev.target.dataset.deleteReminder;
    if (editId) editReminder(editId);
    if (delId) {
      const r = reminders.find((x) => String(x.id) === delId);
      if (!confirm(`Delete "${r ? r.title : 'this reminder'}"? This cannot be undone.`)) return;
      const data = new FormData();
      data.set('id', delId);
      try {
        const res = await api('delete_reminder', data);
        showStatus(res.message);
        if ($('reminder_id').value === delId) resetReminderForm();
        await loadReminders();
      } catch (err) { showStatus(err.message, false); }
    }
  });

  // ---------- Wiring ----------
  $('service_weekday').innerHTML = WEEKDAYS.map((d, i) => `<option value="${i}">${d}</option>`).join('');

  $('serviceForm').addEventListener('submit', async (ev) => {
    ev.preventDefault();
    const data = new FormData(ev.target);
    if (!$('service_active').checked) data.delete('active');
    try {
      const res = await api('save_service', data);
      showStatus(res.message);
      resetServiceForm();
      await loadServices();
      await loadExceptions();
    } catch (err) { showStatus(err.message, false); }
  });
  $('serviceCancel').addEventListener('click', resetServiceForm);
  $('service_recurrence').addEventListener('change', toggleRecurrenceFields);

  $('servicesBody').addEventListener('click', async (ev) => {
    const editId = ev.target.dataset.editService;
    const delId = ev.target.dataset.deleteService;
    if (editId) editService(editId);
    if (delId) {
      const s = services.find((x) => String(x.id) === delId);
      const extra = s && Number(s.exception_count) ? ` and its ${s.exception_count} exception(s)` : '';
      if (!confirm(`Delete "${s ? s.name : 'this service'}"${extra}? This cannot be undone.`)) return;
      const data = new FormData();
      data.set('id', delId);
      try {
        const res = await api('delete_service', data);
        showStatus(res.message);
        if ($('service_id').value === delId) resetServiceForm();
        await loadServices();
        await loadExceptions();
      } catch (err) { showStatus(err.message, false); }
    }
  });

  $('exception_type').addEventListener('change', () => { toggleReplacementFields(); prefillReplacement(); });
  $('exception_occurrence').addEventListener('change', prefillReplacement);
  $('exception_service').addEventListener('change', prefillReplacement);

  $('exceptionForm').addEventListener('submit', async (ev) => {
    ev.preventDefault();
    const data = new FormData(ev.target);
    try {
      const res = await api('save_exception', data);
      showStatus(res.message);
      resetExceptionForm();
      await loadServices();
      await loadExceptions();
    } catch (err) { showStatus(err.message, false); }
  });
  $('exceptionCancel').addEventListener('click', resetExceptionForm);
  $('exceptionFilter').addEventListener('change', () => loadExceptions().catch((err) => showStatus(err.message, false)));

  $('exceptionsBody').addEventListener('click', async (ev) => {
    const editId = ev.target.dataset.editException;
    const delId = ev.target.dataset.deleteException;
    if (editId) editException(editId);
    if (delId) {
      if (!confirm('Delete this exception? The regular service will appear on that date again.')) return;
      const data = new FormData();
      data.set('id', delId);
      try {
        const res = await api('delete_exception', data);
        showStatus(res.message);
        if ($('exception_id').value === delId) resetExceptionForm();
        await loadServices();
        await loadExceptions();
      } catch (err) { showStatus(err.message, false); }
    }
  });

  toggleReplacementFields();
  toggleRecurrenceFields();
  toggleProgramDays();
  Promise.all([loadServices(), loadExceptions(), loadReminders(), loadProgramSchedules(), loadProgramExceptions()])
    .catch((err) => showStatus(err.message, false));
})();
</script>
</body>
</html>
