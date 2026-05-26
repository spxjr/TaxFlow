<?php
/**
 * header.php — TaxFlow CRM
 *
 * Set before including:
 *   $page_title  = 'Dashboard';    // page title + topbar heading
 *   $active_nav  = 'dashboard';    // dashboard|season|clients|pipeline|
 *                                  // returns|invoices|documents|tasks|
 *                                  // reports|analytics
 *   $topbar_btn  = '+ New Client'; // optional CTA label (default: '+ New Client')
 *   $topbar_href = '#';            // optional CTA href   (default: '#')
 *
 * Then:
 *   include 'header.php';
 *   // ... page content ...
 *   include 'footer.php';
 */

if (!isset($page_title))  $page_title  = 'TaxFlow CRM';
if (!isset($active_nav))  $active_nav  = '';
if (!isset($topbar_btn))  $topbar_btn  = '+ New Client';
if (!isset($topbar_href)) $topbar_href = '#';

$nav_sections = array(
  'dashboard' => 'Overview', 'season'    => 'Overview',
  'clients'   => 'Clients',  'pipeline'  => 'Clients',  'returns' => 'Clients',
  'invoices'  => 'Work',     'documents' => 'Work',      'tasks'   => 'Work',
  'reports'   => 'Insights', 'analytics' => 'Insights',
);
$section_label = isset($nav_sections[$active_nav]) ? $nav_sections[$active_nav] : '';

