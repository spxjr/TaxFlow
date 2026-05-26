  </div><!-- /.content -->
</div><!-- /.main -->

<!-- ════════════════════════════════════ MOBILE BOTTOM NAV -->
<nav class="mobile-bottom-nav">
  <div class="mobile-nav-items">
    <a class="mobile-nav-item <?php echo (isset($active_nav) && $active_nav==='dashboard') ? 'active' : ''; ?>" href="dashboard.php">
      <svg viewBox="0 0 16 16" fill="currentColor"><path d="M2 2h5v5H2V2zm0 7h5v5H2V9zm7-7h5v5H9V2zm0 7h5v5H9V9z"/></svg>
      Home
    </a>
    <a class="mobile-nav-item <?php echo (isset($active_nav) && $active_nav==='clients') ? 'active' : ''; ?>" href="clients.php">
      <svg viewBox="0 0 16 16" fill="currentColor"><path d="M8 8a3 3 0 100-6 3 3 0 000 6zm-5 5a5 5 0 0110 0H3z"/></svg>
      Clients
    </a>
    <a class="mobile-nav-item <?php echo (isset($active_nav) && $active_nav==='returns') ? 'active' : ''; ?>" href="returns.php">
      <svg viewBox="0 0 16 16" fill="currentColor"><path d="M3 2a1 1 0 00-1 1v10a1 1 0 001 1h10a1 1 0 001-1V6l-4-4H3zm0 1h6v3h3v7H3V3zm4 4v1h3v-1H7zm0 2v1h3v-1H7zm0 2v1h2v-1H7z"/></svg>
      Returns
    </a>
    <a class="mobile-nav-item <?php echo (isset($active_nav) && $active_nav==='tasks') ? 'active' : ''; ?>" href="tasks.php" style="position:relative;">
      <svg viewBox="0 0 16 16" fill="currentColor"><path d="M2 4a2 2 0 012-2h8a2 2 0 012 2v8a2 2 0 01-2 2H4a2 2 0 01-2-2V4zm2 1v6h8V5H4zm1 1h2v1H5V6zm0 2h2v1H5V8zm3-2h2v1H8V6zm0 2h2v1H8V8z"/></svg>
      Tasks
      <?php if (isset($open_tasks) && $open_tasks > 0): ?>
      <span style="position:absolute;top:2px;right:4px;background:#b84c2e;color:#fff;font-size:8px;font-family:'DM Mono','Courier New',monospace;padding:1px 4px;border-radius:8px;line-height:1.4;"><?php echo $open_tasks; ?></span>
      <?php endif; ?>
    </a>
    <a class="mobile-nav-item <?php echo (isset($active_nav) && ($active_nav==='reports'||$active_nav==='analytics')) ? 'active' : ''; ?>" href="reports.php">
      <svg viewBox="0 0 16 16" fill="currentColor"><path d="M2 10V5l4-3 4 3 4-3v5l-4 3-4-3-4 3zm0 1l4 3 4-3 4 3v1H2v-1z"/></svg>
      Reports
    </a>
  </div>
</nav>

<!-- Mobile FAB -->
<button class="mobile-fab" aria-label="New client">+</button>

