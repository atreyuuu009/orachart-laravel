/* Shared UI helpers */
document.addEventListener('DOMContentLoaded', () => {
  const toggle = document.getElementById('sidebarToggle');
  if (!toggle) return;

  const updateToggle = () => {
    const isCollapsed = document.documentElement.classList.contains('sidebar-collapsed');
    toggle.setAttribute('aria-expanded', String(!isCollapsed));
    toggle.setAttribute('aria-label', isCollapsed ? 'Expand sidebar' : 'Collapse sidebar');
    toggle.title = isCollapsed ? 'Expand sidebar' : 'Collapse sidebar';
  };
  updateToggle();

  toggle.addEventListener('click', () => {
    document.documentElement.classList.toggle('sidebar-collapsed');
    localStorage.setItem('sidebar-collapsed', String(document.documentElement.classList.contains('sidebar-collapsed')));
    updateToggle();
  });
});

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
  }
});
