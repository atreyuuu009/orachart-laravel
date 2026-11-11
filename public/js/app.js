/* Shared UI helpers */
document.addEventListener('click', (e) => {
  // Service price modal (landing + patient home)
  const cat = e.target.closest('[data-service-cat]');
  if (cat) {
    const tpl = document.getElementById('svc-tpl-' + cat.dataset.serviceCat);
    document.getElementById('serviceModalBody').replaceChildren(tpl.content.cloneNode(true));
    bootstrap.Modal.getOrCreateInstance(document.getElementById('serviceModal')).show();
    return;
  }
  // Cancel / reject modal
  const r = e.target.closest('[data-reason-action]');
  if (r) {
    document.getElementById('reasonForm').action = r.dataset.reasonAction;
    document.getElementById('reasonTitle').textContent = r.dataset.reasonTitle || 'Reason';
    const req = !!r.dataset.reasonRequired;
    document.getElementById('reasonText').required = req;
    document.getElementById('reasonText').value = '';
    document.getElementById('reasonHint').textContent = req ? '(required)' : '(optional)';
    bootstrap.Modal.getOrCreateInstance(document.getElementById('reasonModal')).show();
    return;
  }
  // Follow-up modal
  const f = e.target.closest('[data-followup-action]');
  if (f) {
    document.getElementById('followForm').action = f.dataset.followupAction;
    bootstrap.Modal.getOrCreateInstance(document.getElementById('followModal')).show();
    return;
  }
  // Quick reschedule modal (staff)
  const qr = e.target.closest('.quick-reschedule-btn');
  if (qr) {
    const modalEl = document.getElementById('quickRescheduleModal');
    if (!modalEl) return;
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);

    document.getElementById('qrPatientName').textContent = qr.dataset.patient || 'Patient';
    document.getElementById('qrService').textContent = qr.dataset.service || 'Procedure';
    document.getElementById('qrDentist').textContent = qr.dataset.dentist || 'Dentist';
    document.getElementById('qrCustomLink').href = qr.dataset.customUrl || '#';
    document.getElementById('qrCancelForm').action = qr.dataset.cancelUrl || '#';

    const loading = document.getElementById('qrLoading');
    const list = document.getElementById('qrSlotsList');
    loading.classList.remove('d-none');
    list.replaceChildren();

    modal.show();

    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const rescheduleUrl = qr.dataset.rescheduleUrl;

    fetch(qr.dataset.slotsUrl, { headers: { Accept: 'application/json' } })
      .then(res => res.json())
      .then(data => {
        loading.classList.add('d-none');
        if (!data.slots || data.slots.length === 0) {
          list.innerHTML = '<div class="text-secondary small py-2">No upcoming slots found in the next 14 days. Please use the custom picker below.</div>';
          return;
        }

        data.slots.forEach(s => {
          const form = document.createElement('form');
          form.method = 'POST';
          form.action = rescheduleUrl;
          form.className = 'w-100';
          form.innerHTML = `
            <input type="hidden" name="_token" value="${csrf}">
            <input type="hidden" name="date" value="${s.date}">
            <input type="hidden" name="time" value="${s.time}">
            <input type="hidden" name="dentist_id" value="${s.dentist_id}">
            <button type="submit" class="btn btn-outline-primary text-start w-100 d-flex justify-content-between align-items-center p-2">
              <div>
                <strong>${s.date_label} at ${s.time_label}</strong>
                <div class="small text-secondary">${s.dentist_name}</div>
              </div>
              <span class="badge text-bg-primary">Book this slot</span>
            </button>
          `;
          list.appendChild(form);
        });
      })
      .catch(() => {
        loading.classList.add('d-none');
        list.innerHTML = '<div class="text-danger small py-2">Error loading suggested slots.</div>';
      });
  }
});