/* ── Live sidebar stats (cached in request scope) ── */
try {
    $header_stats = db()->query("
        SELECT
          (SELECT COUNT(*) FROM tax_returns WHERE tax_year=2025 AND status='filed')     AS filed,
          (SELECT COUNT(*) FROM tax_returns WHERE tax_year=2025)                        AS total,
          (SELECT DATEDIFF('2026-04-15', CURDATE()))                                    AS days_left,
          (SELECT COUNT(*) FROM tasks WHERE status IN('open','in_progress') AND status!='cancelled') AS open_tasks,
          (SELECT COUNT(*) FROM tax_returns WHERE tax_year=2025 AND status NOT IN('filed','archived','extension')) AS pending
    ")->fetch();
} catch (Exception $e) {
    $header_stats = ['filed'=>0,'total'=>0,'days_left'=>17,'open_tasks'=>0,'pending'=>0];
}

$filed_pct   = $header_stats['total'] > 0 ? round($header_stats['filed'] / $header_stats['total'] * 100) : 0;
$deadline_pct = max(0, min(100, 100 - max(0, (int)$header_stats['days_left']) / 30 * 100));
$open_tasks  = (int)$header_stats['open_tasks'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<title><?php echo htmlspecialchars($page_title); ?> &mdash; TaxFlow CRM</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Mono:wght@300;400;500&family=Instrument+Sans:wght@400;500;600&display=swap">
<link rel="stylesheet" href="taxflow.css">
</head>
<body>

<!-- ════════════════════════════════════ SIDEBAR -->
<aside class="sidebar" id="sidebar">

  <div class="logo">
    <div class="logo-mark">TaxFlow</div>
    <div class="logo-sub">CRM Platform</div>
  </div>

  <nav class="nav">

    <div class="nav-section">
      <div class="nav-label">Overview</div>
      <a class="nav-item <?php echo ($active_nav==='dashboard') ? 'active' : ''; ?>" href="dashboard.php">
        <svg class="icon" viewBox="0 0 16 16" fill="currentColor"><path d="M2 2h5v5H2V2zm0 7h5v5H2V9zm7-7h5v5H9V2zm0 7h5v5H9V9z"/></svg>
        Dashboard
      </a>
      <a class="nav-item <?php echo ($active_nav==='season') ? 'active' : ''; ?>" href="season.php">
        <svg class="icon" viewBox="0 0 16 16" fill="currentColor"><path d="M8 1a7 7 0 100 14A7 7 0 008 1zm0 2a5 5 0 110 10A5 5 0 018 3zm.5 2.5h-1v4l3.5 2.1.5-.8-3-1.8V5.5z"/></svg>
        Tax Season
        <span class="nav-badge">APR</span>
      </a>
    </div>

    <div class="nav-section">
      <div class="nav-label">Clients</div>
      <a class="nav-item <?php echo ($active_nav==='clients') ? 'active' : ''; ?>" href="clients.php">
        <svg class="icon" viewBox="0 0 16 16" fill="currentColor"><path d="M8 8a3 3 0 100-6 3 3 0 000 6zm-5 5a5 5 0 0110 0H3z"/></svg>
        All Clients
      </a>
      <a class="nav-item <?php echo ($active_nav==='pipeline') ? 'active' : ''; ?>" href="pipeline.php">
        <svg class="icon" viewBox="0 0 16 16" fill="currentColor"><path d="M1 4a1 1 0 011-1h4a1 1 0 010 2H2a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 010 2H2a1 1 0 01-1-1zm0 4a1 1 0 011-1h8a1 1 0 010 2H2a1 1 0 01-1-1z"/></svg>
        Pipeline
      </a>
      <a class="nav-item <?php echo ($active_nav==='returns') ? 'active' : ''; ?>" href="returns.php">
        <svg class="icon" viewBox="0 0 16 16" fill="currentColor"><path d="M3 2a1 1 0 00-1 1v10a1 1 0 001 1h10a1 1 0 001-1V6l-4-4H3zm0 1h6v3h3v7H3V3zm4 4v1h3v-1H7zm0 2v1h3v-1H7zm0 2v1h2v-1H7z"/></svg>
        Returns
        <span class="nav-badge">14</span>
      </a>
    </div>

    <div class="nav-section">
      <div class="nav-label">Work</div>
      <a class="nav-item <?php echo ($active_nav==='invoices') ? 'active' : ''; ?>" href="invoices.php">
        <svg class="icon" viewBox="0 0 16 16" fill="currentColor"><path d="M2 3a1 1 0 011-1h10a1 1 0 011 1v1H2V3zm0 3h12v7a1 1 0 01-1 1H3a1 1 0 01-1-1V6zm3 2v1h2V8H5zm0 2v1h2v-1H5zm3-2v1h3V8H8zm0 2v1h3v-1H8z"/></svg>
        Invoices
      </a>
      <a class="nav-item <?php echo ($active_nav==='documents') ? 'active' : ''; ?>" href="documents.php">
        <svg class="icon" viewBox="0 0 16 16" fill="currentColor"><path d="M4 4a2 2 0 00-2 2v6a2 2 0 002 2h8a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a3 3 0 00-6 0v1H4zm2-1a1 1 0 012 0v1H6V3z"/></svg>
        Documents
      </a>
      <a class="nav-item <?php echo ($active_nav==='tasks') ? 'active' : ''; ?>" href="tasks.php">
        <svg class="icon" viewBox="0 0 16 16" fill="currentColor"><path d="M2 4a2 2 0 012-2h8a2 2 0 012 2v8a2 2 0 01-2 2H4a2 2 0 01-2-2V4zm2 1v6h8V5H4zm1 1h2v1H5V6zm0 2h2v1H5V8zm3-2h2v1H8V6zm0 2h2v1H8V8z"/></svg>
        Tasks
        <?php if ($open_tasks > 0): ?>
        <span class="nav-badge" style="background:#c8922a;"><?php echo $open_tasks; ?></span>
        <?php endif; ?>
      </a>
    </div>

    <div class="nav-section">
      <div class="nav-label">Insights</div>
      <a class="nav-item <?php echo ($active_nav==='reports') ? 'active' : ''; ?>" href="reports.php">
        <svg class="icon" viewBox="0 0 16 16" fill="currentColor"><path d="M2 10V5l4-3 4 3 4-3v5l-4 3-4-3-4 3zm0 1l4 3 4-3 4 3v1H2v-1z"/></svg>
        Reports
      </a>
      <a class="nav-item <?php echo ($active_nav==='analytics') ? 'active' : ''; ?>" href="analytics.php">
        <svg class="icon" viewBox="0 0 16 16" fill="currentColor"><path d="M8 1v6H2a6 6 0 106-6zm1 0a6 6 0 015.1 8.9L9 7V1z"/></svg>
        Analytics
      </a>
    </div>

  </nav>

  <div class="sidebar-footer">

    <!-- Filing deadline progress (live) -->
    <div class="sidebar-alert">
      <div class="sidebar-alert-label">
        <?php if ((int)$header_stats['days_left'] > 0): ?>
        &#9888; <?php echo (int)$header_stats['days_left']; ?> days to Apr 15
        <?php elseif ((int)$header_stats['days_left'] === 0): ?>
        &#9888; Deadline is today!
        <?php else: ?>
        &#9888; Deadline passed
        <?php endif; ?>
      </div>
      <div class="sidebar-alert-bar">
        <div class="sidebar-alert-fill" style="width:<?php echo $filed_pct; ?>%"></div>
      </div>
      <div class="sidebar-alert-text">
        <?php echo (int)$header_stats['filed']; ?> of <?php echo (int)$header_stats['total']; ?> returns filed
      </div>
    </div>

    <!-- User card with dropdown -->
    <div class="user-card" id="user-card-btn" role="button" tabindex="0" aria-expanded="false">
      <div class="avatar">MR</div>
      <div class="user-info">
        <div class="user-name">Margaret Ross</div>
        <div class="user-role">Senior CPA</div>
      </div>
      <svg class="user-chevron" width="10" height="10" viewBox="0 0 10 6" fill="none">
        <path d="M1 1l4 4 4-4" stroke="rgba(255,255,255,0.35)" stroke-width="1.5" stroke-linecap="round"/>
      </svg>
    </div>

    <div class="user-menu" id="user-menu" aria-hidden="true">
      <a class="user-menu-item" href="#">
        <svg width="13" height="13" viewBox="0 0 16 16" fill="currentColor"><path d="M8 8a3 3 0 100-6 3 3 0 000 6zm-5 5a5 5 0 0110 0H3z"/></svg>
        My Profile
      </a>
      <a class="user-menu-item" href="#">
        <svg width="13" height="13" viewBox="0 0 16 16" fill="currentColor"><path d="M8 2a6 6 0 100 12A6 6 0 008 2zm0 2a4 4 0 110 8 4 4 0 010-8zm0 1a1 1 0 100 2 1 1 0 000-2zm-.5 3h1v3h-1V8z"/></svg>
        Firm Settings
      </a>
      <a class="user-menu-item" href="#">
        <svg width="13" height="13" viewBox="0 0 16 16" fill="currentColor"><path d="M8 1a7 7 0 100 14A7 7 0 008 1zm0 2a5 5 0 110 10A5 5 0 018 3zm-.5 2v4l3 1.5.5-.9-2.5-1.2V5h-1z"/></svg>
        Help &amp; Support
      </a>
      <div class="user-menu-divider"></div>
      <a class="user-menu-item user-menu-danger" href="#">
        <svg width="13" height="13" viewBox="0 0 16 16" fill="currentColor"><path d="M10 2H6a1 1 0 00-1 1v1H2v1h1l1 9h8l1-9h1V4h-3V3a1 1 0 00-1-1zm-4 2V3h4v1H6z"/></svg>
        Sign Out
      </a>
    </div>

  </div>
</aside>

<div class="sidebar-overlay" id="overlay"></div>

<!-- ════════════════════════════════════ MAIN -->
<div class="main">

  <!-- Notification panel -->
  <div class="notif-panel" id="notif-panel" aria-hidden="true">
    <div class="notif-panel-header">
      <span class="notif-panel-title">Notifications</span>
      <button class="notif-mark-all" id="mark-all-read">Mark all read</button>
    </div>
    <div class="notif-list">
      <div class="notif-row unread">
        <div class="notif-icon" style="background:#f5dbd4;">&#9888;</div>
        <div class="notif-body">
          <div class="notif-text"><strong>Bright Arch Studios</strong> missing W-2 &amp; K-1 forms</div>
          <div class="notif-time">2 hours ago</div>
        </div>
        <div class="notif-unread-dot"></div>
      </div>
      <div class="notif-row unread">
        <div class="notif-icon" style="background:#daeade;">&#128228;</div>
        <div class="notif-body">
          <div class="notif-text"><strong>P. Okonkwo</strong> 1040 e-filed &amp; accepted</div>
          <div class="notif-time">3 hours ago</div>
        </div>
        <div class="notif-unread-dot"></div>
      </div>
      <div class="notif-row unread">
        <div class="notif-icon" style="background:#daeade;">&#128179;</div>
        <div class="notif-body">
          <div class="notif-text">Invoice #1042 paid by <strong>M. Fontaine</strong> &middot; $850</div>
          <div class="notif-time">Yesterday</div>
        </div>
        <div class="notif-unread-dot"></div>
      </div>
      <div class="notif-row">
        <div class="notif-icon" style="background:#dce6f5;">&#128172;</div>
        <div class="notif-body">
          <div class="notif-text"><strong>D. Reinholt</strong> message re: FBAR filing</div>
          <div class="notif-time">Yesterday</div>
        </div>
      </div>
      <div class="notif-row">
        <div class="notif-icon" style="background:#f5e6c8;">&#128206;</div>
        <div class="notif-body">
          <div class="notif-text"><strong>Henderson &amp; Walsh</strong> uploaded 3 documents</div>
          <div class="notif-time">Apr 26</div>
        </div>
      </div>
    </div>
    <a href="#" class="notif-panel-footer">View all notifications &rarr;</a>
  </div>

  <!-- TOPBAR -->
  <header class="topbar">

    <button class="hamburger" id="hamburger" aria-label="Toggle menu">
      <span></span><span></span><span></span>
    </button>

    <div class="topbar-title">
      <?php if ($section_label): ?>
        <span class="topbar-breadcrumb"><?php echo htmlspecialchars($section_label); ?></span>
      <?php endif; ?>
      <?php echo htmlspecialchars($page_title); ?>
      <span class="topbar-date"><?php echo strtoupper(date('D, M j, Y')); ?></span>
    </div>

    <div class="search-bar" id="topbar-search">
      <svg width="13" height="13" viewBox="0 0 16 16" fill="#8a8070"><path d="M7 2a5 5 0 100 10A5 5 0 007 2zm6.7 10.3l-3-3a6 6 0 10-1.4 1.4l3 3a1 1 0 001.4-1.4z"/></svg>
      <input type="text" placeholder="Search clients, returns, docs..." id="global-search" autocomplete="off" role="combobox" aria-expanded="false" aria-haspopup="listbox" aria-controls="search-dropdown">
      <span class="search-shortcut" id="search-shortcut">&#8984;K</span>
      <!-- Search results dropdown -->
      <div class="search-dropdown" id="search-dropdown" role="listbox" aria-label="Search results"></div>
    </div>

    <!-- Mobile search icon (hidden on desktop) -->
    <button class="topbar-icon-btn" id="mobile-search-btn" aria-label="Search">
      <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor"><path d="M7 2a5 5 0 100 10A5 5 0 007 2zm6.7 10.3l-3-3a6 6 0 10-1.4 1.4l3 3a1 1 0 001.4-1.4z"/></svg>
    </button>

    <button class="notif-btn" id="notif-btn" aria-label="Notifications">
      <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor"><path d="M8 1a5 5 0 00-5 5v2l-1.5 2.5A1 1 0 002.4 12H6a2 2 0 004 0h3.6a1 1 0 00.9-1.5L13 8V6a5 5 0 00-5-5z"/></svg>
      <div class="notif-dot" id="notif-dot"></div>
    </button>

    <a href="<?php echo htmlspecialchars($topbar_href); ?>" class="topbar-btn btn-primary">
      <?php echo htmlspecialchars($topbar_btn); ?>
    </a>

  </header>

  <!-- Mobile search drawer (hidden, toggled by JS) -->
  <div class="mobile-search-bar" id="mobile-search-bar">
    <svg width="14" height="14" viewBox="0 0 16 16" fill="#8a8070"><path d="M7 2a5 5 0 100 10A5 5 0 007 2zm6.7 10.3l-3-3a6 6 0 10-1.4 1.4l3 3a1 1 0 001.4-1.4z"/></svg>
    <input type="text" id="mobile-search-input" placeholder="Search clients, returns, docs..." autocomplete="off">
    <button id="mobile-search-close" aria-label="Close search">&#10005;</button>
    <!-- Mobile search results dropdown -->
    <div class="mobile-search-dropdown" id="mobile-search-dropdown" role="listbox"></div>
  </div>

  <div class="content">
