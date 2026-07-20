/* =========================================================================
   OmniTools — PDF Engines (client-side, private)
   Powered by vendored pdf-lib (create/edit) + pdf.js (render/read).
   Loaded only on PDF-category tool pages. Registers into window.OmniEngines,
   which assets/js/tools.js bootstraps.
   ========================================================================= */
(function () {
  'use strict';

  const U = window.OmniUtil || { toast() {}, copy() {}, download() {}, escape: s => s };
  const engines = (window.OmniEngines = window.OmniEngines || {});
  const reg = (slug, fn) => { engines[slug] = fn; };
  const esc = s => U.escape(String(s == null ? '' : s));
  const BASE = window.OMNITOOLS_BASE || '';

  // pdf.js worker (local, no CDN).
  if (window.pdfjsLib) {
    window.pdfjsLib.GlobalWorkerOptions.workerSrc = BASE + '/assets/js/vendor/pdf.worker.min.js';
  }

  /* -------------------------------------------------------- small helpers */
  const q = (s, r) => r.querySelector(s);
  const qa = (s, r) => Array.from(r.querySelectorAll(s));
  function fmtBytes(b) { if (b < 1024) return b + ' B'; const u = ['KB', 'MB', 'GB']; let i = -1; do { b /= 1024; i++; } while (b >= 1024 && i < 2); return b.toFixed(b < 10 ? 2 : 1) + ' ' + u[i]; }
  const readBytes = file => file.arrayBuffer().then(b => new Uint8Array(b));
  function svgUp(cls) { return `<svg class="${cls || 'dropzone__icon'}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" x2="12" y1="3" y2="15"/></svg>`; }

  function dropzone(container, opts) {
    container.innerHTML = `
      <div class="dropzone" tabindex="0" role="button" aria-label="Upload">
        ${svgUp()}
        <div><strong>Click to upload</strong> or drag &amp; drop</div>
        <div class="dropzone__hint">${opts.hint || 'Processed privately in your browser — files never leave your device.'}</div>
        <input type="file" ${opts.multiple ? 'multiple' : ''} accept="${opts.accept || 'application/pdf'}" hidden>
      </div>`;
    const dz = q('.dropzone', container), input = q('input', container);
    dz.addEventListener('click', () => input.click());
    dz.addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); input.click(); } });
    input.addEventListener('change', () => { if (input.files.length) opts.onFiles(Array.from(input.files)); });
    ['dragover', 'dragenter'].forEach(ev => dz.addEventListener(ev, e => { e.preventDefault(); dz.classList.add('is-drag'); }));
    ['dragleave', 'drop'].forEach(ev => dz.addEventListener(ev, e => { e.preventDefault(); dz.classList.remove('is-drag'); }));
    dz.addEventListener('drop', e => { if (e.dataTransfer.files.length) opts.onFiles(Array.from(e.dataTransfer.files)); });
  }

  const spinner = msg => `<div class="row" style="align-items:center"><div class="spinner"></div><span>${esc(msg || 'Working…')}</span></div>`;
  const errBox = m => `<div class="notice notice--error">${esc(m)}</div>`;
  const okBox = m => `<div class="notice notice--success">${esc(m)}</div>`;

  function fileListHtml(files) {
    return files.map((f, i) => `<div class="row" data-i="${i}" style="align-items:center;border-bottom:1px solid var(--border);padding:8px 0">
      <span style="min-width:0;overflow:hidden;text-overflow:ellipsis">${esc(f.name)}</span>
      <span class="muted" style="text-align:right;flex:none">${fmtBytes(f.size)}</span></div>`).join('');
  }

  async function loadDoc(file, opts) {
    const bytes = await readBytes(file);
    return window.PDFLib.PDFDocument.load(bytes, Object.assign({ ignoreEncryption: true }, opts || {}));
  }
  function downloadPdf(bytes, name) {
    U.download(name || 'output.pdf', new Blob([bytes], { type: 'application/pdf' }));
  }

  /** Parse a page-range string like "1-3,5,8-10" into 0-based indices within [0,total). */
  function parseRanges(str, total) {
    const out = [];
    (str || '').split(',').forEach(part => {
      part = part.trim(); if (!part) return;
      const m = part.match(/^(\d+)\s*-\s*(\d+)$/);
      if (m) { let a = +m[1], b = +m[2]; if (a > b) [a, b] = [b, a]; for (let p = a; p <= b; p++) if (p >= 1 && p <= total) out.push(p - 1); }
      else if (/^\d+$/.test(part)) { const p = +part; if (p >= 1 && p <= total) out.push(p - 1); }
    });
    return out;
  }

  /* Render a pdf.js page to a canvas at a given scale. */
  async function renderPage(pdf, pageNum, scale) {
    const page = await pdf.getPage(pageNum);
    const viewport = page.getViewport({ scale: scale || 1.5 });
    const canvas = document.createElement('canvas');
    canvas.width = Math.floor(viewport.width); canvas.height = Math.floor(viewport.height);
    await page.render({ canvasContext: canvas.getContext('2d'), viewport }).promise;
    return canvas;
  }
  const getPdfjs = bytes => window.pdfjsLib.getDocument({ data: bytes }).promise;

  /* =======================================================================
     MERGE
     ==================================================================== */
  reg('merge-pdf', root => {
    root.innerHTML = `<div id="drop"></div>
      <p class="muted mt-4" id="hint" style="display:none">Drag rows to reorder before merging.</p>
      <div id="list" class="mt-4"></div>
      <div class="btn-row mt-4" id="bar" style="display:none"><button class="btn btn--primary" id="go">Merge PDFs</button><button class="btn btn--ghost" id="clr">Clear</button></div>
      <div id="msg" class="mt-4"></div>`;
    let files = [];
    dropzone(q('#drop', root), { accept: 'application/pdf', multiple: true, onFiles: fl => { files = files.concat(fl.filter(f => f.type === 'application/pdf' || /\.pdf$/i.test(f.name))); render(); } });
    function render() {
      q('#list', root).innerHTML = files.map((f, i) => `<div class="row" draggable="true" data-i="${i}" style="align-items:center;border:1px solid var(--border);border-radius:10px;padding:10px;margin-bottom:8px;cursor:grab">
        <span style="min-width:0;overflow:hidden;text-overflow:ellipsis">☰ ${esc(f.name)}</span>
        <span class="muted" style="text-align:right;flex:none">${fmtBytes(f.size)} <button class="btn btn--ghost btn--sm" data-del="${i}">✕</button></span></div>`).join('');
      q('#bar', root).style.display = files.length ? '' : 'none';
      q('#hint', root).style.display = files.length > 1 ? '' : 'none';
      qa('[data-del]', root).forEach(b => b.addEventListener('click', e => { e.stopPropagation(); files.splice(+b.dataset.del, 1); render(); }));
      let dragI = null;
      qa('.row[draggable]', root).forEach(rowEl => {
        rowEl.addEventListener('dragstart', () => dragI = +rowEl.dataset.i);
        rowEl.addEventListener('dragover', e => e.preventDefault());
        rowEl.addEventListener('drop', e => { e.preventDefault(); const to = +rowEl.dataset.i; const [m] = files.splice(dragI, 1); files.splice(to, 0, m); render(); });
      });
    }
    q('#clr', root)?.addEventListener('click', () => { files = []; render(); });
    q('#go', root).addEventListener('click', async () => {
      if (files.length < 2) { q('#msg', root).innerHTML = errBox('Add at least two PDF files.'); return; }
      q('#msg', root).innerHTML = spinner('Merging…');
      try {
        const out = await window.PDFLib.PDFDocument.create();
        for (const f of files) {
          const src = await loadDoc(f);
          const pages = await out.copyPages(src, src.getPageIndices());
          pages.forEach(p => out.addPage(p));
        }
        downloadPdf(await out.save(), 'merged.pdf');
        q('#msg', root).innerHTML = okBox(`Merged ${files.length} files into one PDF.`);
      } catch (e) { q('#msg', root).innerHTML = errBox('Merge failed: ' + e.message); }
    });
  });

  /* =======================================================================
     SPLIT / EXTRACT
     ==================================================================== */
  reg('split-pdf', root => {
    root.innerHTML = `<div id="drop"></div>
      <div id="opts" class="mt-4" style="display:none">
        <div class="row"><div><label class="field__label">Pages to extract (e.g. 1-3,5,8)</label><input id="rng" class="input" placeholder="1-3,5"></div></div>
        <div class="chips mt-4"><label class="chip"><input type="radio" name="mode" value="extract" checked> Extract into one PDF</label>
        <label class="chip"><input type="radio" name="mode" value="each"> Each page as a separate PDF</label></div>
        <div class="btn-row mt-4"><button class="btn btn--primary" id="go">Split PDF</button></div>
      </div><div id="msg" class="mt-4"></div>`;
    let file, total = 0;
    dropzone(q('#drop', root), { onFiles: async fl => { file = fl[0]; try { const d = await loadDoc(file); total = d.getPageCount(); q('#opts', root).style.display = ''; q('#msg', root).innerHTML = `<span class="muted">${total} pages. Leave blank to use all.</span>`; } catch (e) { q('#msg', root).innerHTML = errBox('Could not read PDF: ' + e.message); } } });
    q('#go', root).addEventListener('click', async () => {
      if (!file) return;
      q('#msg', root).innerHTML = spinner('Splitting…');
      try {
        const src = await loadDoc(file);
        let idx = parseRanges(q('#rng', root).value, total);
        if (!idx.length) idx = src.getPageIndices();
        const mode = q('input[name=mode]:checked', root).value;
        if (mode === 'each') {
          for (let k = 0; k < idx.length; k++) {
            const out = await window.PDFLib.PDFDocument.create();
            const [pg] = await out.copyPages(src, [idx[k]]); out.addPage(pg);
            downloadPdf(await out.save(), `page-${idx[k] + 1}.pdf`);
          }
          q('#msg', root).innerHTML = okBox(`Exported ${idx.length} separate PDF(s).`);
        } else {
          const out = await window.PDFLib.PDFDocument.create();
          const pgs = await out.copyPages(src, idx); pgs.forEach(p => out.addPage(p));
          downloadPdf(await out.save(), 'extracted.pdf');
          q('#msg', root).innerHTML = okBox(`Extracted ${idx.length} page(s).`);
        }
      } catch (e) { q('#msg', root).innerHTML = errBox('Split failed: ' + e.message); }
    });
  });

  /* =======================================================================
     ORGANIZE (delete pages)
     ==================================================================== */
  reg('organize-pdf', root => {
    root.innerHTML = `<div id="drop"></div><div id="grid" class="mt-4" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:12px"></div>
      <div class="btn-row mt-4" id="bar" style="display:none"><button class="btn btn--primary" id="go">Save PDF</button><span class="muted" id="cnt"></span></div>
      <div id="msg" class="mt-4"></div>`;
    let file, keep = [];
    dropzone(q('#drop', root), { onFiles: async fl => {
      file = fl[0]; q('#msg', root).innerHTML = spinner('Rendering pages…');
      try {
        const bytes = await readBytes(file); const pdf = await getPdfjs(bytes.slice());
        keep = []; const g = q('#grid', root); g.innerHTML = '';
        for (let i = 1; i <= pdf.numPages; i++) {
          const c = await renderPage(pdf, i, 0.35); keep[i - 1] = true;
          const cell = document.createElement('div');
          cell.style.cssText = 'border:2px solid var(--accent);border-radius:8px;overflow:hidden;position:relative;cursor:pointer';
          cell.innerHTML = `<div style="position:absolute;top:4px;right:4px;background:var(--danger);color:#fff;border-radius:50%;width:22px;height:22px;display:none;place-items:center;font-size:12px">✕</div><div style="position:absolute;bottom:0;left:0;right:0;background:rgba(0,0,0,.5);color:#fff;font-size:11px;text-align:center">Page ${i}</div>`;
          c.style.width = '100%'; c.style.display = 'block'; cell.insertBefore(c, cell.firstChild);
          const idx = i - 1;
          cell.addEventListener('click', () => { keep[idx] = !keep[idx]; cell.style.borderColor = keep[idx] ? 'var(--accent)' : 'var(--border)'; cell.style.opacity = keep[idx] ? '1' : '.4'; cell.querySelector('div').style.display = keep[idx] ? 'none' : 'grid'; updateCnt(); });
          g.appendChild(cell);
        }
        q('#bar', root).style.display = ''; q('#msg', root).innerHTML = '<span class="muted">Click pages to remove them, then Save.</span>'; updateCnt();
      } catch (e) { q('#msg', root).innerHTML = errBox('Could not render PDF: ' + e.message); }
    }});
    function updateCnt() { const k = keep.filter(Boolean).length; q('#cnt', root).textContent = `${k} of ${keep.length} pages kept`; }
    q('#go', root).addEventListener('click', async () => {
      const idx = keep.map((v, i) => v ? i : -1).filter(i => i >= 0);
      if (!idx.length) { q('#msg', root).innerHTML = errBox('Keep at least one page.'); return; }
      q('#msg', root).innerHTML = spinner('Saving…');
      try { const src = await loadDoc(file); const out = await window.PDFLib.PDFDocument.create(); const pgs = await out.copyPages(src, idx); pgs.forEach(p => out.addPage(p)); downloadPdf(await out.save(), 'organized.pdf'); q('#msg', root).innerHTML = okBox(`Saved PDF with ${idx.length} page(s).`); }
      catch (e) { q('#msg', root).innerHTML = errBox('Save failed: ' + e.message); }
    });
  });

  /* =======================================================================
     ROTATE
     ==================================================================== */
  reg('rotate-pdf', root => {
    root.innerHTML = `<div id="drop"></div>
      <div id="opts" class="mt-4" style="display:none">
        <div class="row"><div><label class="field__label">Rotate by</label><select id="ang" class="select"><option value="90">90° clockwise</option><option value="180">180°</option><option value="270">90° counter-clockwise</option></select></div>
        <div><label class="field__label">Pages (blank = all)</label><input id="rng" class="input" placeholder="e.g. 1-3,5"></div></div>
        <div class="btn-row mt-4"><button class="btn btn--primary" id="go">Rotate PDF</button></div>
      </div><div id="msg" class="mt-4"></div>`;
    let file, total = 0;
    dropzone(q('#drop', root), { onFiles: async fl => { file = fl[0]; try { const d = await loadDoc(file); total = d.getPageCount(); q('#opts', root).style.display = ''; } catch (e) { q('#msg', root).innerHTML = errBox(e.message); } } });
    q('#go', root).addEventListener('click', async () => {
      if (!file) return; q('#msg', root).innerHTML = spinner('Rotating…');
      try {
        const doc = await loadDoc(file); const add = +q('#ang', root).value;
        let idx = parseRanges(q('#rng', root).value, total); if (!idx.length) idx = doc.getPageIndices();
        const pages = doc.getPages();
        idx.forEach(i => { const cur = pages[i].getRotation().angle || 0; pages[i].setRotation(window.PDFLib.degrees((cur + add) % 360)); });
        downloadPdf(await doc.save(), 'rotated.pdf'); q('#msg', root).innerHTML = okBox('Rotated ' + idx.length + ' page(s).');
      } catch (e) { q('#msg', root).innerHTML = errBox('Rotate failed: ' + e.message); }
    });
  });

  /* =======================================================================
     PAGE NUMBERS
     ==================================================================== */
  reg('page-numbers-pdf', root => {
    root.innerHTML = `<div id="drop"></div>
      <div id="opts" class="mt-4" style="display:none">
        <div class="row"><div><label class="field__label">Position</label><select id="pos" class="select"><option value="bc">Bottom center</option><option value="br">Bottom right</option><option value="bl">Bottom left</option><option value="tc">Top center</option></select></div>
        <div><label class="field__label">Format</label><select id="fmt" class="select"><option value="n">1, 2, 3…</option><option value="np">Page 1</option><option value="nof">1 of N</option></select></div>
        <div><label class="field__label">Font size</label><input id="fs" class="input" type="number" value="11" min="6" max="40"></div></div>
        <div class="btn-row mt-4"><button class="btn btn--primary" id="go">Add Page Numbers</button></div>
      </div><div id="msg" class="mt-4"></div>`;
    let file;
    dropzone(q('#drop', root), { onFiles: fl => { file = fl[0]; q('#opts', root).style.display = ''; } });
    q('#go', root).addEventListener('click', async () => {
      if (!file) return; q('#msg', root).innerHTML = spinner('Adding numbers…');
      try {
        const doc = await loadDoc(file); const font = await doc.embedFont(window.PDFLib.StandardFonts.Helvetica);
        const pages = doc.getPages(); const N = pages.length; const size = +q('#fs', root).value || 11; const pos = q('#pos', root).value; const fmt = q('#fmt', root).value;
        pages.forEach((pg, i) => {
          const { width, height } = pg.getSize();
          let txt = fmt === 'np' ? `Page ${i + 1}` : fmt === 'nof' ? `${i + 1} of ${N}` : `${i + 1}`;
          const tw = font.widthOfTextAtSize(txt, size); let x, y;
          if (pos[1] === 'c') x = (width - tw) / 2; else if (pos[1] === 'r') x = width - tw - 30; else x = 30;
          y = pos[0] === 't' ? height - size - 24 : 24;
          pg.drawText(txt, { x, y, size, font, color: window.PDFLib.rgb(0.2, 0.2, 0.2) });
        });
        downloadPdf(await doc.save(), 'numbered.pdf'); q('#msg', root).innerHTML = okBox(`Numbered ${N} page(s).`);
      } catch (e) { q('#msg', root).innerHTML = errBox('Failed: ' + e.message); }
    });
  });

  /* =======================================================================
     WATERMARK
     ==================================================================== */
  reg('watermark-pdf', root => {
    root.innerHTML = `<div id="drop"></div>
      <div id="opts" class="mt-4" style="display:none">
        <div class="row"><div><label class="field__label">Watermark text</label><input id="txt" class="input" value="CONFIDENTIAL"></div>
        <div><label class="field__label">Opacity: <b id="ov">20</b>%</label><input id="op" type="range" min="5" max="80" value="20" style="width:100%"></div>
        <div><label class="field__label">Size</label><input id="sz" class="input" type="number" value="50" min="8" max="200"></div>
        <div><label class="field__label">Colour</label><input id="col" type="color" value="#ff0000" class="input" style="height:44px"></div></div>
        <label class="chip mt-4" style="display:inline-flex"><input type="checkbox" id="diag" checked> Diagonal</label>
        <div class="btn-row mt-4"><button class="btn btn--primary" id="go">Add Watermark</button></div>
      </div><div id="msg" class="mt-4"></div>`;
    let file;
    dropzone(q('#drop', root), { onFiles: fl => { file = fl[0]; q('#opts', root).style.display = ''; } });
    q('#op', root).addEventListener('input', () => q('#ov', root).textContent = q('#op', root).value);
    q('#go', root).addEventListener('click', async () => {
      if (!file) return; q('#msg', root).innerHTML = spinner('Applying…');
      try {
        const doc = await loadDoc(file); const font = await doc.embedFont(window.PDFLib.StandardFonts.HelveticaBold);
        const txt = q('#txt', root).value || 'WATERMARK'; const size = +q('#sz', root).value || 50; const op = (+q('#op', root).value || 20) / 100;
        const hex = q('#col', root).value; const rgb = window.PDFLib.rgb(parseInt(hex.slice(1, 3), 16) / 255, parseInt(hex.slice(3, 5), 16) / 255, parseInt(hex.slice(5, 7), 16) / 255);
        const diag = q('#diag', root).checked;
        doc.getPages().forEach(pg => {
          const { width, height } = pg.getSize(); const tw = font.widthOfTextAtSize(txt, size);
          pg.drawText(txt, { x: (width - tw) / 2, y: height / 2, size, font, color: rgb, opacity: op, rotate: window.PDFLib.degrees(diag ? 45 : 0) });
        });
        downloadPdf(await doc.save(), 'watermarked.pdf'); q('#msg', root).innerHTML = okBox('Watermark added to all pages.');
      } catch (e) { q('#msg', root).innerHTML = errBox('Failed: ' + e.message); }
    });
  });

  /* =======================================================================
     CROP (trim margins)
     ==================================================================== */
  reg('crop-pdf', root => {
    root.innerHTML = `<div id="drop"></div>
      <div id="opts" class="mt-4" style="display:none">
        <p class="muted">Trim margins from every page (in points; 72 pt = 1 inch).</p>
        <div class="row mt-2"><div><label class="field__label">Top</label><input id="t" class="input" type="number" value="0"></div>
        <div><label class="field__label">Right</label><input id="r" class="input" type="number" value="0"></div>
        <div><label class="field__label">Bottom</label><input id="b" class="input" type="number" value="0"></div>
        <div><label class="field__label">Left</label><input id="l" class="input" type="number" value="0"></div></div>
        <div class="btn-row mt-4"><button class="btn btn--primary" id="go">Crop PDF</button></div>
      </div><div id="msg" class="mt-4"></div>`;
    let file;
    dropzone(q('#drop', root), { onFiles: fl => { file = fl[0]; q('#opts', root).style.display = ''; } });
    q('#go', root).addEventListener('click', async () => {
      if (!file) return; q('#msg', root).innerHTML = spinner('Cropping…');
      try {
        const doc = await loadDoc(file); const t = +q('#t', root).value, r = +q('#r', root).value, b = +q('#b', root).value, l = +q('#l', root).value;
        doc.getPages().forEach(pg => { const { width, height } = pg.getSize(); const nx = l, ny = b, nw = Math.max(1, width - l - r), nh = Math.max(1, height - t - b); pg.setCropBox(nx, ny, nw, nh); pg.setMediaBox(nx, ny, nw, nh); });
        downloadPdf(await doc.save(), 'cropped.pdf'); q('#msg', root).innerHTML = okBox('Cropped all pages.');
      } catch (e) { q('#msg', root).innerHTML = errBox('Failed: ' + e.message); }
    });
  });

  /* =======================================================================
     IMAGES → PDF (jpg-to-pdf, scan-to-pdf share this)
     ==================================================================== */
  function imagesToPdf(root, withCamera) {
    root.innerHTML = `<div id="drop"></div>
      ${withCamera ? '<div class="btn-row mt-4"><button class="btn btn--ghost" id="cam">📷 Use Camera</button></div><video id="vid" playsinline style="display:none;width:100%;max-height:320px;border-radius:12px;margin-top:12px"></video><div class="btn-row mt-2" id="cambar" style="display:none"><button class="btn btn--primary" id="snap">Capture Page</button><button class="btn btn--ghost" id="stop">Stop</button></div>' : ''}
      <div id="list" class="mt-4"></div>
      <div class="btn-row mt-4" id="bar" style="display:none"><button class="btn btn--primary" id="go">Create PDF</button><button class="btn btn--ghost" id="clr">Clear</button></div>
      <div id="msg" class="mt-4"></div>`;
    let images = []; // {name, bytes, type, w, h}
    dropzone(q('#drop', root), { accept: 'image/*', multiple: true, hint: 'Add JPG or PNG images.', onFiles: fl => addFiles(fl) });
    async function addFiles(fl) {
      for (const f of fl) { if (!/^image\//.test(f.type)) continue; const bytes = await readBytes(f); images.push({ name: f.name, bytes, type: f.type }); }
      render();
    }
    function render() { q('#list', root).innerHTML = images.map((im, i) => `<div class="row" style="align-items:center;border-bottom:1px solid var(--border);padding:8px 0"><span>🖼 ${esc(im.name)}</span><span class="muted" style="text-align:right;flex:none">${fmtBytes(im.bytes.length)} <button class="btn btn--ghost btn--sm" data-del="${i}">✕</button></span></div>`).join(''); q('#bar', root).style.display = images.length ? '' : 'none'; qa('[data-del]', root).forEach(b => b.addEventListener('click', () => { images.splice(+b.dataset.del, 1); render(); })); }
    q('#clr', root)?.addEventListener('click', () => { images = []; render(); });
    if (withCamera) {
      let stream;
      q('#cam', root).addEventListener('click', async () => { try { stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } }); const v = q('#vid', root); v.srcObject = stream; v.style.display = ''; await v.play(); q('#cambar', root).style.display = ''; } catch (e) { q('#msg', root).innerHTML = errBox('Camera unavailable: ' + e.message); } });
      q('#snap', root)?.addEventListener('click', () => { const v = q('#vid', root); const c = document.createElement('canvas'); c.width = v.videoWidth; c.height = v.videoHeight; c.getContext('2d').drawImage(v, 0, 0); c.toBlob(async b => { images.push({ name: 'scan-' + (images.length + 1) + '.jpg', bytes: new Uint8Array(await b.arrayBuffer()), type: 'image/jpeg' }); render(); }, 'image/jpeg', 0.9); });
      q('#stop', root)?.addEventListener('click', () => { if (stream) stream.getTracks().forEach(t => t.stop()); q('#vid', root).style.display = 'none'; q('#cambar', root).style.display = 'none'; });
    }
    q('#go', root).addEventListener('click', async () => {
      if (!images.length) return; q('#msg', root).innerHTML = spinner('Building PDF…');
      try {
        const doc = await window.PDFLib.PDFDocument.create();
        for (const im of images) {
          const embed = /png/i.test(im.type) ? await doc.embedPng(im.bytes) : await doc.embedJpg(im.bytes);
          const page = doc.addPage([embed.width, embed.height]); page.drawImage(embed, { x: 0, y: 0, width: embed.width, height: embed.height });
        }
        downloadPdf(await doc.save(), withCamera ? 'scan.pdf' : 'images.pdf'); q('#msg', root).innerHTML = okBox(`Created a ${images.length}-page PDF.`);
      } catch (e) { q('#msg', root).innerHTML = errBox('Failed: ' + e.message + ' (PNG/JPG only)'); }
    });
  }
  reg('jpg-to-pdf', r => imagesToPdf(r, false));
  reg('scan-to-pdf', r => imagesToPdf(r, true));

  /* =======================================================================
     PDF → JPG (render pages)
     ==================================================================== */
  reg('pdf-to-jpg', root => {
    root.innerHTML = `<div id="drop"></div>
      <div id="opts" class="mt-4" style="display:none"><div class="row"><div><label class="field__label">Quality: <b id="qv">90</b>%</label><input id="q" type="range" min="40" max="100" value="90" style="width:100%"></div>
      <div><label class="field__label">Resolution</label><select id="sc" class="select"><option value="1.5">Standard</option><option value="2.5">High</option><option value="1">Low</option></select></div></div>
      <div class="btn-row mt-4"><button class="btn btn--primary" id="go">Convert to JPG</button></div></div>
      <div id="msg" class="mt-4"></div><div id="out" class="mt-4" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:12px"></div>`;
    let file;
    dropzone(q('#drop', root), { onFiles: fl => { file = fl[0]; q('#opts', root).style.display = ''; } });
    q('#q', root).addEventListener('input', () => q('#qv', root).textContent = q('#q', root).value);
    q('#go', root).addEventListener('click', async () => {
      if (!file) return; q('#msg', root).innerHTML = spinner('Rendering…'); q('#out', root).innerHTML = '';
      try {
        const bytes = await readBytes(file); const pdf = await getPdfjs(bytes); const quality = (+q('#q', root).value) / 100; const scale = +q('#sc', root).value;
        for (let i = 1; i <= pdf.numPages; i++) {
          const c = await renderPage(pdf, i, scale);
          const blob = await new Promise(res => c.toBlob(res, 'image/jpeg', quality));
          const url = URL.createObjectURL(blob);
          const cell = document.createElement('div'); cell.style.cssText = 'border:1px solid var(--border);border-radius:10px;overflow:hidden';
          cell.innerHTML = `<img src="${url}" style="width:100%;display:block"><button class="btn btn--ghost btn--sm btn--block" data-dl="${i}">Download p${i}</button>`;
          cell.querySelector('[data-dl]').addEventListener('click', () => U.download(`page-${i}.jpg`, blob));
          q('#out', root).appendChild(cell);
        }
        q('#msg', root).innerHTML = okBox(`Rendered ${pdf.numPages} page(s). Click to download each.`);
      } catch (e) { q('#msg', root).innerHTML = errBox('Failed: ' + e.message); }
    });
  });

  /* =======================================================================
     PDF → TEXT / MARKDOWN / WORD / SUMMARY  (pdf.js text extraction)
     ==================================================================== */
  async function extractText(file) {
    const bytes = await readBytes(file); const pdf = await getPdfjs(bytes); const pages = [];
    for (let i = 1; i <= pdf.numPages; i++) {
      const page = await pdf.getPage(i); const tc = await page.getTextContent();
      let last = 0, line = '', out = '';
      tc.items.forEach(it => { const y = it.transform[5]; if (last && Math.abs(y - last) > 3) { out += line.trimEnd() + '\n'; line = ''; } line += it.str + (it.hasEOL ? '\n' : ' '); last = y; });
      out += line; pages.push(out.replace(/\n{3,}/g, '\n\n').trim());
    }
    return pages;
  }
  function textToolUI(root, label) {
    root.innerHTML = `<div id="drop"></div><div class="btn-row mt-4" id="bar" style="display:none"><button class="btn btn--primary" id="go">${label}</button></div><div id="msg" class="mt-4"></div><div id="out"></div>`;
    let file; dropzone(q('#drop', root), { onFiles: fl => { file = fl[0]; q('#bar', root).style.display = ''; } });
    return { getFile: () => file, root };
  }
  reg('pdf-to-markdown', root => {
    const ui = textToolUI(root, 'Convert to Markdown');
    q('#go', root).addEventListener('click', async () => {
      const file = ui.getFile(); if (!file) return; q('#msg', root).innerHTML = spinner('Extracting text…');
      try {
        const pages = await extractText(file);
        const md = pages.map((p, i) => `## Page ${i + 1}\n\n${p}`).join('\n\n---\n\n');
        q('#out', root).innerHTML = `<div class="output-box" style="white-space:pre-wrap">${esc(md)}</div><div class="btn-row mt-4"><button class="btn btn--ghost" id="cp">Copy</button><button class="btn btn--primary" id="dl">Download .md</button></div>`;
        q('#cp', root).addEventListener('click', () => U.copy(md)); q('#dl', root).addEventListener('click', () => U.download('document.md', md, 'text/markdown'));
        q('#msg', root).innerHTML = okBox('Extracted text from ' + pages.length + ' page(s).');
      } catch (e) { q('#msg', root).innerHTML = errBox('Failed: ' + e.message); }
    });
  });
  reg('pdf-to-word', root => {
    const ui = textToolUI(root, 'Convert to Word (.doc)');
    root.insertAdjacentHTML('beforeend', '<div class="notice notice--info mt-4">Produces an editable Word document from the PDF’s text. Complex layouts/scanned PDFs may not preserve formatting.</div>');
    q('#go', root).addEventListener('click', async () => {
      const file = ui.getFile(); if (!file) return; q('#msg', root).innerHTML = spinner('Converting…');
      try {
        const pages = await extractText(file);
        const bodyHtml = pages.map(p => p.split('\n').map(l => `<p>${esc(l) || '&nbsp;'}</p>`).join('')).join('<br style="page-break-before:always">');
        const doc = `<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word" xmlns="http://www.w3.org/TR/REC-html40"><head><meta charset="utf-8"><title>Document</title></head><body>${bodyHtml}</body></html>`;
        U.download('document.doc', doc, 'application/msword');
        q('#msg', root).innerHTML = okBox('Word document created & downloaded.');
      } catch (e) { q('#msg', root).innerHTML = errBox('Failed: ' + e.message); }
    });
  });
  reg('pdf-summarizer', root => {
    const ui = textToolUI(root, 'Summarize PDF');
    q('#go', root).addEventListener('click', async () => {
      const file = ui.getFile(); if (!file) return; q('#msg', root).innerHTML = spinner('Reading & summarising…');
      try {
        const text = (await extractText(file)).join(' ');
        const sentences = text.match(/[^.!?]+[.!?]+/g) || [text];
        const STOP = new Set('the a an and or but of to in on at for with by is are was were be as from this that it its'.split(' '));
        const freq = {}; (text.toLowerCase().match(/\b[a-z]{3,}\b/g) || []).forEach(w => { if (!STOP.has(w)) freq[w] = (freq[w] || 0) + 1; });
        const scored = sentences.map((s, i) => { let sc = 0; (s.toLowerCase().match(/\b[a-z]{3,}\b/g) || []).forEach(w => sc += freq[w] || 0); return { s: s.trim(), sc: sc / Math.sqrt(s.split(' ').length + 1), i }; });
        const n = Math.min(7, sentences.length); const top = scored.slice().sort((a, b) => b.sc - a.sc).slice(0, n).sort((a, b) => a.i - b.i).map(t => t.s).join(' ');
        q('#out', root).innerHTML = `<label class="field__label mt-4">Summary</label><div class="output-box" style="white-space:pre-wrap">${esc(top)}</div><div class="btn-row mt-4"><button class="btn btn--ghost" id="cp">Copy</button></div>`;
        q('#cp', root).addEventListener('click', () => U.copy(top)); q('#msg', root).innerHTML = okBox('Summarised on-device.');
      } catch (e) { q('#msg', root).innerHTML = errBox('Failed: ' + e.message); }
    });
  });

  /* =======================================================================
     SIGN (draw signature → stamp on last page)
     ==================================================================== */
  reg('sign-pdf', root => {
    root.innerHTML = `<div id="drop"></div>
      <div id="opts" class="mt-4" style="display:none">
        <label class="field__label">Draw your signature</label>
        <canvas id="pad" width="500" height="180" style="border:1px dashed var(--border-strong);border-radius:10px;width:100%;touch-action:none;background:#fff"></canvas>
        <div class="btn-row mt-2"><button class="btn btn--ghost btn--sm" id="clr">Clear</button></div>
        <div class="row mt-4"><div><label class="field__label">Place on page</label><select id="pg" class="select"></select></div>
        <div><label class="field__label">Position</label><select id="pos" class="select"><option value="br">Bottom right</option><option value="bl">Bottom left</option><option value="bc">Bottom center</option></select></div></div>
        <div class="btn-row mt-4"><button class="btn btn--primary" id="go">Sign PDF</button></div>
      </div><div id="msg" class="mt-4"></div>`;
    let file, total = 0; const pad = q('#pad', root); const ctx = pad.getContext('2d'); let drawing = false, has = false;
    ctx.lineWidth = 2.5; ctx.lineCap = 'round'; ctx.strokeStyle = '#111';
    const pt = e => { const r = pad.getBoundingClientRect(); const c = (e.touches ? e.touches[0] : e); return { x: (c.clientX - r.left) * pad.width / r.width, y: (c.clientY - r.top) * pad.height / r.height }; };
    const start = e => { drawing = true; has = true; const p = pt(e); ctx.beginPath(); ctx.moveTo(p.x, p.y); e.preventDefault(); };
    const move = e => { if (!drawing) return; const p = pt(e); ctx.lineTo(p.x, p.y); ctx.stroke(); e.preventDefault(); };
    const end = () => drawing = false;
    ['mousedown', 'touchstart'].forEach(ev => pad.addEventListener(ev, start));
    ['mousemove', 'touchmove'].forEach(ev => pad.addEventListener(ev, move));
    ['mouseup', 'touchend', 'mouseleave'].forEach(ev => pad.addEventListener(ev, end));
    q('#clr', root).addEventListener('click', () => { ctx.clearRect(0, 0, pad.width, pad.height); has = false; });
    dropzone(q('#drop', root), { onFiles: async fl => { file = fl[0]; try { const d = await loadDoc(file); total = d.getPageCount(); q('#pg', root).innerHTML = Array.from({ length: total }, (_, i) => `<option value="${i}">Page ${i + 1}</option>`).join(''); q('#pg', root).value = total - 1; q('#opts', root).style.display = ''; } catch (e) { q('#msg', root).innerHTML = errBox(e.message); } } });
    q('#go', root).addEventListener('click', async () => {
      if (!file) return; if (!has) { q('#msg', root).innerHTML = errBox('Please draw a signature first.'); return; }
      q('#msg', root).innerHTML = spinner('Signing…');
      try {
        const doc = await loadDoc(file); const png = await new Promise(res => pad.toBlob(res, 'image/png'));
        const emb = await doc.embedPng(new Uint8Array(await png.arrayBuffer()));
        const pi = +q('#pg', root).value; const page = doc.getPages()[pi]; const { width, height } = page.getSize();
        const w = Math.min(180, width * 0.35), h = w * (pad.height / pad.width); const pos = q('#pos', root).value;
        const x = pos[1] === 'r' ? width - w - 36 : pos[1] === 'c' ? (width - w) / 2 : 36; const y = 36;
        page.drawImage(emb, { x, y, width: w, height: h });
        downloadPdf(await doc.save(), 'signed.pdf'); q('#msg', root).innerHTML = okBox('Signature added to page ' + (pi + 1) + '.');
      } catch (e) { q('#msg', root).innerHTML = errBox('Failed: ' + e.message); }
    });
  });

  /* =======================================================================
     REDACT (rasterise + black boxes — truly removes underlying content)
     ==================================================================== */
  reg('redact-pdf', root => {
    root.innerHTML = `<div id="drop"></div>
      <div id="opts" class="mt-4" style="display:none">
        <p class="muted">Drag to draw black boxes over sensitive areas. The result is flattened to images, so redacted content is permanently removed.</p>
        <div class="btn-row mt-2"><button class="btn btn--ghost btn--sm" id="prev">◀ Prev</button><span id="pi" class="muted"></span><button class="btn btn--ghost btn--sm" id="next">Next ▶</button><button class="btn btn--ghost btn--sm" id="undo">Undo box</button></div>
        <div id="wrap" style="position:relative;overflow:auto;border:1px solid var(--border);border-radius:8px;margin-top:8px"></div>
        <div class="btn-row mt-4"><button class="btn btn--primary" id="go">Apply &amp; Download</button></div>
      </div><div id="msg" class="mt-4"></div>`;
    let file, pdf, cur = 1, boxes = {}, canvases = {}, scale = 1.5;
    dropzone(q('#drop', root), { onFiles: async fl => { file = fl[0]; q('#msg', root).innerHTML = spinner('Rendering…'); try { const bytes = await readBytes(file); pdf = await getPdfjs(bytes); cur = 1; boxes = {}; canvases = {}; q('#opts', root).style.display = ''; await show(); q('#msg', root).innerHTML = ''; } catch (e) { q('#msg', root).innerHTML = errBox(e.message); } } });
    async function show() {
      q('#pi', root).textContent = `Page ${cur} / ${pdf.numPages}`;
      if (!canvases[cur]) canvases[cur] = await renderPage(pdf, cur, scale);
      const base = canvases[cur]; const wrap = q('#wrap', root); wrap.innerHTML = '';
      const disp = document.createElement('canvas'); disp.width = base.width; disp.height = base.height; disp.style.maxWidth = '100%'; disp.style.cursor = 'crosshair';
      const dctx = disp.getContext('2d'); const redraw = () => { dctx.drawImage(base, 0, 0); dctx.fillStyle = '#000'; (boxes[cur] || []).forEach(b => dctx.fillRect(b.x, b.y, b.w, b.h)); };
      redraw();
      let sx, sy, drawing = false;
      const toXY = e => { const r = disp.getBoundingClientRect(); return { x: (e.clientX - r.left) * disp.width / r.width, y: (e.clientY - r.top) * disp.height / r.height }; };
      disp.addEventListener('mousedown', e => { drawing = true; const p = toXY(e); sx = p.x; sy = p.y; });
      disp.addEventListener('mousemove', e => { if (!drawing) return; const p = toXY(e); redraw(); dctx.fillStyle = 'rgba(0,0,0,.5)'; dctx.fillRect(sx, sy, p.x - sx, p.y - sy); });
      disp.addEventListener('mouseup', e => { if (!drawing) return; drawing = false; const p = toXY(e); const b = { x: Math.min(sx, p.x), y: Math.min(sy, p.y), w: Math.abs(p.x - sx), h: Math.abs(p.y - sy) }; if (b.w > 3 && b.h > 3) { (boxes[cur] = boxes[cur] || []).push(b); } redraw(); });
      wrap.appendChild(disp);
    }
    q('#prev', root).addEventListener('click', async () => { if (cur > 1) { cur--; await show(); } });
    q('#next', root).addEventListener('click', async () => { if (cur < pdf.numPages) { cur++; await show(); } });
    q('#undo', root).addEventListener('click', () => { if (boxes[cur] && boxes[cur].length) { boxes[cur].pop(); show(); } });
    q('#go', root).addEventListener('click', async () => {
      q('#msg', root).innerHTML = spinner('Flattening & redacting…');
      try {
        const out = await window.PDFLib.PDFDocument.create();
        for (let i = 1; i <= pdf.numPages; i++) {
          if (!canvases[i]) canvases[i] = await renderPage(pdf, i, scale);
          const c = document.createElement('canvas'); c.width = canvases[i].width; c.height = canvases[i].height; const cx = c.getContext('2d');
          cx.drawImage(canvases[i], 0, 0); cx.fillStyle = '#000'; (boxes[i] || []).forEach(b => cx.fillRect(b.x, b.y, b.w, b.h));
          const blob = await new Promise(res => c.toBlob(res, 'image/jpeg', 0.92)); const emb = await out.embedJpg(new Uint8Array(await blob.arrayBuffer()));
          const pg = out.addPage([emb.width, emb.height]); pg.drawImage(emb, { x: 0, y: 0, width: emb.width, height: emb.height });
        }
        downloadPdf(await out.save(), 'redacted.pdf'); q('#msg', root).innerHTML = okBox('Redacted PDF downloaded (flattened to images).');
      } catch (e) { q('#msg', root).innerHTML = errBox('Failed: ' + e.message); }
    });
  });

  /* =======================================================================
     COMPARE (side-by-side render)
     ==================================================================== */
  reg('compare-pdf', root => {
    root.innerHTML = `<div class="grid-2"><div><label class="field__label">Original</label><div id="dA"></div></div><div><label class="field__label">Changed</label><div id="dB"></div></div></div>
      <div class="btn-row mt-4" id="bar" style="display:none"><button class="btn btn--ghost btn--sm" id="prev">◀</button><span id="pi" class="muted"></span><button class="btn btn--ghost btn--sm" id="next">▶</button></div>
      <div id="view" class="grid-2 mt-4"></div><div id="msg" class="mt-4"></div>`;
    let a, b, pdfA, pdfB, cur = 1, maxP = 0;
    dropzone(q('#dA', root), { onFiles: fl => { a = fl[0]; ready(); } });
    dropzone(q('#dB', root), { onFiles: fl => { b = fl[0]; ready(); } });
    async function ready() {
      if (!a || !b) return; q('#msg', root).innerHTML = spinner('Loading…');
      try { pdfA = await getPdfjs(await readBytes(a)); pdfB = await getPdfjs(await readBytes(b)); maxP = Math.max(pdfA.numPages, pdfB.numPages); cur = 1; q('#bar', root).style.display = ''; await show(); q('#msg', root).innerHTML = okBox(`A: ${pdfA.numPages} pages · B: ${pdfB.numPages} pages`); }
      catch (e) { q('#msg', root).innerHTML = errBox(e.message); }
    }
    async function show() {
      q('#pi', root).textContent = `Page ${cur} / ${maxP}`; const v = q('#view', root); v.innerHTML = '';
      for (const [pdf, lbl] of [[pdfA, 'A'], [pdfB, 'B']]) {
        const cell = document.createElement('div'); cell.style.cssText = 'border:1px solid var(--border);border-radius:8px;overflow:auto';
        if (cur <= pdf.numPages) { const c = await renderPage(pdf, cur, 1.2); c.style.width = '100%'; cell.appendChild(c); } else { cell.innerHTML = '<div class="muted" style="padding:20px;text-align:center">(no page)</div>'; }
        v.appendChild(cell);
      }
    }
    q('#prev', root).addEventListener('click', async () => { if (cur > 1) { cur--; await show(); } });
    q('#next', root).addEventListener('click', async () => { if (cur < maxP) { cur++; await show(); } });
  });

  /* =======================================================================
     REPAIR (re-serialise via pdf-lib)
     ==================================================================== */
  reg('repair-pdf', root => {
    root.innerHTML = `<div id="drop"></div><div class="btn-row mt-4" id="bar" style="display:none"><button class="btn btn--primary" id="go">Repair PDF</button></div>
      <div class="notice notice--info mt-4">Re-reads and rewrites the PDF structure, which fixes many “can’t open / damaged” files. Severely corrupted files may be unrecoverable.</div><div id="msg" class="mt-4"></div>`;
    let file; dropzone(q('#drop', root), { onFiles: fl => { file = fl[0]; q('#bar', root).style.display = ''; } });
    q('#go', root).addEventListener('click', async () => {
      if (!file) return; q('#msg', root).innerHTML = spinner('Repairing…');
      try { const doc = await window.PDFLib.PDFDocument.load(await readBytes(file), { ignoreEncryption: true, throwOnInvalidObject: false, updateMetadata: false }); downloadPdf(await doc.save({ useObjectStreams: false }), 'repaired.pdf'); q('#msg', root).innerHTML = okBox('Repaired PDF saved (' + doc.getPageCount() + ' pages).'); }
      catch (e) { q('#msg', root).innerHTML = errBox('Could not repair this file: ' + e.message); }
    });
  });

  /* =======================================================================
     COMPRESS (rasterise pages at chosen quality)
     ==================================================================== */
  reg('compress-pdf', root => {
    root.innerHTML = `<div id="drop"></div>
      <div id="opts" class="mt-4" style="display:none"><div class="row"><div><label class="field__label">Quality: <b id="qv">65</b>%</label><input id="q" type="range" min="30" max="90" value="65" style="width:100%"></div>
      <div><label class="field__label">Resolution</label><select id="sc" class="select"><option value="1.3">Screen</option><option value="1.7">eBook</option><option value="2.2">Print</option></select></div></div>
      <div class="btn-row mt-4"><button class="btn btn--primary" id="go">Compress PDF</button></div></div>
      <div class="notice notice--info mt-4">Compresses by rasterising pages (great for scans &amp; image-heavy PDFs). Selectable text becomes part of the image.</div>
      <div id="msg" class="mt-4"></div>`;
    let file; dropzone(q('#drop', root), { onFiles: fl => { file = fl[0]; q('#opts', root).style.display = ''; } });
    q('#q', root).addEventListener('input', () => q('#qv', root).textContent = q('#q', root).value);
    q('#go', root).addEventListener('click', async () => {
      if (!file) return; q('#msg', root).innerHTML = spinner('Compressing…');
      try {
        const bytes = await readBytes(file); const orig = bytes.length; const pdf = await getPdfjs(bytes.slice()); const quality = (+q('#q', root).value) / 100; const scale = +q('#sc', root).value;
        const out = await window.PDFLib.PDFDocument.create();
        for (let i = 1; i <= pdf.numPages; i++) { const c = await renderPage(pdf, i, scale); const blob = await new Promise(res => c.toBlob(res, 'image/jpeg', quality)); const emb = await out.embedJpg(new Uint8Array(await blob.arrayBuffer())); const pg = out.addPage([emb.width, emb.height]); pg.drawImage(emb, { x: 0, y: 0, width: emb.width, height: emb.height }); }
        const saved = await out.save(); downloadPdf(saved, 'compressed.pdf');
        const pct = Math.round((1 - saved.length / orig) * 100);
        q('#msg', root).innerHTML = okBox(`${fmtBytes(orig)} → ${fmtBytes(saved.length)} (${pct > 0 ? pct + '% smaller' : 'no reduction — already optimised'}).`);
      } catch (e) { q('#msg', root).innerHTML = errBox('Failed: ' + e.message); }
    });
  });

  /* =======================================================================
     EDIT (add text overlay)
     ==================================================================== */
  reg('edit-pdf', root => {
    root.innerHTML = `<div id="drop"></div>
      <div id="opts" class="mt-4" style="display:none">
        <div class="row"><div style="flex:2"><label class="field__label">Text to add</label><input id="txt" class="input" placeholder="Type text…"></div>
        <div><label class="field__label">Page</label><select id="pg" class="select"></select></div>
        <div><label class="field__label">Size</label><input id="sz" class="input" type="number" value="16" min="6" max="72"></div>
        <div><label class="field__label">Colour</label><input id="col" type="color" value="#111111" class="input" style="height:44px"></div></div>
        <p class="muted mt-2">Click on the preview to place the text.</p>
        <div id="wrap" style="position:relative;border:1px solid var(--border);border-radius:8px;overflow:auto;margin-top:8px"></div>
        <div class="btn-row mt-4"><button class="btn btn--primary" id="go">Download edited PDF</button></div>
      </div><div id="msg" class="mt-4"></div>`;
    let file, pdf, cur = 0, scale = 1.4, placements = []; // {page,xRatio,yRatio,text,size,color}
    dropzone(q('#drop', root), { onFiles: async fl => { file = fl[0]; q('#msg', root).innerHTML = spinner('Loading…'); try { pdf = await getPdfjs(await readBytes(file)); q('#pg', root).innerHTML = Array.from({ length: pdf.numPages }, (_, i) => `<option value="${i}">Page ${i + 1}</option>`).join(''); cur = 0; q('#opts', root).style.display = ''; await show(); q('#msg', root).innerHTML = ''; } catch (e) { q('#msg', root).innerHTML = errBox(e.message); } } });
    q('#pg', root)?.addEventListener('change', async () => { cur = +q('#pg', root).value; await show(); });
    async function show() {
      const wrap = q('#wrap', root); wrap.innerHTML = ''; const c = await renderPage(pdf, cur + 1, scale); c.style.maxWidth = '100%'; c.style.cursor = 'text'; wrap.appendChild(c);
      const overlay = document.createElement('div'); overlay.style.cssText = 'position:absolute;inset:0'; wrap.appendChild(overlay);
      c.addEventListener('click', e => {
        const txt = q('#txt', root).value.trim(); if (!txt) { q('#msg', root).innerHTML = errBox('Enter text first.'); return; }
        const r = c.getBoundingClientRect(); const xR = (e.clientX - r.left) / r.width, yR = (e.clientY - r.top) / r.height;
        placements.push({ page: cur, xR, yR, text: txt, size: +q('#sz', root).value, color: q('#col', root).value });
        const tag = document.createElement('div'); tag.textContent = txt; tag.style.cssText = `position:absolute;left:${xR * 100}%;top:${yR * 100}%;color:${q('#col', root).value};font-size:${q('#sz', root).value}px;transform:translateY(-100%);white-space:nowrap`; overlay.appendChild(tag);
        q('#msg', root).innerHTML = '<span class="muted">Added. Click again to add more, then Download.</span>';
      });
    }
    q('#go', root).addEventListener('click', async () => {
      if (!placements.length) { q('#msg', root).innerHTML = errBox('Click the preview to place text first.'); return; }
      q('#msg', root).innerHTML = spinner('Saving…');
      try {
        const doc = await loadDoc(file); const font = await doc.embedFont(window.PDFLib.StandardFonts.Helvetica); const pages = doc.getPages();
        placements.forEach(pl => { const pg = pages[pl.page]; const { width, height } = pg.getSize(); const hex = pl.color; const col = window.PDFLib.rgb(parseInt(hex.slice(1, 3), 16) / 255, parseInt(hex.slice(3, 5), 16) / 255, parseInt(hex.slice(5, 7), 16) / 255); pg.drawText(pl.text, { x: pl.xR * width, y: height - pl.yR * height, size: pl.size, font, color: col }); });
        downloadPdf(await doc.save(), 'edited.pdf'); q('#msg', root).innerHTML = okBox('Edited PDF downloaded.');
      } catch (e) { q('#msg', root).innerHTML = errBox('Failed: ' + e.message); }
    });
  });

  /* =======================================================================
     PDF FORMS (fill AcroForm fields)
     ==================================================================== */
  reg('pdf-forms', root => {
    root.innerHTML = `<div id="drop"></div><div id="fields" class="mt-4"></div><div class="btn-row mt-4" id="bar" style="display:none"><button class="btn btn--primary" id="go">Fill &amp; Download</button><label class="chip" style="display:inline-flex"><input type="checkbox" id="flat"> Flatten (make read-only)</label></div><div id="msg" class="mt-4"></div>`;
    let file;
    dropzone(q('#drop', root), { onFiles: async fl => {
      file = fl[0]; q('#msg', root).innerHTML = spinner('Reading form…');
      try {
        const doc = await loadDoc(file); const form = doc.getForm(); const fields = form.getFields();
        if (!fields.length) { q('#msg', root).innerHTML = errBox('No fillable form fields found in this PDF.'); q('#bar', root).style.display = 'none'; q('#fields', root).innerHTML = ''; return; }
        q('#fields', root).innerHTML = fields.map(f => { const name = f.getName(); const type = f.constructor.name; if (type === 'PDFCheckBox') return `<label class="chip" style="display:flex;margin-bottom:8px"><input type="checkbox" data-f="${esc(name)}" data-t="check"> ${esc(name)}</label>`; return `<div class="field"><label class="field__label">${esc(name)}</label><input class="input" data-f="${esc(name)}" data-t="text"></div>`; }).join('');
        q('#bar', root).style.display = ''; q('#msg', root).innerHTML = okBox(fields.length + ' field(s) found.');
      } catch (e) { q('#msg', root).innerHTML = errBox('Could not read form: ' + e.message); }
    }});
    q('#go', root).addEventListener('click', async () => {
      if (!file) return; q('#msg', root).innerHTML = spinner('Filling…');
      try {
        const doc = await loadDoc(file); const form = doc.getForm();
        qa('[data-f]', root).forEach(inp => { const name = inp.dataset.f; try { if (inp.dataset.t === 'check') { const cb = form.getCheckBox(name); inp.checked ? cb.check() : cb.uncheck(); } else if (inp.value) { form.getTextField(name).setText(inp.value); } } catch (_) {} });
        if (q('#flat', root).checked) form.flatten();
        downloadPdf(await doc.save(), 'filled.pdf'); q('#msg', root).innerHTML = okBox('Form filled & downloaded.');
      } catch (e) { q('#msg', root).innerHTML = errBox('Failed: ' + e.message); }
    });
  });

  /* =======================================================================
     HTML → PDF (render + browser print-to-PDF)
     ==================================================================== */
  reg('html-to-pdf', root => {
    root.innerHTML = `<label class="field__label">Paste HTML (or plain text)</label>
      <textarea id="html" class="textarea textarea--tall" placeholder="<h1>Hello</h1><p>Your content…</p>"></textarea>
      <div class="btn-row mt-4"><button class="btn btn--primary" id="go">Open Print → Save as PDF</button></div>
      <div class="notice notice--info mt-4">Opens your content in a new tab and launches the browser’s print dialog — choose “Save as PDF”. This uses the browser’s own high-fidelity PDF engine.</div>`;
    q('#go', root).addEventListener('click', () => {
      const html = q('#html', root).value || '<p>(empty)</p>';
      const w = window.open('', '_blank');
      if (!w) { U.toast('Allow pop-ups to use this tool'); return; }
      w.document.write(`<!doctype html><html><head><meta charset="utf-8"><title>Document</title><style>body{font-family:-apple-system,Segoe UI,Roboto,sans-serif;padding:40px;line-height:1.6}@media print{body{padding:0}}</style></head><body>${html}<script>window.onload=function(){setTimeout(function(){window.print();},300);}<\/script></body></html>`);
      w.document.close();
    });
  });

  /* =======================================================================
     SERVER / HEAVY conversions — honest handler (Office, PDF/A, OCR, …)
     ==================================================================== */
  function serverTool(root, action, opts) {
    opts = opts || {};
    root.innerHTML = `<div id="drop"></div><div id="list" class="mt-4"></div>
      <div class="btn-row mt-4" id="bar" style="display:none"><button class="btn btn--primary" id="go">${esc(opts.label || 'Convert')}</button></div>
      <div id="msg" class="mt-4"></div>
      <div class="notice notice--info mt-4">${esc(opts.note || 'This conversion is processed on the server; files are deleted immediately after.')}</div>`;
    let files = [];
    dropzone(q('#drop', root), { accept: opts.accept || 'application/pdf', multiple: !!opts.multiple, onFiles: fl => { files = opts.multiple ? files.concat(fl) : [fl[0]]; q('#list', root).innerHTML = fileListHtml(files); q('#bar', root).style.display = files.length ? '' : 'none'; } });
    q('#go', root).addEventListener('click', async () => {
      if (!files.length) return; q('#msg', root).innerHTML = spinner('Processing on server…');
      const fd = new FormData(); fd.append('action', action); files.forEach(f => fd.append('files[]', f)); qa('[data-p]', root).forEach(i => fd.append(i.dataset.p, i.value));
      try {
        const res = await fetch(`${BASE}/api/pdf.php`, { method: 'POST', body: fd });
        if ((res.headers.get('content-type') || '').includes('application/json')) { const j = await res.json(); q('#msg', root).innerHTML = `<div class="notice notice--${j.ok ? 'success' : 'error'}">${esc(j.error || j.message || 'Done')}</div>`; return; }
        U.download(opts.outName || 'output', await res.blob()); q('#msg', root).innerHTML = okBox('Done — your file has downloaded.');
      } catch (e) { q('#msg', root).innerHTML = errBox('Upload failed: ' + e.message); }
    });
  }
  const officeNote = 'Office conversions need LibreOffice on the server. If your host doesn’t provide it, you’ll get a clear message — the in-browser PDF tools above work without any server.';
  reg('word-to-pdf', r => serverTool(r, 'word-to-pdf', { accept: '.doc,.docx', label: 'Convert to PDF', outName: 'document.pdf', note: officeNote }));
  reg('powerpoint-to-pdf', r => serverTool(r, 'ppt-to-pdf', { accept: '.ppt,.pptx', label: 'Convert to PDF', outName: 'slides.pdf', note: officeNote }));
  reg('excel-to-pdf', r => serverTool(r, 'excel-to-pdf', { accept: '.xls,.xlsx', label: 'Convert to PDF', outName: 'sheet.pdf', note: officeNote }));
  reg('pdf-to-powerpoint', r => serverTool(r, 'pdf-to-ppt', { label: 'Convert to PowerPoint', outName: 'slides.pptx', note: officeNote }));
  reg('pdf-to-excel', r => serverTool(r, 'pdf-to-excel', { label: 'Convert to Excel', outName: 'data.xlsx', note: officeNote }));
  reg('pdf-to-pdfa', r => serverTool(r, 'pdf-to-pdfa', { label: 'Convert to PDF/A', outName: 'archive.pdf', note: 'PDF/A conversion needs Ghostscript on the server.' }));
  reg('ocr-pdf', r => serverTool(r, 'ocr', { label: 'Run OCR', outName: 'ocr.pdf', note: 'OCR (making scans searchable) needs an OCR engine on the server. For text-based PDFs, use PDF to Text/Markdown above — it works entirely in your browser.' }));
  reg('translate-pdf', r => serverTool(r, 'translate', { label: 'Translate', outName: 'translated.pdf', note: 'PDF translation requires a translation service/API. Extract the text with PDF to Markdown, translate it, then rebuild if needed.' }));

  /* Encryption (pdf-lib can’t encrypt) → server qpdf. */
  reg('protect-pdf', r => serverTool(r, 'protect', { label: 'Protect PDF', outName: 'protected.pdf', note: 'Password protection uses qpdf on the server; files are deleted immediately after.' }));
  reg('unlock-pdf', r => {
    serverTool(r, 'unlock', { label: 'Unlock PDF', outName: 'unlocked.pdf', note: 'Removing a known password uses qpdf on the server; files are deleted immediately after.' });
    // inject a password field
    const bar = q('#bar', r); if (bar) bar.insertAdjacentHTML('beforebegin', '<div class="field mt-4"><label class="field__label">Password (if known)</label><input class="input" type="password" data-p="password"></div>');
  });
})();
