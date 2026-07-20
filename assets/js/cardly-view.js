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
    if (_fontLoaded || !window.FontFace) return;
    try { const f = new FontFace('Sora', "url('" + (window.OMNITOOLS_BASE || '') + "/assets/fonts/sora.woff2')", { weight: '100 800' }); await f.load(); document.fonts.add(f); } catch (e) {}
    _fontLoaded = true;
  }
  function drawPattern(ctx, key, c1, c2, W, H) {
    const style = ({ music: 'waves', developer: 'grid', business: 'grid', startup: 'grid', creator: 'rays', event: 'rays', wedding: 'blobs', freelancer: 'blobs', student: 'blobs', photographer: 'rings', doctor: 'rings', gym: 'streaks', realestate: 'streaks' })[key] || 'blobs';
    const g = ctx.createLinearGradient(0, 0, W, H); g.addColorStop(0, c1); g.addColorStop(1, c2); ctx.fillStyle = g; ctx.fillRect(0, 0, W, H);
    const v = ctx.createLinearGradient(0, 0, 0, H); v.addColorStop(0, 'rgba(8,8,18,.45)'); v.addColorStop(.45, 'rgba(8,8,18,.12)'); v.addColorStop(1, 'rgba(6,6,14,.86)'); ctx.fillStyle = v; ctx.fillRect(0, 0, W, H);
    ctx.save();
    if (style === 'waves') { ctx.strokeStyle = '#fff'; ctx.lineWidth = 6; for (let k = 0; k < 6; k++) { ctx.beginPath(); const base = H * 0.6 + k * 40; for (let x = 0; x <= W; x += 12) { const y = base + Math.sin((x / W) * Math.PI * 4 + k) * (46 + k * 6); x === 0 ? ctx.moveTo(x, y) : ctx.lineTo(x, y); } ctx.globalAlpha = 0.12 - k * 0.015; ctx.stroke(); } }
    else if (style === 'grid') { ctx.globalAlpha = .08; ctx.strokeStyle = '#fff'; ctx.lineWidth = 2; for (let x = 0; x <= W; x += 72) { ctx.beginPath(); ctx.moveTo(x, 0); ctx.lineTo(x, H); ctx.stroke(); } for (let y = 0; y <= H; y += 72) { ctx.beginPath(); ctx.moveTo(0, y); ctx.lineTo(W, y); ctx.stroke(); } }
    else if (style === 'rays') { ctx.globalAlpha = .07; ctx.fillStyle = '#fff'; const cx = W / 2, cy = -120, len = H * 1.6; for (let a = 0; a < 22; a++) { const ang = (a / 22) * Math.PI; ctx.beginPath(); ctx.moveTo(cx, cy); ctx.lineTo(cx + Math.cos(ang) * len, cy + Math.sin(ang) * len); ctx.lineTo(cx + Math.cos(ang + .045) * len, cy + Math.sin(ang + .045) * len); ctx.closePath(); ctx.fill(); } }
    else if (style === 'rings') { ctx.globalAlpha = .1; ctx.strokeStyle = '#fff'; ctx.lineWidth = 3; for (let r = 120; r < 1500; r += 120) { ctx.beginPath(); ctx.arc(W / 2, H * 0.42, r, 0, 6.2832); ctx.stroke(); } }
    else if (style === 'streaks') { ctx.globalAlpha = .1; ctx.strokeStyle = '#fff'; ctx.lineWidth = 42; for (let i = -2; i < 15; i++) { ctx.beginPath(); ctx.moveTo(i * 130 - 200, H + 120); ctx.lineTo(i * 130 + 340, -120); ctx.stroke(); } }
    else { [[W * .2, H * .2, '#fff'], [W * .85, H * .32, c1], [W * .25, H * .82, c2], [W * .82, H * .86, '#fff']].forEach(([bx, by, col]) => { const rg = ctx.createRadialGradient(bx, by, 10, bx, by, 540); ctx.globalAlpha = .22; rg.addColorStop(0, col); rg.addColorStop(1, 'rgba(0,0,0,0)'); ctx.fillStyle = rg; ctx.fillRect(0, 0, W, H); }); }
    ctx.restore(); ctx.globalAlpha = 1;
  }

  async function buildStoryImage() { return new Promise(async res => { const c = await drawStory(); c.toBlob(b => res(b), 'image/png', 0.95); }); }

  async function drawStory() {
    const W = 1080, H = 1920;
    const c1 = cssVar('--c1', '#0071e3'), c2 = cssVar('--c2', '#7c3aed');
    const tplKey = D.template || 'default';
    const ctaText = CTA[tplKey] || CTA.default;
    const name = (D.name || 'My Card').trim();
    const tagline = (D.tagline || '').trim();
    await ensureFont();
    const canvas = document.createElement('canvas'); canvas.width = W; canvas.height = H;
    const ctx = canvas.getContext('2d'); ctx.textAlign = 'center';

    const cover = await loadImg(D.cover);
    if (cover) {
      const s = Math.max(W / cover.width, H / cover.height), dw = cover.width * s, dh = cover.height * s;
      ctx.drawImage(cover, (W - dw) / 2, (H - dh) / 2, dw, dh);
      const ov = ctx.createLinearGradient(0, 0, 0, H); ov.addColorStop(0, 'rgba(8,8,16,.55)'); ov.addColorStop(.5, 'rgba(8,8,16,.35)'); ov.addColorStop(1, 'rgba(6,6,12,.92)'); ctx.fillStyle = ov; ctx.fillRect(0, 0, W, H);
    } else drawPattern(ctx, tplKey, c1, c2, W, H);
    for (let i = 0; i < 36; i++) { ctx.globalAlpha = .05 + Math.random() * .12; ctx.beginPath(); ctx.arc(Math.random() * W, Math.random() * H, Math.random() * 3 + 1, 0, 6.28); ctx.fillStyle = '#fff'; ctx.fill(); }
    ctx.globalAlpha = 1;

    const shadow = on => { ctx.shadowColor = on ? 'rgba(0,0,0,.45)' : 'transparent'; ctx.shadowBlur = on ? 24 : 0; ctx.shadowOffsetY = on ? 4 : 0; };
    shadow(true);
    ctx.fillStyle = 'rgba(255,255,255,.9)'; ctx.font = '700 40px Sora,sans-serif';
    ctx.fillText('✨ ' + name.split(' ')[0] + '’s card', W / 2, 130);

    const ax = W / 2, ay = 560, ar = 215;
    const photo = await loadImg(D.photo);
    ctx.save(); shadow(true); ctx.beginPath(); ctx.arc(ax, ay, ar + 10, 0, 6.2832); ctx.fillStyle = 'rgba(255,255,255,.9)'; ctx.fill(); shadow(false);
    ctx.beginPath(); ctx.arc(ax, ay, ar, 0, 6.2832); ctx.clip();
    if (photo) { const s = Math.max((ar * 2) / photo.width, (ar * 2) / photo.height); ctx.drawImage(photo, ax - photo.width * s / 2, ay - photo.height * s / 2, photo.width * s, photo.height * s); }
    else { const g = ctx.createLinearGradient(ax - ar, ay - ar, ax + ar, ay + ar); g.addColorStop(0, c1); g.addColorStop(1, c2); ctx.fillStyle = g; ctx.fillRect(ax - ar, ay - ar, ar * 2, ar * 2); ctx.fillStyle = '#fff'; ctx.font = '700 190px Sora,sans-serif'; ctx.fillText((name[0] || '?').toUpperCase(), ax, ay + 68); }
    ctx.restore();

    shadow(true);
    ctx.fillStyle = '#fff'; ctx.font = '800 82px Sora,sans-serif';
    const nLines = wrapText(ctx, name, W / 2, ay + ar + 130, W - 140, 90, 2);
    let ty = ay + ar + 130 + nLines * 90 + 8;
    if (tagline) { ctx.fillStyle = 'rgba(255,255,255,.82)'; ctx.font = '500 40px Sora,sans-serif'; ty += wrapText(ctx, tagline, W / 2, ty, W - 200, 52, 2) * 52; }
    shadow(false);

    ctx.font = '800 46px Sora,sans-serif';
    const ctaW = Math.min(W - 160, ctx.measureText(ctaText).width + 130), ctaH = 122, ctaX = (W - ctaW) / 2, ctaY = 1230;
    ctx.save(); shadow(true); roundRect(ctx, ctaX, ctaY, ctaW, ctaH, 61); const pg = ctx.createLinearGradient(ctaX, ctaY, ctaX + ctaW, ctaY); pg.addColorStop(0, c1); pg.addColorStop(1, c2); ctx.fillStyle = pg; ctx.fill(); ctx.restore();
    ctx.fillStyle = '#fff'; ctx.textBaseline = 'middle'; ctx.fillText(ctaText, W / 2, ctaY + ctaH / 2 + 2); ctx.textBaseline = 'alphabetic';
    ctx.fillStyle = 'rgba(255,255,255,.7)'; ctx.font = '500 32px Sora,sans-serif'; ctx.fillText('Tap the link or scan below ↓', W / 2, ctaY + ctaH + 62);

    if (window.OmniLib && OmniLib.QR) {
      const qs = 210, pad = 16, qx = 90, qy = H - qs - pad * 2 - 70;
      ctx.save(); shadow(true); roundRect(ctx, qx, qy, qs + pad * 2, qs + pad * 2, 26); ctx.fillStyle = '#fff'; ctx.fill(); ctx.restore();
      ctx.drawImage(OmniLib.qrToCanvas(OmniLib.QR.encode(url, 'MEDIUM'), 8, 1, '#111', '#fff'), qx + pad, qy + pad, qs, qs);
      ctx.textAlign = 'left'; ctx.fillStyle = '#fff'; ctx.font = '700 38px Sora,sans-serif'; ctx.fillText('Scan to connect', qx + qs + pad * 2 + 34, qy + 78);
      ctx.fillStyle = 'rgba(255,255,255,.75)'; ctx.font = '500 30px Sora,sans-serif'; ctx.fillText(url.replace(/^https?:\/\//, ''), qx + qs + pad * 2 + 34, qy + 128);
      ctx.textAlign = 'center';
    }
    ctx.fillStyle = 'rgba(255,255,255,.55)'; ctx.font = '500 30px Sora,sans-serif';
    ctx.fillText('Made with Cardly · ' + (window.OMNITOOLS_BASE || 'apps.briefnepal.com').replace(/^https?:\/\//, ''), W / 2, H - 44);
    return canvas;
  }
})();
