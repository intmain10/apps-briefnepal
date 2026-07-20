/* Cardly public card — share, QR, and Instagram-Story image export. */
(function () {
  'use strict';
  const wrap = document.querySelector('.cardly');
  if (!wrap) return;
  const url = wrap.dataset.cardUrl || location.href;
  const U = window.OmniUtil || { toast(m) { alert(m); }, copy(t) { navigator.clipboard && navigator.clipboard.writeText(t); }, download() {} };

  /* --------------------------------------------------------------- Share */
  const shareBtn = document.getElementById('cardlyShare');
  if (shareBtn) shareBtn.addEventListener('click', async () => {
    if (navigator.share) { try { await navigator.share({ title: document.title, url }); return; } catch (e) {} }
    U.copy(url); U.toast('Link copied');
  });

  /* ----------------------------------------------------------------- QR */
  const qrBtn = document.getElementById('cardlyQr');
  const modal = document.getElementById('cardlyQrModal');
  const canvasWrap = document.getElementById('cardlyQrCanvas');
  let drawn = false;
  if (qrBtn && modal) {
    qrBtn.addEventListener('click', () => {
      if (!drawn && window.OmniLib && OmniLib.QR) {
        try {
          const qr = OmniLib.QR.encode(url, 'MEDIUM');
          const c = OmniLib.qrToCanvas(qr, 6, 4, '#000', '#fff');
          c.style.width = '240px'; c.style.height = '240px'; c.style.borderRadius = '10px';
          canvasWrap.appendChild(c); drawn = true;
        } catch (e) { canvasWrap.textContent = 'QR unavailable'; }
      }
      modal.hidden = false;
    });
    const close = () => modal.hidden = true;
    document.getElementById('cardlyQrClose').addEventListener('click', close);
    modal.addEventListener('click', e => { if (e.target === modal) close(); });
  }

  /* ------------------------------------------- Instagram Story image (9:16) */
  const storyBtn = document.getElementById('cardlyStory');
  if (storyBtn) storyBtn.addEventListener('click', async () => {
    const orig = storyBtn.textContent;
    storyBtn.textContent = 'Creating…'; storyBtn.disabled = true;
    try {
      const blob = await buildStoryImage();
      const file = new File([blob], 'cardly-story.png', { type: 'image/png' });
      // Prefer native share (mobile) → lets you pick Instagram → Stories.
      if (navigator.canShare && navigator.canShare({ files: [file] })) {
        await navigator.share({ files: [file], title: document.title, text: url });
      } else {
        U.download('cardly-story.png', blob);
        U.toast('Image saved — open Instagram Story and add it from your gallery');
      }
    } catch (e) {
      U.toast('Could not create the story image');
    } finally {
      storyBtn.textContent = orig; storyBtn.disabled = false;
    }
  });

  function cssVar(name, fallback) {
    const v = getComputedStyle(wrap).getPropertyValue(name).trim();
    return v || fallback;
  }
  function loadImg(src) {
    return new Promise(res => {
      if (!src) return res(null);
      const img = new Image(); img.crossOrigin = 'anonymous';
      img.onload = () => res(img); img.onerror = () => res(null); img.src = src;
    });
  }
  function roundRect(ctx, x, y, w, h, r) {
    ctx.beginPath();
    ctx.moveTo(x + r, y);
    ctx.arcTo(x + w, y, x + w, y + h, r);
    ctx.arcTo(x + w, y + h, x, y + h, r);
    ctx.arcTo(x, y + h, x, y, r);
    ctx.arcTo(x, y, x + w, y, r);
    ctx.closePath();
  }
  function wrapText(ctx, text, x, y, maxW, lh, maxLines) {
    const words = (text || '').split(/\s+/); let line = '', lines = [];
    for (const w of words) {
      const test = line ? line + ' ' + w : w;
      if (ctx.measureText(test).width > maxW && line) { lines.push(line); line = w; }
      else line = test;
      if (lines.length >= maxLines) break;
    }
    if (line && lines.length < maxLines) lines.push(line);
    lines.forEach((l, i) => ctx.fillText(l, x, y + i * lh));
    return lines.length;
  }

  async function buildStoryImage() {
    const canvas = await drawStory();
    return new Promise(res => canvas.toBlob(res, 'image/png', 0.95));
  }

  async function drawStory() {
    const W = 1080, H = 1920;
    const c1 = cssVar('--c1', '#0071e3'), c2 = cssVar('--c2', '#7c3aed');
    const nameEl = wrap.querySelector('.cardly__name');
    const tagEl = wrap.querySelector('.cardly__tagline');
    const avatarEl = wrap.querySelector('.cardly__avatar img, img.cardly__avatar');
    const name = nameEl ? nameEl.textContent.trim() : 'My Card';
    const tagline = tagEl ? tagEl.textContent.trim() : '';
    const photoSrc = avatarEl ? avatarEl.getAttribute('src') : '';

    const canvas = document.createElement('canvas');
    canvas.width = W; canvas.height = H;
    const ctx = canvas.getContext('2d');
    ctx.textAlign = 'center';

    // Background: dark with an accent gradient glow.
    ctx.fillStyle = '#0a0a12'; ctx.fillRect(0, 0, W, H);
    const bg = ctx.createLinearGradient(0, 0, W, H);
    bg.addColorStop(0, c1); bg.addColorStop(1, c2);
    ctx.globalAlpha = 0.22; ctx.fillStyle = bg; ctx.fillRect(0, 0, W, H); ctx.globalAlpha = 1;

    // Header
    ctx.fillStyle = 'rgba(255,255,255,.85)';
    ctx.font = '600 44px -apple-system, "Segoe UI", Roboto, sans-serif';
    ctx.fillText("Let’s connect 👋", W / 2, 170);

    // Card panel
    const cx = 110, cy = 250, cw = W - 220, ch = 1200, r = 56;
    ctx.save();
    ctx.shadowColor = 'rgba(0,0,0,.5)'; ctx.shadowBlur = 60; ctx.shadowOffsetY = 20;
    roundRect(ctx, cx, cy, cw, ch, r); ctx.fillStyle = '#16161f'; ctx.fill();
    ctx.restore();

    // Cover band (rounded top) with accent gradient
    ctx.save();
    roundRect(ctx, cx, cy, cw, ch, r); ctx.clip();
    const cover = ctx.createLinearGradient(cx, cy, cx + cw, cy);
    cover.addColorStop(0, c1); cover.addColorStop(1, c2);
    ctx.fillStyle = cover; ctx.fillRect(cx, cy, cw, 300);
    ctx.restore();

    // Avatar
    const ax = W / 2, ay = cy + 300, ar = 130;
    const photo = await loadImg(photoSrc);
    ctx.save();
    ctx.beginPath(); ctx.arc(ax, ay, ar + 8, 0, Math.PI * 2); ctx.fillStyle = '#16161f'; ctx.fill();
    ctx.beginPath(); ctx.arc(ax, ay, ar, 0, Math.PI * 2); ctx.closePath(); ctx.clip();
    if (photo) {
      ctx.drawImage(photo, ax - ar, ay - ar, ar * 2, ar * 2);
    } else {
      const g = ctx.createLinearGradient(ax - ar, ay - ar, ax + ar, ay + ar);
      g.addColorStop(0, c1); g.addColorStop(1, c2); ctx.fillStyle = g; ctx.fillRect(ax - ar, ay - ar, ar * 2, ar * 2);
      ctx.fillStyle = '#fff'; ctx.font = '700 120px -apple-system, sans-serif';
      ctx.fillText((name[0] || '?').toUpperCase(), ax, ay + 44);
    }
    ctx.restore();

    // Name + tagline
    ctx.fillStyle = '#fff';
    ctx.font = '700 66px -apple-system, "Segoe UI", Roboto, sans-serif';
    const nameLines = wrapText(ctx, name, W / 2, ay + ar + 100, cw - 120, 76, 2);
    let ty = ay + ar + 100 + nameLines * 76 + 10;
    if (tagline) {
      ctx.fillStyle = 'rgba(255,255,255,.7)';
      ctx.font = '400 38px -apple-system, "Segoe UI", Roboto, sans-serif';
      ty += wrapText(ctx, tagline, W / 2, ty, cw - 160, 48, 2) * 48;
    }

    // QR code
    if (window.OmniLib && OmniLib.QR) {
      const qc = OmniLib.qrToCanvas(OmniLib.QR.encode(url, 'MEDIUM'), 8, 2, '#111111', '#ffffff');
      const qs = 340, qx = (W - qs) / 2, qy = cy + ch - qs - 120;
      ctx.save(); roundRect(ctx, qx - 18, qy - 18, qs + 36, qs + 36, 24); ctx.fillStyle = '#fff'; ctx.fill(); ctx.restore();
      ctx.drawImage(qc, qx, qy, qs, qs);
      ctx.fillStyle = 'rgba(255,255,255,.75)';
      ctx.font = '600 34px -apple-system, sans-serif';
      ctx.fillText('Scan to open my card', W / 2, qy + qs + 60);
    }

    // Footer
    ctx.fillStyle = 'rgba(255,255,255,.9)';
    ctx.font = '600 38px -apple-system, sans-serif';
    ctx.fillText(url.replace(/^https?:\/\//, ''), W / 2, cy + ch + 90);
    ctx.fillStyle = 'rgba(255,255,255,.5)';
    ctx.font = '400 30px -apple-system, sans-serif';
    ctx.fillText('Made with Cardly', W / 2, cy + ch + 140);

    return canvas;
  }

  // Exposed for automated visual testing.
  window.__cardlyDrawStory = drawStory;
})();
