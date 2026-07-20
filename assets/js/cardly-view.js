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

  // Premium, cinematic 1080×1920 story — designed background, not a raw photo.
  async function drawStory() {
    const W = 1080, H = 1920;
    const c1 = cssVar('--c1', '#0071e3'), c2 = cssVar('--c2', '#7c3aed');
    const [r1, g1, b1] = hex2rgb(c1), [r2, g2, b2] = hex2rgb(c2);
    const tplKey = D.template || 'default';
    const ctaText = CTA[tplKey] || CTA.default;
    const name = (D.name || 'My Card').trim();
    const tagline = (D.tagline || '').trim();
    await ensureFont();

    const canvas = document.createElement('canvas'); canvas.width = W; canvas.height = H;
    const ctx = canvas.getContext('2d'); ctx.textAlign = 'center';

    /* ---------- Cinematic background (designed, moody) ---------- */
    ctx.fillStyle = '#07070c'; ctx.fillRect(0, 0, W, H);
    // faint real-photo texture (cover) — barely visible for depth
    const cover = await loadImg(D.cover);
    if (cover) {
      const s = Math.max(W / cover.width, H / cover.height), dw = cover.width * s, dh = cover.height * s;
      ctx.globalAlpha = 0.28; ctx.drawImage(cover, (W - dw) / 2, (H - dh) / 2, dw, dh); ctx.globalAlpha = 1;
      ctx.fillStyle = 'rgba(7,7,12,.62)'; ctx.fillRect(0, 0, W, H);
    }
    // accent glows
    const glow = (x, y, rad, r, g, b, a) => { const rg = ctx.createRadialGradient(x, y, 0, x, y, rad); rg.addColorStop(0, `rgba(${r},${g},${b},${a})`); rg.addColorStop(1, `rgba(${r},${g},${b},0)`); ctx.fillStyle = rg; ctx.fillRect(0, 0, W, H); };
    glow(W / 2, 560, 700, r1, g1, b1, 0.38);
    glow(W * 0.15, 1350, 620, r2, g2, b2, 0.30);
    glow(W * 0.9, 1650, 520, r1, g1, b1, 0.22);
    // fine grain
    for (let i = 0; i < 90; i++) { ctx.globalAlpha = 0.03 + Math.random() * 0.07; ctx.beginPath(); ctx.arc(Math.random() * W, Math.random() * H, Math.random() * 2.2 + 0.5, 0, 6.28); ctx.fillStyle = '#fff'; ctx.fill(); }
    ctx.globalAlpha = 1;
    // vignette
    const vg = ctx.createRadialGradient(W / 2, H / 2, H * 0.3, W / 2, H / 2, H * 0.72); vg.addColorStop(0, 'rgba(0,0,0,0)'); vg.addColorStop(1, 'rgba(0,0,0,.55)'); ctx.fillStyle = vg; ctx.fillRect(0, 0, W, H);

    const shadow = on => { ctx.shadowColor = on ? 'rgba(0,0,0,.5)' : 'transparent'; ctx.shadowBlur = on ? 22 : 0; ctx.shadowOffsetY = on ? 4 : 0; };

    /* ---------- Kicker ---------- */
    ctx.fillStyle = 'rgba(255,255,255,.55)'; ctx.font = "500 30px 'Satoshi',sans-serif";
    ctx.save(); ctx.translate(W / 2, 150); ctx.fillText('L E T ’ S   C O N N E C T', 0, 0); ctx.restore();

    /* ---------- Avatar with gradient ring + glow ---------- */
    const ax = W / 2, ay = 600, ar = 220;
    const photo = await loadImg(D.photo);
    // glow
    ctx.save(); const ring = ctx.createLinearGradient(ax - ar, ay - ar, ax + ar, ay + ar); ring.addColorStop(0, c1); ring.addColorStop(1, c2);
    ctx.shadowColor = `rgba(${r1},${g1},${b1},.6)`; ctx.shadowBlur = 60;
    ctx.beginPath(); ctx.arc(ax, ay, ar + 12, 0, 6.2832); ctx.fillStyle = ring; ctx.fill(); ctx.restore();
    // inner dark gap
    ctx.beginPath(); ctx.arc(ax, ay, ar + 4, 0, 6.2832); ctx.fillStyle = '#07070c'; ctx.fill();
    ctx.save(); ctx.beginPath(); ctx.arc(ax, ay, ar, 0, 6.2832); ctx.clip();
    if (photo) { const s = Math.max((ar * 2) / photo.width, (ar * 2) / photo.height); ctx.drawImage(photo, ax - photo.width * s / 2, ay - photo.height * s / 2, photo.width * s, photo.height * s); }
    else { ctx.fillStyle = ring; ctx.fillRect(ax - ar, ay - ar, ar * 2, ar * 2); ctx.fillStyle = '#fff'; ctx.font = "700 190px 'Clash Display',sans-serif"; ctx.fillText((name[0] || '?').toUpperCase(), ax, ay + 68); }
    ctx.restore();

    /* ---------- Name (Clash Display) + role (Satoshi) ---------- */
    shadow(true);
    ctx.fillStyle = '#fff'; ctx.font = "700 90px 'Clash Display',sans-serif";
    const nLines = wrapText(ctx, name, W / 2, ay + ar + 140, W - 120, 96, 2);
    let ty = ay + ar + 140 + nLines * 96 + 6;
    if (tagline) { ctx.fillStyle = 'rgba(255,255,255,.78)'; ctx.font = "500 38px 'Satoshi',sans-serif"; ty += wrapText(ctx, tagline, W / 2, ty, W - 220, 50, 2) * 50; }
    shadow(false);

    // gradient divider
    const dv = ctx.createLinearGradient(W / 2 - 70, 0, W / 2 + 70, 0); dv.addColorStop(0, c1); dv.addColorStop(1, c2);
    ctx.fillStyle = dv; roundRect(ctx, W / 2 - 70, ty + 18, 140, 6, 3); ctx.fill();

    /* ---------- CTA pill ---------- */
    ctx.font = "700 48px 'Clash Display',sans-serif";
    const ctaW = Math.min(W - 150, ctx.measureText(ctaText).width + 140), ctaH = 128, ctaX = (W - ctaW) / 2, ctaY = 1300;
    ctx.save(); ctx.shadowColor = `rgba(${r1},${g1},${b1},.55)`; ctx.shadowBlur = 40; ctx.shadowOffsetY = 12;
    roundRect(ctx, ctaX, ctaY, ctaW, ctaH, 64); const pg = ctx.createLinearGradient(ctaX, ctaY, ctaX + ctaW, ctaY); pg.addColorStop(0, c1); pg.addColorStop(1, c2); ctx.fillStyle = pg; ctx.fill(); ctx.restore();
    ctx.fillStyle = '#fff'; ctx.textBaseline = 'middle'; ctx.fillText(ctaText, W / 2, ctaY + ctaH / 2 + 3); ctx.textBaseline = 'alphabetic';
    ctx.fillStyle = 'rgba(255,255,255,.6)'; ctx.font = "500 31px 'Satoshi',sans-serif"; ctx.fillText('Tap the link or scan below', W / 2, ctaY + ctaH + 60);

    /* ---------- QR (glass frame, bottom-left) ---------- */
    if (window.OmniLib && OmniLib.QR) {
      const qs = 200, pad = 18, qx = 88, qy = H - qs - pad * 2 - 78;
      ctx.save(); ctx.shadowColor = `rgba(${r1},${g1},${b1},.5)`; ctx.shadowBlur = 34; roundRect(ctx, qx, qy, qs + pad * 2, qs + pad * 2, 28); ctx.fillStyle = '#fff'; ctx.fill(); ctx.restore();
      ctx.drawImage(OmniLib.qrToCanvas(OmniLib.QR.encode(url, 'MEDIUM'), 8, 1, '#0a0a0f', '#fff'), qx + pad, qy + pad, qs, qs);
      ctx.textAlign = 'left'; ctx.fillStyle = '#fff'; ctx.font = "700 40px 'Clash Display',sans-serif"; ctx.fillText('Scan to connect', qx + qs + pad * 2 + 34, qy + 82);
      ctx.fillStyle = 'rgba(255,255,255,.72)'; ctx.font = "500 30px 'Satoshi',sans-serif"; ctx.fillText(url.replace(/^https?:\/\//, ''), qx + qs + pad * 2 + 34, qy + 132);
      ctx.textAlign = 'center';
    }
    /* ---------- Footer ---------- */
    ctx.fillStyle = 'rgba(255,255,255,.5)'; ctx.font = "500 29px 'Satoshi',sans-serif";
    ctx.fillText('Made with Cardly · ' + (window.OMNITOOLS_BASE || 'apps.briefnepal.com').replace(/^https?:\/\//, ''), W / 2, H - 44);
    return canvas;
  }
})();
