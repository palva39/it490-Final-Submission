// Every 60s, refresh count silently
setInterval(async () => {
  try {
    const res = await fetch('/features/notifications.php', {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'list' })
    });
    const data = await res.json();
    const count = data?.success ? (data.count ?? (data.notifications?.length || 0)) : 0;
    setNotifBadge(count);
  } catch { /* no-op */ }
}, 60000);
