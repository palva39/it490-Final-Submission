/* ===========================
   Search + Recent feed
   =========================== */
(function(){
  const results = document.getElementById('results');
  const status  = document.getElementById('status');
  const qInput  = document.getElementById('q');
  const btn     = document.getElementById('searchBtn');
  const loadMoreBtn = document.getElementById('loadMoreBtn');

  let page = 1;
  const pageSize = 9;
  let totalPages = 1;
  let currentScope = 'recent';
  let isFetching = false;

  function setStatus(text, type) {
    status.className = 'alert';
    status.classList.add(type ? `alert-${type}` : 'alert-info');
    status.innerHTML = text;
    status.classList.remove('d-none');
  }
  function clearStatus(){ status.classList.add('d-none'); }

  async function requestJSONOnce(bodyParams){
    const url = '/features/games.php?_=' + Date.now();
    const body = new URLSearchParams(bodyParams).toString();
    const resp = await fetch(url, { method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'}, body });
    const raw = await resp.text();
    try { return { ok: resp.ok, json: JSON.parse(raw), raw }; }
    catch { return { ok: resp.ok, json: null, raw }; }
  }
  async function requestJSONWithRetry(bodyParams){
    let r = await requestJSONOnce(bodyParams);
    const needsRetry = (!r.json) ||
                       (r.json && r.json.success === false && (r.json.hint === 'rpc_timeout' || /timeout/i.test(r.json.message||'')));
    if (needsRetry) {
      await new Promise(res => setTimeout(res, 700));
      r = await requestJSONOnce(bodyParams);
    }
    return r;
  }

  function triggerScopeAndFetch(resetToFirst = true){
    if (isFetching) return;
    const q = qInput.value.trim();
    currentScope = (q === '') ? 'recent' : 'search';
    if (resetToFirst) page = 1;
    fetchGames(currentScope, { append: false });
  }

  function prefRating(g) {
    if (g.user_rating != null && g.user_rating !== '') return Number(g.user_rating);
    if (g.userRating != null && g.userRating !== '') return Number(g.userRating);
    if (g.rating != null && g.rating !== '') return Number(g.rating);
    return null;
  }
  const toName = (x)=> (typeof x === 'string' ? x : (x?.name ?? ''));

  async function fetchGames(scope, { append = false } = {}){
    if (isFetching) return;
    if (scope) currentScope = scope;
    isFetching = true;

    if (!append) {
      setStatus('Loading games…', null);
      results.innerHTML = '';
    }
    loadMoreBtn.classList.add('d-none');

    const q = qInput.value.trim();
    const params = { page: String(page), pageSize: String(pageSize) };
    if (currentScope === 'search' && q) { params.scope = 'search'; params.query = q; }
    else { params.scope = 'recent'; params.query = ''; }

    try {
      const { ok, json, raw } = await requestJSONWithRetry(params);
      if (!ok || !json) { console.error('games.php non-JSON/HTTP error:\n', raw); setStatus('Failed to load games (temporary server hiccup).', 'danger'); return; }
      if (json.success === false) { setStatus(json.message || 'Failed to load games', 'danger'); return; }

      clearStatus();
      totalPages = Number(json.totalPages || 1);
      renderGames(json.items || [], { append });

      const canLoadMore = (currentScope === 'recent') && (page < totalPages);
      loadMoreBtn.classList.toggle('d-none', !canLoadMore);
    } catch (err) {
      console.error(err); setStatus('Network error loading games', 'danger');
    } finally {
      isFetching = false;
    }
  }

  function renderGames(items, { append = false } = {}){
    if (!items.length && !append) {
      results.innerHTML = '<div class="col-12"><div class="alert">No games found.</div></div>';
      return;
    }

    const cards = items.map(g => {
      const id   = g.id ?? g.rawg_id ?? '';
      const img  = g.background_image || g.image || '';
      const name = g.name || 'Untitled';
      const rating = prefRating(g);
      const released = g.released ? new Date(g.released).toLocaleDateString() : 'Unknown';
      const platforms = (g.platforms || []).slice(0,4).map(p => `<span class="badge badge-chip me-1 mb-1">${toName(p)}</span>`).join('');
      const genres = (g.genres || []).slice(0,3).map(p => `<span class="badge badge-chip me-1 mb-1">${toName(p)}</span>`).join('');

      return `
        <div class="col-12 col-sm-6 col-lg-4" data-card-id="${id}">
          <div class="game-card h-100">
            ${img ? `<img src="${img}" class="game-img w-100" alt="${name}">` : ''}
            <div class="p-3">
              <div class="d-flex justify-content-between align-items-start mb-1">
                <h5 class="mb-0">${name}</h5>
                <span class="card-rating">
                  ${rating != null ? `<span class="badge rating-badge ms-2">${Number(rating).toFixed(1)}</span>` : ''}
                </span>
              </div>
              <div class="text-secondary small mb-2">Released: ${released}</div>
              ${platforms ? `<div class="mb-2">${platforms}</div>` : ''}
              ${genres ? `<div class="mb-2">${genres}</div>` : ''}
              <a href="#" class="btn btn-sm btn-light" data-action="details" data-id="${id}">Details</a>
            </div>
          </div>
        </div>`;
    }).join('');

    if (append) {
      const temp = document.createElement('div'); temp.innerHTML = cards;
      [...temp.children].forEach(c => results.appendChild(c));
    } else {
      results.innerHTML = cards;
    }

    results.querySelectorAll('[data-action="details"]').forEach(el=>{
      el.onclick = (e)=>{ e.preventDefault(); const id = parseInt(el.dataset.id || '0', 10); if (!id) return; openDetails(id); };
    });
  }

  // ===== Details modal + Like/Wishlist/Played + Stars =====
  window.openDetails = function(id){
    const detailsModalEl = document.getElementById('gameDetailsModal');
    const detailsTitleEl = document.getElementById('gdmTitle');
    const detailsBodyEl  = document.getElementById('gdmBody');
    const detailsModal = detailsModalEl ? new bootstrap.Modal(detailsModalEl) : null;
    if (!detailsModal) return;

    detailsTitleEl.textContent = 'Game Details';
    detailsBodyEl.innerHTML = 'Loading…';
    detailsModal.show();

    const body = new URLSearchParams(); body.append('id', String(id));
    fetch('./features/game.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: body.toString() })
      .then(async (r)=> { const text = await r.text(); try { return { ok: r.ok, json: JSON.parse(text), text }; } catch { return { ok: r.ok, json: null, text }; } })
      .then(({ok, json, text})=>{
        if (!ok || !json) { console.error('game.php error:', text); detailsBodyEl.innerHTML = `<div class="alert alert-danger">Failed to load details.</div>`; return; }
        if (json.success === false) { detailsBodyEl.innerHTML = `<div class="alert alert-danger">${json.message || 'Failed to load details.'}</div>`; return; }

        const g = (typeof json.item === 'string') ? JSON.parse(json.item) : (json.item || {});
        if (!g || !g.rawg_id) { detailsBodyEl.innerHTML = `<div class="alert alert-warning">No details found for this game.</div>`; return; }

        const pill = (arr)=> (arr && arr.length) ? `<div class="mb-2">${arr.map(x=>`<span class="badge badge-chip me-1 mb-1">${(typeof x==='string')?x:(x?.name||x)}</span>`).join('')}</div>` : '';
        const hero = g.background_image_additional || g.background_image || '';

        const avgUser = (g.user_rating != null) ? Number(g.user_rating) :
                        (g.userRating != null) ? Number(g.userRating) : null;

        const infoRows = [
          ['Released', g.released || 'Unknown'],
          ['Rating (avg)', (avgUser!=null ? avgUser.toFixed(1) : '—')],
          ['Metacritic', (g.metacritic!=null ? g.metacritic : '—')],
          ['Playtime', (g.playtime!=null ? g.playtime+'h' : '—')],
          ['ESRB', g.esrb_rating || '—'],
          ['Website', g.website ? `<a href="${g.website}" target="_blank" rel="noopener">Visit</a>` : '—'],
          ['Reddit', g.reddit_url ? `<a href="${g.reddit_url}" target="_blank" rel="noopener">Community</a>` : '—'],
        ].map(([k,v])=>`<div class="d-flex justify-content-between border-bottom border-1 border-opacity-25 py-2"><div class="text-secondary">${k}</div><div>${v}</div></div>`).join('');

        const shots = (g.screenshots||[]).slice(0,6).map(u=>`
          <div class="col-6 col-md-4 mb-3">
            <img src="${u}" class="w-100 rounded-3" style="border:1px solid rgba(124,77,255,.25)" alt="">
          </div>`).join('');

        const desc = (g.description || g.description_raw || '').trim();
        const descHtml = g.description ? g.description : (desc ? `<p>${desc.replace(/\n/g,'<br>')}</p>` : '<p>No description available.</p>');

        detailsTitleEl.textContent = g.name || 'Game Details';
        detailsBodyEl.innerHTML = `
          ${hero ? `<img src="${hero}" class="w-100 mb-3 rounded-3" style="border:1px solid rgba(124,77,255,.25)" alt="">` : ''}

          ${pill(g.platforms)}
          ${pill(g.genres)}
          ${pill(g.tags)}
          ${pill(g.stores)}
          ${pill(g.developers)}
          ${pill(g.publishers)}

          <div class="detail-actions" id="detailActions">
            <button class="btn btn-sm btn-outline-light" data-act="like" data-id="${g.rawg_id}">❤️ Like</button>
            <button class="btn btn-sm btn-outline-light" data-act="wishlist" data-id="${g.rawg_id}">📝 Wishlist</button>
            <button class="btn btn-sm btn-outline-light" data-act="played" data-id="${g.rawg_id}">✅ Played</button>
            <button class="btn btn-sm btn-outline-light" data-act="create-forum" data-id="${g.rawg_id}">➕ Create Forum</button>
          </div>

          <div class="row g-3">
            <div class="col-lg-8">
              <h6 class="mb-2">About</h6>
              <div class="mb-3" style="line-height:1.6">${descHtml}</div>
              ${shots ? `<h6 class="mb-2">Screenshots</h6><div class="row">${shots}</div>` : ''}
            </div>
            <div class="col-lg-4">
              <h6 class="mb-2">Info</h6>
              ${infoRows}

              <div class="mt-3">
                <div class="d-flex justify-content-between align-items-center mb-1">
                  <span id="ratingNum">${(g.user_rating_for_user!=null)?Number(g.user_rating_for_user).toFixed(1):'—'}</span>
                </div>
              </div>
              
            </div>
          </div>
          <div class="mt-4 pt-4 border-top border-1 border-opacity-25" id="reviewsRoot">
  <div class="d-flex gap-2 flex-wrap">
    <button class="btn btn-primary" type="button" id="btnToggleReviews">
      Show Reviews
    </button>
    <button class="btn btn-outline-light" type="button" id="btnWriteReview">
      + Write a Review
    </button>
  </div>

  <!-- Create Review -->
  <div class="card mt-3 d-none" id="createReviewCard" style="background:#15151f;border:1px solid rgba(124,77,255,.25)">
    <div class="card-body">
      <h6 class="mb-3">Your Review for <span id="revGameName"></span></h6>
      <div class="row g-3 align-items-center">
        <div class="col-sm-6">
          <label class="form-label mb-1">Title (optional)</label>
          <input type="text" id="revTitle" class="form-control" maxlength="100" placeholder="e.g., Masterpiece with clunky UI">
        </div>
        <div class="col-sm-6">
          <label class="form-label mb-1">Rating</label>
          <div class="d-flex align-items-center gap-2">
            <input type="range" min="0.5" max="5" step="0.5" id="revRating" class="form-range" style="width:200px">
            <span id="revRatingNum" class="badge bg-secondary">3.0</span>
          </div>
        </div>
      </div>
      <label class="form-label mt-2">Review</label>
      <textarea id="revBody" class="form-control" rows="4" maxlength="2000" placeholder="Share what you liked, disliked, and who might enjoy it."></textarea>
      <div class="mt-3 d-flex gap-2">
        <button class="btn btn-success" id="revSubmit">Post Review</button>
        <button class="btn btn-outline-light" id="revCancel">Cancel</button>
      </div>
      <div class="small text-secondary mt-2">Tip: ratings support halves (e.g., 3.5).</div>
    </div>
  </div>

  <!-- Reviews List -->
  <div class="collapse mt-3" id="reviewsCollapse">
    <div class="card card-body text-dark bg-light">
      <div class="d-flex justify-content-between align-items-center">
        <h6 class="mb-0 text-secondary">Recent Reviews</h6>
        <div id="reviewsMeta" class="small text-muted"></div>
      </div>
      <div id="reviewsList" class="mt-2"></div>
      <div class="text-center mt-3">
        <button class="btn btn-outline-dark btn-sm d-none" id="reviewsLoadMore">Load more</button>
      </div>
    </div>
  </div>
</div>

        `;

        // === Action buttons ===
        /* ===== Reviews: show/hide, list, create ===== */
(function initReviews(){
  const root = document.getElementById('reviewsRoot');
  if (!root) return;

  const btnToggle  = document.getElementById('btnToggleReviews');
  const btnWrite   = document.getElementById('btnWriteReview');
  const collapseEl = document.getElementById('reviewsCollapse');
  const listEl     = document.getElementById('reviewsList');
  const metaEl     = document.getElementById('reviewsMeta');
  const moreBtn    = document.getElementById('reviewsLoadMore');

  const cardCreate = document.getElementById('createReviewCard');
  const revGameNm  = document.getElementById('revGameName');
  const revTitle   = document.getElementById('revTitle');
  const revBody    = document.getElementById('revBody');
  const revRating  = document.getElementById('revRating');
  const revRatingNum = document.getElementById('revRatingNum');
  const revSubmit  = document.getElementById('revSubmit');
  const revCancel  = document.getElementById('revCancel');

  // init values
  revGameNm.textContent = g.name || 'this game';
  revRating.value = (g.user_rating_for_user!=null) ? Number(g.user_rating_for_user) : 3.0;
  revRatingNum.textContent = Number(revRating.value).toFixed(1);
  revRating.addEventListener('input', ()=> revRatingNum.textContent = Number(revRating.value).toFixed(1));

  let page = 1, totalPages = 1, isLoading=false;

  function bootstrapCollapse(el, show){
    const inst = bootstrap.Collapse.getOrCreateInstance(el, {toggle:false});
    if (show) inst.show(); else inst.hide();
  }

  btnToggle.addEventListener('click', async ()=>{
    const shown = collapseEl.classList.contains('show');
    bootstrapCollapse(collapseEl, !shown);
    if (!shown && listEl.dataset.loaded !== '1') {
      page = 1; await loadReviews(true);
    }
    btnToggle.textContent = shown ? 'Show Reviews' : 'Hide Reviews';
  });

  btnWrite.addEventListener('click', ()=>{
    cardCreate.classList.toggle('d-none');
    if (!cardCreate.classList.contains('d-none')) revTitle.focus();
  });
  revCancel.addEventListener('click', ()=> cardCreate.classList.add('d-none'));

  async function loadReviews(reset=false){
    if (isLoading) return; isLoading=true;
    if (reset) { listEl.innerHTML=''; listEl.dataset.loaded='0'; metaEl.textContent='Loading…'; }
    moreBtn.classList.add('d-none');

    try{
      const payload = new URLSearchParams();
      payload.append('action', 'list');
      payload.append('rawg_id', String(g.rawg_id));
      payload.append('page', String(page));
      payload.append('pageSize', '6');

      const r = await fetch('/features/reviews.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: payload.toString() });
      const raw = await r.text();
      let j=null; try{ j=JSON.parse(raw) }catch{}
      if (!r.ok || !j || j.success===false) throw new Error(j?.message || raw || 'list failed');

      const items = j.items || [];
      totalPages = j.totalPages || 1;

      if (!items.length && page===1) {
        listEl.innerHTML = `<p class="text-muted mb-0">No reviews yet. Be the first!</p>`;
        metaEl.textContent = '';
      } else {
        const html = items.map(rw => renderReview(rw)).join('');
        if (page===1) listEl.innerHTML = html; else listEl.insertAdjacentHTML('beforeend', html);
        metaEl.textContent = (j.total!=null)
          ? `Showing ${Math.min(page*6, j.total)} of ${j.total} • Avg ${j.avg!=null?Number(j.avg).toFixed(1):'—'}`
          : `Page ${page}/${totalPages} • Avg ${j.avg!=null?Number(j.avg).toFixed(1):'—'}`;
      }

      if (page < totalPages) {
        moreBtn.classList.remove('d-none');
      }
      listEl.dataset.loaded = '1';

      
      if (j.avg != null) {
        const rows = document.querySelectorAll('#gdmBody .col-lg-4 .d-flex');
        rows.forEach(row=>{
          const label = row.firstChild?.textContent?.trim();
          if (label === 'Rating (avg)' || label === 'Rating') {
            row.lastChild.innerHTML = Number(j.avg).toFixed(1);
          }
        });
        // update card badge on grid too
        const card = document.querySelector(`[data-card-id="${g.rawg_id}"] .card-rating`);
        if (card) card.innerHTML = `<span class="badge rating-badge ms-2">${Number(j.avg).toFixed(1)}</span>`;
      }
    } catch(e){
      console.error('reviews list failed', e);
      if (page===1) { listEl.innerHTML = `<p class="text-danger">Failed to load reviews.</p>`; metaEl.textContent=''; }
    } finally {
      isLoading=false;
    }
  }

  moreBtn.addEventListener('click', async ()=>{
    if (page < totalPages) { page+=1; await loadReviews(false); }
  });

  function renderReview(rw){
    const stars = starBar(Number(rw.rating || 0));
    const title = rw.title ? `<div class="fw-semibold">${escapeHtml(rw.title)}</div>` : '';
    const user  = escapeHtml(rw.username || 'User');
    const when  = escapeHtml(rw.created_at || '');
    const body  = escapeHtml(rw.body || '');
    return `
      <div class="border-bottom py-2">
        <div class="d-flex justify-content-between align-items-center">
          <div>${title}<div class="small text-muted">by <strong>${user}</strong> • ${when}</div></div>
          <div>${stars}</div>
        </div>
        <div class="mt-2">${body.replace(/\n/g,'<br>')}</div>
      </div>`;
  }

  function starBar(v){
    const full = Math.floor(v), half = (v-full)>=0.5 ? 1 : 0, empty = 5-full-half;
    return `${'★'.repeat(full)}${half? '☆' : ''}${'✩'.repeat(empty)}`; // visually simple; you can reuse your SVG if preferred
  }
  function escapeHtml(s){ return String(s||'').replace(/[&<>"]/g, c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c])); }

  // submit review
  revSubmit.addEventListener('click', async ()=>{
    const title = (revTitle.value||'').trim();
    const body  = (revBody.value||'').trim();
    const rating= Number(revRating.value||0);
    if (!body) { alert('Please write a few words.'); return; }
    if (rating < 0.5 || rating > 5) { alert('Pick a rating 0.5–5.0'); return; }

    revSubmit.disabled = true;
    try{
      const payload = new URLSearchParams();
      payload.append('action','create');
      payload.append('rawg_id', String(g.rawg_id));
      payload.append('title', title);
      payload.append('body', body);
      payload.append('rating', String(rating));

      const r = await fetch('reviews.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: payload.toString() });
      const raw = await r.text();
      let j=null; try{ j=JSON.parse(raw) }catch{}
      if (!r.ok || !j || j.success===false) throw new Error(j?.message || raw || 'create failed');

      
      revTitle.value=''; revBody.value=''; cardCreate.classList.add('d-none');

     
      page=1; await loadReviews(true);

      
      const ratingNum = document.getElementById('ratingNum');
      if (ratingNum) ratingNum.textContent = rating.toFixed(1);

    } catch(e){
      console.error('review create failed', e);
      alert('Failed to post review.');
    } finally {
      revSubmit.disabled = false;
    }
  });
})();
        const actionsWrap = document.getElementById('detailActions');
        if (actionsWrap) {
          async function postToggle(url) {
            const payload = new URLSearchParams();
            payload.append('action', 'toggle');
            payload.append('rawg_id', String(g.rawg_id || ''));
            payload.append('liked', '1');
            payload.append('name', g.name || '');
            payload.append('image', g.background_image || g.background_image_additional || '');
            payload.append('released', g.released || '');
            const effective = (g.user_rating!=null?g.user_rating:g.rating);
            payload.append('rating', (effective!=null ? String(effective) : ''));

            const r = await fetch(url + '?_=' + Date.now(), {
              method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'},
              body: payload.toString()
            });
            const raw = await r.text();
            let j = null; try { j = JSON.parse(raw); } catch {}
            if (!r.ok || !j || j.success === false) throw new Error(raw || 'toggle failed');
          }

          const likeBtn = actionsWrap.querySelector('button[data-act="like"]');
          if (likeBtn) {
            let busy = false;
            likeBtn.addEventListener('click', async ()=> {
              if (busy) return; busy = true;
              likeBtn.classList.toggle('btn-outline-light'); likeBtn.classList.toggle('btn-light');
              try {
                await postToggle('likes.php');
                if (document.getElementById('liked-content').classList.contains('active-section') && typeof window.loadLikes === 'function') {
                  window.loadLikes();
                }
              } catch (e) {
                likeBtn.classList.toggle('btn-outline-light'); likeBtn.classList.toggle('btn-light');
                alert('Failed to update Like.');
              } finally { busy = false; }
            });
          }

          const wishBtn = actionsWrap.querySelector('button[data-act="wishlist"]');
          if (wishBtn) {
            let busy = false;
            wishBtn.addEventListener('click', async ()=> {
              if (busy) return; busy = true;
              wishBtn.classList.toggle('btn-outline-light'); wishBtn.classList.toggle('btn-light');
              try {
                await postToggle('wishlist.php');
                if (document.getElementById('wishlist-content').classList.contains('active-section') && typeof window.loadWishlist === 'function') {
                  window.loadWishlist();
                }
              } catch (e) {
                wishBtn.classList.toggle('btn-outline-light'); wishBtn.classList.toggle('btn-light');
                alert('Failed to update Wishlist.');
              } finally { busy = false; }
            });
          }

          const playedBtn = actionsWrap.querySelector('button[data-act="played"]');
          if (playedBtn) {
            let busy = false;
            playedBtn.addEventListener('click', async ()=> {
              if (busy) return; busy = true;
              playedBtn.classList.toggle('btn-outline-light'); playedBtn.classList.toggle('btn-light');
              try {
                await postToggle('played.php');
                if (document.getElementById('played-content').classList.contains('active-section') && typeof window.loadPlayed === 'function') {
                  window.loadPlayed();
                }
              } catch (e) {
                playedBtn.classList.toggle('btn-outline-light'); playedBtn.classList.toggle('btn-light');
                alert('Failed to update Played.');
              } finally { busy = false; }
            });
          }
        }

          // Create Forum
          const cfBtn = actionsWrap.querySelector('button[data-act="create-forum"]');
          if (cfBtn) {
            cfBtn.addEventListener('click', () => {
              // Prep data for the form
              const draft = {
                rawg_id: g.rawg_id,
                game_name: g.name || '',
                game_image: g.background_image || g.background_image_additional || ''
              };
              window.__forumDraft = draft;

              
              const goToForumCreate = () => {
                const nav = document.getElementById('sidebarNav');
                nav?.querySelector('a[data-target="forum-content"]')?.click();
                if (typeof window.forumShowCreate === 'function') {
                  window.forumShowCreate(draft);
                }
              };

              if (detailsModal) {
               
                const modalEl = document.getElementById('gameDetailsModal');
                if (modalEl) {
                  modalEl.addEventListener('hidden.bs.modal', goToForumCreate, { once: true });
                }
                detailsModal.hide();
              } else {
                goToForumCreate();
              }
            });
          }


        /* ===== Stars (true half fill, glow, save via rating.php) ===== */
        (function initStars(){
          const starsWrap = document.getElementById('stars');
          if (!starsWrap) return;if (!starsWrap) return;
          const ratingNum = document.getElementById('ratingNum');
          const starBtns  = [...starsWrap.querySelectorAll('button[data-star]')];

          
          let current = (g.user_rating_for_user!=null) ? Number(g.user_rating_for_user) : 0;

          function paintValue(v) {
            starBtns.forEach(btn => {
              const i = Number(btn.getAttribute('data-star')); // 1..5
              let fill = Math.max(0, Math.min(1, v - (i - 1))); // 0..1
              // snap visually to halves
              if (fill > 0 && fill < 1) fill = (fill >= 0.75 ? 1 : (fill >= 0.25 ? 0.5 : 0));
              const pct = (fill * 100).toFixed(0) + '%';
              btn.querySelector('.fill').style.width = pct; // 0%, 50%, 100%
            });
          }

          function computeValueFromMouse(ev) {
            const btn = ev.target.closest('button[data-star]');
            if (!btn) return current || 0.5;
            const rect = btn.getBoundingClientRect();
            const x = ev.clientX - rect.left;
            const starIdx = Number(btn.getAttribute('data-star')); 
            return Math.max(0.5, Math.min(5, starIdx - (x < rect.width/2 ? 0.5 : 0)));
          }

          paintValue(current);
          if (ratingNum) ratingNum.textContent = current ? current.toFixed(1) : '—';

          starsWrap.addEventListener('mousemove', (ev)=>{
            const hoverVal = computeValueFromMouse(ev);
            paintValue(hoverVal);
          });
          starsWrap.addEventListener('mouseleave', ()=>{
            paintValue(current);
          });
          starBtns.forEach(b=>{
            b.addEventListener('click', async (ev)=>{
              const chosen = computeValueFromMouse(ev);
              await saveRating(g.rawg_id, chosen);
            });
          });

          async function saveRating(rawgId, value) {
            try {
              const payload = new URLSearchParams();
              payload.append('rawg_id', String(rawgId));
              payload.append('value', String(value));
              const r = await fetch('rating.php?_=' + Date.now(), {
                method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: payload.toString()
              });
              const raw = await r.text();
              let j=null; try { j = JSON.parse(raw); } catch {}
              if (!r.ok || !j || j.success === false) throw new Error(j?.message || raw || 'Save failed');

              current = j.user_value ? Number(j.user_value) : value;
              paintValue(current);
              if (ratingNum) ratingNum.textContent = current.toFixed(1);

             
              if (j.avg != null) {
                const rows = document.querySelectorAll('#gdmBody .col-lg-4 .d-flex');
                rows.forEach(row=>{
                  const label = row.firstChild?.textContent?.trim();
                  if (label === 'Rating (avg)') row.lastChild.innerHTML = Number(j.avg).toFixed(1);
                });
              }

              
              const card = document.querySelector(`[data-card-id="${rawgId}"] .card-rating`);
              if (card) {
                const val = (j.avg != null ? Number(j.avg) : current);
                card.innerHTML = `<span class="badge rating-badge ms-2">${val.toFixed(1)}</span>`;
              }
            } catch (e) {
              console.error('rating save error', e);
              alert('Failed to save rating.');
            }
          }
        })();
      })
      .catch(err=>{ console.error('fetch game.php failed:', err); detailsBodyEl.innerHTML = `<div class="alert alert-danger">Network error.</div>`; });
  }

 
  btn.addEventListener('click', e => { e.preventDefault(); triggerScopeAndFetch(true); });
  qInput.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); triggerScopeAndFetch(true); } });
  loadMoreBtn.addEventListener('click', () => { if (page < totalPages) { page += 1; fetchGames(currentScope, { append: true }); } });

  
  window.fetchGames = () => triggerScopeAndFetch(false);
})();
