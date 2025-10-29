(function(){
  const statusEl   = document.getElementById('forumStatus');
  const listWrap   = document.getElementById('forumList');
  const listView   = document.getElementById('forumListView');
  const loadMoreBt = document.getElementById('forumLoadMore');

  const createView = document.getElementById('forumCreateView');
  const newBtn     = document.getElementById('forumNewBtn');
  const cancelBtn  = document.getElementById('forumCancelCreate');
  const form       = document.getElementById('forumCreateForm');
  const fGameId    = document.getElementById('forumGameId');
  const fTitle     = document.getElementById('forumTitle');
  const fDesc      = document.getElementById('forumDesc');
  const gamePrev   = document.getElementById('forumCreateGamePreview');
  const gamePrevImg= document.getElementById('forumCreateGameImg');
  const gamePrevNm = document.getElementById('forumCreateGameName');

  const threadView = document.getElementById('forumThreadView');
  const backBtn    = document.getElementById('forumBackToList');
  const thGameImg  = document.getElementById('forumThreadGameImg');
  const thGameTitle= document.getElementById('forumThreadGameTitle');
  const thTitle    = document.getElementById('forumThreadTitle');
  const thDesc     = document.getElementById('forumThreadDesc');
  const thMsgs     = document.getElementById('forumThreadMessages');
  const msgForm    = document.getElementById('forumMessageForm');
  const msgText    = document.getElementById('forumMessageText');

  let page = 1, totalPages = 1, isLoading=false;
  let currentThreadId = null;

  function setStatus(msg, type){
    statusEl.className = 'alert';
    statusEl.classList.add(type ? `alert-${type}` : 'alert-info');
    statusEl.textContent = msg;
    statusEl.classList.remove('d-none');
  }
  function clearStatus(){ statusEl.classList.add('d-none'); }

  function show(viewName){
    // list/create/thread views
    listView.classList.add('d-none');
    createView.classList.add('d-none');
    threadView.classList.add('d-none');
    if (viewName==='list') listView.classList.remove('d-none');
    if (viewName==='create') createView.classList.remove('d-none');
    if (viewName==='thread') threadView.classList.remove('d-none');
    window.scrollTo({top:0, behavior:'instant'});
  }

  // ===== List =====
  async function loadForums(reset=false){
    if (isLoading) return; isLoading=true;
    if (reset) { page=1; listWrap.innerHTML=''; totalPages=1; }
    setStatus('Loading forums…');

    try {
      const r = await fetch('/features/forums.php', {
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body: new URLSearchParams({ action:'list', page:String(page), pageSize:'9' }).toString()
      });
      const raw = await r.text();
      let j=null; try{ j=JSON.parse(raw) }catch{}
      if (!r.ok || !j || j.success===false) throw new Error(j?.message || raw || 'list failed');

      clearStatus();
      totalPages = j.totalPages || 1;
      renderList(j.items || [], {append: page>1});
      loadMoreBt.classList.toggle('d-none', !(page < totalPages));
    } catch(e){
      console.error('forums list failed', e);
      setStatus('Failed to load forums', 'danger');
    } finally {
      isLoading=false;
    }
  }

  function renderList(items, {append=false}={}){
    if (!items.length && !append) {
      listWrap.innerHTML = '<div class="col-12"><div class="alert">No forums yet.</div></div>';
      return;
    }
    const html = items.map(it=>{
      const img  = it.game_image || '';
      const gttl = it.game_name || 'Unknown game';
      const fttl = it.title || 'Untitled';
      const fid  = it.id;
      return `
      <div class="col-12 col-md-6 col-xl-4">
        <div class="game-card h-100">
          ${img ? `<img src="${img}" class="game-img w-100" alt="">` : ''}
          <div class="p-3 d-flex flex-column">
            <div class="small text-secondary">${gttl}</div>
            <h5 class="mb-2">${fttl}</h5>
            <div class="mt-auto">
              <a href="#" data-forum-id="${fid}" class="btn btn-sm btn-light">Open</a>
            </div>
          </div>
        </div>
      </div>`;
    }).join('');
    if (append){
      const tmp = document.createElement('div'); tmp.innerHTML = html;
      [...tmp.children].forEach(n=>listWrap.appendChild(n));
    } else {
      listWrap.innerHTML = html;
    }
    listWrap.querySelectorAll('a[data-forum-id]').forEach(a=>{
      a.addEventListener('click', e=>{
        e.preventDefault();
        const fid = parseInt(a.getAttribute('data-forum-id')||'0',10);
        if (fid) openThread(fid);
      });
    });
  }

  loadMoreBt?.addEventListener('click', ()=>{
    if (page < totalPages) { page+=1; loadForums(false); }
  });

  // ===== Create =====
  function prefillCreate(draft){
    fGameId.value = draft?.rawg_id || '';
    fTitle.value  = draft?.game_name ? `Discussion: ${draft.game_name}` : '';
    fDesc.value   = '';
    if (draft?.game_name || draft?.game_image){
      gamePrev.classList.remove('d-none');
      gamePrevNm.textContent = draft?.game_name || '';
      gamePrevImg.src = draft?.game_image || '';
    } else {
      gamePrev.classList.add('d-none');
      gamePrevNm.textContent = '';
      gamePrevImg.src = '';
    }
  }

  newBtn?.addEventListener('click', ()=>{
    prefillCreate(window.__forumDraft || null);
    show('create');
    setTimeout(()=>fTitle.focus(), 50);
  });

  cancelBtn?.addEventListener('click', ()=>{
    show('list');
    window.__forumDraft = null;
  });

  form?.addEventListener('submit', async (e)=>{
    e.preventDefault();
    const rawg_id    = fGameId.value.trim();
    const title      = fTitle.value.trim();
    const description= fDesc.value.trim();
    if (!rawg_id || !title){ alert('Please provide a game (open a game and click "+ Create Forum") and a title.'); return; }

    try {
      const r = await fetch('/features/forums.php', {
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body: new URLSearchParams({
          action:'create',
          rawg_id, title, description
        }).toString()
      });
      const raw = await r.text();
      let j=null; try{ j=JSON.parse(raw) }catch{}
      if (!r.ok || !j || j.success===false) throw new Error(j?.message || raw || 'create failed');

      window.__forumDraft = null;
      show('list');
      page=1; loadForums(true);
    } catch(e){
      console.error('forum create failed', e);
      alert('Failed to create forum');
    }
  });

 
  window.forumShowCreate = (draft)=>{
    prefillCreate(draft);
    show('create');
    setTimeout(()=>fTitle.focus(), 50);
  };

  // ===== Thread =====
  async function openThread(forumId){
  currentThreadId = forumId;
  setStatus('Loading thread…');
  show('thread');
  try {
    const r = await fetch('/features/forums.php', {
      method:'POST',
      headers:{'Content-Type':'application/x-www-form-urlencoded'},
      body: new URLSearchParams({ action:'get', id:String(forumId) }).toString()
    });
    const raw = await r.text();
    let j=null; try{ j=JSON.parse(raw) }catch{}
    if (!r.ok || !j || j.success===false) throw new Error(j?.message || raw || 'get failed');

    clearStatus();

    // Accept either shape from the listener
    const th = j.item || j.forum || {};

    // Header fields
    thGameImg.src              = th.game_image || '';
    thGameTitle.textContent    = th.game_name || '';
    thTitle.textContent        = th.title || '';
    thDesc.textContent         = th.description || '';


    const messages = j.messages || th.messages || [];
    renderMessages(messages);
  } catch(e){
    console.error('thread get failed', e);
    setStatus('Failed to load thread', 'danger');
  }
}


  function renderMessages(msgs){
  
  const byParent = new Map();
  (Array.isArray(msgs) ? msgs : []).forEach(m => {
    const pid = (m.parent_id == null ? null : Number(m.parent_id));
    if (!byParent.has(pid)) byParent.set(pid, []);
    byParent.get(pid).push(m);
  });
  
  for (const arr of byParent.values()) {
    arr.sort((a,b) => String(a.created_at).localeCompare(String(b.created_at)));
  }

  function esc(s){ return String(s).replace(/[&<>]/g, t => ({'&':'&amp;','<':'&lt;','>':'&gt;'}[t])); }

  function renderBranch(parentId, depth){
    const items = byParent.get(parentId) || [];
    if (!items.length) return '';
    return items.map(m => {
      const bodyRaw = (m.message ?? m.text ?? '');
      const body = esc(bodyRaw);
      const by   = esc(m.username || 'User');
      const ts   = esc(m.created_at || '');
      const mid  = Number(m.id);

      const formId = `reply-form-${mid}`;

      return `
        <div class="list-group-item" style="background:#15151f;color:#e8e8ff">
          <div class="d-flex justify-content-between">
            <strong>${by}</strong>
            <small class="text-secondary">${ts}</small>
          </div>
          <div class="mt-1">${body}</div>

          <div class="mt-2">
            <a href="#" class="small text-decoration-underline" data-reply-btn="${mid}">Reply</a>
          </div>

          <form class="mt-2 d-none" id="${formId}" data-reply-form="${mid}">
            <textarea class="form-control mb-2" rows="2" placeholder="Write a reply..."></textarea>
            <div class="d-flex gap-2">
              <button type="submit" class="btn btn-sm btn-light">Post reply</button>
              <button type="button" class="btn btn-sm btn-outline-light" data-reply-cancel="${mid}">Cancel</button>
            </div>
          </form>

          <!-- children -->
          <div class="mt-2 ms-4">
            ${renderBranch(mid, depth+1)}
          </div>
        </div>
      `;
    }).join('');
  }

  const html = renderBranch(null, 0);
  thMsgs.innerHTML = html || `<div class="list-group-item" style="background:#15151f;color:#e8e8ff">No messages yet.</div>`;
}



  backBtn?.addEventListener('click', ()=>{
    show('list');
    page=1; loadForums(true);
  });

  msgForm?.addEventListener('submit', async (e)=>{
    e.preventDefault();
    const text = (msgText.value || '').trim();
    if (!currentThreadId || !text) return;
    try {
      const r = await fetch('/features/forums.php', {
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body: new URLSearchParams({
          action:'post_message',
          id:String(currentThreadId),
          text
        }).toString()
      });
      const raw = await r.text();
      let j=null; try{ j=JSON.parse(raw) }catch{}
      if (!r.ok || !j || j.success===false) throw new Error(j?.message || raw || 'post failed');
      msgText.value='';
      // reload thread messages
      openThread(currentThreadId);
    } catch(e){
      console.error('message post failed', e);
      alert('Failed to post message');
    }
  });
  
thMsgs.addEventListener('click', (e) => {
  const btn = e.target.closest('[data-reply-btn]');
  const cancel = e.target.closest('[data-reply-cancel]');
  if (btn) {
    e.preventDefault();
    const mid = btn.getAttribute('data-reply-btn');
    const form = thMsgs.querySelector(`[data-reply-form="${mid}"]`);
    if (form) form.classList.toggle('d-none');
  }
  if (cancel) {
    e.preventDefault();
    const mid = cancel.getAttribute('data-reply-cancel');
    const form = thMsgs.querySelector(`[data-reply-form="${mid}"]`);
    if (form) form.classList.add('d-none');
  }
});

thMsgs.addEventListener('submit', async (e) => {
  const form = e.target.closest('[data-reply-form]');
  if (!form) return; // only intercept reply forms
  e.preventDefault();

  const mid = Number(form.getAttribute('data-reply-form'));
  const ta = form.querySelector('textarea');
  const text = (ta?.value || '').trim();
  if (!currentThreadId || !mid || !text) return;

  try {
    const r = await fetch('/features/forums.php', {
      method:'POST',
      headers:{'Content-Type':'application/x-www-form-urlencoded'},
      body: new URLSearchParams({
        action: 'post_message',
        id: String(currentThreadId),
        text,
        parent_id: String(mid)
      }).toString()
    });
    const raw = await r.text();
    let j=null; try{ j=JSON.parse(raw) }catch{}
    if (!r.ok || !j || j.success===false) throw new Error(j?.message || raw || 'post failed');

    // reset + hide form, then reload
    if (ta) ta.value = '';
    form.classList.add('d-none');
    openThread(currentThreadId);
  } catch(e2){
    console.error('reply post failed', e2);
    alert('Failed to post reply');
  }
});


  
  document.addEventListener('DOMContentLoaded', ()=>{
    const nav = document.getElementById('sidebarNav');
    const forumLink = nav?.querySelector('a[data-target="forum-content"]');
    
    if (forumLink){
      forumLink.addEventListener('click', ()=>{
        show('list');
        page=1; loadForums(true);
      });
    }
  });
})();
