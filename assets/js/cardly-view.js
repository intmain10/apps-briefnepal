/* Cardly public card — share + QR interactions. */
(function () {
  'use strict';
  const wrap = document.querySelector('.cardly');
  if (!wrap) return;
  const url = wrap.dataset.cardUrl || location.href;
  const U = window.OmniUtil;

  const shareBtn = document.getElementById('cardlyShare');
  if (shareBtn) shareBtn.addEventListener('click', async () => {
    if (navigator.share) { try { await navigator.share({ title: document.title, url }); return; } catch (e) {} }
    (U ? U.copy(url) : navigator.clipboard.writeText(url));
    if (U) U.toast('Link copied');
  });

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
})();
