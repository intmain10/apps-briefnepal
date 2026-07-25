/* Cardly contact preview — reveal contact values, QR, share/copy, save vCard. */
(function () {
  'use strict';
  const root = document.querySelector('.cxc');
  if (!root) return;
  const D = root.dataset;
  const url = D.cardUrl || location.href;
  const U = window.OmniUtil || { toast(m) { alert(m); }, copy(t) { navigator.clipboard && navigator.clipboard.writeText(t); } };
  const $ = id => document.getElementById(id);

  /* ---- Reveal protected contact rows ----
     Phone/email/WhatsApp are base64-encoded in the markup (both the tap target
     and the displayed value) so they're absent from the page source. Decode
     them here so real visitors see and can review the details. */
  document.querySelectorAll('[data-cx-to]').forEach(el => {
    try {
      const dest = atob(el.getAttribute('data-cx-to'));
      el.setAttribute('href', dest);
      if (/^https?:/i.test(dest)) { el.target = '_blank'; el.rel = 'noopener'; }
      el.removeAttribute('data-cx-to');
      const val = el.querySelector('b.is-masked');
      if (val && el.dataset.cxVal) {
        val.textContent = atob(el.dataset.cxVal);
        val.classList.remove('is-masked');
      }
      el.removeAttribute('data-cx-val');
    } catch (e) { /* leave the row masked */ }
  });

  /* ---- QR (the same code as on the card, so a scan opens the card) ---- */
  (function () {
    const box = $('cxcQr');
    if (box && window.OmniLib && OmniLib.QR) {
      try { box.appendChild(OmniLib.qrToCanvas(OmniLib.QR.encode(url, 'MEDIUM'), 4, 1, '#0a0a0f', '#ffffff')); }
      catch (e) {}
    }
  })();

  /* ---- Share / copy ---- */
  $('cxcShare') && $('cxcShare').addEventListener('click', async () => {
    if (navigator.share) { try { await navigator.share({ title: document.title, url }); return; } catch (e) {} }
    U.copy(url); U.toast('Link copied');
  });
  $('cxcCopy') && $('cxcCopy').addEventListener('click', () => { U.copy(url); U.toast('Link copied'); });

  /* ---- Save to Contacts ----
     iOS parks an `attachment` vCard in Files, which buries the whole point of
     this page; served inline, Safari hands it straight to Contacts. Android and
     desktop are happier with the download path, so they keep the default. */
  const save = $('cxcSave');
  if (save) {
    const ua = navigator.userAgent || '';
    const iOS = /iPad|iPhone|iPod/.test(ua)
      || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    if (iOS && D.vcf) save.setAttribute('href', D.vcf + '&inline=1');
    save.addEventListener('click', () => {
      save.classList.add('is-busy');
      U.toast('Opening your Contacts app…');
      setTimeout(() => save.classList.remove('is-busy'), 2500);
    });
  }
})();
