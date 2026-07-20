/* =========================================================================
   OmniTools — Core app JS
   Theme, navigation, command-palette search, and shared UI utilities.
   Vanilla JS only. No dependencies.
   ========================================================================= */
(function () {
  'use strict';

  const BASE = window.OMNITOOLS_BASE || '';
  const $ = (s, c) => (c || document).querySelector(s);
  const $$ = (s, c) => Array.from((c || document).querySelectorAll(s));

  /* ------------------------------------------------------------ Utilities */
  const OmniUtil = {
    toast(msg) {
      let el = $('#omniToast');
      if (!el) {
        el = document.createElement('div');
        el.id = 'omniToast';
        el.className = 'toast';
        document.body.appendChild(el);
      }
      el.textContent = msg;
      el.classList.add('show');
      clearTimeout(el._t);
      el._t = setTimeout(() => el.classList.remove('show'), 2200);
    },
    async copy(text) {
      try {
        await navigator.clipboard.writeText(text);
        this.toast('Copied to clipboard');
        return true;
      } catch (e) {
        // Fallback for non-secure contexts.
        const ta = document.createElement('textarea');
        ta.value = text; ta.style.position = 'fixed'; ta.style.opacity = '0';
        document.body.appendChild(ta); ta.select();
        try { document.execCommand('copy'); this.toast('Copied to clipboard'); } catch (_) {}
        document.body.removeChild(ta);
        return true;
      }
    },
    download(filename, content, mime) {
      const blob = content instanceof Blob ? content : new Blob([content], { type: mime || 'text/plain' });
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url; a.download = filename;
      document.body.appendChild(a); a.click(); a.remove();
      setTimeout(() => URL.revokeObjectURL(url), 1000);
    },
    debounce(fn, ms) {
      let t; return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), ms); };
    },
    escape(s) {
      return String(s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    }
  };
  window.OmniUtil = OmniUtil;

  /* --------------------------------------------------------------- Theme */
  const themeToggle = $('#themeToggle');
  function setTheme(t) {
    document.documentElement.setAttribute('data-theme', t);
    try { localStorage.setItem('omnitools-theme', t); } catch (e) {}
  }
  if (themeToggle) {
    themeToggle.addEventListener('click', () => {
      const cur = document.documentElement.getAttribute('data-theme');
      setTheme(cur === 'dark' ? 'light' : 'dark');
    });
  }

  /* --------------------------------------------------------- Dropdown nav */
  $$('.dropdown').forEach(dd => {
    const btn = $('.dropdown__btn', dd);
    if (!btn) return;
    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      const open = dd.getAttribute('data-open') === 'true';
      $$('.dropdown').forEach(d => d.setAttribute('data-open', 'false'));
      dd.setAttribute('data-open', String(!open));
      btn.setAttribute('aria-expanded', String(!open));
    });
  });
  document.addEventListener('click', () => {
    $$('.dropdown').forEach(d => d.setAttribute('data-open', 'false'));
  });

  /* ---------------------------------------------------------- Mobile menu */
  const burger = $('#navBurger');
  const mobileMenu = $('#mobileMenu');
  if (burger && mobileMenu) {
    burger.addEventListener('click', () => {
      const open = !mobileMenu.hidden;
      mobileMenu.hidden = open;
      burger.setAttribute('aria-expanded', String(!open));
    });
  }

  /* ---------------------------------------------------- Command search UI */
  const overlay = $('#searchOverlay');
  const searchInput = $('#globalSearch');
  const results = $('#searchResults');
  let selectedIdx = -1;
  let currentResults = [];
  let allToolsCache = null;

  function openSearch() {
    if (!overlay) return;
    overlay.hidden = false;
    document.body.style.overflow = 'hidden';
    setTimeout(() => searchInput && searchInput.focus(), 30);
    if (!currentResults.length) runSearch('');
  }
  function closeSearch() {
    if (!overlay) return;
    overlay.hidden = true;
    document.body.style.overflow = '';
    if (searchInput) searchInput.value = '';
    selectedIdx = -1;
  }

  $('#searchTrigger') && $('#searchTrigger').addEventListener('click', openSearch);
  $('#searchClose') && $('#searchClose').addEventListener('click', closeSearch);
  overlay && overlay.addEventListener('click', (e) => { if (e.target === overlay) closeSearch(); });

  function renderResults(items, q) {
    currentResults = items;
    selectedIdx = items.length ? 0 : -1;
    if (!items.length) {
      results.innerHTML = '<div class="search-empty">No tools found for “' + OmniUtil.escape(q) + '”.<br>Try another keyword.</div>';
      return;
    }
    const rx = q ? new RegExp('(' + q.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'ig') : null;
    results.innerHTML = items.map((it, i) => {
      const title = rx ? it.name.replace(rx, '<mark>$1</mark>') : it.name;
      return `<a class="search-result" role="option" data-idx="${i}" href="${BASE}/${it.slug}" aria-selected="${i === 0}">
        <span class="search-result__icon" style="color:${it.color}">${it.icon}</span>
        <span style="min-width:0">
          <span class="search-result__title">${title}</span>
          <span class="search-result__desc">${OmniUtil.escape(it.desc)}</span>
        </span>
        <span class="search-result__cat">${OmniUtil.escape(it.category)}</span>
      </a>`;
    }).join('');
    $$('.search-result', results).forEach(el => {
      el.addEventListener('mousemove', () => setSelected(parseInt(el.dataset.idx, 10)));
    });
  }

  function setSelected(idx) {
    selectedIdx = idx;
    $$('.search-result', results).forEach((el, i) => {
      el.setAttribute('aria-selected', String(i === idx));
      if (i === idx) el.scrollIntoView({ block: 'nearest' });
    });
  }

  const runSearch = OmniUtil.debounce(async (q) => {
    try {
      const res = await fetch(`${BASE}/api/search.php?q=${encodeURIComponent(q)}`);
      const data = await res.json();
      renderResults(data.results || [], q);
    } catch (e) {
      // Fallback to a cached client-side index if the API is unreachable.
      if (!allToolsCache) return;
      const lc = q.toLowerCase();
      const filtered = allToolsCache.filter(t =>
        t.name.toLowerCase().includes(lc) || t.desc.toLowerCase().includes(lc) || (t.keywords || '').includes(lc)
      ).slice(0, 20);
      renderResults(filtered, q);
    }
  }, 120);

  if (searchInput) {
    searchInput.addEventListener('input', () => runSearch(searchInput.value.trim()));
    searchInput.addEventListener('keydown', (e) => {
      if (e.key === 'ArrowDown') { e.preventDefault(); setSelected(Math.min(selectedIdx + 1, currentResults.length - 1)); }
      else if (e.key === 'ArrowUp') { e.preventDefault(); setSelected(Math.max(selectedIdx - 1, 0)); }
      else if (e.key === 'Enter' && currentResults[selectedIdx]) { window.location.href = `${BASE}/${currentResults[selectedIdx].slug}`; }
    });
  }

  /* Global keyboard shortcuts: "/" or Cmd/Ctrl+K to search, Esc to close. */
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') { closeSearch(); return; }
    const typing = /^(input|textarea|select)$/i.test((e.target.tagName || '')) || e.target.isContentEditable;
    if (!typing && e.key === '/') { e.preventDefault(); openSearch(); }
    if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') { e.preventDefault(); openSearch(); }
  });

  /* --------------------------------------------------- Hero search field */
  const heroForm = $('#heroSearchForm');
  if (heroForm) {
    const heroInput = $('#heroSearchInput');
    heroForm.addEventListener('submit', (e) => {
      e.preventDefault();
      const q = heroInput.value.trim();
      window.location.href = `${BASE}/tools?q=${encodeURIComponent(q)}`;
    });
    // Live suggestions dropdown under the hero bar (optional element).
    heroInput && heroInput.addEventListener('focus', () => {
      if (window.innerWidth > 640) { /* keep inline typing; overlay on demand */ }
    });
  }

  /* ------------------------------------------------------------ Back to top */
  const toTop = $('#toTop');
  if (toTop) {
    window.addEventListener('scroll', () => {
      toTop.classList.toggle('show', window.scrollY > 500);
    }, { passive: true });
    toTop.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));
  }

  /* ------------------------------------------------------- FAQ accordions */
  // Native <details> handles this; JS just closes siblings for a11y nicety.
  $$('.faq__item').forEach(item => {
    item.addEventListener('toggle', () => {
      if (item.open) $$('.faq__item').forEach(o => { if (o !== item) o.open = false; });
    });
  });

  /* Expose a way for tool pages to preload the client-side index (fallback). */
  window.__setOmniIndex = (arr) => { allToolsCache = arr; };

  /* ------------------------------------------------ Personalised sections */
  // Renders "Recently Used" and "Your Favourites" on the homepage from
  // localStorage. Recent visits are recorded on every tool page (below).
  (function personalise() {
    const idx = window.OMNITOOLS_INDEX;
    if (!idx || !idx.tools) return;

    function card(slug) {
      const t = idx.tools[slug];
      if (!t) return '';
      const c = idx.cats[t.cat] || { color: 'var(--accent)', icon: '' };
      return `<a class="tool-card" href="${BASE}/${slug}">
        <span class="tool-card__icon" style="background:${c.color}">${c.icon}</span>
        <div class="tool-card__title">${OmniUtil.escape(t.name)}</div>
        <div class="tool-card__desc">${OmniUtil.escape(t.desc)}</div></a>`;
    }
    function read(key) {
      try { return (JSON.parse(localStorage.getItem(key) || '[]') || []).filter(s => idx.tools[s]); }
      catch (e) { return []; }
    }
    function fill(cardsId, blockId, list) {
      const el = document.getElementById(cardsId);
      if (!el || !list.length) return false;
      el.innerHTML = list.slice(0, 8).map(card).join('');
      const block = document.getElementById(blockId);
      if (block) block.classList.remove('hidden');
      return true;
    }

    const section = document.getElementById('personalSection');
    if (!section) return;
    const hasRecent = fill('recentCards', 'recentBlock', read('omnitools-recent'));
    const hasFavs = fill('favCards', 'favBlock', read('omnitools-favs'));
    if (hasRecent || hasFavs) section.classList.remove('hidden');
  })();

  /* Record the current tool page into the "recently used" list. */
  (function recordRecent() {
    const ws = document.querySelector('[data-tool]');
    if (!ws) return;
    const slug = ws.getAttribute('data-tool');
    if (!slug) return;
    try {
      let recent = JSON.parse(localStorage.getItem('omnitools-recent') || '[]');
      recent = recent.filter(s => s !== slug);
      recent.unshift(slug);
      localStorage.setItem('omnitools-recent', JSON.stringify(recent.slice(0, 12)));
    } catch (e) {}
  })();
})();
