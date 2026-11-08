/* Digital odontogram (FDI). Teeth are drawn client-side; findings come from /odontogram/{patient}/findings */
(() => {
  const cfg = window.ODONTO;
  const $ = (id) => document.getElementById(id);
  const csrf = document.querySelector('meta[name="csrf-token"]').content;
  const SURF_ABBR = { whole: '', mesial: 'M', distal: 'D', occlusal: 'O', buccal: 'B', lingual: 'L' };
  const LAYOUT = {
    upper: [[18, 17, 16, 15, 14, 13, 12, 11], [21, 22, 23, 24, 25, 26, 27, 28]],
    lower: [[48, 47, 46, 45, 44, 43, 42, 41], [31, 32, 33, 34, 35, 36, 37, 38]],
    upperP: [[55, 54, 53, 52, 51], [61, 62, 63, 64, 65]],
    lowerP: [[85, 84, 83, 82, 81], [71, 72, 73, 74, 75]],
  };
  let patientId = null, findings = [], selected = null, editing = null;

  const api = async (url, method = 'GET', body) => {
    const res = await fetch(url, { method, headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf }, body: body ? JSON.stringify(body) : undefined });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) throw new Error(data.message || (data.errors && Object.values(data.errors)[0][0]) || 'Request failed');
    return data;
  };
  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

  function drawRow(el, halves) {
    el.replaceChildren();
    halves.forEach((half, i) => {
      if (i) { const g = document.createElement('div'); g.className = 'tooth-gap'; el.appendChild(g); }
      half.forEach((n) => {
        const list = findings.filter((f) => f.tooth === n);
        const t = document.createElement('div');
        t.className = 'tooth' + (selected === n ? ' selected' : '') + (list.some((f) => f.code === 'MISSING' || f.code === 'EXTRACTED') ? ' missing' : '');
        const whole = list.find((f) => f.surface === 'whole');
        const main = whole || list[0];
        t.innerHTML = '<div>' + n + '</div><div class="crown"' + (main ? ' style="background:' + main.color + '"' : '') + '>' +
          (main ? '<span class="mark">' + esc(main.code.slice(0, 3)) + '</span>' : '') + '</div>' +
          (list.length > 1 ? '<div class="dots">' + list.slice(0, 3).map((f) => '<i style="background:' + f.color + '"></i>').join('') + '</div>' : '');
        t.title = list.map((f) => f.label + (f.surface !== 'whole' ? ' (' + f.surface + ')' : '')).join(', ') || 'No findings';
        t.addEventListener('click', () => select(n));
        el.appendChild(t);
      });
    });
  }

  function draw() {
    drawRow($('rowUpper'), LAYOUT.upper); drawRow($('rowLower'), LAYOUT.lower);
    drawRow($('rowUpperP'), LAYOUT.upperP); drawRow($('rowLowerP'), LAYOUT.lowerP);
    renderPanel();
  }

  function select(n) { selected = n; resetForm(); draw(); }

  function renderPanel() {
    const box = $('toothFindings');
    if (!patientId) { box.textContent = 'Select a patient first.'; return; }
    if (!selected) { box.textContent = 'Select a tooth.'; $('toothTitle').textContent = 'Tooth details'; if ($('findingForm')) $('findingForm').classList.add('d-none'); return; }
    $('toothTitle').textContent = 'Tooth ' + selected;
    const list = findings.filter((f) => f.tooth === selected);
    box.innerHTML = list.length ? list.map((f) =>
      '<div class="border rounded-3 p-2 mb-2"><div class="d-flex justify-content-between"><strong><span class="legend-dot" style="background:' + f.color + '"></span>' + esc(f.label) + '</strong>' +
      (cfg.canEdit ? '<span><button class="btn btn-link btn-sm p-0 me-2" data-edit="' + f.id + '">Edit</button><button class="btn btn-link btn-sm text-danger p-0" data-del="' + f.id + '">Remove</button></span>' : '') + '</div>' +
      '<div class="small text-secondary">' + (f.surface !== 'whole' ? 'Surface: ' + esc(f.surface) + ' · ' : '') + esc(f.category) + (f.by ? ' · ' + esc(f.by) : '') + '</div>' +
      (f.notes ? '<div class="small mt-1">' + esc(f.notes) + '</div>' : '') + '</div>').join('') : '<span class="text-secondary">No findings recorded — tooth is considered healthy.</span>';
    if ($('findingForm')) { $('findingForm').classList.remove('d-none'); syncSurface(); }
  }

  function syncSurface() {
    const opt = $('condSelect').selectedOptions[0];
    $('surfaceWrap').classList.toggle('d-none', !opt || opt.dataset.applies === 'tooth');
  }
  function resetForm() {
    editing = null;
    if (!$('findingForm')) return;
    $('findingId').value = ''; $('notesInput').value = ''; $('saveFinding').textContent = 'Add finding'; $('cancelEdit').classList.add('d-none');
  }

  async function loadPatient(id) {
    patientId = id || null; selected = null; findings = []; resetForm(); $('historyBtn').disabled = !patientId;
    if (!patientId) { $('patientInfo').textContent = 'Select a patient to load the chart.'; draw(); return; }
    const d = await api(cfg.findingsUrl + '/' + patientId + '/findings');
    findings = d.findings;
    $('patientInfo').innerHTML = '<strong>' + esc(d.patient.name) + '</strong> · ' + esc(d.patient.code) + ' · Age ' + d.patient.age + (d.patient.sex ? ' · ' + esc(d.patient.sex) : '');
    if (d.patient.age < 13) $('showPrimary').checked = true;
    $('primaryWrap').classList.toggle('d-none', !$('showPrimary').checked);
    draw();
  }

  $('patientSelect').addEventListener('change', (e) => loadPatient(e.target.value).catch((err) => alert(err.message)));
  $('showPrimary').addEventListener('change', (e) => $('primaryWrap').classList.toggle('d-none', !e.target.checked));

  $('toothFindings').addEventListener('click', async (e) => {
    const ed = e.target.closest('[data-edit]'), del = e.target.closest('[data-del]');
    try {
      if (ed) {
        const f = findings.find((x) => x.id == ed.dataset.edit); editing = f;
        $('condSelect').value = f.condition_id; $('surfaceSelect').value = f.surface === 'whole' ? 'mesial' : f.surface;
        $('notesInput').value = f.notes || ''; $('saveFinding').textContent = 'Save changes'; $('cancelEdit').classList.remove('d-none'); syncSurface();
      } else if (del && confirm('Remove this finding? It stays in the history log.')) {
        await api(cfg.findingsUrl + '/findings/' + del.dataset.del, 'DELETE');
        findings = findings.filter((x) => x.id != del.dataset.del); draw();
      }
    } catch (err) { alert(err.message); }
  });

  if (cfg.canEdit) {
    $('condSelect').addEventListener('change', syncSurface);
    $('cancelEdit').addEventListener('click', () => { resetForm(); renderPanel(); });
    $('findingForm').addEventListener('submit', async (e) => {
      e.preventDefault();
      const applies = $('condSelect').selectedOptions[0].dataset.applies;
      const body = { condition_code_id: $('condSelect').value, surface: applies === 'tooth' ? 'whole' : $('surfaceSelect').value, notes: $('notesInput').value };
      try {
        if (editing) {
          const f = await api(cfg.findingsUrl + '/findings/' + editing.id, 'PUT', body);
          findings = findings.map((x) => (x.id === f.id ? f : x));
        } else {
          const f = await api(cfg.findingsUrl + '/' + patientId + '/findings', 'POST', { ...body, tooth_number: selected });
          findings = findings.filter((x) => x.id !== f.id).concat(f);
        }
        resetForm(); draw();
      } catch (err) { alert(err.message); }
    });
  }

  $('historyBtn').addEventListener('click', async () => {
    const rows = await api(cfg.findingsUrl + '/' + patientId + '/history' + (selected ? '?tooth=' + selected : ''));
    const label = (v) => v ? esc(v.condition) + (v.surface && v.surface !== 'whole' ? ' (' + esc(v.surface) + ')' : '') : '—';
    $('historyBody').innerHTML = rows.length ? '<table class="table table-sm"><thead><tr><th>When</th><th>Tooth</th><th>Action</th><th>Before</th><th>After</th><th>By</th></tr></thead><tbody>' +
      rows.map((h) => '<tr><td class="text-nowrap">' + esc(h.at) + '</td><td>' + h.tooth + '</td><td><span class="badge text-bg-secondary">' + h.action + '</span></td><td>' + label(h.old) + '</td><td>' + label(h.new) + '</td><td>' + esc(h.by) + '</td></tr>').join('') + '</tbody></table>'
      : '<p class="text-secondary">No history yet.</p>';
    bootstrap.Modal.getOrCreateInstance($('historyModal')).show();
  });

  draw();
  if (cfg.selected) { $('patientSelect').value = cfg.selected; loadPatient(cfg.selected); }
})();
