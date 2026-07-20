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

  // Dynamic call-to-action + role emoji per profile template.
  const CTA = {
    music:        ['🎵 Listen Now',        '🎤'],
    creator:      ['📺 Watch My Videos',    '🎬'],
    developer:    ['💻 View My Work',       '👨‍💻'],
    business:     ['📞 Get in Touch',       '💼'],
    photographer: ['📸 Explore My Work',    '📷'],
    freelancer:   ['💼 Work With Me',       '✍️'],
    student:      ['🎓 Connect With Me',    '🎓'],
    gym:          ['💪 Train With Me',      '🔥'],
    startup:      ['🚀 Check Us Out',       '⚡'],
    doctor:       ['🩺 Book Appointment',   '🩺'],
    realestate:   ['🏠 View Listings',      '🏡'],
    wedding:      ["💍 You're Invited",     '💐'],
    event:        ['🎉 Join the Event',     '🎊'],
    default:      ['👋 Connect With Me',    '✨'],
  };

  function coverSrc() {
    const el = wrap.querySelector('.cardly__cover');
    if (!el) return '';
    const bg = el.style.backgroundImage || getComputedStyle(el).backgroundImage || '';
    const m = bg.match(/url\(["']?(.*?)["']?\)/);
    return m ? m[1] : '';
  }

  async function buildStoryImage() {
    const canvas = await drawStory();
    return new Promise(res => canvas.toBlob(res, 'image/png', 0.95));
  }

  // A premium, content-first 1080×1920 story image (an ad, not a screenshot).
  async function drawStory() {
    const W = 1080, H = 1920;
    const c1 = cssVar('--c1', '#0071e3'), c2 = cssVar('--c2', '#7c3aed');
    const tplKey = wrap.dataset.template || 'default';
    const [ctaText] = CTA[tplKey] || CTA.default;
    const name = (wrap.querySelector('.cardly__name')?.textContent || 'My Card').trim();
    const tagline = (wrap.querySelector('.cardly__tagline')?.textContent || '').trim();
    const photoSrc = wrap.querySelector('.cardly__avatar img, img.cardly__avatar')?.getAttribute('src') || '';

    const canvas = document.createElement('canvas');
    canvas.width = W; canvas.height = H;
    const ctx = canvas.getContext('2d');
    ctx.textAlign = 'center';

    /* ---- Background: cover image (with overlay) or rich multi-stop gradient ---- */
    const cover = await loadImg(coverSrc());
    if (cover) {
      // cover-fit
      const s = Math.max(W / cover.width, H / cover.height);
      const dw = cover.width * s, dh = cover.height * s;
      ctx.drawImage(cover, (W - dw) / 2, (H - dh) / 2, dw, dh);
      // dark + accent overlay for legibility & mood
      const ov = ctx.createLinearGradient(0, 0, 0, H);
      ov.addColorStop(0, 'rgba(8,8,16,.55)'); ov.addColorStop(0.5, 'rgba(8,8,16,.35)'); ov.addColorStop(1, 'rgba(6,6,12,.92)');
      ctx.fillStyle = ov; ctx.fillRect(0, 0, W, H);
      ctx.globalAlpha = 0.18; const at = ctx.createLinearGradient(0, 0, W, H); at.addColorStop(0, c1); at.addColorStop(1, c2); ctx.fillStyle = at; ctx.fillRect(0, 0, W, H); ctx.globalAlpha = 1;
    } else {
      const g = ctx.createLinearGradient(0, 0, W, H);
      g.addColorStop(0, '#0a0a14'); g.addColorStop(0.35, c2); g.addColorStop(0.7, c1); g.addColorStop(1, '#0a0a14');
      ctx.fillStyle = g; ctx.fillRect(0, 0, W, H);
      // radial glow top
      const rg = ctx.createRadialGradient(W / 2, 380, 40, W / 2, 380, 700);
      rg.addColorStop(0, 'rgba(255,255,255,.18)'); rg.addColorStop(1, 'rgba(255,255,255,0)');
      ctx.fillStyle = rg; ctx.fillRect(0, 0, W, H);
    }
    // subtle particles
    for (let i = 0; i < 40; i++) {
      ctx.globalAlpha = 0.05 + Math.random() * 0.15;
      ctx.beginPath(); ctx.arc(Math.random() * W, Math.random() * H, Math.random() * 3 + 1, 0, 6.28);
      ctx.fillStyle = '#fff'; ctx.fill();
    }
    ctx.globalAlpha = 1;

    const shadow = on => { if (on) { ctx.shadowColor = 'rgba(0,0,0,.45)'; ctx.shadowBlur = 24; ctx.shadowOffsetY = 4; } else { ctx.shadowColor = 'transparent'; ctx.shadowBlur = 0; ctx.shadowOffsetY = 0; } };

    /* ---- Top brand ---- */
    shadow(true);
    ctx.fillStyle = 'rgba(255,255,255,.9)'; ctx.font = '700 40px -apple-system,"Segoe UI",Roboto,sans-serif';
    ctx.fillText('✨ ' + name.split(' ')[0] + '’s card', W / 2, 130);

    /* ---- Big avatar ---- */
    const ax = W / 2, ay = 560, ar = 215;
    const photo = await loadImg(photoSrc);
    ctx.save(); shadow(true);
    ctx.beginPath(); ctx.arc(ax, ay, ar + 10, 0, 6.2832); ctx.fillStyle = 'rgba(255,255,255,.9)'; ctx.fill();
    shadow(false);
    ctx.beginPath(); ctx.arc(ax, ay, ar, 0, 6.2832); ctx.clip();
    if (photo) {
      const s = Math.max((ar * 2) / photo.width, (ar * 2) / photo.height);
      ctx.drawImage(photo, ax - photo.width * s / 2, ay - photo.height * s / 2, photo.width * s, photo.height * s);
    } else {
      const g = ctx.createLinearGradient(ax - ar, ay - ar, ax + ar, ay + ar); g.addColorStop(0, c1); g.addColorStop(1, c2);
      ctx.fillStyle = g; ctx.fillRect(ax - ar, ay - ar, ar * 2, ar * 2);
      ctx.fillStyle = '#fff'; ctx.font = '700 190px -apple-system,sans-serif'; ctx.fillText((name[0] || '?').toUpperCase(), ax, ay + 68);
    }
    ctx.restore();

    /* ---- Name + role ---- */
    shadow(true);
    ctx.fillStyle = '#fff'; ctx.font = '800 82px -apple-system,"Segoe UI",Roboto,sans-serif';
    const nLines = wrapText(ctx, name, W / 2, ay + ar + 130, W - 140, 90, 2);
    let ty = ay + ar + 130 + nLines * 90 + 8;
    if (tagline) {
      ctx.fillStyle = 'rgba(255,255,255,.82)'; ctx.font = '500 40px -apple-system,"Segoe UI",Roboto,sans-serif';
      ty += wrapText(ctx, tagline, W / 2, ty, W - 200, 52, 2) * 52;
    }
    shadow(false);

    /* ---- Prominent CTA pill (the hero) ---- */
    ctx.font = '800 46px -apple-system,"Segoe UI",Roboto,sans-serif';
    const ctaW = Math.min(W - 160, ctx.measureText(ctaText).width + 130);
    const ctaH = 122, ctaX = (W - ctaW) / 2, ctaY = 1230;
    ctx.save(); shadow(true);
    roundRect(ctx, ctaX, ctaY, ctaW, ctaH, 61);
    const pg = ctx.createLinearGradient(ctaX, ctaY, ctaX + ctaW, ctaY); pg.addColorStop(0, c1); pg.addColorStop(1, c2);
    ctx.fillStyle = pg; ctx.fill(); ctx.restore();
    ctx.fillStyle = '#fff'; ctx.textBaseline = 'middle';
    ctx.fillText(ctaText, W / 2, ctaY + ctaH / 2 + 2); ctx.textBaseline = 'alphabetic';

    ctx.fillStyle = 'rgba(255,255,255,.7)'; ctx.font = '500 32px -apple-system,sans-serif';
    ctx.fillText('Tap the link or scan below ↓', W / 2, ctaY + ctaH + 62);

    /* ---- QR bottom-left corner (secondary) ---- */
    if (window.OmniLib && OmniLib.QR) {
      const qs = 210, pad = 16, qx = 90, qy = H - qs - pad * 2 - 70;
      ctx.save(); shadow(true); roundRect(ctx, qx, qy, qs + pad * 2, qs + pad * 2, 26); ctx.fillStyle = '#fff'; ctx.fill(); ctx.restore();
      const qc = OmniLib.qrToCanvas(OmniLib.QR.encode(url, 'MEDIUM'), 8, 1, '#111', '#fff');
      ctx.drawImage(qc, qx + pad, qy + pad, qs, qs);
      // text to the right of the QR
      ctx.textAlign = 'left'; ctx.fillStyle = '#fff'; ctx.font = '700 38px -apple-system,sans-serif';
      ctx.fillText('Scan to connect', qx + qs + pad * 2 + 34, qy + 78);
      ctx.fillStyle = 'rgba(255,255,255,.75)'; ctx.font = '500 30px -apple-system,sans-serif';
      ctx.fillText(url.replace(/^https?:\/\//, ''), qx + qs + pad * 2 + 34, qy + 128);
      ctx.textAlign = 'center';
    }

    /* ---- Footer branding ---- */
    ctx.fillStyle = 'rgba(255,255,255,.55)'; ctx.font = '500 30px -apple-system,sans-serif';
    ctx.fillText('Made with Cardly · ' + (window.OMNITOOLS_BASE || 'apps.briefnepal.com').replace(/^https?:\/\//, ''), W / 2, H - 44);

    return canvas;
  }

})();
