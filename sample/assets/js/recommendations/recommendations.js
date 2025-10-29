/* ===== Recommendations loader/render ===== */
(function(){
  const listEl   = document.getElementById('recsList');
  const statusEl = document.getElementById('recsStatus');
  const refresh  = document.getElementById('recsRefreshBtn');

  function setStatus(text, type) {
    statusEl.className = 'alert';
    statusEl.classList.add(type ? `alert-${type}` : 'alert-info');
    statusEl.textContent = text;
    statusEl.classList.remove('d-none');
  }
  function clearStatus(){ statusEl.classList.add('d-none'); }

 
  const toName = x => (typeof x === 'string' ? x : (x?.name || ''));
  function prefRating(g) {
    if (g.user_rating != null && g.user_rating !== '') return Number(g.user_rating);
    if (g.userRating != null && g.userRating !== '') return Number(g.userRating);
    if (g.rating != null && g.rating !== '') return Number(g.rating);
    return null;
  }

  async function loadRecommendations() {
    if (!listEl) return;
    setStatus('Finding games you might like…');
    listEl.innerHTML = '';

    try {
      
      const r = await fetch('/features/recommendations.php?_=' + Date.now(), { method: 'GET', credentials: 'include' });
      const raw = await r.text();
      let j = null; try { j = JSON.parse(raw); } catch { j = null; }

      if (!r.ok || !j) { console.error('recs non-JSON/HTTP error:', raw); setStatus('Failed to load recommendations.', 'danger'); return; }
      if (j.success === false) { setStatus(j.message || 'Failed to load recommendations.', 'danger'); return; }

      const items = j.items || [];
      clearStatus();
      render(items);
      if (!items.length) {
        listEl.innerHTML = '<div class="col-12"><div class="alert">No recommendations yet — like or wishlist a few games first!</div></div>';
      }
    } catch (e) {
      console.error('recs network error', e);
      setStatus('Network error loading recommendations.', 'danger');
    }
  }

  function render(items) {
    const html = items.map(g => {
      const id   = g.rawg_id ?? g.id ?? '';
      const img  = g.background_image || g.image || '';
      const name = g.name || 'Untitled';
      const rating = prefRating(g);
      const released = g.released ? new Date(g.released).toLocaleDateString() : 'Unknown';
      const platforms = (g.platforms || []).slice(0,4).map(p => `<span class="badge badge-chip me-1 mb-1">${toName(p)}</span>`).join('');
      const genres    = (g.genres || []).slice(0,3).map(gn => `<span class="badge badge-chip me-1 mb-1">${toName(gn)}</span>`).join('');
      return `
        <div class="col-12 col-sm-6 col-lg-4" data-card-id="${id}">
          <div class="game-card h-100">
            ${img ? `<img src="${img}" class="game-img w-100" alt="${name}">` : ''}
            <div class="p-3">
              <div class="d-flex justify-content-between align-items-start mb-1">
                <h5 class="mb-0">${name}</h5>
                <span class="card-rating">
                  ${rating != null ? `<span class="badge rating-badge ms-2">${rating.toFixed(1)}</span>` : ''}
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

    listEl.innerHTML = html || '';
    listEl.querySelectorAll('[data-action="details"]').forEach(el=>{
      el.onclick = (e)=>{ e.preventDefault(); const id = parseInt(el.dataset.id || '0', 10); if (!id) return; window.openDetails(id); };
    });
  }

  window.loadRecommendations = loadRecommendations;
  refresh?.addEventListener('click', loadRecommendations);
})();
