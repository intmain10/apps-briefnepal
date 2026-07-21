/* Cardly public card — interactions (share, QR, menu, nav) + Story image export. */
(function () {
  'use strict';
  const wrap = document.querySelector('.cx');
  if (!wrap) return;
  const D = wrap.dataset;
  const url = D.cardUrl || location.href;
  const U = window.OmniUtil || { toast(m) { alert(m); }, copy(t) { navigator.clipboard && navigator.clipboard.writeText(t); }, download() {} };
  const $ = id => document.getElementById(id);

  /* ---- Share / copy ---- */
  $('cardlyShare') && $('cardlyShare').addEventListener('click', async () => {
    if (navigator.share) { try { await navigator.share({ title: document.title, url }); return; } catch (e) {} }
    U.copy(url); U.toast('Link copied');
  });
  $('cardlyCopy') && $('cardlyCopy').addEventListener('click', () => { U.copy(url); U.toast('Link copied'); closeSheet(); });

  /* ---- Menu sheet ---- */
  const sheet = $('cardlySheet');
  const openSheet = () => { if (sheet) sheet.hidden = false; };
  function closeSheet() { if (sheet) sheet.hidden = true; }
  $('cardlyMenu') && $('cardlyMenu').addEventListener('click', openSheet);
  $('cardlySheetClose') && $('cardlySheetClose').addEventListener('click', closeSheet);
  sheet && sheet.addEventListener('click', e => { if (e.target === sheet) closeSheet(); });

  /* ---- Inline QR ---- */
  (function () {
    const box = $('cardlyQr');
    if (box && window.OmniLib && OmniLib.QR) {
      try { box.appendChild(OmniLib.qrToCanvas(OmniLib.QR.encode(url, 'MEDIUM'), 6, 1, '#0a0a0f', '#ffffff')); }
      catch (e) {}
    }
  })();

  /* ---- Bottom nav (smooth scroll) ---- */
  const navBtns = Array.from(document.querySelectorAll('.cx__navbtn'));
  navBtns.forEach(b => b.addEventListener('click', () => {
    const el = document.getElementById(b.dataset.go);
    if (el) { el.scrollIntoView({ behavior: 'smooth', block: 'start' }); navBtns.forEach(x => x.classList.toggle('is-active', x === b)); }
  }));

  /* ---- Instagram Story export ---- */
  const storyBtn = $('cardlyStory');
  if (storyBtn) storyBtn.addEventListener('click', async () => {
    closeSheet();
    const orig = storyBtn.innerHTML; storyBtn.disabled = true; storyBtn.innerHTML = '⏳ <span>Creating…</span>';
    try {
      const blob = await buildStoryImage();
      const file = new File([blob], 'cardly-story.png', { type: 'image/png' });
      if (navigator.canShare && navigator.canShare({ files: [file] })) {
        await navigator.share({ files: [file], title: document.title, text: url });
      } else {
        U.download('cardly-story.png', blob);
        U.toast('Image saved — add it to your Instagram Story');
      }
    } catch (e) { U.toast('Could not create the story image'); }
    finally { storyBtn.disabled = false; storyBtn.innerHTML = orig; }
  });

  /* ===================================================================
     Story image generator (1080×1920)
     ================================================================ */
  function cssVar(name, fb) { const v = getComputedStyle(wrap).getPropertyValue(name).trim(); return v || fb; }
  function loadImg(src) {
    return new Promise(res => {
      if (!src) return res(null);
      const img = new Image(); img.crossOrigin = 'anonymous';
      img.onload = () => res(img); img.onerror = () => res(null); img.src = src;
    });
  }
  function roundRect(ctx, x, y, w, h, r) {
    ctx.beginPath(); ctx.moveTo(x + r, y);
    ctx.arcTo(x + w, y, x + w, y + h, r); ctx.arcTo(x + w, y + h, x, y + h, r);
    ctx.arcTo(x, y + h, x, y, r); ctx.arcTo(x, y, x + w, y, r); ctx.closePath();
  }
  function wrapText(ctx, text, x, y, maxW, lh, maxLines) {
    const words = (text || '').split(/\s+/); let line = '', lines = [];
    for (const w of words) {
      const test = line ? line + ' ' + w : w;
      if (ctx.measureText(test).width > maxW && line) { lines.push(line); line = w; } else line = test;
      if (lines.length >= maxLines) break;
    }
    if (line && lines.length < maxLines) lines.push(line);
    lines.forEach((l, i) => ctx.fillText(l, x, y + i * lh));
    return lines.length;
  }
  const CTA = {
    music: '🎵 Listen Now', creator: '📺 Watch My Videos', developer: '💻 View My Work',
    business: '📞 Get in Touch', photographer: '📸 Explore My Work', freelancer: '💼 Work With Me',
    student: '🎓 Connect With Me', gym: '💪 Train With Me', startup: '🚀 Check Us Out',
    doctor: '🩺 Book Appointment', realestate: '🏠 View Listings', wedding: "💍 You're Invited",
    event: '🎉 Join the Event', default: '👋 Connect With Me',
  };
  let _fontLoaded = false;
  async function ensureFont() {
    if (_fontLoaded) return;
    try {
      if (document.fonts && document.fonts.load) {
        await Promise.allSettled([
          document.fonts.load("700 82px 'Clash Display'"),
          document.fonts.load("600 82px 'Clash Display'"),
          document.fonts.load("500 40px 'Satoshi'"),
          document.fonts.load("700 40px 'Satoshi'"),
        ]);
        if (document.fonts.ready) await document.fonts.ready;
      }
    } catch (e) {}
    _fontLoaded = true;
  }
  async function buildStoryImage() { return new Promise(async res => { const c = await drawStory(); c.toBlob(b => res(b), 'image/png', 0.95); }); }

  const hex2rgb = h => { h = h.replace('#', ''); if (h.length === 3) h = h.split('').map(x => x + x).join(''); return [parseInt(h.slice(0, 2), 16), parseInt(h.slice(2, 4), 16), parseInt(h.slice(4, 6), 16)]; };

  // Official brand logos (Simple Icons path data, 24×24 viewBox) so the story
  // shows the real, recognisable Spotify / YouTube / LinkedIn / … marks.
  // c = chip colour, fg = glyph colour, grad = use the Instagram gradient.
  const BRAND = {
    spotify: { c: '#1DB954', fg: '#fff', p: 'M12 0C5.4 0 0 5.4 0 12s5.4 12 12 12 12-5.4 12-12S18.66 0 12 0zm5.521 17.34c-.24.359-.66.48-1.021.24-2.82-1.74-6.36-2.101-10.561-1.141-.418.122-.779-.179-.899-.539-.12-.421.18-.78.54-.9 4.56-1.021 8.52-.6 11.64 1.32.42.18.479.659.301 1.02zm1.44-3.3c-.301.42-.841.6-1.262.3-3.239-1.98-8.159-2.58-11.939-1.38-.479.12-1.02-.12-1.14-.6-.12-.48.12-1.021.6-1.141C9.6 9.9 15 10.561 18.72 12.84c.361.181.601.78.241 1.2zm.12-3.36C15.24 8.4 8.82 8.16 5.16 9.301c-.6.179-1.2-.181-1.38-.721-.18-.601.18-1.2.72-1.381 4.26-1.26 11.28-1.02 15.721 1.621.539.3.719 1.02.419 1.56-.299.421-1.02.599-1.559.3z' },
    youtube: { c: '#FF0000', fg: '#fff', p: 'M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z' },
    linkedin: { c: '#0A66C2', fg: '#fff', p: 'M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 01-2.063-2.065 2.064 2.064 0 112.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z' },
    instagram: { grad: true, fg: '#fff', p: 'M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z' },
    facebook: { c: '#1877F2', fg: '#fff', p: 'M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z' },
    github: { c: '#181717', fg: '#fff', ring: true, p: 'M12 .297c-6.63 0-12 5.373-12 12 0 5.303 3.438 9.8 8.205 11.385.6.113.82-.258.82-.577 0-.285-.01-1.04-.015-2.04-3.338.724-4.042-1.61-4.042-1.61C4.422 18.07 3.633 17.7 3.633 17.7c-1.087-.744.084-.729.084-.729 1.205.084 1.838 1.236 1.838 1.236 1.07 1.835 2.809 1.305 3.495.998.108-.776.417-1.305.76-1.605-2.665-.3-5.466-1.332-5.466-5.93 0-1.31.465-2.38 1.235-3.22-.135-.303-.54-1.523.105-3.176 0 0 1.005-.322 3.3 1.23.96-.267 1.98-.399 3-.405 1.02.006 2.04.138 3 .405 2.28-1.552 3.285-1.23 3.285-1.23.645 1.653.24 2.873.12 3.176.765.84 1.23 1.91 1.23 3.22 0 4.61-2.805 5.625-5.475 5.92.42.36.81 1.096.81 2.22 0 1.606-.015 2.896-.015 3.286 0 .315.21.69.825.57C20.565 22.092 24 17.592 24 12.297c0-6.627-5.373-12-12-12' },
    x: { c: '#000', fg: '#fff', ring: true, p: 'M18.901 1.153h3.68l-8.04 9.19L24 22.846h-7.406l-5.8-7.584-6.638 7.584H.474l8.6-9.83L0 1.154h7.594l5.243 6.932ZM17.61 20.644h2.039L6.486 3.24H4.298Z' },
    whatsapp: { c: '#25D366', fg: '#fff', p: 'M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.71.306 1.263.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z' },
    telegram: { c: '#26A5E4', fg: '#fff', p: 'M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z' },
    tiktok: { c: '#010101', fg: '#fff', ring: true, p: 'M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-.99-.32-2.15-.23-3.02.37-.63.41-1.11 1.04-1.36 1.75-.21.51-.15 1.07-.14 1.61.24 1.64 1.82 3.02 3.5 2.87 1.12-.01 2.19-.66 2.77-1.61.19-.33.4-.67.41-1.06.1-1.79.06-3.57.07-5.36.01-4.03-.01-8.05.02-12.07z' },
  };
  // Draw a circular social chip using the real brand logo + brand colour.
  function drawSocialChip(ctx, net, cx, cy, R) {
    const b = BRAND[net];
    ctx.save();
    ctx.beginPath(); ctx.arc(cx, cy, R, 0, 6.2832);
    if (b && b.grad) {
      const g = ctx.createLinearGradient(cx - R, cy + R, cx + R, cy - R);
      g.addColorStop(0, '#feda75'); g.addColorStop(0.35, '#fa7e1e'); g.addColorStop(0.6, '#d62976'); g.addColorStop(1, '#962fbf');
      ctx.fillStyle = g;
    } else ctx.fillStyle = b ? b.c : 'rgba(255,255,255,.08)';
    ctx.fill();
    // Ring so dark brand chips (X, GitHub, TikTok) separate from the dark bg.
    ctx.lineWidth = 2; ctx.strokeStyle = (b && b.ring) ? 'rgba(255,255,255,.35)' : 'rgba(255,255,255,.14)'; ctx.stroke();
    if (b && b.p && typeof Path2D !== 'undefined') {
      const sc = (R * 1.12) / 24;
      ctx.translate(cx - 12 * sc, cy - 12 * sc); ctx.scale(sc, sc);
      ctx.fillStyle = b.fg; ctx.fill(new Path2D(b.p));
    } else {
      ctx.fillStyle = '#fff'; ctx.font = '700 ' + (R * 0.9) + "px 'Clash Display',sans-serif";
      ctx.textAlign = 'center'; ctx.textBaseline = 'middle'; ctx.fillText((net[0] || '?').toUpperCase(), cx, cy + 1);
    }
    ctx.restore();
  }

  // Premium, cinematic 1080×1920 story — designed background, not a raw photo.
  function drawBadge(ctx, x, y, r) {
    ctx.save();
    const g = ctx.createLinearGradient(x - r, y - r, x + r, y + r); g.addColorStop(0, '#f5b301'); g.addColorStop(1, '#f59e0b');
    ctx.beginPath(); ctx.arc(x, y, r, 0, 6.2832); ctx.fillStyle = g; ctx.fill();
    ctx.strokeStyle = '#fff'; ctx.lineWidth = r * 0.24; ctx.lineCap = 'round'; ctx.lineJoin = 'round';
    ctx.beginPath(); ctx.moveTo(x - r * 0.42, y + r * 0.02); ctx.lineTo(x - r * 0.05, y + r * 0.38); ctx.lineTo(x + r * 0.46, y - r * 0.36); ctx.stroke();
    ctx.restore();
  }
  function chevron(ctx, x, y) {
    ctx.beginPath(); ctx.arc(x, y, 27, 0, 6.2832); ctx.strokeStyle = 'rgba(255,255,255,.2)'; ctx.lineWidth = 2; ctx.stroke();
    ctx.strokeStyle = '#fff'; ctx.lineWidth = 3; ctx.lineCap = 'round'; ctx.lineJoin = 'round';
    ctx.beginPath(); ctx.moveTo(x - 5, y - 9); ctx.lineTo(x + 7, y); ctx.lineTo(x - 5, y + 9); ctx.stroke();
  }

  // Story = the card view, formatted for 9:16 (hero photo, gradient name,
  // role, quote, social chips, primary link card, QR).
  async function drawStory() {
    const W = 1080, H = 1920, PAD = 64, HERO = 1180;
    const c1 = cssVar('--c1', '#0071e3'), c2 = cssVar('--c2', '#7c3aed');
    const [r1, g1, b1] = hex2rgb(c1);
    const tplKey = D.template || 'default';
    const name = (D.name || 'My Card').trim();
    const tagline = (D.tagline || '').trim();
    const about = (document.querySelector('.cx__quote')?.textContent || '').trim();
    const socials = Array.from(document.querySelectorAll('.cx__soc')).map(a => a.getAttribute('aria-label')).filter(Boolean).slice(0, 6);
    const ctaFull = CTA[tplKey] || CTA.default;
    const sp = ctaFull.indexOf(' ');
    const emoji = sp > 0 ? ctaFull.slice(0, sp) : '👋';
    const ctaTitle = sp > 0 ? ctaFull.slice(sp + 1) : ctaFull;
    const ctaSub = (document.querySelector('.cx__link--primary .cx__link-tx small')?.textContent || 'Tap to open').trim();
    await ensureFont();

    const canvas = document.createElement('canvas'); canvas.width = W; canvas.height = H;
    const ctx = canvas.getContext('2d');
    const shadow = on => { ctx.shadowColor = on ? 'rgba(0,0,0,.5)' : 'transparent'; ctx.shadowBlur = on ? 20 : 0; ctx.shadowOffsetY = on ? 4 : 0; };

    /* ---------- Base ---------- */
    ctx.fillStyle = '#0b0b12'; ctx.fillRect(0, 0, W, H);

    /* ---------- Hero image (photo, else cover) ---------- */
    const hero = await loadImg(D.photo || D.cover);
    ctx.save(); ctx.beginPath(); ctx.rect(0, 0, W, HERO); ctx.clip();
    if (hero) {
      const s = Math.max(W / hero.width, HERO / hero.height), dw = hero.width * s, dh = hero.height * s;
      ctx.drawImage(hero, (W - dw) / 2, (HERO - dh) * 0.28, dw, dh);
    } else { const g = ctx.createLinearGradient(0, 0, W, HERO); g.addColorStop(0, c1); g.addColorStop(1, c2); ctx.fillStyle = g; ctx.fillRect(0, 0, W, HERO); }
    // scrim: darken bottom (for text) + left, blend to body colour
    const sc = ctx.createLinearGradient(0, 0, 0, HERO); sc.addColorStop(0, 'rgba(11,11,18,.35)'); sc.addColorStop(0.45, 'rgba(11,11,18,.05)'); sc.addColorStop(0.78, 'rgba(11,11,18,.55)'); sc.addColorStop(1, '#0b0b12'); ctx.fillStyle = sc; ctx.fillRect(0, 0, W, HERO);
    const lx = ctx.createLinearGradient(0, 0, W * 0.9, 0); lx.addColorStop(0, 'rgba(11,11,18,.6)'); lx.addColorStop(1, 'rgba(11,11,18,0)'); ctx.fillStyle = lx; ctx.fillRect(0, HERO * 0.4, W, HERO);
    ctx.restore();

    /* ---------- Kicker ---------- */
    ctx.textAlign = 'left'; ctx.textBaseline = 'alphabetic';
    ctx.fillStyle = 'rgba(255,255,255,.85)'; ctx.font = "500 30px 'Satoshi',sans-serif";
    ctx.fillText('✨ ' + name.split(' ')[0] + '’s card', PAD, 96);

    /* ---------- Name (gradient last word) + badge ---------- */
    const parts = name.split(/\s+/);
    const last = parts.length > 1 ? parts[parts.length - 1] : '';
    const first = last ? parts.slice(0, -1).join(' ') : name;
    let ns = 86; ctx.font = `700 ${ns}px 'Clash Display',sans-serif`;
    const longer = Math.max(ctx.measureText(first).width, ctx.measureText(last || first).width);
    if (longer > W - PAD * 2) { ns = Math.max(52, Math.floor(ns * (W - PAD * 2) / longer)); }
    ctx.font = `700 ${ns}px 'Clash Display',sans-serif`; const lh = ns * 1.05;
    const nameBottom = 1000;
    shadow(true);
    let badgeX, badgeY;
    if (last) {
      ctx.fillStyle = '#fff'; ctx.fillText(first, PAD, nameBottom - lh);
      const lg = ctx.createLinearGradient(PAD, 0, PAD + ctx.measureText(last).width, 0); lg.addColorStop(0, c1); lg.addColorStop(1, c2);
      ctx.fillStyle = lg; ctx.fillText(last, PAD, nameBottom);
      badgeX = PAD + ctx.measureText(last).width + 22; badgeY = nameBottom - ns * 0.32;
    } else { ctx.fillStyle = '#fff'; ctx.fillText(first, PAD, nameBottom); badgeX = PAD + ctx.measureText(first).width + 22; badgeY = nameBottom - ns * 0.32; }
    shadow(false); drawBadge(ctx, badgeX, badgeY, 22);

    /* ---------- Role + quote ---------- */
    let y = nameBottom + 52;
    if (tagline) { ctx.fillStyle = 'rgba(255,255,255,.85)'; ctx.font = "500 34px 'Satoshi',sans-serif"; shadow(true); y += wrapText(ctx, tagline, PAD, y, W - PAD * 2, 42, 1) * 42; shadow(false); }
    if (about) {
      const qy = y + 10;
      ctx.font = "400 27px 'Satoshi',sans-serif"; ctx.fillStyle = 'rgba(255,255,255,.72)';
      const qLines = Math.min(2, Math.ceil(ctx.measureText(about).width / (W - PAD * 2 - 24)));
      const bar = ctx.createLinearGradient(0, qy - 20, 0, qy - 20 + qLines * 36); bar.addColorStop(0, c1); bar.addColorStop(1, c2);
      ctx.fillStyle = bar; roundRect(ctx, PAD, qy - 22, 4, qLines * 36 + 4, 2); ctx.fill();
      ctx.fillStyle = 'rgba(255,255,255,.72)'; wrapText(ctx, about, PAD + 20, qy, W - PAD * 2 - 24, 36, 2);
    }

    /* ---------- Social chips ---------- */
    if (socials.length) { let sx = PAD + 30; const sy = HERO + 66; socials.forEach(net => { drawSocialChip(ctx, net, sx, sy, 30); sx += 78; }); }

    /* ---------- Primary link card ---------- */
    const lcX = PAD, lcW = W - PAD * 2, lcH = 148, lcY = socials.length ? 1300 : 1270;
    ctx.save(); ctx.shadowColor = `rgba(${r1},${g1},${b1},.35)`; ctx.shadowBlur = 34; ctx.shadowOffsetY = 12; roundRect(ctx, lcX, lcY, lcW, lcH, 28); ctx.fillStyle = 'rgba(255,255,255,.055)'; ctx.fill(); ctx.restore();
    roundRect(ctx, lcX, lcY, lcW, lcH, 28); ctx.lineWidth = 1.5; ctx.strokeStyle = 'rgba(255,255,255,.1)'; ctx.stroke();
    const bs = 92, bx = lcX + 26, by = lcY + (lcH - bs) / 2;
    ctx.save(); const bg2 = ctx.createLinearGradient(bx, by, bx + bs, by + bs); bg2.addColorStop(0, c1); bg2.addColorStop(1, c2); ctx.shadowColor = `rgba(${r1},${g1},${b1},.5)`; ctx.shadowBlur = 22; roundRect(ctx, bx, by, bs, bs, 22); ctx.fillStyle = bg2; ctx.fill(); ctx.restore();
    ctx.textAlign = 'center'; ctx.textBaseline = 'middle'; ctx.font = "400 48px 'Satoshi',sans-serif"; ctx.fillStyle = '#fff'; ctx.fillText(emoji, bx + bs / 2, by + bs / 2 + 2);
    ctx.textAlign = 'left'; ctx.textBaseline = 'alphabetic';
    const tx = bx + bs + 30;
    ctx.fillStyle = '#fff'; ctx.font = "700 44px 'Clash Display',sans-serif"; ctx.fillText(ctaTitle, tx, lcY + 64);
    ctx.fillStyle = 'rgba(255,255,255,.6)'; ctx.font = "500 27px 'Satoshi',sans-serif"; ctx.fillText(ctaSub, tx, lcY + 104);
    chevron(ctx, lcX + lcW - 54, lcY + lcH / 2);

    /* ---------- QR card ---------- */
    const qcY = lcY + lcH + 40, qcH = 236;
    roundRect(ctx, PAD, qcY, W - PAD * 2, qcH, 28); ctx.fillStyle = 'rgba(255,255,255,.055)'; ctx.fill();
    roundRect(ctx, PAD, qcY, W - PAD * 2, qcH, 28); ctx.lineWidth = 1.5; ctx.strokeStyle = 'rgba(255,255,255,.1)'; ctx.stroke();
    const qs = 176, qp = 16, qx = PAD + 26, qy2 = qcY + (qcH - qs - qp * 2) / 2;
    ctx.save(); ctx.shadowColor = `rgba(${r1},${g1},${b1},.5)`; ctx.shadowBlur = 28; roundRect(ctx, qx, qy2, qs + qp * 2, qs + qp * 2, 20); ctx.fillStyle = '#fff'; ctx.fill(); ctx.restore();
    if (window.OmniLib && OmniLib.QR) ctx.drawImage(OmniLib.qrToCanvas(OmniLib.QR.encode(url, 'MEDIUM'), 8, 1, '#0a0a0f', '#fff'), qx + qp, qy2 + qp, qs, qs);
    const qtx = qx + qs + qp * 2 + 30;
    ctx.fillStyle = '#fff'; ctx.font = "700 40px 'Clash Display',sans-serif"; ctx.fillText('Scan to connect', qtx, qcY + 100);
    ctx.fillStyle = 'rgba(255,255,255,.7)'; ctx.font = "500 27px 'Satoshi',sans-serif"; ctx.fillText(url.replace(/^https?:\/\//, ''), qtx, qcY + 146);

    /* ---------- Footer ---------- */
    ctx.textAlign = 'center'; ctx.fillStyle = 'rgba(255,255,255,.5)'; ctx.font = "500 28px 'Satoshi',sans-serif";
    ctx.fillText('Made with Cardly · ' + (window.OMNITOOLS_BASE || 'apps.briefnepal.com').replace(/^https?:\/\//, ''), W / 2, H - 46);
    return canvas;
  }

  /* TEMP: ?story-preview renders the story full-screen for QA screenshots. */
  if (location.search.indexOf('story-preview') !== -1) {
    (async () => {
      const c = await drawStory();
      const o = document.createElement('div');
      o.style.cssText = 'position:fixed;inset:0;z-index:99999;background:#000;display:flex;align-items:center;justify-content:center';
      c.style.cssText = 'height:100vh;width:auto;max-width:100vw';
      o.appendChild(c); document.body.appendChild(o);
    })();
  }
})();
