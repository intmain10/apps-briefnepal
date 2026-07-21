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

  // Draw a circular social chip with a simple monochrome glyph.
  function drawSocialChip(ctx, net, cx, cy, R) {
    ctx.save();
    ctx.beginPath(); ctx.arc(cx, cy, R, 0, 6.2832);
    ctx.fillStyle = 'rgba(255,255,255,.06)'; ctx.fill();
    ctx.lineWidth = 2; ctx.strokeStyle = 'rgba(255,255,255,.28)'; ctx.stroke();
    ctx.strokeStyle = '#fff'; ctx.fillStyle = '#fff'; ctx.lineWidth = 2.4; ctx.lineCap = 'round'; ctx.lineJoin = 'round';
    const s = R * 0.5;
    if (net === 'x') { ctx.beginPath(); ctx.moveTo(cx - s, cy - s); ctx.lineTo(cx + s, cy + s); ctx.moveTo(cx + s, cy - s); ctx.lineTo(cx - s, cy + s); ctx.stroke(); }
    else if (net === 'linkedin') { ctx.font = "700 " + (R * 0.95) + "px 'Satoshi',sans-serif"; ctx.textAlign = 'center'; ctx.textBaseline = 'middle'; ctx.fillText('in', cx, cy + 1); }
    else if (net === 'instagram') { const q = R * 0.62; ctx.beginPath(); if (ctx.roundRect) ctx.roundRect(cx - q, cy - q, q * 2, q * 2, q * 0.55); else ctx.rect(cx - q, cy - q, q * 2, q * 2); ctx.stroke(); ctx.beginPath(); ctx.arc(cx, cy, q * 0.5, 0, 6.2832); ctx.stroke(); ctx.beginPath(); ctx.arc(cx + q * 0.55, cy - q * 0.55, 1.6, 0, 6.2832); ctx.fill(); }
    else if (net === 'youtube') { const w = R * 0.85, h = R * 0.62; ctx.beginPath(); if (ctx.roundRect) ctx.roundRect(cx - w, cy - h, w * 2, h * 2, 5); else ctx.rect(cx - w, cy - h, w * 2, h * 2); ctx.stroke(); ctx.beginPath(); ctx.moveTo(cx - s * 0.5, cy - s * 0.6); ctx.lineTo(cx + s * 0.7, cy); ctx.lineTo(cx - s * 0.5, cy + s * 0.6); ctx.closePath(); ctx.fill(); }
    else if (net === 'spotify') { ctx.beginPath(); ctx.arc(cx, cy, R * 0.62, 0, 6.2832); ctx.stroke(); ctx.lineWidth = 2; for (let i = 0; i < 3; i++) { const yy = cy - s * 0.4 + i * s * 0.5; ctx.beginPath(); ctx.arc(cx - R * 0.1, yy + R * 0.6, R * (0.5 - i * 0.12), -1.1, -0.1); ctx.stroke(); } }
    else { ctx.font = "700 " + (R * 0.95) + "px 'Clash Display',sans-serif"; ctx.textAlign = 'center'; ctx.textBaseline = 'middle'; ctx.fillText((net[0] || '?').toUpperCase(), cx, cy + 1); }
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