<!-- ════════════════════════════════════ SCRIPTS -->
<script>
(function () {
  'use strict';

  /* ── Elements ── */
  var hamburger         = document.getElementById('hamburger');
  var sidebar           = document.getElementById('sidebar');
  var overlay           = document.getElementById('overlay');
  var notifBtn          = document.getElementById('notif-btn');
  var notifPanel        = document.getElementById('notif-panel');
  var notifDot          = document.getElementById('notif-dot');
  var markAllRead       = document.getElementById('mark-all-read');
  var userCardBtn       = document.getElementById('user-card-btn');
  var userMenu          = document.getElementById('user-menu');
  var mobileSearchBtn   = document.getElementById('mobile-search-btn');
  var mobileSearchBar   = document.getElementById('mobile-search-bar');
  var mobileSearchClose = document.getElementById('mobile-search-close');
  var globalSearch      = document.getElementById('global-search');
  var searchDropdown    = document.getElementById('search-dropdown');
  var mobileSearchInput = document.getElementById('mobile-search-input');
  var mobileDropdown    = document.getElementById('mobile-search-dropdown');
  var searchShortcut    = document.getElementById('search-shortcut');

  /* ══════════════════════════════════════
     SIDEBAR
  ══════════════════════════════════════ */
  function openSidebar() {
    if (!sidebar) return;
    sidebar.classList.add('open');
    overlay.classList.add('open');
    hamburger.classList.add('open');
    document.body.style.overflow = 'hidden';
  }

  function closeSidebar() {
    if (!sidebar) return;
    sidebar.classList.remove('open');
    overlay.classList.remove('open');
    if (hamburger) hamburger.classList.remove('open');
    document.body.style.overflow = '';
  }

  if (hamburger) hamburger.addEventListener('click', function () {
    sidebar.classList.contains('open') ? closeSidebar() : openSidebar();
  });

  if (overlay) overlay.addEventListener('click', function () {
    closeSidebar(); closeNotif();
  });

  document.querySelectorAll('.nav-item').forEach(function (item) {
    item.addEventListener('click', function () {
      if (window.innerWidth <= 768) closeSidebar();
    });
  });

  /* ══════════════════════════════════════
     NOTIFICATIONS
  ══════════════════════════════════════ */
  var notifOpen = false;

  function openNotif() {
    if (!notifPanel) return;
    notifOpen = true;
    notifPanel.classList.add('open');
    notifPanel.setAttribute('aria-hidden', 'false');
    if (notifBtn) notifBtn.classList.add('active');
  }

  function closeNotif() {
    if (!notifPanel) return;
    notifOpen = false;
    notifPanel.classList.remove('open');
    notifPanel.setAttribute('aria-hidden', 'true');
    if (notifBtn) notifBtn.classList.remove('active');
  }

  if (notifBtn) notifBtn.addEventListener('click', function (e) {
    e.stopPropagation();
    notifOpen ? closeNotif() : openNotif();
  });

  if (markAllRead) markAllRead.addEventListener('click', function () {
    document.querySelectorAll('.notif-row.unread').forEach(function (row) { row.classList.remove('unread'); });
    document.querySelectorAll('.notif-unread-dot').forEach(function (dot) { dot.style.display = 'none'; });
    if (notifDot) notifDot.style.display = 'none';
  });

  /* ══════════════════════════════════════
     USER MENU
  ══════════════════════════════════════ */
  var userMenuOpen = false;

  if (userCardBtn) {
    userCardBtn.addEventListener('click', function (e) {
      e.stopPropagation();
      userMenuOpen = !userMenuOpen;
      userMenu.classList.toggle('open', userMenuOpen);
      userCardBtn.setAttribute('aria-expanded', userMenuOpen ? 'true' : 'false');
      userMenu.setAttribute('aria-hidden', userMenuOpen ? 'false' : 'true');
    });
    userCardBtn.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); userCardBtn.click(); }
    });
  }

  document.addEventListener('click', function () {
    closeNotif();
    closeSearch();
    if (userMenuOpen && userMenu) {
      userMenuOpen = false;
      userMenu.classList.remove('open');
      if (userCardBtn) userCardBtn.setAttribute('aria-expanded', 'false');
    }
  });

  /* ══════════════════════════════════════
     MOBILE SEARCH DRAWER
  ══════════════════════════════════════ */
  if (mobileSearchBtn) mobileSearchBtn.addEventListener('click', function () {
    mobileSearchBar.classList.add('open');
    if (mobileSearchInput) mobileSearchInput.focus();
  });

  if (mobileSearchClose) mobileSearchClose.addEventListener('click', function () {
    mobileSearchBar.classList.remove('open');
    if (mobileDropdown) { mobileDropdown.classList.remove('open'); mobileDropdown.innerHTML = ''; }
    if (mobileSearchInput) mobileSearchInput.value = '';
  });

  /* ══════════════════════════════════════
     KEYBOARD SHORTCUTS
  ══════════════════════════════════════ */
  document.addEventListener('keydown', function (e) {
    if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
      e.preventDefault();
      if (window.innerWidth > 768 && globalSearch) {
        globalSearch.focus();
        globalSearch.select();
      } else if (mobileSearchBar) {
        mobileSearchBar.classList.add('open');
        if (mobileSearchInput) mobileSearchInput.focus();
      }
    }
    if (e.key === 'Escape') {
      closeNotif(); closeSidebar(); closeSearch();
      if (mobileSearchBar) mobileSearchBar.classList.remove('open');
    }
  });

  /* ══════════════════════════════════════
     LIVE SEARCH
  ══════════════════════════════════════ */

  var searchTimer   = null;
  var activeXHR     = null;
  var focusedIndex  = -1;
  var lastQuery     = '';
  var searchCache   = {};

  /* Detect ⌘ vs Ctrl for shortcut hint */
  if (searchShortcut) {
    var isMac = navigator.platform.toUpperCase().indexOf('MAC') >= 0;
    searchShortcut.innerHTML = isMac ? '&#8984;K' : 'Ctrl+K';
  }

  function closeSearch() {
    if (searchDropdown) {
      searchDropdown.classList.remove('open');
      searchDropdown.innerHTML = '';
    }
    if (globalSearch) globalSearch.setAttribute('aria-expanded', 'false');
    focusedIndex = -1;
  }

  /** Highlight matched substring in text */
  function highlight(text, query) {
    if (!query) return escapeHtml(text);
    var escaped = escapeHtml(text);
    var escapedQ = escapeHtml(query).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    return escaped.replace(new RegExp('(' + escapedQ + ')', 'gi'), '<mark>$1</mark>');
  }

  function escapeHtml(s) {
    return String(s)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  /** Build a single result row HTML */
  function buildResultHTML(item, query) {
    var typeLabel = item.type === 'client' ? 'Client' : 'Return';
    var badge = item.badge
      ? '<span class="search-result-badge ' + escapeHtml(item.badge_class) + '" style="font-size:10px;padding:2px 7px;">' + escapeHtml(item.badge) + '</span>'
      : '';

    return '<a href="' + escapeHtml(item.url) + '" class="search-result-item" role="option" tabindex="-1">'
      + '<div class="search-result-avatar" style="background:' + escapeHtml(item.color) + ';">' + escapeHtml(item.avatar) + '</div>'
      + '<div class="search-result-body">'
      +   '<div class="search-result-name">' + highlight(item.name, query) + '</div>'
      +   '<div class="search-result-sub">' + escapeHtml(item.sub) + '</div>'
      + '</div>'
      + badge
      + '<span class="search-result-type">' + typeLabel + '</span>'
      + '</a>';
  }

  /** Render results into a dropdown element */
  function renderResults(data, dropdown, query) {
    if (!dropdown) return;

    if (!data.results || data.results.length === 0) {
      dropdown.innerHTML = ''
        + '<div class="search-empty">'
        +   '<svg viewBox="0 0 16 16" fill="#8a8070"><path d="M7 2a5 5 0 100 10A5 5 0 007 2zm6.7 10.3l-3-3a6 6 0 10-1.4 1.4l3 3a1 1 0 001.4-1.4z"/></svg>'
        +   'No results for <strong>&ldquo;' + escapeHtml(query) + '&rdquo;</strong>'
        + '</div>';
      dropdown.classList.add('open');
      return;
    }

    var html = '<div class="search-dropdown-header">Results for &ldquo;' + escapeHtml(query) + '&rdquo;</div>';
    data.results.forEach(function (item) {
      html += buildResultHTML(item, query);
    });
    html += '<div class="search-footer">'
      + '<span class="search-footer-count">' + data.results.length + ' result' + (data.results.length !== 1 ? 's' : '') + '</span>'
      + '<span class="search-footer-hint"><kbd>&uarr;</kbd><kbd>&darr;</kbd> navigate &nbsp; <kbd>Enter</kbd> open &nbsp; <kbd>Esc</kbd> close</span>'
      + '</div>';

    dropdown.innerHTML = html;
    dropdown.classList.add('open');
    focusedIndex = -1;
  }

  /** Keyboard navigation within the dropdown */
  function handleSearchKeydown(e, dropdown) {
    if (!dropdown || !dropdown.classList.contains('open')) return;
    var items = dropdown.querySelectorAll('.search-result-item');
    if (!items.length) return;

    if (e.key === 'ArrowDown') {
      e.preventDefault();
      focusedIndex = Math.min(focusedIndex + 1, items.length - 1);
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      focusedIndex = Math.max(focusedIndex - 1, 0);
    } else if (e.key === 'Enter') {
      if (focusedIndex >= 0 && items[focusedIndex]) {
        e.preventDefault();
        window.location.href = items[focusedIndex].href;
      }
      return;
    } else {
      return;
    }

    items.forEach(function (el, i) {
      el.setAttribute('aria-selected', i === focusedIndex ? 'true' : 'false');
      el.classList.toggle('focused', i === focusedIndex);
    });

    if (items[focusedIndex]) {
      items[focusedIndex].scrollIntoView({ block: 'nearest' });
    }
  }

  /** Fetch search results with debounce + caching */
  function doSearch(query, dropdown, inputEl) {
    var q = query.trim();

    if (q.length < 3) {
      if (dropdown) { dropdown.classList.remove('open'); dropdown.innerHTML = ''; }
      if (inputEl) inputEl.setAttribute('aria-expanded', 'false');
      return;
    }

    if (q === lastQuery && dropdown && dropdown.classList.contains('open')) return;
    lastQuery = q;

    // Show loading spinner
    if (dropdown) {
      dropdown.innerHTML = '<div class="search-loading"><div class="search-spinner"></div>Searching&hellip;</div>';
      dropdown.classList.add('open');
    }
    if (inputEl) inputEl.setAttribute('aria-expanded', 'true');

    // Cache hit
    if (searchCache[q]) {
      renderResults(searchCache[q], dropdown, q);
      return;
    }

    // Abort previous request
    if (activeXHR) { activeXHR.abort(); activeXHR = null; }

    var controller = typeof AbortController !== 'undefined' ? new AbortController() : null;
    activeXHR = controller;

    fetch('search.php?q=' + encodeURIComponent(q), {
      signal: controller ? controller.signal : undefined,
      headers: { 'Accept': 'application/json' }
    })
    .then(function (res) {
      if (!res.ok) throw new Error('HTTP ' + res.status);
      return res.json();
    })
    .then(function (data) {
      searchCache[q] = data;
      renderResults(data, dropdown, q);
      activeXHR = null;
    })
    .catch(function (err) {
      if (err.name === 'AbortError') return;
      if (dropdown) {
        dropdown.innerHTML = '<div class="search-empty">Search unavailable. Please try again.</div>';
      }
      activeXHR = null;
    });
  }

  /** Wire up a search input + dropdown pair */
  function wireSearch(inputEl, dropdown) {
    if (!inputEl || !dropdown) return;

    inputEl.addEventListener('input', function () {
      clearTimeout(searchTimer);
      var q = inputEl.value.trim();
      if (q.length < 3) {
        dropdown.classList.remove('open');
        dropdown.innerHTML = '';
        focusedIndex = -1;
        lastQuery = '';
        return;
      }
      // Debounce: wait 220ms after last keystroke
      searchTimer = setTimeout(function () {
        doSearch(q, dropdown, inputEl);
      }, 220);
    });

    inputEl.addEventListener('focus', function () {
      var q = inputEl.value.trim();
      if (q.length >= 3) doSearch(q, dropdown, inputEl);
    });

    inputEl.addEventListener('keydown', function (e) {
      handleSearchKeydown(e, dropdown);
      if (e.key === 'Escape') {
        dropdown.classList.remove('open');
        dropdown.innerHTML = '';
        focusedIndex = -1;
        lastQuery = '';
        inputEl.blur();
      }
    });

    // Stop clicks inside dropdown closing it
    dropdown.addEventListener('click', function (e) {
      e.stopPropagation();
    });

    inputEl.addEventListener('click', function (e) {
      e.stopPropagation();
      var q = inputEl.value.trim();
      if (q.length >= 3 && !dropdown.classList.contains('open')) {
        doSearch(q, dropdown, inputEl);
      }
    });
  }

  // Wire desktop search
  wireSearch(globalSearch, searchDropdown);

  // Wire mobile search
  wireSearch(mobileSearchInput, mobileDropdown);

  // Close search dropdown on outside click (already handled in global click listener above)

}());
</script>

</body>
</html>
