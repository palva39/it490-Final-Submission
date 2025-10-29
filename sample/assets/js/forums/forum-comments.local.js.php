<?php
// Keep this as .js.php so the embedded PHP username works.
header('Content-Type: application/javascript');
?>
/* forum comments (local only) */
document.addEventListener('DOMContentLoaded', () => {
  const header = document.getElementById('forum-comment-header');
  const container = document.getElementById('forum-comment-container');
  const form = document.getElementById('forum-comment-form');
  const list = document.getElementById('forum-comment-list');
  const text = document.getElementById('forum-comment-text');
  const currentUser = "<?php echo $username; ?>";
  const LS_KEY = `forumComments_${currentUser}`;

  function escapeHtml(s) { return s.replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
  function loadComments() { try { return JSON.parse(localStorage.getItem(LS_KEY) || '[]'); } catch { return []; } }
  function saveComments(arr) { localStorage.setItem(LS_KEY, JSON.stringify(arr)); }

  let comments = loadComments();
  function render() {
    if (!list) return;
    list.innerHTML = comments.map(c => `
      <li class="forum-comment">
        <div class="meta"><strong>${escapeHtml(c.user)}</strong><small>${escapeHtml(c.time)}</small></div>
        <div>${escapeHtml(c.text)}</div>
      </li>`).join('');
  }

  header?.addEventListener('click', () => { container.classList.toggle('is-expanded'); });
  form?.addEventListener('submit', e => {
    e.preventDefault();
    const val = (text?.value || '').trim(); if (!val) return;
    const now = new Date();
    const ts = now.toLocaleString([], { dateStyle: 'short', timeStyle: 'short' });
    comments.push({ user: currentUser, time: ts, text: val });
    saveComments(comments); render(); text.value = '';
  });
  render();
});
