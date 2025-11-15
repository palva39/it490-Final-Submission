<?php
// --- Auth guard ---
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', 0);

require_once('/home/craig/git/it490-Final-Submission/path.inc');
require_once('/home/craig/git/it490-Final-Submission/get_host_info.inc');
require_once('/home/craig/git/it490-Final-Submission/rabbitMQLib.inc');

$sid = $_COOKIE['sid'] ?? '';
if ($sid === '') { header('Location: /auth/index.html'); exit; }

try {
  $client = new rabbitMQClient('testRabbitMQ.ini', 'loginServer');
  $res = $client->send_request(['type' => 'validate_session', 'sessionId' => $sid]);
  if (!is_array($res) || empty($res['success'])) {
    setcookie('sid','',time()-3600,'/');
    header('Location: /auth/index.html'); exit;
  }
  $username = htmlspecialchars($res['username'] ?? 'Player', ENT_QUOTES, 'UTF-8');
} catch (Throwable $e) {
  header('Location: /auth/index.html'); exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Home • GameHub</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link rel="stylesheet" href="/home_styling/home_looks.css">
</head>
<body>

<nav class="navbar navbar-dark sticky-top">
  <div class="container">
    <a id="brandLink" class="navbar-brand d-flex align-items-center gap-2" href="home.php?section=home">
      <svg width="26" height="26" viewBox="0 0 24 24" fill="none">
        <path d="M4 12l4-8 4 8-4 8-4-8Zm8 0 4-8 4 8-4 8-4-8Z" stroke="url(#g)" stroke-width="1.5"/>
        <defs><linearGradient id="g" x1="0" y1="0" x2="24" y2="24"><stop stop-color="#7c4dff"/><stop offset="1" stop-color="#00ffc6"/></linearGradient></defs>
      </svg>
      <span class="brand-text">GameHub</span>
    </a>
    <div class="d-flex align-items-center gap-3">
      <span class="text-secondary small d-none d-md-inline">Signed in as</span>
      <span class="fw-bold"><?php echo $username; ?></span>
      <a class="btn btn-sm btn-outline-light" href="/auth/logout.php">Logout</a>
    </div>
  </div>
</nav>

<div class="container-fluid">
  <aside class="sidebar">
    <ul class="list-unstyled sidebar-nav" id="sidebarNav">
      <li><a href="home.php?section=home" data-target="home-content" class="active"><i class="bi bi-house-door me-2"></i>Home</a></li>
      <li>
        <button class="d-flex align-items-center justify-content-between" type="button"
                data-bs-toggle="collapse" data-bs-target="#myGamesMenu" aria-expanded="false" aria-controls="myGamesMenu">
          <span><i class="bi bi-controller me-2"></i>My Games</span>
          <i class="bi bi-caret-down-fill"></i>
        </button>
        <div class="collapse submenu" id="myGamesMenu">
          <a href="#" data-target="liked-content"><i class="bi bi-heart-fill me-2"></i>Liked Games</a>
          <a href="#" data-target="wishlist-content"><i class="bi bi-bookmark-heart me-2"></i>Wishlist</a>
          <a href="#" data-target="played-content"><i class="bi bi-check2-circle me-2"></i>Played Games</a>
        </div>
      </li>
      <li><a href="#" data-target="forum-content"><i class="bi bi-chat-dots me-2"></i>Forum</a></li>
      <li><a href="#" data-target="recommendations-content"><i class="bi bi-lightbulb me-2"></i>Recommendations</a></li>
      <li>
      <a id="nav-notifications-link" href="#" data-target="notifications-content" class="d-flex align-items-center justify-content-between">
        <span><i class="bi bi-bell me-2"></i>Notifications</span>
        <span id="notif-badge" class="notif-badge" style="display:none">0</span>
      </a>
</li>

    </ul>
  </aside>
</div>

<main class="main-content">
  <!-- Home -->
  <section id="home-content" class="content-display active-section">
    <section class="hero py-5 text-center">
      <div class="container">
        <h1 class="display-5 fw-bold mb-2">Find your next adventure</h1>
        <p class="lead text-secondary mb-0">Search the library and explore what everyone’s playing.</p>
      </div>
    </section>

    <div class="container my-4">
      <div class="search-wrap mb-4">
        <div class="row g-2">
          <div class="col-12 col-lg-9">
            <input id="q" type="text" class="form-control form-control-lg" placeholder="Search games (e.g., Elden Ring, Hades, GTA V)">
          </div>
          <div class="col-12 col-lg-3 d-grid">
            <button id="searchBtn" class="btn btn-dark btn-lg">Search</button>
          </div>
        </div>
      </div>
      <div id="status" class="alert d-none">Loading…</div>
      <div id="results" class="row g-4"></div>
      <div class="mt-4 text-center">
        <button id="loadMoreBtn" class="btn btn-dark btn-lg d-none">Load more</button>
      </div>
    </div>
  </section>

  <!-- Liked -->
  <section id="liked-content" class="content-display">
    <div class="container my-4">
      <h2>Liked Games</h2>
      <div id="likedStatus" class="alert d-none">Loading…</div>
      <div id="likedList" class="row g-4">
        <div class="col-12"><div class="alert">No liked games yet.</div></div>
      </div>
    </div>
  </section>

  <!-- Wishlist -->
  <section id="wishlist-content" class="content-display">
    <div class="container my-4">
      <h2>Wishlist</h2>
      <div id="wishlistStatus" class="alert d-none">Loading…</div>
      <div id="wishlistList" class="row g-4">
        <div class="col-12"><div class="alert">No wishlist items yet.</div></div>
      </div>
    </div>
  </section>

  <!-- Played -->
  <section id="played-content" class="content-display">
    <div class="container my-4">
      <h2>Played Games</h2>
      <div id="playedStatus" class="alert d-none">Loading…</div>
      <div id="playedList" class="row g-4">
        <div class="col-12"><div class="alert">No played games yet.</div></div>
      </div>
    </div>
  </section>

  <!-- Reviews -->
  <section id="reviews-content" class="content-display">
    <div class="container my-4">
      <h2>Reviews</h2>
      <p class="lead text-secondary">Your latest reviews here.</p>
    </div>
  </section>

  <!-- Forum -->
<section id="forum-content" class="content-display">
  <div class="container my-4">

    <div class="d-flex align-items-center justify-content-between mb-3">
      <h2 class="mb-0">Forums</h2>
    </div>

    <!-- Status line -->
    <div id="forumStatus" class="alert d-none">Loading…</div>

    <!-- View: Create Forum -->
    <div id="forumCreateView" class="card mb-4 d-none" style="background:#12121b;border:1px solid rgba(124,77,255,.25)">
      <div class="card-body">
        <h5 class="card-title mb-3">Create a Forum</h5>

        <div id="forumCreateGamePreview" class="d-flex align-items-center mb-3 d-none">
          <img id="forumCreateGameImg" src="" alt="" style="width:96px;height:54px;object-fit:cover;border-radius:8px;border:1px solid rgba(124,77,255,.25);margin-right:12px;">
          <div>
            <div class="small text-secondary">Game</div>
            <div id="forumCreateGameName" class="fw-semibold"></div>
          </div>
        </div>

        <form id="forumCreateForm">
          <input type="hidden" id="forumGameId" name="rawg_id" value="">
          <div class="mb-3">
            <label class="form-label">Forum title</label>
            <input type="text" id="forumTitle" name="title" class="form-control" placeholder="e.g. Strategies, Builds, Tips">
          </div>
          <div class="mb-3">
            <label class="form-label">Forum description</label>
            <textarea id="forumDesc" name="description" class="form-control" rows="3" placeholder="What’s this forum about?"></textarea>
          </div>
          <div class="d-flex gap-2">
            <button class="btn btn-light" type="submit">Create</button>
            <button class="btn btn-outline-light" type="button" id="forumCancelCreate">Cancel</button>
          </div>
        </form>
      </div>
    </div>

    <!-- View: Forum List -->
    <div id="forumListView">
      <div id="forumList" class="row g-4"></div>
      <div class="text-center mt-3">
        <button id="forumLoadMore" class="btn btn-dark d-none">Load more</button>
      </div>
    </div>

    <!-- View: Thread -->
    <div id="forumThreadView" class="d-none">
      <button id="forumBackToList" class="btn btn-sm btn-outline-light mb-3">&larr; Back to Forums</button>
      <div id="forumThreadHeader" class="card mb-3" style="background:#12121b;border:1px solid rgba(124,77,255,.25)">
        <div class="card-body d-flex gap-3">
          <img id="forumThreadGameImg" src="" alt="" style="width:128px;height:72px;object-fit:cover;border-radius:10px;border:1px solid rgba(124,77,255,.25);">
          <div>
            <div class="small text-secondary" id="forumThreadGameTitle"></div>
            <h5 id="forumThreadTitle" class="mb-1"></h5>
            <div id="forumThreadDesc" class="text-secondary"></div>
          </div>
        </div>
      </div>

      <div id="forumThreadMessages" class="list-group mb-3" style="border-radius:10px;overflow:hidden;"></div>

      <form id="forumMessageForm" class="card" style="background:#12121b;border:1px solid rgba(124,77,255,.25)">
        <div class="card-body">
          <label class="form-label">Add a message</label>
          <textarea id="forumMessageText" class="form-control mb-2" rows="3" placeholder="Write something helpful..."></textarea>
          <button type="submit" class="btn btn-light">Post</button>
        </div>
      </form>
    </div>

  </div>
</section>

  <!-- Recommendations -->
<section id="recommendations-content" class="content-display">
  <div class="container my-4">
    <div class="d-flex align-items-center justify-content-between">
      <div>
        <h2 class="mb-0">Recommendations</h2>
        <p class="lead text-secondary mb-0">Personalized game suggestions.</p>
      </div>
      <button id="recsRefreshBtn" class="btn btn-dark">Refresh</button>
    </div>

    <div id="recsStatus" class="alert d-none mt-3">Loading…</div>
    <div id="recsList" class="row g-4 mt-1"></div>
  </div>
</section>

    <!-- Notifications -->
<section id="notifications-content" class="content-display">
  <div class="container my-4">
    <h2>Notifications</h2>
    <p class="lead text-secondary">All your recent activity and alerts.</p>

    <!-- Refresh and Delete All Buttons -->
    <div class="mb-3">
      <button id="refresh-notifications" class="btn btn-primary me-2">
        Refresh Notifications
      </button>
      <button id="delete-all-notifications" class="btn btn-danger">
        Delete All
      </button>
    </div>

    <div id="notifications-list">
      <p class="text-muted">Loading notifications...</p>
    </div>
  </div>
</section>
</main>

<!-- Game Details Modal -->
<div class="modal fade" id="gameDetailsModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content" style="background:#12121b;color:#e8e8ff;border:1px solid rgba(124,77,255,.25)">
      <div class="modal-header">
        <h5 class="modal-title" id="gdmTitle">Game Details</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div id="gdmBody">Loading…</div>
      </div>
    </div>
  </div>
</div>

<footer class="text-center py-3 mt-4">
  <div class="container">
    <small>&copy; 2025 GameHub • All Rights Reserved</small>
  </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>


<!-- Notifications -->
<script src="/assets/js/notifications/notifications-ui.js"></script>
<script src="/assets/js/notifications/notifications-badge-refresh.js"></script>

<!-- Forums -->
<script src="/assets/js/forums/forums.js"></script>
<script src="/assets/js/forums/forum-comments.local.js.php"></script>

<!-- Feature sections -->
<script src="/assets/js/recommendations/recommendations.js"></script>
<script src="/assets/js/likes/likes-list.js"></script>
<script src="/assets/js/wishlist/wishlist-list.js"></script>
<script src="/assets/js/played/played-list.js"></script>

<!-- Home: search + recent feed (+ details modal, reviews, like/wishlist/played actions) -->
<script src="/assets/js/home/search-and-details.js"></script>

<!-- Router (load this last so it can call the above loaders) -->
<script src="/assets/js/router/sidebar-router.js"></script>

</body>
</html>