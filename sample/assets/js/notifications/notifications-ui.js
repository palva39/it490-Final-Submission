// === Notifications UI ===
const notifBadgeEl = document.getElementById('notif-badge');
const notifNavLink = document.getElementById('nav-notifications-link');

function setNotifBadge(count) {
  if (!notifBadgeEl) return;
  if (!count || count <= 0) {
    notifBadgeEl.style.display = 'none';
    notifBadgeEl.textContent = '';
  } else {
    notifBadgeEl.style.display = 'inline-block';
    notifBadgeEl.textContent = String(count);
  }
}

async function loadNotifications() {
  const listContainer = document.getElementById('notifications-list');
  try {
    const res = await fetch('/features/notifications.php', {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'list' })
    });
    const data = await res.json();

    if (!data.success) {
      listContainer.innerHTML = `<p class="text-danger">${data.message || 'Failed to load'}</p>`;
      setNotifBadge(0);
      return;
    }

    const items = data.notifications || [];
    const count = typeof data.count === 'number' ? data.count : items.length;
    setNotifBadge(count);

    if (items.length === 0) {
      listContainer.innerHTML = `<p class="text-muted">No new notifications.</p>`;
      return;
    }

   
    listContainer.innerHTML = '';
    items.forEach(n => {
      const card = document.createElement('div');
      card.className = 'card mb-3 shadow-sm';
      card.innerHTML = `
        <div class="card-body">
          <p class="mb-1">${n.message}</p>
          <small class="text-secondary d-block mb-2">${n.created_at}</small>

        </div>
      `;
      listContainer.appendChild(card);
    });


    listContainer.querySelectorAll('[data-action="view-thread"]').forEach(btn => {
      btn.addEventListener('click', () => {
        const rawgId = parseInt(btn.getAttribute('data-rawg') || '0', 10);
        if (!rawgId) return;
        goToForumThread(rawgId);
      });
    });

  } catch (err) {
    console.error(err);
    setNotifBadge(0);
    document.getElementById('notifications-list').innerHTML =
      `<p class="text-danger">Error loading notifications.</p>`;
  }
}

async function deleteAllNotifications() {
  try {
    const res = await fetch('/features/notifications.php', {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'delete' })
    });
    const data = await res.json();
    if (data.success) {
      setNotifBadge(0);
      loadNotifications();
    } else {
      alert(`Failed to delete all: ${data.message || 'Unknown error'}`);
    }
  } catch (err) {
    console.error(err);
    alert('Error deleting all notifications.');
  }
}

function goToForumThread(rawgId) {
  if (!window.openDetails) return;
  window.openDetails(rawgId);

 
  const tryExpand = () => {
    const collapse = document.getElementById('commentsCollapse');
    if (!collapse) { requestAnimationFrame(tryExpand); return; }
    const bsCollapse = bootstrap.Collapse.getOrCreateInstance(collapse, {toggle:false});
    bsCollapse.show();

    
    setTimeout(() => {
      collapse.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }, 150);
  };
  tryExpand();
}


document.addEventListener('DOMContentLoaded', () => {
  loadNotifications();
  document.getElementById('refresh-notifications').addEventListener('click', loadNotifications);
  document.getElementById('delete-all-notifications').addEventListener('click', deleteAllNotifications);


  const nav = document.getElementById('sidebarNav');
  nav?.addEventListener('click', (e) => {
    const a = e.target.closest('a[data-target="notifications-content"]');
    if (a) setTimeout(loadNotifications, 50);
  });
});
