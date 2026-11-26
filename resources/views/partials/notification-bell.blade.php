<div class="dropdown patient-notification" id="patientNotification" data-index-url="{{ route('notifications.index') }}" data-seen-url="{{ route('notifications.seen') }}">
  <button class="btn notification-bell" id="notificationBell" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" aria-label="Notifications">
    <i class="bi bi-bell"></i>
    <span class="notification-count d-none" id="notificationCount"></span>
  </button>
  <div class="dropdown-menu dropdown-menu-end notification-menu p-0" aria-labelledby="notificationBell">
    <div class="notification-heading">Notifications</div>
    <div id="notificationItems" aria-live="polite"><div class="notification-loading">Loading notifications…</div></div>
  </div>
</div>
@push('scripts')
<script>
(() => {
  const root = document.getElementById('patientNotification');
  if (!root) return;
  const button = document.getElementById('notificationBell');
  const count = document.getElementById('notificationCount');
  const list = document.getElementById('notificationItems');
  const token = document.querySelector('meta[name="csrf-token"]')?.content;
  const updateBadge = data => {
    count.textContent = data.unread > 9 ? '9+' : data.unread;
    count.classList.toggle('d-none', data.unread < 1);
    count.classList.toggle('has-red', data.has_red_unread);
  };
  const render = items => {
    if (!items.length) { list.innerHTML = '<div class="notification-empty">You\'re all caught up.</div>'; return; }
    list.innerHTML = items.map(item => {
      const content = `<span class="notification-title">${escapeHtml(item.title)}</span><span class="notification-message">${escapeHtml(item.message)}</span><span class="notification-time">${escapeHtml(item.time)}</span>`;
      const cls = `notification-item tone-${item.tone}${item.unread ? ' is-unread' : ''}${item.sticky ? ' is-sticky' : ''}`;
      return item.url ? `<a class="${cls}" href="${escapeAttr(item.url)}">${content}</a>` : `<div class="${cls}">${content}</div>`;
    }).join('');
  };
  const escapeHtml = value => { const el = document.createElement('span'); el.textContent = value ?? ''; return el.innerHTML; };
  const escapeAttr = value => escapeHtml(value).replaceAll('"', '&quot;');
  const load = async () => {
    try {
      const response = await fetch(root.dataset.indexUrl, {headers: {'Accept': 'application/json'}});
      if (!response.ok) throw new Error('Notification request failed');
      const data = await response.json(); updateBadge(data); render(data.items || []);
    } catch (_) { list.innerHTML = '<div class="notification-empty">Notifications could not be loaded.</div>'; }
  };
  button.addEventListener('show.bs.dropdown', async () => {
    await load();
    try {
      const response = await fetch(root.dataset.seenUrl, {method: 'POST', headers: {'Accept': 'application/json', 'X-CSRF-TOKEN': token, 'Content-Type': 'application/json'}, body: '{}'});
      if (response.ok) await load();
    } catch (_) { /* Keep the server-provided badge if marking seen fails. */ }
  });
})();
</script>
@endpush
