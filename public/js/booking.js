/* Booking / reschedule: procedure picker, slot loading, temporary slot hold */
(() => {
  const cfg = window.BOOKING || {};
  const $ = (id) => document.getElementById(id);
  const csrf = document.querySelector('meta[name="csrf-token"]').content;
  const serviceId = $('serviceId'), dateInput = $('dateInput'), dentist = $('dentistSelect'),
        emergency = $('emergency'), grid = $('slotGrid'), msg = $('slotMsg'),
        timeInput = $('timeInput'), holdBox = $('holdBox'), form = $('bookingForm');
  let timer = null, loadToken = 0;

  const STATE_LABEL = { taken: 'Taken', notice: 'Too soon', full: 'Day full', emergency: 'Reserved' };

  function params() {
    const p = new URLSearchParams({ service_id: serviceId.value, date: dateInput.value, emergency: emergency.checked ? 1 : 0 });
    if (dentist.value) p.set('dentist_id', dentist.value);
    if (cfg.exclude) p.set('exclude', cfg.exclude);
    return p;
  }

  function clearSelection() {
    timeInput.value = ''; holdBox.classList.add('d-none'); clearInterval(timer);
    grid.querySelectorAll('.slot.selected').forEach((b) => b.classList.remove('selected'));
  }

  async function loadSlots() {
    clearSelection(); grid.replaceChildren();
    if (!serviceId.value || !dateInput.value) { msg.textContent = 'Choose a procedure and a date to see available times.'; return; }
    const token = ++loadToken;
    msg.textContent = 'Loading available times…';
    try {
      const res = await fetch(cfg.slotsUrl + '?' + params(), { headers: { Accept: 'application/json' } });
      if (token !== loadToken) return;
      if (!res.ok) { msg.textContent = 'Could not load times. Please check the date and try again.'; return; }
      const data = await res.json();
      if (data.message) { msg.textContent = data.message; return; }
      const open = data.slots.filter((s) => s.state === 'open').length;
      msg.textContent = open ? 'Pick a time. It is held for you while you finish the form.' : 'No times are available on this date. Please try another day.';
      data.slots.forEach((s) => {
        const b = document.createElement('button');
        b.type = 'button'; b.className = 'slot'; b.dataset.time = s.time; b.disabled = s.state !== 'open';
        b.innerHTML = s.label + (s.state !== 'open' ? '<small>' + (STATE_LABEL[s.state] || '') + '</small>' : '');
        if (s.state === 'open') b.addEventListener('click', () => hold(b, s));
        grid.appendChild(b);
      });
    } catch (e) { msg.textContent = 'Network error while loading times.'; }
  }

  async function hold(btn, slot) {
    clearSelection();
    const body = { service_id: serviceId.value, date: dateInput.value, time: slot.time, emergency: emergency.checked ? 1 : 0 };
    if (dentist.value) body.dentist_id = dentist.value;
    if (cfg.exclude) body.exclude = cfg.exclude;
    const res = await fetch(cfg.holdUrl, {
      method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf }, body: JSON.stringify(body),
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) { alert(data.message || 'That time is no longer available.'); loadSlots(); return; }
    btn.classList.add('selected'); timeInput.value = slot.time;
    let left = data.seconds;
    const render = () => {
      const m = Math.floor(left / 60), s = String(left % 60).padStart(2, '0');
      holdBox.innerHTML = '<strong>' + slot.label + '</strong> with <strong>' + data.dentist_name + '</strong> is held for you for ' + m + ':' + s;
    };
    holdBox.classList.remove('d-none'); render();
    timer = setInterval(() => {
      left--; if (left <= 0) { clearInterval(timer); alert('Your held time expired. Please pick a time again.'); loadSlots(); } else render();
    }, 1000);
  }

  [dateInput, dentist, emergency].forEach((el) => el && el.addEventListener('change', loadSlots));

  // emergency box
  const eBox = $('emergencyBox');
  const syncEmergency = () => { if (eBox) { eBox.classList.toggle('d-none', !emergency.checked); eBox.querySelector('textarea').required = emergency.checked; } };
  emergency && emergency.addEventListener('change', syncEmergency); syncEmergency();

  // procedure modal (booking page only)
  const procBtn = $('procBtn');
  if (procBtn) {
    const meta = $('procMeta');
    const apply = (radio) => {
      serviceId.value = radio.value;
      procBtn.textContent = radio.dataset.name;
      meta.textContent = 'About ' + radio.dataset.duration + ' · ' + radio.dataset.price;
      loadSlots();
    };
    $('procOk').addEventListener('click', () => { const r = document.querySelector('input[name=proc_pick]:checked'); if (r) apply(r); });
    $('procSearch').addEventListener('input', (e) => {
      const q = e.target.value.toLowerCase();
      document.querySelectorAll('.proc-option').forEach((o) => o.classList.toggle('d-none', !o.dataset.name.includes(q)));
      document.querySelectorAll('.proc-group').forEach((g) => g.classList.toggle('d-none', !g.querySelector('.proc-option:not(.d-none)')));
    });
    if (serviceId.value) { const r = document.querySelector('input[name=proc_pick][value="' + serviceId.value + '"]'); if (r) { r.checked = true; apply(r); } }
  }

  // staff: existing vs walk-in
  const pmNew = $('pmNew');
  if (pmNew) {
    const sync = () => { $('newBox').classList.toggle('d-none', !pmNew.checked); $('existingBox').classList.toggle('d-none', pmNew.checked); };
    document.querySelectorAll('input[name=patient_mode]').forEach((r) => r.addEventListener('change', sync)); sync();
  }
  // patient: health-change box + urgent hint
  const hc = $('hChanged'); if (hc) { const s = () => $('healthBox').classList.toggle('d-none', !hc.checked); hc.addEventListener('change', s); s(); }
  const notes = $('notes');
  if (notes) notes.addEventListener('input', () => $('urgentHint').classList.toggle('d-none', !(/\b(swollen|swelling|bleeding)\b/i.test(notes.value) && !emergency.checked)));

  form.addEventListener('submit', (e) => {
    if (!serviceId.value) { e.preventDefault(); alert('Please select a procedure.'); }
    else if (!timeInput.value) { e.preventDefault(); alert('Please pick an available time.'); }
  });

  if (serviceId.value && dateInput.value) loadSlots();
})();
