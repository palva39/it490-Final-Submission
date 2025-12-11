/* Sidebar routing + single-init */
document.addEventListener('DOMContentLoaded', () => {
  const nav = document.getElementById('sidebarNav');
  const sections = document.querySelectorAll('.content-display');
  const homeSectionId = 'home-content';

  function showSection(targetId) {
    sections.forEach(sec => sec.classList.remove('active-section'));
    const targetSection = document.getElementById(targetId);
    if (targetSection) targetSection.classList.add('active-section');

    nav.querySelectorAll('a').forEach(link => link.classList.remove('active'));
    const activeLink = nav.querySelector(`a[data-target="${targetId}"]`);
    if (activeLink) activeLink.classList.add('active');

    const myGamesMenu = document.getElementById('myGamesMenu');
    const isMyGamesChild = ['liked-content','wishlist-content','played-content'].includes(targetId);
    if (myGamesMenu) {
      const bsCollapse = bootstrap.Collapse.getOrCreateInstance(myGamesMenu, {toggle:false});
      if (isMyGamesChild) bsCollapse.show(); else bsCollapse.hide();
    }

    localStorage.setItem('activeSection', targetId);
    window.scrollTo({ top: 0, behavior: 'instant' });

    if (targetId === homeSectionId && typeof window.fetchGames === 'function') window.fetchGames();
    if (targetId === 'liked-content' && typeof window.loadLikes === 'function') window.loadLikes();
    if (targetId === 'wishlist-content' && typeof window.loadWishlist === 'function') window.loadWishlist();
    if (targetId === 'played-content' && typeof window.loadPlayed === 'function') window.loadPlayed();
    if (targetId === 'recommendations-content' && typeof window.loadRecommendations === 'function') window.loadRecommendations();
  }

  function sectionFromQuery() {
    const m = location.search.match(/[?&]section=([^&]+)/i);
    if (!m) return null;
    const s = decodeURIComponent(m[1] || '').toLowerCase();
    if (s === 'home') return homeSectionId;
    if (s === 'liked') return 'liked-content';
    if (s === 'wishlist') return 'wishlist-content';
    if (s === 'played') return 'played-content';
    if (s === 'forum') return 'forum-content';
    if (s === 'reco' || s === 'recommendations') return 'recommendations-content';
    if (s === 'notifications') return 'notifications-content';
    return null;
  }

  // Sidebar links
  nav.querySelectorAll('a[data-target]').forEach(link => {
    link.addEventListener('click', e => {
      e.preventDefault();
      const targetId = link.dataset.target;
      if (targetId) showSection(targetId);
      if (link.href && /section=home/i.test(link.href)) {
        history.replaceState(null, '', 'home.php?section=home');
      }
    });
  });

  // Initial section
  const urlSection = sectionFromQuery();
  const saved = localStorage.getItem('activeSection');
  if (urlSection && document.getElementById(urlSection)) showSection(urlSection);
  else if (saved && document.getElementById(saved)) showSection(saved);
  else showSection(homeSectionId);
});
/* Sidebar routing + single-init */
document.addEventListener('DOMContentLoaded', () => {
  const nav = document.getElementById('sidebarNav');
  const sections = document.querySelectorAll('.content-display');
  const homeSectionId = 'home-content';

  function showSection(targetId) {
    sections.forEach(sec => sec.classList.remove('active-section'));
    const targetSection = document.getElementById(targetId);
    if (targetSection) targetSection.classList.add('active-section');

    nav.querySelectorAll('a').forEach(link => link.classList.remove('active'));
    const activeLink = nav.querySelector(`a[data-target="${targetId}"]`);
    if (activeLink) activeLink.classList.add('active');

    const myGamesMenu = document.getElementById('myGamesMenu');
    const isMyGamesChild = ['liked-content','wishlist-content','played-content'].includes(targetId);
    if (myGamesMenu) {
      const bsCollapse = bootstrap.Collapse.getOrCreateInstance(myGamesMenu, {toggle:false});
      if (isMyGamesChild) bsCollapse.show(); else bsCollapse.hide();
    }

    localStorage.setItem('activeSection', targetId);
    window.scrollTo({ top: 0, behavior: 'instant' });

    if (targetId === homeSectionId && typeof window.fetchGames === 'function') window.fetchGames();
    if (targetId === 'liked-content' && typeof window.loadLikes === 'function') window.loadLikes();
    if (targetId === 'wishlist-content' && typeof window.loadWishlist === 'function') window.loadWishlist();
    if (targetId === 'played-content' && typeof window.loadPlayed === 'function') window.loadPlayed();
    if (targetId === 'recommendations-content' && typeof window.loadRecommendations === 'function') window.loadRecommendations();
  }

  function sectionFromQuery() {
    const m = location.search.match(/[?&]section=([^&]+)/i);
    if (!m) return null;
    const s = decodeURIComponent(m[1] || '').toLowerCase();
    if (s === 'home') return homeSectionId;
    if (s === 'liked') return 'liked-content';
    if (s === 'wishlist') return 'wishlist-content';
    if (s === 'played') return 'played-content';
    if (s === 'forum') return 'forum-content';
    if (s === 'reco' || s === 'recommendations') return 'recommendations-content';
    if (s === 'notifications') return 'notifications-content';
    return null;
  }

  // Sidebar links
  nav.querySelectorAll('a[data-target]').forEach(link => {
    link.addEventListener('click', e => {
      e.preventDefault();
      const targetId = link.dataset.target;
      if (targetId) showSection(targetId);
      if (link.href && /section=home/i.test(link.href)) {
        history.replaceState(null, '', 'home.php?section=home');
      }
    });
  });

  // Initial section
  const urlSection = sectionFromQuery();
  const saved = localStorage.getItem('activeSection');
  if (urlSection && document.getElementById(urlSection)) showSection(urlSection);
  else if (saved && document.getElementById(saved)) showSection(saved);
  else showSection(homeSectionId);
});
