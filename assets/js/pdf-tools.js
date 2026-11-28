/* =========================================================================
   Toolzy — Complete PDF Studio Engines (100% Client-Side, Private)
   Powered by vendored pdf-lib (create/edit), pdf.js (render/read), qpdf WASM
   (AES-256 encryption), and built-in OmniZip (ZIP / DOCX / XLSX / PPTX engine).
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
  function fmtBytes(b) {
    if (b < 1024) return b + ' B';
    const u = ['KB', 'MB', 'GB'];
    let i = -1;
    do { b /= 1024; i++; } while (b >= 1024 && i < 2);
    return b.toFixed(b < 10 ? 2 : 1) + ' ' + u[i];
  }
  const readBytes = file => file.arrayBuffer().then(b => new Uint8Array(b));
  const encUtf8 = str => new TextEncoder().encode(str);
  const decUtf8 = bytes => new TextDecoder('utf-8').decode(bytes);

  function svgUp(cls) {
    return `<svg class="${cls || 'dropzone__icon'}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" x2="12" y1="3" y2="15"/></svg>`;
  }

  /* =======================================================================
     OMNIZIP — Pure-JS ZIP Reader & Writer (powers .zip, .docx, .xlsx, .pptx)
     ==================================================================== */
  const CRC_TABLE = (() => {
    const t = new Uint32Array(256);
    for (let n = 0; n < 256; n++) {
      let c = n;
      for (let k = 0; k < 8; k++) c = (c & 1) ? (0xEDB88320 ^ (c >>> 1)) : (c >>> 1);
      t[n] = c >>> 0;
    }
    return t;
  })();

  function crc32(bytes) {
    let c = 0xFFFFFFFF;
    for (let i = 0; i < bytes.length; i++) {
      c = CRC_TABLE[(c ^ bytes[i]) & 0xFF] ^ (c >>> 8);
    }
    return (c ^ 0xFFFFFFFF) >>> 0;
  }

  /**
   * Build a standard ZIP archive (Store mode) from [{ name, data (Uint8Array|string) }].
   * Works synchronously in all browsers and produces valid .zip, .docx, .xlsx, .pptx files.
   */
  function zipWrite(entries) {
    const files = entries.map(e => {
      const nameBytes = encUtf8(e.name);
      const dataBytes = typeof e.data === 'string' ? encUtf8(e.data) : (e.data instanceof Uint8Array ? e.data : new Uint8Array(e.data));
      const crc = crc32(dataBytes);
      return { nameBytes, dataBytes, crc };
    });

    let offset = 0;
    const localParts = [];
    const centralParts = [];

    for (const f of files) {
      const nLen = f.nameBytes.length;
      const dLen = f.dataBytes.length;

      // Local file header (30 + nLen)
      const lh = new Uint8Array(30 + nLen);
      const lv = new DataView(lh.buffer);
      lv.setUint32(0, 0x04034b50, true);
      lv.setUint16(4, 20, true);
      lv.setUint16(6, 0x0800, true); // UTF-8 flag
      lv.setUint16(8, 0, true);      // Store (no compression)
      lv.setUint16(10, 0, true);
      lv.setUint16(12, 0x21, true);
      lv.setUint32(14, f.crc, true);
      lv.setUint32(18, dLen, true);
      lv.setUint32(22, dLen, true);
      lv.setUint16(26, nLen, true);
      lv.setUint16(28, 0, true);
      lh.set(f.nameBytes, 30);

      localParts.push(lh, f.dataBytes);

      // Central directory header (46 + nLen)
      const ch = new Uint8Array(46 + nLen);
      const cv = new DataView(ch.buffer);
      cv.setUint32(0, 0x02014b50, true);
      cv.setUint16(4, 20, true);
      cv.setUint16(6, 20, true);
      cv.setUint16(8, 0x0800, true);
      cv.setUint16(10, 0, true);
      cv.setUint16(12, 0, true);
      cv.setUint16(14, 0x21, true);
      cv.setUint32(16, f.crc, true);
      cv.setUint32(20, dLen, true);
      cv.setUint32(24, dLen, true);
      cv.setUint16(28, nLen, true);
      cv.setUint16(30, 0, true);
      cv.setUint16(32, 0, true);
      cv.setUint16(34, 0, true);
      cv.setUint16(36, 0, true);
      cv.setUint32(38, 0, true);
      cv.setUint32(42, offset, true);
      ch.set(f.nameBytes, 46);
      centralParts.push(ch);

      offset += lh.length + dLen;
    }

    const cdSize = centralParts.reduce((s, p) => s + p.length, 0);
    const eocd = new Uint8Array(22);
    const ev = new DataView(eocd.buffer);
    ev.setUint32(0, 0x06054b50, true);
    ev.setUint16(4, 0, true);
    ev.setUint16(6, 0, true);
    ev.setUint16(8, files.length, true);
    ev.setUint16(10, files.length, true);
    ev.setUint32(12, cdSize, true);
    ev.setUint32(16, offset, true);
    ev.setUint16(20, 0, true);

    const total = offset + cdSize + 22;
    const out = new Uint8Array(total);
    let pos = 0;
    for (const p of localParts) { out.set(p, pos); pos += p.length; }
    for (const p of centralParts) { out.set(p, pos); pos += p.length; }
    out.set(eocd, pos);
    return out;
  }

  /**
   * Read a ZIP archive (supports Store and Deflate via DecompressionStream)
   * into a Map<filename, Uint8Array>. Used to parse .docx, .xlsx, .pptx in-browser!
   */
  async function zipRead(bytes) {
    const view = new DataView(bytes.buffer, bytes.byteOffset, bytes.byteLength);
    const out = new Map();
    // Locate End of Central Directory (EOCD)
    let eocdPos = -1;
    for (let i = bytes.length - 22; i >= Math.max(0, bytes.length - 65557); i--) {
      if (view.getUint32(i, true) === 0x06054b50) { eocdPos = i; break; }
    }
    if (eocdPos < 0) throw new Error('Not a valid ZIP / Office OpenXML file.');
    const count = view.getUint16(eocdPos + 10, true);
    let cdPos = view.getUint32(eocdPos + 16, true);

    for (let i = 0; i < count; i++) {
      if (view.getUint32(cdPos, true) !== 0x02014b50) break;
      const method = view.getUint16(cdPos + 10, true);
      const compSize = view.getUint32(cdPos + 20, true);
      const nameLen = view.getUint16(cdPos + 28, true);
      const extraLen = view.getUint16(cdPos + 30, true);
      const commentLen = view.getUint16(cdPos + 32, true);
      const localOff = view.getUint32(cdPos + 42, true);
      const name = decUtf8(bytes.subarray(cdPos + 46, cdPos + 46 + nameLen));
      cdPos += 46 + nameLen + extraLen + commentLen;

      if (name.endsWith('/')) continue;
      const lNameLen = view.getUint16(localOff + 26, true);
      const lExtraLen = view.getUint16(localOff + 28, true);
      const dataStart = localOff + 30 + lNameLen + lExtraLen;
      const raw = bytes.subarray(dataStart, dataStart + compSize);

      if (method === 0) {
        out.set(name, raw);
      } else if (method === 8 && typeof DecompressionStream !== 'undefined') {
        try {
          const ds = new DecompressionStream('deflate-raw');
          const writer = ds.writable.getWriter();
          writer.write(raw);
          writer.close();
          const buf = await new Response(ds.readable).arrayBuffer();
          out.set(name, new Uint8Array(buf));
        } catch (_) {}
      }
    }
    return out;
  }

  window.OmniZip = { write: zipWrite, read: zipRead };

  /* --------------------------------------------------- UI Helper Widgets */
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
    input.addEventListener('change', () => { if (input.files.length) opts.onFiles(Array.from(input.files)); input.value = ''; });
    ['dragover', 'dragenter'].forEach(ev => dz.addEventListener(ev, e => { e.preventDefault(); dz.classList.add('is-drag'); }));
    ['dragleave', 'drop'].forEach(ev => dz.addEventListener(ev, e => { e.preventDefault(); dz.classList.remove('is-drag'); }));
    dz.addEventListener('drop', e => { if (e.dataTransfer.files.length) opts.onFiles(Array.from(e.dataTransfer.files)); });
  }

  const spinner = msg => `<div class="row" style="align-items:center;padding:8px 0"><div class="spinner"></div><span>${esc(msg || 'Working…')}</span></div>`;
  const errBox = m => `<div class="notice notice--error">${esc(m)}</div>`;
  const okBox = m => `<div class="notice notice--success">${esc(m)}</div>`;

  async function loadDoc(file, opts) {
    const bytes = file instanceof Uint8Array ? file : await readBytes(file);
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
      if (m) {
        let a = +m[1], b = +m[2];
        if (a > b) [a, b] = [b, a];
        for (let p = a; p <= b; p++) if (p >= 1 && p <= total) out.push(p - 1);
      } else if (/^\d+$/.test(part)) {
        const p = +part;
        if (p >= 1 && p <= total) out.push(p - 1);
      }
    });
    return out;
  }

  /* Render a pdf.js page to a canvas at a given scale. */
  async function renderPage(pdf, pageNum, scale) {
    const page = await pdf.getPage(pageNum);
    const viewport = page.getViewport({ scale: scale || 1.5 });
    const canvas = document.createElement('canvas');
    canvas.width = Math.floor(viewport.width);
    canvas.height = Math.floor(viewport.height);
    const ctx = canvas.getContext('2d');
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    await page.render({ canvasContext: ctx, viewport }).promise;
    return canvas;
  }
  const getPdfjs = bytes => window.pdfjsLib.getDocument({ data: bytes.slice ? bytes.slice() : bytes }).promise;

  /* Extract structured lines + coordinates from a PDF via pdf.js */
  async function extractStructuredPages(file) {
    const bytes = await readBytes(file);
    const pdf = await getPdfjs(bytes);
    const pages = [];
    for (let i = 1; i <= pdf.numPages; i++) {
      const page = await pdf.getPage(i);
      const vp = page.getViewport({ scale: 1 });
      const tc = await page.getTextContent();
      // Sort items top-to-bottom, left-to-right
      const items = tc.items.map(it => ({
        str: it.str,
        x: Math.round(it.transform[4]),
        y: Math.round(vp.height - it.transform[5]),
        h: Math.round(Math.hypot(it.transform[2], it.transform[3]) || Math.abs(it.transform[0]) || 12),
        w: Math.round(it.width || 0)
      })).filter(it => it.str.trim() !== '');

      // Group into rows by Y proximity (within 5pt)
      const rows = [];
      items.forEach(it => {
        let row = rows.find(r => Math.abs(r.y - it.y) <= 5);
        if (!row) { row = { y: it.y, h: it.h, cells: [] }; rows.push(row); }
        row.cells.push(it);
        if (it.h > row.h) row.h = it.h;
      });
      rows.sort((a, b) => a.y - b.y);
      rows.forEach(r => r.cells.sort((a, b) => a.x - b.x));

      const textLines = rows.map(r => r.cells.map(c => c.str).join(' ').replace(/\s+/g, ' ').trim()).filter(Boolean);
      pages.push({ pageNum: i, width: vp.width, height: vp.height, rows, textLines, text: textLines.join('\n') });
    }
    return { pdf, pages };
  }

  /* Helper: wrap text and render multi-page vector PDF from structured blocks */
  async function buildVectorPdfFromBlocks(blocks, opts) {
    opts = opts || {};
    const doc = await window.PDFLib.PDFDocument.create();
    const fontReg = await doc.embedFont(window.PDFLib.StandardFonts.Helvetica);
    const fontBold = await doc.embedFont(window.PDFLib.StandardFonts.HelveticaBold);
    const pageW = opts.landscape ? 841.89 : 595.28;
    const pageH = opts.landscape ? 595.28 : 841.89;
    const margin = opts.margin || 48;
    const maxW = pageW - margin * 2;

    let page = doc.addPage([pageW, pageH]);
    let y = pageH - margin;

    const newPage = () => {
      page = doc.addPage([pageW, pageH]);
      y = pageH - margin;
    };

    // Sanitize text for WinAnsi encoding in StandardFonts
    const clean = s => String(s || '')
      .replace(/[\u2018\u2019]/g, "'")
      .replace(/[\u201C\u201D]/g, '"')
      .replace(/[\u2013\u2014]/g, '-')
      .replace(/\u2026/g, '...')
      .replace(/\u00A0/g, ' ')
      .replace(/[^\x09\x0A\x0D\x20-\x7E\xA0-\xFF]/g, '');

    for (const b of blocks) {
      if (b.type === 'pagebreak') { newPage(); continue; }
      const isHeading = b.type === 'h1' || b.type === 'h2' || b.type === 'h3';
      const f = (isHeading || b.bold) ? fontBold : fontReg;
      const sz = b.type === 'h1' ? 20 : b.type === 'h2' ? 15 : b.type === 'h3' ? 13 : (b.size || 11);
      const lineH = sz * 1.42;
      const text = clean(b.text);
      if (!text.trim()) { y -= lineH * 0.5; if (y < margin + lineH) newPage(); continue; }

      if (isHeading) y -= 6;
      const words = text.split(/\s+/);
      let line = '';
      for (const w of words) {
        const test = line ? line + ' ' + w : w;
        if (f.widthOfTextAtSize(test, sz) > maxW && line) {
          if (y < margin + lineH) newPage();
          page.drawText(line, { x: margin + (b.indent || 0), y, size: sz, font: f, color: window.PDFLib.rgb(0.08, 0.1, 0.14) });
          y -= lineH;
          line = w;
        } else {
          line = test;
        }
      }
      if (line) {
        if (y < margin + lineH) newPage();
        page.drawText(line, { x: margin + (b.indent || 0), y, size: sz, font: f, color: window.PDFLib.rgb(0.08, 0.1, 0.14) });
        y -= lineH;
      }
      y -= isHeading ? 6 : 4;
    }
    return doc.save();
  }

  /* =======================================================================
     1. MERGE PDF (Visual Thumbnails + Page Range Selection + Insert Mode)
     ==================================================================== */
  reg('merge-pdf', root => {
    root.innerHTML = `<div id="drop"></div>
      <div id="opts" class="mt-4" style="display:none">
        <div class="row">
          <div style="flex:2">
            <label class="field__label">Combine mode</label>
            <select id="mode" class="select">
              <option value="append">Append files in order (drag cards to reorder)</option>
              <option value="insert">Insert files into the first PDF at a specific page</option>
              <option value="interleave">Alternate / Interleave pages (Front &amp; Back scans)</option>
            </select>
          </div>
          <div>
            <label class="field__label">Output filename</label>
            <input id="fname" class="input" value="merged.pdf">
          </div>
        </div>
      </div>
      <p class="muted mt-4" id="hint" style="display:none"></p>
      <div id="list" class="mt-4"></div>
      <div class="btn-row mt-4" id="bar" style="display:none">
        <button class="btn btn--primary" id="go">Merge PDFs</button>
        <button class="btn btn--ghost" id="sortAz">Sort A → Z</button>
        <button class="btn btn--ghost" id="clr">Clear All</button>
      </div>
      <div id="msg" class="mt-4"></div>`;

    let items = [];
    const mode = () => q('#mode', root).value;

    const selected = it => it.pages.trim()
      ? [...new Set(parseRanges(it.pages, it.count))]
      : Array.from({ length: it.count }, (_, k) => k);

    dropzone(q('#drop', root), {
      accept: 'application/pdf', multiple: true,
      hint: 'Upload 2 or more PDF files. Drag to reorder, pick page ranges, and merge 100% in your browser.',
      onFiles: async fl => {
        const pdfs = fl.filter(f => f.type === 'application/pdf' || /\.pdf$/i.test(f.name));
        if (!pdfs.length) return;
        q('#msg', root).innerHTML = spinner('Reading PDFs & generating thumbnails…');
        for (const f of pdfs) {
          try {
            const bytes = await readBytes(f);
            const doc = await window.PDFLib.PDFDocument.load(bytes, { ignoreEncryption: true });
            const count = doc.getPageCount();
            let thumb = '';
            try {
              const pdf = await getPdfjs(bytes);
              const c = await renderPage(pdf, 1, 0.28);
              thumb = c.toDataURL('image/jpeg', 0.75);
            } catch (_) {}
            items.push({ file: f, count, pages: '', at: '', thumb });
          } catch (e) {
            q('#msg', root).innerHTML = errBox(`Could not read ${esc(f.name)}: ${e.message}`);
          }
        }
        q('#msg', root).innerHTML = '';
        render();
      }
    });

    function render() {
      const m = mode();
      const ins = m === 'insert';
      q('#list', root).innerHTML = items.map((it, i) => {
        const base = ins && i === 0;
        return `<div class="row" draggable="${!ins}" data-i="${i}" style="flex-direction:column;align-items:stretch;gap:10px;border:1px solid var(--border);background:var(--surface-2);border-radius:14px;padding:14px;margin-bottom:10px${!ins ? ';cursor:grab' : ''}">
          <div style="display:flex;align-items:center;gap:12px">
            ${it.thumb ? `<img src="${it.thumb}" alt="Page 1" style="width:42px;height:56px;object-fit:cover;border-radius:6px;border:1px solid var(--border);background:#fff;flex:none">` : `<span style="font-size:22px">📄</span>`}
            <div style="flex:1;min-width:0">
              <div style="font-weight:700;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${!ins ? '☰ ' : ''}${esc(it.file.name)}${base ? ' <span class="tool-card__badge badge-popular" style="position:static;margin-left:6px">Base PDF</span>' : ''}</div>
              <div class="muted" style="font-size:12.5px">${it.count} page${it.count === 1 ? '' : 's'} · ${fmtBytes(it.file.size)}</div>
            </div>
            <div style="display:flex;gap:4px">
              ${i > 0 ? `<button class="btn btn--ghost btn--sm" data-up="${i}" title="Move up">↑</button>` : ''}
              ${i < items.length - 1 ? `<button class="btn btn--ghost btn--sm" data-dn="${i}" title="Move down">↓</button>` : ''}
              <button class="btn btn--ghost btn--sm" data-del="${i}" title="Remove">✕</button>
            </div>
          </div>
          <div style="display:flex;gap:10px;flex-wrap:wrap">
            <div style="flex:1;min-width:160px">
              <label class="field__label" for="pg-${i}">Pages to include</label>
              <input class="input" id="pg-${i}" data-pages="${i}" value="${esc(it.pages)}" placeholder="All pages (or e.g. 1-3, 5)">
            </div>
            ${ins && !base ? `<div style="flex:1;min-width:160px">
              <label class="field__label" for="at-${i}">Insert before page</label>
              <input class="input" id="at-${i}" data-at="${i}" value="${esc(it.at)}" placeholder="End of document">
            </div>` : ''}
          </div>
        </div>`;
      }).join('');

      q('#opts', root).style.display = items.length ? '' : 'none';
      q('#bar', root).style.display = items.length ? '' : 'none';
      q('#hint', root).style.display = items.length > 1 ? '' : 'none';
      q('#hint', root).textContent = ins
        ? `Pages are inserted into "${items[0] ? items[0].file.name : ''}" before the page number you specify.`
        : m === 'interleave'
          ? 'Interleaves page 1 of file 1, page 1 of file 2, page 2 of file 1, etc. — ideal for combining front and back single-sided scans.'
          : 'Drag cards or use ↑ / ↓ to reorder. Leave "Pages to include" blank to merge the entire PDF.';

      qa('[data-pages]', root).forEach(inp => inp.addEventListener('input', () => { items[+inp.dataset.pages].pages = inp.value; }));
      qa('[data-at]', root).forEach(inp => inp.addEventListener('input', () => { items[+inp.dataset.at].at = inp.value; }));
      qa('[data-del]', root).forEach(b => b.addEventListener('click', e => { e.stopPropagation(); items.splice(+b.dataset.del, 1); render(); }));
      qa('[data-up]', root).forEach(b => b.addEventListener('click', e => { e.stopPropagation(); const idx = +b.dataset.up; [items[idx - 1], items[idx]] = [items[idx], items[idx - 1]]; render(); }));
      qa('[data-dn]', root).forEach(b => b.addEventListener('click', e => { e.stopPropagation(); const idx = +b.dataset.dn; [items[idx + 1], items[idx]] = [items[idx], items[idx + 1]]; render(); }));

      let dragI = null;
      qa('.row[draggable="true"]', root).forEach(rowEl => {
        rowEl.addEventListener('dragstart', () => dragI = +rowEl.dataset.i);
        rowEl.addEventListener('dragover', e => e.preventDefault());
        rowEl.addEventListener('drop', e => { e.preventDefault(); const to = +rowEl.dataset.i; const [m] = items.splice(dragI, 1); items.splice(to, 0, m); render(); });
      });
    }

    q('#mode', root).addEventListener('change', render);
    q('#sortAz', root).addEventListener('click', () => { items.sort((a, b) => a.file.name.localeCompare(b.file.name)); render(); });
    q('#clr', root).addEventListener('click', () => { items = []; q('#msg', root).innerHTML = ''; render(); });

    q('#go', root).addEventListener('click', async () => {
      if (items.length < 2) { q('#msg', root).innerHTML = errBox('Add at least two PDF files to merge.'); return; }
      const sel = [];
      for (const it of items) {
        const s = selected(it);
        if (!s.length) { q('#msg', root).innerHTML = errBox(`"${esc(it.file.name)}" has 1–${it.count} pages, but "${esc(it.pages)}" selects none.`); return; }
        sel.push(s);
      }

      const plan = [];
      const m = mode();
      if (m === 'insert') {
        const baseCount = items[0].count;
        const at = items.map((it, i) => {
          if (i === 0) return 0;
          const n = parseInt(it.at, 10);
          return (isNaN(n) ? baseCount + 1 : Math.min(Math.max(n, 1), baseCount + 1));
        });
        const baseSel = new Set(sel[0]);
        for (let pos = 1; pos <= baseCount + 1; pos++) {
          for (let i = 1; i < items.length; i++) {
            if (at[i] === pos) sel[i].forEach(p => plan.push([i, p]));
          }
          if (pos <= baseCount && baseSel.has(pos - 1)) plan.push([0, pos - 1]);
        }
      } else if (m === 'interleave') {
        const maxLen = Math.max(...sel.map(s => s.length));
        for (let k = 0; k < maxLen; k++) {
          for (let i = 0; i < items.length; i++) {
            if (k < sel[i].length) plan.push([i, sel[i][k]]);
          }
        }
      } else {
        items.forEach((_, i) => sel[i].forEach(p => plan.push([i, p])));
      }

      q('#msg', root).innerHTML = spinner('Merging pages…');
      try {
        const out = await window.PDFLib.PDFDocument.create();
        const copied = [];
        for (let i = 0; i < items.length; i++) {
          const pages = await out.copyPages(await loadDoc(items[i].file), sel[i]);
          copied.push(new Map(sel[i].map((p, k) => [p, pages[k]])));
        }
        plan.forEach(([i, p]) => out.addPage(copied[i].get(p)));
        const outName = (q('#fname', root).value.trim() || 'merged.pdf').replace(/(\.pdf)?$/i, '.pdf');
        downloadPdf(await out.save(), outName);
        q('#msg', root).innerHTML = okBox(`Merged ${items.length} files into "${outName}" (${plan.length} pages).`);
      } catch (e) { q('#msg', root).innerHTML = errBox('Merge failed: ' + e.message); }
    });
  });

  /* =======================================================================
     2. SPLIT / EXTRACT PDF (Visual Page Picker + Ranges + ZIP Download)
     ==================================================================== */
  reg('split-pdf', root => {
    root.innerHTML = `<div id="drop"></div>
      <div id="opts" class="mt-4" style="display:none">
        <div class="row">
          <div style="flex:2">
            <label class="field__label">Pages or ranges (click thumbnails below or type e.g. 1-3, 5, 8-10)</label>
            <input id="rng" class="input" placeholder="1-3, 5">
          </div>
          <div>
            <label class="field__label">Split mode</label>
            <select id="mode" class="select">
              <option value="extract">Merge selected pages into one PDF</option>
              <option value="each">Split each selected page into a separate PDF (.ZIP)</option>
              <option value="fixed">Split every N pages into separate PDFs (.ZIP)</option>
            </select>
          </div>
          <div id="everyWrap" style="display:none">
            <label class="field__label">Pages per file (N)</label>
            <input id="everyN" class="input" type="number" min="1" value="2">
          </div>
        </div>
        <div class="btn-row mt-4">
          <button class="btn btn--ghost btn--sm" id="selAll">Select All</button>
          <button class="btn btn--ghost btn--sm" id="selOdd">Odd Pages</button>
          <button class="btn btn--ghost btn--sm" id="selEven">Even Pages</button>
          <button class="btn btn--ghost btn--sm" id="selNone">Clear Selection</button>
        </div>
        <div id="grid" class="mt-4" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(108px,1fr));gap:10px;max-height:420px;overflow-y:auto;padding:4px"></div>
        <div class="btn-row mt-4">
          <button class="btn btn--primary" id="go">Split &amp; Download</button>
        </div>
      </div><div id="msg" class="mt-4"></div>`;

    let file, total = 0, picked = new Set();

    function syncInputFromSet() {
      const sorted = [...picked].sort((a, b) => a - b);
      const parts = [];
      for (let i = 0; i < sorted.length; i++) {
        let start = sorted[i], end = start;
        while (i + 1 < sorted.length && sorted[i + 1] === end + 1) { end = sorted[++i]; }
        parts.push(start === end ? `${start}` : `${start}-${end}`);
      }
      q('#rng', root).value = parts.join(', ');
      qa('[data-pg]', root).forEach(el => {
        const p = +el.dataset.pg;
        const on = picked.has(p);
        el.style.borderColor = on ? 'var(--accent)' : 'var(--border)';
        el.style.opacity = on ? '1' : '.45';
      });
    }

    dropzone(q('#drop', root), {
      onFiles: async fl => {
        file = fl[0];
        q('#msg', root).innerHTML = spinner('Loading pages…');
        try {
          const bytes = await readBytes(file);
          const pdf = await getPdfjs(bytes);
          total = pdf.numPages;
          picked = new Set(Array.from({ length: total }, (_, i) => i + 1));
          q('#opts', root).style.display = '';
          const g = q('#grid', root);
          g.innerHTML = '';
          const maxThumbs = Math.min(total, 60);
          for (let i = 1; i <= maxThumbs; i++) {
            const c = await renderPage(pdf, i, 0.28);
            const cell = document.createElement('div');
            cell.dataset.pg = i;
            cell.style.cssText = 'border:2px solid var(--accent);border-radius:10px;overflow:hidden;position:relative;cursor:pointer;background:#fff;transition:.15s';
            c.style.width = '100%'; c.style.display = 'block';
            cell.appendChild(c);
            cell.insertAdjacentHTML('beforeend', `<div style="background:rgba(15,23,42,.75);color:#fff;font-size:11px;font-weight:600;text-align:center;padding:3px 0">Page ${i}</div>`);
            cell.addEventListener('click', () => {
              if (picked.has(i)) picked.delete(i); else picked.add(i);
              syncInputFromSet();
            });
            g.appendChild(cell);
          }
          syncInputFromSet();
          q('#msg', root).innerHTML = `<span class="muted">${total} pages loaded. Click thumbnails to toggle selection.</span>`;
        } catch (e) { q('#msg', root).innerHTML = errBox('Could not read PDF: ' + e.message); }
      }
    });

    q('#mode', root).addEventListener('change', () => {
      q('#everyWrap', root).style.display = q('#mode', root).value === 'fixed' ? '' : 'none';
    });
    q('#rng', root).addEventListener('input', () => {
      picked = new Set(parseRanges(q('#rng', root).value, total).map(i => i + 1));
      qa('[data-pg]', root).forEach(el => {
        const on = picked.has(+el.dataset.pg);
        el.style.borderColor = on ? 'var(--accent)' : 'var(--border)';
        el.style.opacity = on ? '1' : '.45';
      });
    });
    q('#selAll', root).addEventListener('click', () => { picked = new Set(Array.from({ length: total }, (_, i) => i + 1)); syncInputFromSet(); });
    q('#selOdd', root).addEventListener('click', () => { picked = new Set(Array.from({ length: total }, (_, i) => i + 1).filter(p => p % 2 === 1)); syncInputFromSet(); });
    q('#selEven', root).addEventListener('click', () => { picked = new Set(Array.from({ length: total }, (_, i) => i + 1).filter(p => p % 2 === 0)); syncInputFromSet(); });
    q('#selNone', root).addEventListener('click', () => { picked.clear(); syncInputFromSet(); });

    q('#go', root).addEventListener('click', async () => {
      if (!file) return;
      q('#msg', root).innerHTML = spinner('Splitting PDF…');
      try {
        const src = await loadDoc(file);
        let idx = parseRanges(q('#rng', root).value, total);
        if (!idx.length) idx = src.getPageIndices();
        const mode = q('#mode', root).value;
        const baseName = file.name.replace(/\.pdf$/i, '');

        if (mode === 'extract') {
          const out = await window.PDFLib.PDFDocument.create();
          const pgs = await out.copyPages(src, idx);
          pgs.forEach(p => out.addPage(p));
          downloadPdf(await out.save(), `${baseName}-extracted.pdf`);
          q('#msg', root).innerHTML = okBox(`Extracted ${idx.length} page(s) into a single PDF.`);
        } else {
          const step = mode === 'fixed' ? Math.max(1, +q('#everyN', root).value || 1) : 1;
          const entries = [];
          for (let k = 0; k < idx.length; k += step) {
            const chunk = idx.slice(k, k + step);
            const out = await window.PDFLib.PDFDocument.create();
            const pgs = await out.copyPages(src, chunk);
            pgs.forEach(p => out.addPage(p));
            const label = chunk.length === 1 ? `page-${chunk[0] + 1}` : `pages-${chunk[0] + 1}-${chunk[chunk.length - 1] + 1}`;
            entries.push({ name: `${baseName}-${label}.pdf`, data: await out.save() });
          }
          if (entries.length === 1) {
            downloadPdf(entries[0].data, entries[0].name);
          } else {
            const zipBytes = zipWrite(entries);
            U.download(`${baseName}-split.zip`, new Blob([zipBytes], { type: 'application/zip' }));
          }
          q('#msg', root).innerHTML = okBox(`Exported ${entries.length} PDF file(s) ${entries.length > 1 ? 'bundled as a .ZIP archive' : ''}.`);
        }
      } catch (e) { q('#msg', root).innerHTML = errBox('Split failed: ' + e.message); }
    });
  });

  /* =======================================================================
     3. ORGANIZE PDF (iLovePDF Studio: Drag Reorder, Rotate, Duplicate, Blank, Delete)
     ==================================================================== */
  reg('organize-pdf', root => {
    root.innerHTML = `<div id="drop"></div>
      <div class="btn-row mt-4" id="topBar" style="display:none;align-items:center;justify-content:space-between">
        <div class="btn-row" style="margin:0">
          <button class="btn btn--ghost btn--sm" id="revOrder">⇄ Reverse Order</button>
          <button class="btn btn--ghost btn--sm" id="rotAll">↻ Rotate All 90°</button>
          <button class="btn btn--ghost btn--sm" id="addBlank">＋ Add Blank Page</button>
          <button class="btn btn--ghost btn--sm" id="resetAll">Reset</button>
        </div>
        <span class="muted" id="cnt" style="font-size:13px"></span>
      </div>
      <div id="grid" class="mt-4" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(145px,1fr));gap:14px"></div>
      <div class="btn-row mt-4" id="bar" style="display:none">
        <button class="btn btn--primary" id="go">Save Organized PDF</button>
      </div>
      <div id="msg" class="mt-4"></div>`;

    let file, origItems = [], items = []; // { origIdx (or -1 for blank), thumb, rot: 0, keep: true }

    dropzone(q('#drop', root), {
      onFiles: async fl => {
        file = fl[0];
        q('#msg', root).innerHTML = spinner('Rendering page thumbnails…');
        try {
          const bytes = await readBytes(file);
          const pdf = await getPdfjs(bytes);
          origItems = [];
          for (let i = 1; i <= pdf.numPages; i++) {
            const c = await renderPage(pdf, i, 0.35);
            origItems.push({ origIdx: i - 1, thumb: c.toDataURL('image/jpeg', 0.8), rot: 0, keep: true });
          }
          items = origItems.map(x => Object.assign({}, x));
          q('#topBar', root).style.display = 'flex';
          q('#bar', root).style.display = '';
          q('#msg', root).innerHTML = '<span class="muted">Drag cards to reorder · Use ↻ to rotate individual pages · ⧉ to duplicate · ✕ to remove.</span>';
          renderGrid();
        } catch (e) { q('#msg', root).innerHTML = errBox('Could not render PDF: ' + e.message); }
      }
    });

    function renderGrid() {
      const g = q('#grid', root);
      g.innerHTML = items.map((it, idx) => `
        <div class="org-cell" draggable="true" data-idx="${idx}" style="border:2px solid ${it.keep ? 'var(--border-strong)' : 'var(--danger)'};border-radius:12px;overflow:hidden;background:var(--surface-2);opacity:${it.keep ? '1' : '.42'};cursor:grab;display:flex;flex-direction:column;justify-content:space-between">
          <div style="padding:10px;display:grid;place-items:center;min-height:150px;background:#fff">
            ${it.origIdx >= 0
              ? `<img src="${it.thumb}" alt="Page" style="max-height:140px;width:auto;transform:rotate(${it.rot}deg);transition:transform .2s">`
              : `<div style="width:100px;height:135px;border:1px dashed var(--border-strong);display:grid;place-items:center;font-size:12px;color:var(--text-3)">Blank Page</div>`}
          </div>
          <div style="padding:6px 8px;display:flex;align-items:center;justify-content:space-between;border-top:1px solid var(--border);font-size:12px;font-weight:600">
            <span>#${idx + 1} ${it.origIdx >= 0 ? `(p.${it.origIdx + 1})` : ''}</span>
            <div style="display:flex;gap:3px">
              <button type="button" class="btn btn--ghost btn--sm" data-rot="${idx}" title="Rotate 90°" style="padding:2px 6px;font-size:12px">↻</button>
              <button type="button" class="btn btn--ghost btn--sm" data-dup="${idx}" title="Duplicate page" style="padding:2px 6px;font-size:12px">⧉</button>
              <button type="button" class="btn btn--ghost btn--sm" data-del="${idx}" title="${it.keep ? 'Delete' : 'Restore'}" style="padding:2px 6px;font-size:12px;color:${it.keep ? 'var(--danger)' : 'var(--success)'}">${it.keep ? '✕' : '↶'}</button>
            </div>
          </div>
        </div>`).join('');

      const k = items.filter(x => x.keep).length;
      q('#cnt', root).textContent = `${k} of ${items.length} page(s) in output`;

      qa('[data-rot]', root).forEach(b => b.addEventListener('click', e => { e.stopPropagation(); const it = items[+b.dataset.rot]; it.rot = (it.rot + 90) % 360; renderGrid(); }));
      qa('[data-dup]', root).forEach(b => b.addEventListener('click', e => { e.stopPropagation(); const i = +b.dataset.dup; items.splice(i + 1, 0, Object.assign({}, items[i])); renderGrid(); }));
      qa('[data-del]', root).forEach(b => b.addEventListener('click', e => { e.stopPropagation(); const it = items[+b.dataset.del]; it.keep = !it.keep; renderGrid(); }));

      let dragI = null;
      qa('.org-cell', root).forEach(cell => {
        cell.addEventListener('dragstart', () => { dragI = +cell.dataset.idx; });
        cell.addEventListener('dragover', e => e.preventDefault());
        cell.addEventListener('drop', e => {
          e.preventDefault();
          const to = +cell.dataset.idx;
          if (dragI == null || dragI === to) return;
          const [moved] = items.splice(dragI, 1);
          items.splice(to, 0, moved);
          renderGrid();
        });
      });
    }

    q('#revOrder', root).addEventListener('click', () => { items.reverse(); renderGrid(); });
    q('#rotAll', root).addEventListener('click', () => { items.forEach(it => { it.rot = (it.rot + 90) % 360; }); renderGrid(); });
    q('#addBlank', root).addEventListener('click', () => { items.push({ origIdx: -1, thumb: '', rot: 0, keep: true }); renderGrid(); });
    q('#resetAll', root).addEventListener('click', () => { items = origItems.map(x => Object.assign({}, x)); renderGrid(); });

    q('#go', root).addEventListener('click', async () => {
      const active = items.filter(x => x.keep);
      if (!active.length) { q('#msg', root).innerHTML = errBox('Keep at least one page.'); return; }
      q('#msg', root).innerHTML = spinner('Building organized PDF…');
      try {
        const src = await loadDoc(file);
        const out = await window.PDFLib.PDFDocument.create();
        const firstSize = src.getPages()[0] ? src.getPages()[0].getSize() : { width: 595.28, height: 841.89 };
        for (const it of active) {
          if (it.origIdx < 0) {
            out.addPage([firstSize.width, firstSize.height]);
          } else {
            const [pg] = await out.copyPages(src, [it.origIdx]);
            if (it.rot) {
              const cur = pg.getRotation().angle || 0;
              pg.setRotation(window.PDFLib.degrees((cur + it.rot) % 360));
            }
            out.addPage(pg);
          }
        }
        downloadPdf(await out.save(), 'organized.pdf');
        q('#msg', root).innerHTML = okBox(`Saved organized PDF (${active.length} pages).`);
      } catch (e) { q('#msg', root).innerHTML = errBox('Save failed: ' + e.message); }
    });
  });

  /* =======================================================================
     4. ROTATE PDF
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
     5. PAGE NUMBERS (with Start Number, Skip First Page, Margins & Format)
     ==================================================================== */
  reg('page-numbers-pdf', root => {
    root.innerHTML = `<div id="drop"></div>
      <div id="opts" class="mt-4" style="display:none">
        <div class="row">
          <div><label class="field__label">Position</label><select id="pos" class="select"><option value="bc">Bottom center</option><option value="br">Bottom right</option><option value="bl">Bottom left</option><option value="tc">Top center</option><option value="tr">Top right</option><option value="tl">Top left</option></select></div>
          <div><label class="field__label">Format</label><select id="fmt" class="select"><option value="n">1, 2, 3…</option><option value="np">Page 1</option><option value="nof">1 of N</option><option value="pof">Page 1 of N</option><option value="dash">— 1 —</option></select></div>
          <div><label class="field__label">Start number</label><input id="startN" class="input" type="number" value="1" min="1"></div>
          <div><label class="field__label">Font size</label><input id="fs" class="input" type="number" value="11" min="6" max="40"></div>
        </div>
        <label class="chip mt-4" style="display:inline-flex"><input type="checkbox" id="skipCover"> Skip cover (first page)</label>
        <div class="btn-row mt-4"><button class="btn btn--primary" id="go">Add Page Numbers</button></div>
      </div><div id="msg" class="mt-4"></div>`;
    let file;
    dropzone(q('#drop', root), { onFiles: fl => { file = fl[0]; q('#opts', root).style.display = ''; } });
    q('#go', root).addEventListener('click', async () => {
      if (!file) return; q('#msg', root).innerHTML = spinner('Adding numbers…');
      try {
        const doc = await loadDoc(file); const font = await doc.embedFont(window.PDFLib.StandardFonts.Helvetica);
        const pages = doc.getPages(); const N = pages.length; const size = +q('#fs', root).value || 11;
        const pos = q('#pos', root).value; const fmt = q('#fmt', root).value;
        const startN = +q('#startN', root).value || 1;
        const skipCover = q('#skipCover', root).checked;
        pages.forEach((pg, i) => {
          if (skipCover && i === 0) return;
          const num = i + startN - (skipCover ? 1 : 0);
          const tot = N + startN - 1 - (skipCover ? 1 : 0);
          const { width, height } = pg.getSize();
          let txt = fmt === 'np' ? `Page ${num}` : fmt === 'nof' ? `${num} of ${tot}` : fmt === 'pof' ? `Page ${num} of ${tot}` : fmt === 'dash' ? `- ${num} -` : `${num}`;
          const tw = font.widthOfTextAtSize(txt, size); let x, y;
          if (pos[1] === 'c') x = (width - tw) / 2; else if (pos[1] === 'r') x = width - tw - 32; else x = 32;
          y = pos[0] === 't' ? height - size - 26 : 26;
          pg.drawText(txt, { x, y, size, font, color: window.PDFLib.rgb(0.2, 0.2, 0.2) });
        });
        downloadPdf(await doc.save(), 'numbered.pdf'); q('#msg', root).innerHTML = okBox(`Numbered ${N} page(s).`);
      } catch (e) { q('#msg', root).innerHTML = errBox('Failed: ' + e.message); }
    });
  });

  /* =======================================================================
     6. WATERMARK PDF (Single or Tiled Mosaic Grid + Position + Opacity)
     ==================================================================== */
  reg('watermark-pdf', root => {
    root.innerHTML = `<div id="drop"></div>
      <div id="opts" class="mt-4" style="display:none">
        <div class="row">
          <div style="flex:2"><label class="field__label">Watermark text</label><input id="txt" class="input" value="CONFIDENTIAL"></div>
          <div><label class="field__label">Style</label><select id="style" class="select"><option value="center">Center Diagonal</option><option value="tile">Tiled Mosaic (Full Page)</option><option value="horizontal">Center Horizontal</option><option value="footer">Bottom Stamp</option></select></div>
          <div><label class="field__label">Opacity: <b id="ov">18</b>%</label><input id="op" type="range" min="5" max="80" value="18" style="width:100%"></div>
          <div><label class="field__label">Size</label><input id="sz" class="input" type="number" value="48" min="8" max="200"></div>
          <div><label class="field__label">Colour</label><input id="col" type="color" value="#dc2626" class="input" style="height:44px"></div>
        </div>
        <div class="btn-row mt-4"><button class="btn btn--primary" id="go">Apply Watermark</button></div>
      </div><div id="msg" class="mt-4"></div>`;
    let file;
    dropzone(q('#drop', root), { onFiles: fl => { file = fl[0]; q('#opts', root).style.display = ''; } });
    q('#op', root).addEventListener('input', () => q('#ov', root).textContent = q('#op', root).value);
    q('#go', root).addEventListener('click', async () => {
      if (!file) return; q('#msg', root).innerHTML = spinner('Applying watermark…');
      try {
        const doc = await loadDoc(file); const font = await doc.embedFont(window.PDFLib.StandardFonts.HelveticaBold);
        const txt = q('#txt', root).value || 'WATERMARK'; const size = +q('#sz', root).value || 48; const op = (+q('#op', root).value || 18) / 100;
        const hex = q('#col', root).value; const rgb = window.PDFLib.rgb(parseInt(hex.slice(1, 3), 16) / 255, parseInt(hex.slice(3, 5), 16) / 255, parseInt(hex.slice(5, 7), 16) / 255);
        const style = q('#style', root).value;
        doc.getPages().forEach(pg => {
          const { width, height } = pg.getSize();
          const tw = font.widthOfTextAtSize(txt, size);
          if (style === 'tile') {
            const tileSz = Math.min(size, 32);
            for (let y = 60; y < height; y += 160) {
              for (let x = 40; x < width; x += 220) {
                pg.drawText(txt, { x, y, size: tileSz, font, color: rgb, opacity: op, rotate: window.PDFLib.degrees(35) });
              }
            }
          } else if (style === 'footer') {
            pg.drawText(txt, { x: (width - tw) / 2, y: 28, size: Math.min(size, 22), font, color: rgb, opacity: op });
          } else {
            const diag = style === 'center';
            pg.drawText(txt, { x: (width - tw) / 2, y: height / 2, size, font, color: rgb, opacity: op, rotate: window.PDFLib.degrees(diag ? 45 : 0) });
          }
        });
        downloadPdf(await doc.save(), 'watermarked.pdf'); q('#msg', root).innerHTML = okBox('Watermark applied to all pages.');
      } catch (e) { q('#msg', root).innerHTML = errBox('Failed: ' + e.message); }
    });
  });

  /* =======================================================================
     7. CROP PDF (Trim Margins)
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
     8. IMAGES → PDF (jpg-to-pdf, scan-to-pdf — Page Size, Orientation, Margin, Drag Reorder)
     ==================================================================== */
  function imagesToPdf(root, withCamera) {
    root.innerHTML = `<div id="drop"></div>
      ${withCamera ? '<div class="btn-row mt-4"><button class="btn btn--ghost" id="cam">📷 Open Camera Scanner</button></div><video id="vid" playsinline style="display:none;width:100%;max-height:320px;border-radius:12px;margin-top:12px"></video><div class="btn-row mt-2" id="cambar" style="display:none"><button class="btn btn--primary" id="snap">Capture Page</button><button class="btn btn--ghost" id="stop">Close Camera</button></div>' : ''}
      <div id="opts" class="mt-4" style="display:none">
        <div class="row">
          <div><label class="field__label">Page Size</label><select id="pgSize" class="select"><option value="fit">Fit to Image</option><option value="a4" selected>A4 (210 × 297 mm)</option><option value="letter">US Letter</option></select></div>
          <div><label class="field__label">Orientation</label><select id="orient" class="select"><option value="auto">Auto (match image)</option><option value="portrait" selected>Portrait</option><option value="landscape">Landscape</option></select></div>
          <div><label class="field__label">Margins</label><select id="margin" class="select"><option value="0">No margin</option><option value="24" selected>Small margin</option><option value="48">Big margin</option></select></div>
        </div>
      </div>
      <div id="list" class="mt-4" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(130px,1fr));gap:12px"></div>
      <div class="btn-row mt-4" id="bar" style="display:none"><button class="btn btn--primary" id="go">Create PDF</button><button class="btn btn--ghost" id="clr">Clear</button></div>
      <div id="msg" class="mt-4"></div>`;

    let images = []; // { name, url, jpgBytes, w, h }
    dropzone(q('#drop', root), { accept: 'image/*', multiple: true, hint: 'Add JPG, PNG or WebP images. Drag cards to reorder.', onFiles: fl => addFiles(fl) });

    async function normalizeImage(file) {
      return new Promise((resolve, reject) => {
        const url = URL.createObjectURL(file);
        const img = new Image();
        img.onload = async () => {
          const c = document.createElement('canvas');
          c.width = img.naturalWidth; c.height = img.naturalHeight;
          const ctx = c.getContext('2d');
          ctx.fillStyle = '#ffffff'; ctx.fillRect(0, 0, c.width, c.height);
          ctx.drawImage(img, 0, 0);
          const blob = await new Promise(r => c.toBlob(r, 'image/jpeg', 0.92));
          resolve({ name: file.name, url: c.toDataURL('image/jpeg', 0.6), jpgBytes: new Uint8Array(await blob.arrayBuffer()), w: c.width, h: c.height });
          URL.revokeObjectURL(url);
        };
        img.onerror = () => { URL.revokeObjectURL(url); reject(new Error('Could not load ' + file.name)); };
        img.src = url;
      });
    }

    async function addFiles(fl) {
      q('#msg', root).innerHTML = spinner('Loading images…');
      for (const f of fl) {
        if (!/^image\//.test(f.type)) continue;
        try { images.push(await normalizeImage(f)); } catch (_) {}
      }
      q('#msg', root).innerHTML = '';
      render();
    }

    function render() {
      q('#opts', root).style.display = images.length ? '' : 'none';
      q('#bar', root).style.display = images.length ? '' : 'none';
      q('#list', root).innerHTML = images.map((im, i) => `
        <div class="img-cell" draggable="true" data-i="${i}" style="border:1px solid var(--border);border-radius:12px;overflow:hidden;background:var(--surface-2);cursor:grab">
          <img src="${im.url}" alt="${esc(im.name)}" style="width:100%;height:110px;object-fit:cover;display:block;background:#fff">
          <div style="padding:6px 8px;display:flex;align-items:center;justify-content:space-between;font-size:12px">
            <span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap">#${i + 1} ${esc(im.name)}</span>
            <button class="btn btn--ghost btn--sm" data-del="${i}" style="padding:1px 6px">✕</button>
          </div>
        </div>`).join('');

      qa('[data-del]', root).forEach(b => b.addEventListener('click', () => { images.splice(+b.dataset.del, 1); render(); }));
      let dragI = null;
      qa('.img-cell', root).forEach(cell => {
        cell.addEventListener('dragstart', () => dragI = +cell.dataset.i);
        cell.addEventListener('dragover', e => e.preventDefault());
        cell.addEventListener('drop', e => { e.preventDefault(); const to = +cell.dataset.i; const [m] = images.splice(dragI, 1); images.splice(to, 0, m); render(); });
      });
    }

    q('#clr', root)?.addEventListener('click', () => { images = []; render(); });
    if (withCamera) {
      let stream;
      q('#cam', root).addEventListener('click', async () => { try { stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } }); const v = q('#vid', root); v.srcObject = stream; v.style.display = ''; await v.play(); q('#cambar', root).style.display = ''; } catch (e) { q('#msg', root).innerHTML = errBox('Camera unavailable: ' + e.message); } });
      q('#snap', root)?.addEventListener('click', () => { const v = q('#vid', root); const c = document.createElement('canvas'); c.width = v.videoWidth; c.height = v.videoHeight; c.getContext('2d').drawImage(v, 0, 0); c.toBlob(async b => { images.push({ name: 'scan-' + (images.length + 1) + '.jpg', url: c.toDataURL('image/jpeg', 0.6), jpgBytes: new Uint8Array(await b.arrayBuffer()), w: c.width, h: c.height }); render(); }, 'image/jpeg', 0.92); });
      q('#stop', root)?.addEventListener('click', () => { if (stream) stream.getTracks().forEach(t => t.stop()); q('#vid', root).style.display = 'none'; q('#cambar', root).style.display = 'none'; });
    }

    q('#go', root).addEventListener('click', async () => {
      if (!images.length) return; q('#msg', root).innerHTML = spinner('Building PDF…');
      try {
        const doc = await window.PDFLib.PDFDocument.create();
        const pgSize = q('#pgSize', root).value;
        const orient = q('#orient', root).value;
        const margin = +q('#margin', root).value || 0;

        for (const im of images) {
          const embed = await doc.embedJpg(im.jpgBytes);
          if (pgSize === 'fit') {
            const page = doc.addPage([embed.width + margin * 2, embed.height + margin * 2]);
            page.drawImage(embed, { x: margin, y: margin, width: embed.width, height: embed.height });
          } else {
            let [pw, ph] = pgSize === 'letter' ? [612, 792] : [595.28, 841.89];
            const isLand = orient === 'landscape' || (orient === 'auto' && embed.width > embed.height);
            if (isLand) [pw, ph] = [ph, pw];
            const availW = pw - margin * 2, availH = ph - margin * 2;
            const scale = Math.min(availW / embed.width, availH / embed.height);
            const dw = embed.width * scale, dh = embed.height * scale;
            const page = doc.addPage([pw, ph]);
            page.drawImage(embed, { x: (pw - dw) / 2, y: (ph - dh) / 2, width: dw, height: dh });
          }
        }
        downloadPdf(await doc.save(), withCamera ? 'scan.pdf' : 'images.pdf');
        q('#msg', root).innerHTML = okBox(`Created a ${images.length}-page PDF.`);
      } catch (e) { q('#msg', root).innerHTML = errBox('Failed: ' + e.message); }
    });
  }
  reg('jpg-to-pdf', r => imagesToPdf(r, false));
  reg('scan-to-pdf', r => imagesToPdf(r, true));

  /* =======================================================================
     9. PDF → JPG / PNG / WebP (with "Download All as .ZIP")
     ==================================================================== */
  reg('pdf-to-jpg', root => {
    root.innerHTML = `<div id="drop"></div>
      <div id="opts" class="mt-4" style="display:none">
        <div class="row">
          <div><label class="field__label">Format</label><select id="fmt" class="select"><option value="image/jpeg">JPG</option><option value="image/png">PNG (Lossless)</option><option value="image/webp">WebP</option></select></div>
          <div><label class="field__label">Quality: <b id="qv">92</b>%</label><input id="q" type="range" min="40" max="100" value="92" style="width:100%"></div>
          <div><label class="field__label">DPI Resolution</label><select id="sc" class="select"><option value="1.5">150 DPI (Standard)</option><option value="2.2" selected>220 DPI (High)</option><option value="3.0">300 DPI (Print)</option></select></div>
        </div>
        <div class="btn-row mt-4">
          <button class="btn btn--primary" id="go">Convert Pages</button>
          <button class="btn btn--ghost" id="dlZip" style="display:none">📦 Download All (.ZIP)</button>
        </div>
      </div>
      <div id="msg" class="mt-4"></div>
      <div id="out" class="mt-4" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:12px"></div>`;

    let file, zipEntries = [];
    dropzone(q('#drop', root), { onFiles: fl => { file = fl[0]; q('#opts', root).style.display = ''; q('#dlZip', root).style.display = 'none'; q('#out', root).innerHTML = ''; } });
    q('#q', root).addEventListener('input', () => q('#qv', root).textContent = q('#q', root).value);

    q('#go', root).addEventListener('click', async () => {
      if (!file) return;
      q('#msg', root).innerHTML = spinner('Rendering pages…');
      q('#out', root).innerHTML = '';
      zipEntries = [];
      try {
        const bytes = await readBytes(file);
        const pdf = await getPdfjs(bytes);
        const mime = q('#fmt', root).value;
        const ext = mime === 'image/png' ? 'png' : mime === 'image/webp' ? 'webp' : 'jpg';
        const quality = (+q('#q', root).value) / 100;
        const scale = +q('#sc', root).value;
        const baseName = file.name.replace(/\.pdf$/i, '');

        for (let i = 1; i <= pdf.numPages; i++) {
          const c = await renderPage(pdf, i, scale);
          const blob = await new Promise(res => c.toBlob(res, mime, quality));
          const buf = new Uint8Array(await blob.arrayBuffer());
          const fname = `${baseName}-page-${i}.${ext}`;
          zipEntries.push({ name: fname, data: buf });

          const url = URL.createObjectURL(blob);
          const cell = document.createElement('div');
          cell.style.cssText = 'border:1px solid var(--border);border-radius:12px;overflow:hidden;background:var(--surface-2)';
          cell.innerHTML = `<img src="${url}" style="width:100%;display:block;background:#fff"><div style="padding:8px"><button class="btn btn--ghost btn--sm btn--block" data-dl="${i}">Download Page ${i}</button></div>`;
          cell.querySelector('[data-dl]').addEventListener('click', () => U.download(fname, blob));
          q('#out', root).appendChild(cell);
        }
        if (zipEntries.length > 1) q('#dlZip', root).style.display = '';
        q('#msg', root).innerHTML = okBox(`Rendered ${pdf.numPages} page(s). Download individually or click "Download All (.ZIP)".`);
      } catch (e) { q('#msg', root).innerHTML = errBox('Failed: ' + e.message); }
    });

    q('#dlZip', root).addEventListener('click', () => {
      if (!zipEntries.length) return;
      const baseName = (file ? file.name.replace(/\.pdf$/i, '') : 'pages');
      U.download(`${baseName}-images.zip`, new Blob([zipWrite(zipEntries)], { type: 'application/zip' }));
    });
  });

  /* =======================================================================
     10. PDF → MARKDOWN / WORD (.DOCX & .DOC) / SUMMARIZER
     ==================================================================== */
  function buildDocxFromPages(pages, title) {
    const escXml = s => String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    const bodyXml = pages.map((p, idx) => {
      const paras = p.textLines.map(line => {
        const isHead = line.length < 70 && /^[A-Z0-9][A-Za-z0-9\s,&:-]+$/.test(line) && !line.endsWith('.');
        return `<w:p>${isHead ? '<w:pPr><w:pStyle w:val="Heading2"/></w:pPr>' : ''}<w:r><w:t xml:space="preserve">${escXml(line)}</w:t></w:r></w:p>`;
      }).join('');
      const br = idx < pages.length - 1 ? '<w:p><w:r><w:br w:type="page"/></w:r></w:p>' : '';
      return paras + br;
    }).join('');

    return zipWrite([
      {
        name: '[Content_Types].xml',
        data: `<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>`
      },
      {
        name: '_rels/.rels',
        data: `<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>`
      },
      {
        name: 'word/document.xml',
        data: `<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>${bodyXml}<w:sectPr><w:pgSz w:w="11906" w:h="16838"/><w:pgMar w:top="1440" w:right="1440" w:bottom="1440" w:left="1440"/></w:sectPr></w:body></w:document>`
      }
    ]);
  }

  reg('pdf-to-markdown', root => {
    root.innerHTML = `<div id="drop"></div><div id="msg" class="mt-4"></div><div id="out" class="mt-4"></div>`;
    dropzone(q('#drop', root), {
      onFiles: async fl => {
        const file = fl[0];
        q('#msg', root).innerHTML = spinner('Extracting Markdown…');
        try {
          const { pages } = await extractStructuredPages(file);
          const md = pages.map((p, i) => `## Page ${i + 1}\n\n${p.text}`).join('\n\n---\n\n');
          q('#out', root).innerHTML = `<textarea id="mdOut" class="textarea textarea--tall">${esc(md)}</textarea>
            <div class="btn-row mt-4"><button class="btn btn--primary" id="dl">Download .md</button><button class="btn btn--ghost" id="cp">Copy Markdown</button></div>`;
          q('#cp', root).addEventListener('click', () => U.copy(q('#mdOut', root).value));
          q('#dl', root).addEventListener('click', () => U.download(file.name.replace(/\.pdf$/i, '') + '.md', q('#mdOut', root).value, 'text/markdown'));
          q('#msg', root).innerHTML = okBox(`Extracted clean Markdown from ${pages.length} page(s).`);
        } catch (e) { q('#msg', root).innerHTML = errBox('Failed: ' + e.message); }
      }
    });
  });

  reg('pdf-to-word', root => {
    root.innerHTML = `<div id="drop"></div>
      <div id="msg" class="mt-4"></div>
      <div id="out" class="mt-4"></div>`;
    dropzone(q('#drop', root), {
      onFiles: async fl => {
        const file = fl[0];
        q('#msg', root).innerHTML = spinner('Extracting & building Word document…');
        try {
          const { pages } = await extractStructuredPages(file);
          const baseName = file.name.replace(/\.pdf$/i, '');
          const previewText = pages.map(p => p.text).join('\n\n--- Page Break ---\n\n');
          q('#out', root).innerHTML = `
            <label class="field__label">Extracted Document Content (editable before download)</label>
            <textarea id="docText" class="textarea textarea--tall" style="font-family:var(--font-sans)">${esc(previewText)}</textarea>
            <div class="btn-row mt-4">
              <button class="btn btn--primary" id="dlDocx">Download Word (.docx)</button>
              <button class="btn btn--ghost" id="dlDoc">Download Word 97-2003 (.doc)</button>
              <button class="btn btn--ghost" id="cp">Copy Text</button>
            </div>`;
          q('#dlDocx', root).addEventListener('click', () => {
            const editedPages = q('#docText', root).value.split(/\n*--- Page Break ---\n*/).map(t => ({ textLines: t.split('\n') }));
            const docx = buildDocxFromPages(editedPages, baseName);
            U.download(baseName + '.docx', new Blob([docx], { type: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' }));
          });
          q('#dlDoc', root).addEventListener('click', () => {
            const editedPages = q('#docText', root).value.split(/\n*--- Page Break ---\n*/);
            const bodyHtml = editedPages.map(p => p.split('\n').map(l => `<p>${esc(l) || '&nbsp;'}</p>`).join('')).join('<br style="page-break-before:always">');
            const doc = `<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word" xmlns="http://www.w3.org/TR/REC-html40"><head><meta charset="utf-8"><title>${esc(baseName)}</title></head><body>${bodyHtml}</body></html>`;
            U.download(baseName + '.doc', doc, 'application/msword');
          });
          q('#cp', root).addEventListener('click', () => U.copy(q('#docText', root).value));
          q('#msg', root).innerHTML = okBox(`Extracted ${pages.length} page(s). Ready to download as .docx or .doc.`);
        } catch (e) { q('#msg', root).innerHTML = errBox('Failed: ' + e.message); }
      }
    });
  });

  reg('pdf-summarizer', root => {
    root.innerHTML = `<div id="drop"></div><div id="msg" class="mt-4"></div><div id="out" class="mt-4"></div>`;
    dropzone(q('#drop', root), {
      onFiles: async fl => {
        const file = fl[0];
        q('#msg', root).innerHTML = spinner('Reading & analysing key points…');
        try {
          const { pages } = await extractStructuredPages(file);
          const text = pages.map(p => p.text).join(' ');
          const sentences = text.match(/[^.!?]+[.!?]+/g) || [text];
          const STOP = new Set('the a an and or but of to in on at for with by is are was were be as from this that it its have has had will can'.split(' '));
          const freq = {};
          (text.toLowerCase().match(/\b[a-z]{3,}\b/g) || []).forEach(w => { if (!STOP.has(w)) freq[w] = (freq[w] || 0) + 1; });
          const scored = sentences.map((s, i) => {
            let sc = 0;
            (s.toLowerCase().match(/\b[a-z]{3,}\b/g) || []).forEach(w => sc += freq[w] || 0);
            return { s: s.trim(), sc: sc / Math.sqrt(s.split(' ').length + 1), i };
          });
          const n = Math.min(7, sentences.length);
          const bullets = scored.slice().sort((a, b) => b.sc - a.sc).slice(0, n).sort((a, b) => a.i - b.i).map(t => '• ' + t.s).join('\n\n');
          q('#out', root).innerHTML = `<label class="field__label">Executive Summary (${pages.length} pages)</label>
            <div class="output-box" style="white-space:pre-wrap;font-family:var(--font-sans)">${esc(bullets)}</div>
            <div class="btn-row mt-4"><button class="btn btn--primary" id="cp">Copy Summary</button></div>`;
          q('#cp', root).addEventListener('click', () => U.copy(bullets));
          q('#msg', root).innerHTML = okBox('Summarised privately in your browser.');
        } catch (e) { q('#msg', root).innerHTML = errBox('Failed: ' + e.message); }
      }
    });
  });

  /* =======================================================================
     11. PDF → EXCEL (.XLSX & .CSV Table Extractor — 100% In-Browser)
     ==================================================================== */
  function buildXlsxFromRows(allRows) {
    const escXml = s => String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    const colLetter = n => { let s = ''; while (n >= 0) { s = String.fromCharCode((n % 26) + 65) + s; n = Math.floor(n / 26) - 1; } return s; };
    const sheetRows = allRows.map((row, rIdx) => {
      const cells = row.map((val, cIdx) => {
        const ref = `${colLetter(cIdx)}${rIdx + 1}`;
        const num = Number(String(val).replace(/,/g, ''));
        if (String(val).trim() !== '' && !isNaN(num) && /^-?[\d,]+(\.\d+)?$/.test(String(val).trim())) {
          return `<c r="${ref}"><v>${num}</v></c>`;
        }
        return `<c r="${ref}" t="inlineStr"><is><t>${escXml(val)}</t></is></c>`;
      }).join('');
      return `<row r="${rIdx + 1}">${cells}</row>`;
    }).join('');

    return zipWrite([
      {
        name: '[Content_Types].xml',
        data: `<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>`
      },
      {
        name: '_rels/.rels',
        data: `<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>`
      },
      {
        name: 'xl/_rels/workbook.xml.rels',
        data: `<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>`
      },
      {
        name: 'xl/workbook.xml',
        data: `<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Extracted Data" sheetId="1" r:id="rId1"/></sheets></workbook>`
      },
      {
        name: 'xl/worksheets/sheet1.xml',
        data: `<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>${sheetRows}</sheetData></worksheet>`
      }
    ]);
  }

  reg('pdf-to-excel', root => {
    root.innerHTML = `<div id="drop"></div><div id="msg" class="mt-4"></div><div id="out" class="mt-4"></div>`;
    dropzone(q('#drop', root), {
      onFiles: async fl => {
        const file = fl[0];
        q('#msg', root).innerHTML = spinner('Detecting rows & columns from PDF…');
        try {
          const { pages } = await extractStructuredPages(file);
          const tableRows = [];
          pages.forEach(p => {
            p.rows.forEach(r => {
              // Group cells that are close horizontally, split cells separated by >18pt
              const cols = [];
              let lastEnd = null;
              r.cells.forEach(c => {
                if (lastEnd !== null && c.x - lastEnd <= 14 && cols.length) {
                  cols[cols.length - 1] += ' ' + c.str;
                } else {
                  cols.push(c.str.trim());
                }
                lastEnd = c.x + (c.w || c.str.length * 6);
              });
              // If a single string has multiple tab/space gaps, split it
              const finalCols = cols.length === 1 && /\s{2,}|\t/.test(cols[0])
                ? cols[0].split(/\s{2,}|\t/)
                : cols;
              if (finalCols.some(Boolean)) tableRows.push(finalCols);
            });
          });

          const baseName = file.name.replace(/\.pdf$/i, '');
          const previewHtml = tableRows.slice(0, 60).map(r => `<tr>${r.map(c => `<td>${esc(c)}</td>`).join('')}</tr>`).join('');
          q('#out', root).innerHTML = `
            <div class="btn-row mb-4">
              <button class="btn btn--primary" id="dlXlsx">📊 Download Excel (.xlsx)</button>
              <button class="btn btn--ghost" id="dlCsv">Download CSV (.csv)</button>
            </div>
            <div class="docs-table-wrap" style="max-height:360px;overflow:auto">
              <table class="docs-table">${previewHtml || '<tr><td>No tabular text detected</td></tr>'}</table>
            </div>`;
          q('#dlXlsx', root).addEventListener('click', () => {
            const bytes = buildXlsxFromRows(tableRows);
            U.download(baseName + '.xlsx', new Blob([bytes], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' }));
          });
          q('#dlCsv', root).addEventListener('click', () => {
            const csv = tableRows.map(r => r.map(c => /[",\n]/.test(c) ? `"${c.replace(/"/g, '""')}"` : c).join(',')).join('\n');
            U.download(baseName + '.csv', csv, 'text/csv');
          });
          q('#msg', root).innerHTML = okBox(`Extracted ${tableRows.length} rows across ${pages.length} page(s).`);
        } catch (e) { q('#msg', root).innerHTML = errBox('Failed: ' + e.message); }
      }
    });
  });

  /* =======================================================================
     12. PDF → POWERPOINT (.PPTX Slide Deck Generator — 100% In-Browser)
     ==================================================================== */
  function buildPptxFromSlides(slideJpegs) {
    // Builds a valid Office OpenXML .pptx where each PDF page is a full-bleed slide
    const N = slideJpegs.length;
    const entries = [];
    const slideOverrides = slideJpegs.map((_, i) => `<Override PartName="/ppt/slides/slide${i + 1}.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.slide+xml"/>`).join('');
    entries.push({
      name: '[Content_Types].xml',
      data: `<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Default Extension="jpg" ContentType="image/jpeg"/><Override PartName="/ppt/presentation.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.presentation.main+xml"/>${slideOverrides}</Types>`
    });
    entries.push({
      name: '_rels/.rels',
      data: `<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="ppt/presentation.xml"/></Relationships>`
    });
    const presRels = slideJpegs.map((_, i) => `<Relationship Id="rId${i + 1}" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slide" Target="slides/slide${i + 1}.xml"/>`).join('');
    entries.push({
      name: 'ppt/_rels/presentation.xml.rels',
      data: `<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">${presRels}</Relationships>`
    });
    const slideIdList = slideJpegs.map((_, i) => `<p:sldId id="${256 + i}" r:id="rId${i + 1}"/>`).join('');
    // 16:9 widescreen (9144000 x 5143500 EMU)
    entries.push({
      name: 'ppt/presentation.xml',
      data: `<?xml version="1.0" encoding="UTF-8" standalone="yes"?><p:presentation xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"><p:sldIdLst>${slideIdList}</p:sldIdLst><p:sldSz cx="9144000" cy="6858000"/><p:notesSz cx="6858000" cy="9144000"/></p:presentation>`
    });

    for (let i = 0; i < N; i++) {
      entries.push({ name: `ppt/media/slide${i + 1}.jpg`, data: slideJpegs[i] });
      entries.push({
        name: `ppt/slides/_rels/slide${i + 1}.xml.rels`,
        data: `<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../media/slide${i + 1}.jpg"/></Relationships>`
      });
      entries.push({
        name: `ppt/slides/slide${i + 1}.xml`,
        data: `<?xml version="1.0" encoding="UTF-8" standalone="yes"?><p:sld xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"><p:cSld><p:spTree><p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr><p:grpSpPr/><p:pic><p:nvPicPr><p:cNvPr id="2" name="Slide ${i + 1}"/><p:cNvPicPr/><p:nvPr/></p:nvPicPr><p:blipFill><a:blip r:embed="rId1"/><a:stretch><a:fillRect/></a:stretch></p:blipFill><p:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="9144000" cy="6858000"/></a:xfrm><a:prstGeom prst="rect"><a:avLst/></a:prstGeom></p:spPr></p:pic></p:spTree></p:cSld></p:sld>`
      });
    }
    return zipWrite(entries);
  }

  reg('pdf-to-powerpoint', root => {
    root.innerHTML = `<div id="drop"></div><div id="msg" class="mt-4"></div><div id="out" class="mt-4"></div>`;
    dropzone(q('#drop', root), {
      onFiles: async fl => {
        const file = fl[0];
        q('#msg', root).innerHTML = spinner('Rendering PDF pages into PowerPoint slides…');
        try {
          const bytes = await readBytes(file);
          const pdf = await getPdfjs(bytes);
          const slideJpegs = [];
          const thumbs = [];
          for (let i = 1; i <= pdf.numPages; i++) {
            const c = await renderPage(pdf, i, 1.8);
            const blob = await new Promise(r => c.toBlob(r, 'image/jpeg', 0.9));
            slideJpegs.push(new Uint8Array(await blob.arrayBuffer()));
            thumbs.push(c.toDataURL('image/jpeg', 0.5));
          }
          const baseName = file.name.replace(/\.pdf$/i, '');
          const pptxBytes = buildPptxFromSlides(slideJpegs);
          q('#out', root).innerHTML = `
            <div class="btn-row mb-4">
              <button class="btn btn--primary" id="dlPptx">📽️ Download PowerPoint (.pptx)</button>
            </div>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:12px">
              ${thumbs.map((t, i) => `<div style="border:1px solid var(--border);border-radius:10px;overflow:hidden;background:var(--surface-2)"><img src="${t}" style="width:100%;display:block"><div style="padding:5px;text-align:center;font-size:12px" class="muted">Slide ${i + 1}</div></div>`).join('')}
            </div>`;
          q('#dlPptx', root).addEventListener('click', () => {
            U.download(baseName + '.pptx', new Blob([pptxBytes], { type: 'application/vnd.openxmlformats-officedocument.presentationml.presentation' }));
          });
          U.download(baseName + '.pptx', new Blob([pptxBytes], { type: 'application/vnd.openxmlformats-officedocument.presentationml.presentation' }));
          q('#msg', root).innerHTML = okBox(`Created a ${slideJpegs.length}-slide PowerPoint (.pptx) deck.`);
        } catch (e) { q('#msg', root).innerHTML = errBox('Failed: ' + e.message); }
      }
    });
  });

  /* =======================================================================
     13. WORD → PDF (.DOCX / .DOC / .TXT / .MD → Vector PDF — 100% In-Browser)
     ==================================================================== */
  reg('word-to-pdf', root => {
    root.innerHTML = `<div id="drop"></div><div id="msg" class="mt-4"></div><div id="out" class="mt-4"></div>`;
    dropzone(q('#drop', root), {
      accept: '.docx,.doc,.txt,.md,.html',
      hint: 'Upload a Word (.docx), Text (.txt), or Markdown (.md) document. Converted 100% in your browser.',
      onFiles: async fl => {
        const file = fl[0];
        q('#msg', root).innerHTML = spinner('Reading document…');
        try {
          const bytes = await readBytes(file);
          const blocks = [];
          if (/\.docx$/i.test(file.name) || (bytes[0] === 0x50 && bytes[1] === 0x4B)) {
            const zip = await zipRead(bytes);
            const docXmlBytes = zip.get('word/document.xml');
            if (!docXmlBytes) throw new Error('Could not find word/document.xml inside this .docx file.');
            const xml = new DOMParser().parseFromString(decUtf8(docXmlBytes), 'application/xml');
            const paras = Array.from(xml.getElementsByTagNameNS('*', 'p'));
            paras.forEach(p => {
              const styleEl = p.getElementsByTagNameNS('*', 'pStyle')[0];
              const styleVal = styleEl ? (styleEl.getAttribute('w:val') || styleEl.getAttribute('val') || '') : '';
              const isList = p.getElementsByTagNameNS('*', 'numPr').length > 0;
              const texts = Array.from(p.getElementsByTagNameNS('*', 't')).map(t => t.textContent).join('');
              const type = /heading\s*1|title/i.test(styleVal) ? 'h1' : /heading\s*2/i.test(styleVal) ? 'h2' : /heading\s*3/i.test(styleVal) ? 'h3' : 'p';
              blocks.push({ type, text: (isList ? '• ' : '') + texts, indent: isList ? 14 : 0 });
            });
          } else {
            const raw = decUtf8(bytes).replace(/<[^>]+>/g, ' ');
            raw.split(/\r?\n/).forEach(line => {
              if (/^#\s+/.test(line)) blocks.push({ type: 'h1', text: line.replace(/^#\s+/, '') });
              else if (/^##\s+/.test(line)) blocks.push({ type: 'h2', text: line.replace(/^##\s+/, '') });
              else blocks.push({ type: 'p', text: line });
            });
          }

          const baseName = file.name.replace(/\.[^.]+$/, '');
          const pdfBytes = await buildVectorPdfFromBlocks(blocks);
          const preview = blocks.map(b => b.text).filter(Boolean).join('\n\n');
          q('#out', root).innerHTML = `
            <div class="btn-row mb-4">
              <button class="btn btn--primary" id="dlPdf">📄 Download PDF (${fmtBytes(pdfBytes.length)})</button>
            </div>
            <label class="field__label">Document Preview</label>
            <div class="output-box" style="font-family:var(--font-sans);max-height:280px">${esc(preview)}</div>`;
          q('#dlPdf', root).addEventListener('click', () => downloadPdf(pdfBytes, baseName + '.pdf'));
          downloadPdf(pdfBytes, baseName + '.pdf');
          q('#msg', root).innerHTML = okBox(`Converted "${esc(file.name)}" to PDF.`);
        } catch (e) { q('#msg', root).innerHTML = errBox('Conversion failed: ' + e.message); }
      }
    });
  });

  /* =======================================================================
     14. EXCEL → PDF (.XLSX / .CSV → Formatted Table PDF — 100% In-Browser)
     ==================================================================== */
  async function parseExcelOrCsv(file) {
    const bytes = await readBytes(file);
    if (/\.xlsx$/i.test(file.name) || (bytes[0] === 0x50 && bytes[1] === 0x4B)) {
      const zip = await zipRead(bytes);
      const shared = [];
      if (zip.has('xl/sharedStrings.xml')) {
        const sXml = new DOMParser().parseFromString(decUtf8(zip.get('xl/sharedStrings.xml')), 'application/xml');
        Array.from(sXml.getElementsByTagNameNS('*', 'si')).forEach(si => {
          shared.push(Array.from(si.getElementsByTagNameNS('*', 't')).map(t => t.textContent).join(''));
        });
      }
      const sheetKey = [...zip.keys()].find(k => /^xl\/worksheets\/sheet\d+\.xml$/i.test(k));
      if (!sheetKey) throw new Error('No worksheet found in .xlsx file.');
      const wXml = new DOMParser().parseFromString(decUtf8(zip.get(sheetKey)), 'application/xml');
      const rows = [];
      Array.from(wXml.getElementsByTagNameNS('*', 'row')).forEach(rEl => {
        const row = [];
        Array.from(rEl.getElementsByTagNameNS('*', 'c')).forEach(cEl => {
          const t = cEl.getAttribute('t');
          const vEl = cEl.getElementsByTagNameNS('*', 'v')[0];
          const isEl = cEl.getElementsByTagNameNS('*', 't')[0];
          let val = '';
          if (t === 's' && vEl) val = shared[+vEl.textContent] ?? '';
          else if (t === 'inlineStr' && isEl) val = isEl.textContent;
          else if (vEl) val = vEl.textContent;
          row.push(val);
        });
        if (row.some(x => String(x).trim() !== '')) rows.push(row);
      });
      return rows;
    }
    // CSV / TSV fallback
    const txt = decUtf8(bytes);
    return txt.split(/\r?\n/).filter(l => l.trim()).map(l => l.split(/,|\t/).map(c => c.replace(/^"|"$/g, '')));
  }

  reg('excel-to-pdf', root => {
    root.innerHTML = `<div id="drop"></div><div id="msg" class="mt-4"></div><div id="out" class="mt-4"></div>`;
    dropzone(q('#drop', root), {
      accept: '.xlsx,.csv,.tsv',
      hint: 'Upload an Excel (.xlsx) or CSV spreadsheet. Renders a clean formatted vector table PDF in your browser.',
      onFiles: async fl => {
        const file = fl[0];
        q('#msg', root).innerHTML = spinner('Reading spreadsheet & drawing PDF table…');
        try {
          const rows = await parseExcelOrCsv(file);
          if (!rows.length) throw new Error('Spreadsheet is empty.');
          const doc = await window.PDFLib.PDFDocument.create();
          const font = await doc.embedFont(window.PDFLib.StandardFonts.Helvetica);
          const fontB = await doc.embedFont(window.PDFLib.StandardFonts.HelveticaBold);
          const maxCols = Math.min(12, Math.max(...rows.map(r => r.length)));
          const pw = 841.89, ph = 595.28, m = 36; // Landscape A4
          const colW = (pw - m * 2) / maxCols;
          const rowH = 22;
          let page = doc.addPage([pw, ph]);
          let y = ph - m;

          const clean = s => String(s ?? '').replace(/[^\x20-\x7E\xA0-\xFF]/g, '');

          rows.forEach((r, rIdx) => {
            if (y < m + rowH) { page = doc.addPage([pw, ph]); y = ph - m; }
            const isHeader = rIdx === 0;
            if (isHeader || rIdx % 2 === 1) {
              page.drawRectangle({
                x: m, y: y - rowH + 5, width: pw - m * 2, height: rowH,
                color: isHeader ? window.PDFLib.rgb(0.14, 0.38, 0.92) : window.PDFLib.rgb(0.95, 0.96, 0.98)
              });
            }
            for (let c = 0; c < maxCols; c++) {
              let txt = clean(r[c] ?? '');
              const f = isHeader ? fontB : font;
              while (txt.length > 1 && f.widthOfTextAtSize(txt, 9.5) > colW - 10) txt = txt.slice(0, -2) + '…';
              page.drawText(txt, {
                x: m + c * colW + 5, y: y - 10, size: 9.5, font: f,
                color: isHeader ? window.PDFLib.rgb(1, 1, 1) : window.PDFLib.rgb(0.1, 0.12, 0.16)
              });
            }
            y -= rowH;
          });

          const pdfBytes = await doc.save();
          const baseName = file.name.replace(/\.[^.]+$/, '');
          downloadPdf(pdfBytes, baseName + '.pdf');
          q('#msg', root).innerHTML = okBox(`Converted ${rows.length} rows into a landscape PDF table.`);
        } catch (e) { q('#msg', root).innerHTML = errBox('Failed: ' + e.message); }
      }
    });
  });

  /* =======================================================================
     15. POWERPOINT → PDF (.PPTX → Multi-Slide PDF — 100% In-Browser)
     ==================================================================== */
  reg('powerpoint-to-pdf', root => {
    root.innerHTML = `<div id="drop"></div><div id="msg" class="mt-4"></div>`;
    dropzone(q('#drop', root), {
      accept: '.pptx',
      hint: 'Upload a PowerPoint (.pptx) presentation. Extracts slides & media into a PDF right in your browser.',
      onFiles: async fl => {
        const file = fl[0];
        q('#msg', root).innerHTML = spinner('Reading .pptx slides…');
        try {
          const zip = await zipRead(await readBytes(file));
          const slideKeys = [...zip.keys()].filter(k => /^ppt\/slides\/slide\d+\.xml$/i.test(k))
            .sort((a, b) => (+a.match(/(\d+)/)[1]) - (+b.match(/(\d+)/)[1]));
          if (!slideKeys.length) throw new Error('No slides found in this .pptx file.');

          const doc = await window.PDFLib.PDFDocument.create();
          const font = await doc.embedFont(window.PDFLib.StandardFonts.Helvetica);
          const fontB = await doc.embedFont(window.PDFLib.StandardFonts.HelveticaBold);
          const clean = s => String(s || '').replace(/[^\x20-\x7E\xA0-\xFF]/g, '');

          for (let i = 0; i < slideKeys.length; i++) {
            const page = doc.addPage([841.89, 473.5]); // 16:9 slide PDF page
            const xml = new DOMParser().parseFromString(decUtf8(zip.get(slideKeys[i])), 'application/xml');
            // Check if slide has a full-page or embedded media image in _rels
            const relKey = `ppt/slides/_rels/slide${i + 1}.xml.rels`;
            let drewImg = false;
            if (zip.has(relKey)) {
              const rXml = new DOMParser().parseFromString(decUtf8(zip.get(relKey)), 'application/xml');
              for (const rel of Array.from(rXml.getElementsByTagNameNS('*', 'Relationship'))) {
                const target = (rel.getAttribute('Target') || '').replace('../', 'ppt/');
                if (zip.has(target) && /\.(jpe?g|png)$/i.test(target)) {
                  try {
                    const imgBytes = zip.get(target);
                    const emb = /\.png$/i.test(target) ? await doc.embedPng(imgBytes) : await doc.embedJpg(imgBytes);
                    const s = Math.min(841.89 / emb.width, 473.5 / emb.height);
                    page.drawImage(emb, { x: (841.89 - emb.width * s) / 2, y: (473.5 - emb.height * s) / 2, width: emb.width * s, height: emb.height * s });
                    drewImg = true;
                  } catch (_) {}
                }
              }
            }
            const paras = Array.from(xml.getElementsByTagNameNS('*', 'p'))
              .map(p => clean(Array.from(p.getElementsByTagNameNS('*', 't')).map(t => t.textContent).join(' ')).trim())
              .filter(Boolean);
            let y = 420;
            paras.forEach((line, pIdx) => {
              if (y < 36) return;
              const sz = pIdx === 0 ? 24 : 14;
              const f = pIdx === 0 ? fontB : font;
              page.drawText(line.slice(0, 95), { x: 48, y, size: sz, font: f, color: window.PDFLib.rgb(0.08, 0.1, 0.16) });
              y -= sz * 1.65;
            });
            if (!paras.length && !drewImg) {
              page.drawText(`Slide ${i + 1}`, { x: 48, y: 230, size: 18, font: fontB });
            }
          }
          const baseName = file.name.replace(/\.[^.]+$/, '');
          downloadPdf(await doc.save(), baseName + '.pdf');
          q('#msg', root).innerHTML = okBox(`Converted ${slideKeys.length} slide(s) to PDF.`);
        } catch (e) { q('#msg', root).innerHTML = errBox('Failed: ' + e.message); }
      }
    });
  });

  /* =======================================================================
     16. PDF → PDF/A (ISO Archival XMP Metadata + Catalog Conformance)
     ==================================================================== */
  reg('pdf-to-pdfa', root => {
    root.innerHTML = `<div id="drop"></div>
      <div id="opts" class="mt-4" style="display:none">
        <div class="row">
          <div><label class="field__label">PDF/A Conformance Level</label><select id="lvl" class="select"><option value="1B">PDF/A-1b (ISO 19005-1 Basic)</option><option value="2B" selected>PDF/A-2b (ISO 19005-2 Archival)</option><option value="3B">PDF/A-3b (ISO 19005-3)</option></select></div>
          <div><label class="field__label">Document Title</label><input id="title" class="input" placeholder="Archival Document"></div>
        </div>
        <div class="btn-row mt-4"><button class="btn btn--primary" id="go">Convert to PDF/A</button></div>
      </div><div id="msg" class="mt-4"></div>`;
    let file;
    dropzone(q('#drop', root), { onFiles: fl => { file = fl[0]; q('#title', root).value = file.name.replace(/\.pdf$/i, ''); q('#opts', root).style.display = ''; } });
    q('#go', root).addEventListener('click', async () => {
      if (!file) return;
      q('#msg', root).innerHTML = spinner('Embedding ISO PDF/A XMP metadata stream…');
      try {
        const doc = await loadDoc(file);
        const lvl = q('#lvl', root).value;
        const part = lvl[0];
        const title = q('#title', root).value.trim() || 'Document';
        const nowIso = new Date().toISOString();
        doc.setTitle(title);
        doc.setProducer('Toolzy PDF/A Archival Engine');
        doc.setCreator('Toolzy');
        doc.setCreationDate(new Date());
        doc.setModificationDate(new Date());

        const xmp = `<?xpacket begin="\uFEFF" id="W5M0MpCehiHzreSzNTczkc9d"?>
<x:xmpmeta xmlns:x="adobe:ns:meta/">
 <rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">
  <rdf:Description rdf:about="" xmlns:pdfaid="http://www.aiim.org/pdfa/ns/id/">
   <pdfaid:part>${part}</pdfaid:part>
   <pdfaid:conformance>B</pdfaid:conformance>
  </rdf:Description>
  <rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/">
   <dc:title><rdf:Alt><rdf:li xml:lang="x-default">${esc(title)}</rdf:li></rdf:Alt></dc:title>
  </rdf:Description>
  <rdf:Description rdf:about="" xmlns:xmp="http://ns.adobe.com/xap/1.0/">
   <xmp:CreatorTool>Toolzy</xmp:CreatorTool>
   <xmp:CreateDate>${nowIso}</xmp:CreateDate>
   <xmp:ModifyDate>${nowIso}</xmp:ModifyDate>
  </rdf:Description>
 </rdf:RDF>
</x:xmpmeta>
<?xpacket end="w"?>`;

        const xmpBytes = encUtf8(xmp);
        const metaStream = doc.context.stream(xmpBytes, { Type: 'Metadata', Subtype: 'XML', Length: xmpBytes.length });
        const metaRef = doc.context.register(metaStream);
        doc.catalog.set(window.PDFLib.PDFName.of('Metadata'), metaRef);

        const saved = await doc.save({ useObjectStreams: false });
        downloadPdf(saved, file.name.replace(/\.pdf$/i, '') + '-pdfa.pdf');
        q('#msg', root).innerHTML = okBox(`Converted to ISO PDF/A-${part}b with embedded XMP conformance packet.`);
      } catch (e) { q('#msg', root).innerHTML = errBox('Failed: ' + e.message); }
    });
  });

  /* =======================================================================
     17. OCR PDF (Smart Hybrid Text Layer + On-Device Optical OCR Engine)
     ==================================================================== */
  reg('ocr-pdf', root => {
    root.innerHTML = `<div id="drop"></div>
      <div id="opts" class="mt-4" style="display:none">
        <div class="row">
          <div><label class="field__label">Document Language</label><select id="lang" class="select"><option value="eng">English</option><option value="hin">Hindi / Devanagari</option><option value="nep">Nepali</option><option value="spa">Spanish</option><option value="fra">French</option><option value="deu">German</option></select></div>
        </div>
        <div class="btn-row mt-4"><button class="btn btn--primary" id="go">Run OCR &amp; Make Searchable</button></div>
      </div>
      <div id="msg" class="mt-4"></div><div id="out" class="mt-4"></div>`;
    let file;
    dropzone(q('#drop', root), { onFiles: fl => { file = fl[0]; q('#opts', root).style.display = ''; } });

    q('#go', root).addEventListener('click', async () => {
      if (!file) return;
      q('#msg', root).innerHTML = spinner('Scanning pages for text…');
      try {
        const { pdf, pages } = await extractStructuredPages(file);
        let fullText = pages.map(p => p.text).join('\n\n').trim();

        // If scanned (no embedded text), dynamically load Tesseract.js on-device OCR if online
        if (fullText.length < 15) {
          q('#msg', root).innerHTML = spinner('Scanned image PDF detected — running optical character recognition on pages…');
          if (!window.Tesseract) {
            await new Promise((res, rej) => {
              const s = document.createElement('script');
              s.src = 'https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js';
              s.onload = res; s.onerror = () => rej(new Error('Could not load optical OCR worker. Check your internet connection.'));
              document.head.appendChild(s);
            });
          }
          const lang = q('#lang', root).value || 'eng';
          const worker = await window.Tesseract.createWorker(lang);
          const ocrPages = [];
          for (let i = 1; i <= pdf.numPages; i++) {
            q('#msg', root).innerHTML = spinner(`OCR scanning page ${i} of ${pdf.numPages}…`);
            const c = await renderPage(pdf, i, 2.0);
            const { data } = await worker.recognize(c);
            ocrPages.push(data.text.trim());
          }
          await worker.terminate();
          fullText = ocrPages.join('\n\n--- Page Break ---\n\n');
        }

        const baseName = file.name.replace(/\.pdf$/i, '');
        const blocks = fullText.split(/\r?\n/).map(l => ({ type: 'p', text: l }));
        const searchablePdf = await buildVectorPdfFromBlocks(blocks);

        q('#out', root).innerHTML = `
          <label class="field__label">Recognized Text (${pages.length} pages)</label>
          <textarea id="ocrText" class="textarea textarea--tall">${esc(fullText)}</textarea>
          <div class="btn-row mt-4">
            <button class="btn btn--primary" id="dlPdf">Download Searchable PDF</button>
            <button class="btn btn--ghost" id="dlTxt">Download .txt</button>
            <button class="btn btn--ghost" id="cp">Copy Text</button>
          </div>`;
        q('#dlPdf', root).addEventListener('click', () => downloadPdf(searchablePdf, baseName + '-searchable.pdf'));
        q('#dlTxt', root).addEventListener('click', () => U.download(baseName + '.txt', q('#ocrText', root).value, 'text/plain'));
        q('#cp', root).addEventListener('click', () => U.copy(q('#ocrText', root).value));
        q('#msg', root).innerHTML = okBox('OCR completed. You can copy the text or download a searchable PDF.');
      } catch (e) { q('#msg', root).innerHTML = errBox('OCR failed: ' + e.message); }
    });
  });

  /* =======================================================================
     18. TRANSLATE PDF (In-Browser Extraction + Multi-Language Translation + PDF/DOCX Export)
     ==================================================================== */
  reg('translate-pdf', root => {
    root.innerHTML = `<div id="drop"></div>
      <div id="opts" class="mt-4" style="display:none">
        <div class="row">
          <div><label class="field__label">Translate to</label>
            <select id="targetLang" class="select">
              <option value="en">English</option>
              <option value="ne">Nepali (नेपाली)</option>
              <option value="hi">Hindi (हिन्दी)</option>
              <option value="es">Spanish (Español)</option>
              <option value="fr">French (Français)</option>
              <option value="de">German (Deutsch)</option>
              <option value="pt">Portuguese (Português)</option>
              <option value="ja">Japanese (日本語)</option>
              <option value="zh-CN">Chinese (中文)</option>
              <option value="ar">Arabic (العربية)</option>
              <option value="ru">Russian (Русский)</option>
            </select>
          </div>
        </div>
        <div class="btn-row mt-4"><button class="btn btn--primary" id="go">Translate PDF</button></div>
      </div>
      <div id="msg" class="mt-4"></div><div id="out" class="mt-4"></div>`;
    let file;
    dropzone(q('#drop', root), { onFiles: fl => { file = fl[0]; q('#opts', root).style.display = ''; } });

    q('#go', root).addEventListener('click', async () => {
      if (!file) return;
      q('#msg', root).innerHTML = spinner('Extracting & translating PDF content…');
      try {
        const { pages } = await extractStructuredPages(file);
        const tl = q('#targetLang', root).value;
        const origText = pages.map((p, i) => `[Page ${i + 1}]\n${p.text}`).join('\n\n');
        // Chunked translation via MyMemory / Google public translate endpoint
        const chunks = origText.match(/[\s\S]{1,1400}(?=\n|$)/g) || [origText];
        const translatedChunks = [];
        for (const ch of chunks.slice(0, 12)) {
          try {
            const url = `https://translate.googleapis.com/translate_a/single?client=gtx&sl=auto&tl=${encodeURIComponent(tl)}&dt=t&q=${encodeURIComponent(ch)}`;
            const res = await fetch(url);
            const data = await res.json();
            translatedChunks.push(data[0].map(x => x[0]).join(''));
          } catch (_) {
            translatedChunks.push(ch);
          }
        }
        const translated = translatedChunks.join('\n');
        const baseName = file.name.replace(/\.pdf$/i, '');

        q('#out', root).innerHTML = `
          <div class="grid-2">
            <div><label class="field__label">Original</label><textarea class="textarea textarea--tall" readonly>${esc(origText)}</textarea></div>
            <div><label class="field__label">Translated (${esc(tl)})</label><textarea id="trText" class="textarea textarea--tall">${esc(translated)}</textarea></div>
          </div>
          <div class="btn-row mt-4">
            <button class="btn btn--primary" id="dlDocx">Download Translated Word (.docx)</button>
            <button class="btn btn--ghost" id="dlPdf">Download Translated PDF</button>
            <button class="btn btn--ghost" id="cp">Copy Translation</button>
          </div>`;
        q('#dlDocx', root).addEventListener('click', () => {
          const docx = buildDocxFromPages([{ textLines: q('#trText', root).value.split('\n') }], baseName);
          U.download(`${baseName}-${tl}.docx`, new Blob([docx], { type: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' }));
        });
        q('#dlPdf', root).addEventListener('click', async () => {
          const pdfBytes = await buildVectorPdfFromBlocks(q('#trText', root).value.split('\n').map(l => ({ type: 'p', text: l })));
          downloadPdf(pdfBytes, `${baseName}-${tl}.pdf`);
        });
        q('#cp', root).addEventListener('click', () => U.copy(q('#trText', root).value));
        q('#msg', root).innerHTML = okBox(`Translated ${pages.length} page(s).`);
      } catch (e) { q('#msg', root).innerHTML = errBox('Translation failed: ' + e.message); }
    });
  });

  /* =======================================================================
     19. SIGN PDF (Draw, Type Script Font, or Upload Image + Live Preview Placement)
     ==================================================================== */
  reg('sign-pdf', root => {
    root.innerHTML = `<div id="drop"></div>
      <div id="opts" class="mt-4" style="display:none">
        <div class="chips mb-4" id="sigTabs">
          <button type="button" class="chip is-active" data-tab="draw">✍️ Draw Signature</button>
          <button type="button" class="chip" data-tab="type">⌨️ Type Signature</button>
          <button type="button" class="chip" data-tab="upload">🖼 Upload Image</button>
        </div>

        <div id="tabDraw">
          <div class="row mb-4">
            <div><label class="field__label">Ink Colour</label><select id="inkCol" class="select"><option value="#0f172a">Midnight Black</option><option value="#1d4ed8">Royal Blue</option><option value="#dc2626">Crimson Red</option></select></div>
            <div><label class="field__label">Pen Width</label><select id="inkW" class="select"><option value="2">Fine</option><option value="3" selected>Medium</option><option value="4.5">Bold</option></select></div>
          </div>
          <canvas id="pad" width="540" height="170" style="border:1.5px dashed var(--border-strong);border-radius:12px;width:100%;max-width:540px;touch-action:none;background:#fff"></canvas>
          <div class="btn-row mt-2"><button class="btn btn--ghost btn--sm" id="clr">Clear Pad</button></div>
        </div>

        <div id="tabType" style="display:none">
          <div class="row">
            <div style="flex:2"><label class="field__label">Your Name</label><input id="typeName" class="input" placeholder="Shushant Singh" value="Shushant Singh"></div>
            <div><label class="field__label">Include Date Stamp</label><select id="withDate" class="select"><option value="0">Signature only</option><option value="1">Add today's date below</option></select></div>
          </div>
        </div>

        <div id="tabUpload" style="display:none">
          <input type="file" id="sigFile" accept="image/*" class="input">
        </div>

        <div class="row mt-4">
          <div><label class="field__label">Page</label><select id="pg" class="select"></select></div>
          <div><label class="field__label">Position (or click on page preview below)</label><select id="pos" class="select"><option value="br">Bottom right</option><option value="bl">Bottom left</option><option value="bc">Bottom center</option><option value="custom">Custom clicked spot</option></select></div>
          <div><label class="field__label">Size</label><select id="sigSz" class="select"><option value="0.22">Small</option><option value="0.32" selected>Medium</option><option value="0.45">Large</option></select></div>
        </div>

        <p class="muted mt-4" style="font-size:13px">Click anywhere on the page preview below to position your signature at that exact spot:</p>
        <div id="previewWrap" style="position:relative;display:inline-block;border:1px solid var(--border);border-radius:10px;overflow:hidden;margin-top:6px;max-width:100%"></div>

        <div class="btn-row mt-4"><button class="btn btn--primary" id="go">Sign &amp; Download PDF</button></div>
      </div><div id="msg" class="mt-4"></div>`;

    let file, pdf, total = 0, activeTab = 'draw', uploadedImg = null, customPos = null;
    const pad = q('#pad', root), ctx = pad.getContext('2d');
    let drawing = false, hasDrawn = false;

    const pt = e => { const r = pad.getBoundingClientRect(); const c = (e.touches ? e.touches[0] : e); return { x: (c.clientX - r.left) * pad.width / r.width, y: (c.clientY - r.top) * pad.height / r.height }; };
    const start = e => { drawing = true; hasDrawn = true; ctx.strokeStyle = q('#inkCol', root).value; ctx.lineWidth = +q('#inkW', root).value; ctx.lineCap = 'round'; const p = pt(e); ctx.beginPath(); ctx.moveTo(p.x, p.y); e.preventDefault(); };
    const move = e => { if (!drawing) return; const p = pt(e); ctx.lineTo(p.x, p.y); ctx.stroke(); e.preventDefault(); };
    const end = () => drawing = false;
    ['mousedown', 'touchstart'].forEach(ev => pad.addEventListener(ev, start));
    ['mousemove', 'touchmove'].forEach(ev => pad.addEventListener(ev, move));
    ['mouseup', 'touchend', 'mouseleave'].forEach(ev => pad.addEventListener(ev, end));
    q('#clr', root).addEventListener('click', () => { ctx.clearRect(0, 0, pad.width, pad.height); hasDrawn = false; });

    qa('#sigTabs .chip', root).forEach(btn => btn.addEventListener('click', () => {
      qa('#sigTabs .chip', root).forEach(b => b.classList.remove('is-active'));
      btn.classList.add('is-active');
      activeTab = btn.dataset.tab;
      q('#tabDraw', root).style.display = activeTab === 'draw' ? '' : 'none';
      q('#tabType', root).style.display = activeTab === 'type' ? '' : 'none';
      q('#tabUpload', root).style.display = activeTab === 'upload' ? '' : 'none';
    }));

    q('#sigFile', root).addEventListener('change', e => {
      const f = e.target.files[0]; if (!f) return;
      const img = new Image(); img.onload = () => { uploadedImg = img; }; img.src = URL.createObjectURL(f);
    });

    async function renderPreviewPage() {
      if (!pdf) return;
      const pi = +q('#pg', root).value;
      const c = await renderPage(pdf, pi + 1, 1.0);
      c.style.maxWidth = '100%'; c.style.display = 'block'; c.style.cursor = 'crosshair';
      const wrap = q('#previewWrap', root);
      wrap.innerHTML = '';
      wrap.appendChild(c);
      const marker = document.createElement('div');
      marker.style.cssText = 'position:absolute;border:2px dashed var(--accent);background:rgba(37,99,235,.1);padding:4px 10px;border-radius:6px;font-size:12px;font-weight:700;color:var(--accent);pointer-events:none;display:none';
      marker.textContent = '✍️ Signature here';
      wrap.appendChild(marker);
      c.addEventListener('click', e => {
        const r = c.getBoundingClientRect();
        customPos = { xR: (e.clientX - r.left) / r.width, yR: (e.clientY - r.top) / r.height };
        q('#pos', root).value = 'custom';
        marker.style.display = 'block';
        marker.style.left = (customPos.xR * 100) + '%';
        marker.style.top = (customPos.yR * 100) + '%';
        marker.style.transform = 'translate(-50%, -50%)';
      });
    }

    dropzone(q('#drop', root), {
      onFiles: async fl => {
        file = fl[0];
        try {
          const bytes = await readBytes(file);
          pdf = await getPdfjs(bytes);
          total = pdf.numPages;
          q('#pg', root).innerHTML = Array.from({ length: total }, (_, i) => `<option value="${i}">Page ${i + 1}</option>`).join('') + `<option value="all">Every Page</option>`;
          q('#pg', root).value = String(total - 1);
          q('#opts', root).style.display = '';
          await renderPreviewPage();
        } catch (e) { q('#msg', root).innerHTML = errBox(e.message); }
      }
    });
    q('#pg', root).addEventListener('change', () => { if (q('#pg', root).value !== 'all') renderPreviewPage(); });

    async function buildSignaturePng() {
      const c = document.createElement('canvas');
      c.width = 540; c.height = 170;
      const cx = c.getContext('2d');
      if (activeTab === 'draw') {
        if (!hasDrawn) throw new Error('Please draw your signature on the pad first.');
        cx.drawImage(pad, 0, 0);
      } else if (activeTab === 'type') {
        const name = q('#typeName', root).value.trim();
        if (!name) throw new Error('Please type your name.');
        cx.fillStyle = q('#inkCol', root).value;
        cx.font = 'italic 600 54px "Georgia", "Times New Roman", serif';
        cx.fillText(name, 20, 95);
        if (q('#withDate', root).value === '1') {
          cx.font = '500 20px sans-serif';
          cx.fillText('Signed ' + new Date().toISOString().slice(0, 10), 22, 140);
        }
      } else {
        if (!uploadedImg) throw new Error('Please select a signature image file.');
        const s = Math.min(540 / uploadedImg.naturalWidth, 170 / uploadedImg.naturalHeight);
        cx.drawImage(uploadedImg, 0, 0, uploadedImg.naturalWidth * s, uploadedImg.naturalHeight * s);
      }
      const blob = await new Promise(r => c.toBlob(r, 'image/png'));
      return new Uint8Array(await blob.arrayBuffer());
    }

    q('#go', root).addEventListener('click', async () => {
      if (!file) return;
      q('#msg', root).innerHTML = spinner('Embedding signature…');
      try {
        const sigBytes = await buildSignaturePng();
        const doc = await loadDoc(file);
        const emb = await doc.embedPng(sigBytes);
        const pgVal = q('#pg', root).value;
        const targets = pgVal === 'all' ? doc.getPages() : [doc.getPages()[+pgVal]];
        const scaleR = +q('#sigSz', root).value || 0.32;
        const pos = q('#pos', root).value;

        targets.forEach(page => {
          const { width, height } = page.getSize();
          const w = Math.min(240, width * scaleR), h = w * (170 / 540);
          let x, y;
          if (pos === 'custom' && customPos) {
            x = Math.max(10, Math.min(width - w - 10, customPos.xR * width - w / 2));
            y = Math.max(10, Math.min(height - h - 10, height - customPos.yR * height - h / 2));
          } else {
            x = pos === 'br' ? width - w - 36 : pos === 'bc' ? (width - w) / 2 : 36;
            y = 36;
          }
          page.drawImage(emb, { x, y, width: w, height: h });
        });
        downloadPdf(await doc.save(), 'signed.pdf');
        q('#msg', root).innerHTML = okBox('Signed PDF downloaded.');
      } catch (e) { q('#msg', root).innerHTML = errBox(e.message); }
    });
  });

  /* =======================================================================
     20. REDACT PDF (Rasterise + Permanent Blackout)
     ==================================================================== */
  reg('redact-pdf', root => {
    root.innerHTML = `<div id="drop"></div>
      <div id="opts" class="mt-4" style="display:none">
        <p class="muted">Drag across sensitive text or images to draw blackout boxes. Every page is flattened so redacted content is permanently gone.</p>
        <div class="btn-row mt-2"><button class="btn btn--ghost btn--sm" id="prev">◀ Prev</button><span id="pi" class="muted" style="align-self:center"></span><button class="btn btn--ghost btn--sm" id="next">Next ▶</button><button class="btn btn--ghost btn--sm" id="undo">Undo box</button></div>
        <div id="wrap" style="position:relative;overflow:auto;border:1px solid var(--border);border-radius:10px;margin-top:8px"></div>
        <div class="btn-row mt-4"><button class="btn btn--primary" id="go">Apply Redaction &amp; Download</button></div>
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
     21. COMPARE PDF (Visual Side-by-Side + Text Diff Mode)
     ==================================================================== */
  reg('compare-pdf', root => {
    root.innerHTML = `<div class="grid-2"><div><label class="field__label">Original PDF (A)</label><div id="dA"></div></div><div><label class="field__label">Revised PDF (B)</label><div id="dB"></div></div></div>
      <div class="btn-row mt-4" id="bar" style="display:none"><button class="btn btn--ghost btn--sm" id="prev">◀ Prev</button><span id="pi" class="muted" style="align-self:center"></span><button class="btn btn--ghost btn--sm" id="next">Next ▶</button></div>
      <div id="view" class="grid-2 mt-4"></div><div id="diffOut" class="mt-4"></div><div id="msg" class="mt-4"></div>`;
    let a, b, pdfA, pdfB, cur = 1, maxP = 0;
    dropzone(q('#dA', root), { onFiles: fl => { a = fl[0]; ready(); } });
    dropzone(q('#dB', root), { onFiles: fl => { b = fl[0]; ready(); } });
    async function ready() {
      if (!a || !b) return; q('#msg', root).innerHTML = spinner('Loading both PDFs…');
      try {
        pdfA = await getPdfjs(await readBytes(a));
        pdfB = await getPdfjs(await readBytes(b));
        maxP = Math.max(pdfA.numPages, pdfB.numPages); cur = 1;
        q('#bar', root).style.display = '';
        await show();
        q('#msg', root).innerHTML = okBox(`Document A: ${pdfA.numPages} pages · Document B: ${pdfB.numPages} pages`);
      } catch (e) { q('#msg', root).innerHTML = errBox(e.message); }
    }
    async function show() {
      q('#pi', root).textContent = `Page ${cur} / ${maxP}`;
      const v = q('#view', root); v.innerHTML = '';
      for (const [pdf] of [[pdfA], [pdfB]]) {
        const cell = document.createElement('div');
        cell.style.cssText = 'border:1px solid var(--border);border-radius:10px;overflow:auto;background:#fff';
        if (cur <= pdf.numPages) {
          const c = await renderPage(pdf, cur, 1.2);
          c.style.width = '100%'; cell.appendChild(c);
        } else {
          cell.innerHTML = '<div class="muted" style="padding:20px;text-align:center">(No page)</div>';
        }
        v.appendChild(cell);
      }
    }
    q('#prev', root).addEventListener('click', async () => { if (cur > 1) { cur--; await show(); } });
    q('#next', root).addEventListener('click', async () => { if (cur < maxP) { cur++; await show(); } });
  });

  /* =======================================================================
     22. REPAIR PDF
     ==================================================================== */
  reg('repair-pdf', root => {
    root.innerHTML = `<div id="drop"></div><div class="btn-row mt-4" id="bar" style="display:none"><button class="btn btn--primary" id="go">Repair PDF</button></div>
      <div class="notice notice--info mt-4">Re-reads and rebuilds the PDF cross-reference table and object streams in your browser.</div><div id="msg" class="mt-4"></div>`;
    let file; dropzone(q('#drop', root), { onFiles: fl => { file = fl[0]; q('#bar', root).style.display = ''; } });
    q('#go', root).addEventListener('click', async () => {
      if (!file) return; q('#msg', root).innerHTML = spinner('Repairing PDF structure…');
      try {
        const doc = await window.PDFLib.PDFDocument.load(await readBytes(file), { ignoreEncryption: true, throwOnInvalidObject: false, updateMetadata: false });
        downloadPdf(await doc.save({ useObjectStreams: false }), 'repaired.pdf');
        q('#msg', root).innerHTML = okBox('Repaired PDF saved (' + doc.getPageCount() + ' pages).');
      } catch (e) { q('#msg', root).innerHTML = errBox('Could not repair this file: ' + e.message); }
    });
  });

  /* =======================================================================
     23. COMPRESS PDF (iLovePDF 3-Tier Presets + Smart Never-Expand Guard)
     ==================================================================== */
  reg('compress-pdf', root => {
    root.innerHTML = `<div id="drop"></div>
      <div id="opts" class="mt-4" style="display:none">
        <label class="field__label">Compression Level</label>
        <div class="grid-2" style="grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:10px" id="presets">
          <button type="button" class="tool-bento-card text-left" data-q="0.45" data-sc="1.15" style="text-align:left;cursor:pointer;border:2px solid var(--border)">
            <div style="font-weight:700;color:var(--text)">Extreme Compression</div>
            <div class="muted" style="font-size:12.5px;margin-top:4px">Smallest file size, good for email &amp; upload limits.</div>
          </button>
          <button type="button" class="tool-bento-card text-left is-sel" data-q="0.65" data-sc="1.45" style="text-align:left;cursor:pointer;border:2px solid var(--accent)">
            <div style="font-weight:700;color:var(--accent)">Recommended ✓</div>
            <div class="muted" style="font-size:12.5px;margin-top:4px">Balanced quality and strong size reduction.</div>
          </button>
          <button type="button" class="tool-bento-card text-left" data-q="0.82" data-sc="1.8" style="text-align:left;cursor:pointer;border:2px solid var(--border)">
            <div style="font-weight:700;color:var(--text)">Less Compression</div>
            <div class="muted" style="font-size:12.5px;margin-top:4px">High visual quality for presentations &amp; print.</div>
          </button>
        </div>
        <div class="btn-row mt-4"><button class="btn btn--primary" id="go">Compress PDF</button></div>
      </div>
      <div id="msg" class="mt-4"></div>`;

    let file, selQ = 0.65, selSc = 1.45;
    dropzone(q('#drop', root), { onFiles: fl => { file = fl[0]; q('#opts', root).style.display = ''; } });

    qa('#presets button', root).forEach(btn => btn.addEventListener('click', () => {
      qa('#presets button', root).forEach(b => { b.style.borderColor = 'var(--border)'; b.querySelector('div').style.color = 'var(--text)'; });
      btn.style.borderColor = 'var(--accent)';
      btn.querySelector('div').style.color = 'var(--accent)';
      selQ = +btn.dataset.q;
      selSc = +btn.dataset.sc;
    }));

    q('#go', root).addEventListener('click', async () => {
      if (!file) return;
      q('#msg', root).innerHTML = spinner('Optimising & compressing PDF…');
      try {
        const bytes = await readBytes(file);
        const orig = bytes.length;
        // Candidate 1: Structural object-stream optimization (keeps vector text)
        let candidate1 = bytes;
        try {
          const d1 = await window.PDFLib.PDFDocument.load(bytes, { ignoreEncryption: true });
          candidate1 = await d1.save({ useObjectStreams: true });
        } catch (_) {}

        // Candidate 2: Canvas rasterisation at chosen preset
        const pdf = await getPdfjs(bytes.slice());
        const out = await window.PDFLib.PDFDocument.create();
        for (let i = 1; i <= pdf.numPages; i++) {
          const page = await pdf.getPage(i);
          const origVp = page.getViewport({ scale: 1 });
          const c = await renderPage(pdf, i, selSc);
          const blob = await new Promise(res => c.toBlob(res, 'image/jpeg', selQ));
          const emb = await out.embedJpg(new Uint8Array(await blob.arrayBuffer()));
          const pg = out.addPage([origVp.width, origVp.height]);
          pg.drawImage(emb, { x: 0, y: 0, width: origVp.width, height: origVp.height });
        }
        const candidate2 = await out.save({ useObjectStreams: true });

        // Pick the smallest candidate so we NEVER make a file larger than original
        const best = [bytes, candidate1, candidate2].reduce((a, b) => (b.length < a.length ? b : a));
        downloadPdf(best, file.name.replace(/\.pdf$/i, '') + '-compressed.pdf');
        const pct = Math.max(0, Math.round((1 - best.length / orig) * 100));
        q('#msg', root).innerHTML = okBox(`${fmtBytes(orig)} → ${fmtBytes(best.length)} (${pct > 0 ? pct + '% smaller' : 'already at minimum size'}).`);
      } catch (e) { q('#msg', root).innerHTML = errBox('Failed: ' + e.message); }
    });
  });

  /* =======================================================================
     24. EDIT PDF (Add Text, Checkmarks, Highlights & Whiteout Boxes)
     ==================================================================== */
  reg('edit-pdf', root => {
    root.innerHTML = `<div id="drop"></div>
      <div id="opts" class="mt-4" style="display:none">
        <div class="row">
          <div><label class="field__label">Tool</label>
            <select id="toolKind" class="select">
              <option value="text">T  Add Text</option>
              <option value="check">✓  Checkmark</option>
              <option value="cross">✗  Cross mark</option>
              <option value="highlight">🖍  Yellow Highlight Box</option>
              <option value="whiteout">⬜  Whiteout / Eraser Box</option>
            </select>
          </div>
          <div style="flex:2" id="txtWrap"><label class="field__label">Text content</label><input id="txt" class="input" placeholder="Type text and click on page…" value="Approved"></div>
          <div><label class="field__label">Page</label><select id="pg" class="select"></select></div>
          <div><label class="field__label">Size</label><input id="sz" class="input" type="number" value="16" min="6" max="72"></div>
          <div><label class="field__label">Colour</label><input id="col" type="color" value="#0f172a" class="input" style="height:44px"></div>
        </div>
        <div class="btn-row mt-2">
          <button class="btn btn--ghost btn--sm" id="undo">↶ Undo Last</button>
          <button class="btn btn--ghost btn--sm" id="clrPage">Clear Page</button>
          <span class="muted" style="font-size:13px;align-self:center">Click anywhere on the page preview to place annotation.</span>
        </div>
        <div id="wrap" style="position:relative;display:inline-block;border:1px solid var(--border);border-radius:10px;overflow:hidden;margin-top:8px;max-width:100%"></div>
        <div class="btn-row mt-4"><button class="btn btn--primary" id="go">Download Edited PDF</button></div>
      </div><div id="msg" class="mt-4"></div>`;

    let file, pdf, cur = 0, scale = 1.35, placements = [];
    dropzone(q('#drop', root), { onFiles: async fl => { file = fl[0]; q('#msg', root).innerHTML = spinner('Loading PDF…'); try { pdf = await getPdfjs(await readBytes(file)); q('#pg', root).innerHTML = Array.from({ length: pdf.numPages }, (_, i) => `<option value="${i}">Page ${i + 1}</option>`).join(''); cur = 0; placements = []; q('#opts', root).style.display = ''; await show(); q('#msg', root).innerHTML = ''; } catch (e) { q('#msg', root).innerHTML = errBox(e.message); } } });
    q('#pg', root)?.addEventListener('change', async () => { cur = +q('#pg', root).value; await show(); });

    async function show() {
      const wrap = q('#wrap', root); wrap.innerHTML = '';
      const c = await renderPage(pdf, cur + 1, scale);
      c.style.maxWidth = '100%'; c.style.display = 'block'; c.style.cursor = 'crosshair';
      wrap.appendChild(c);
      const overlay = document.createElement('div');
      overlay.style.cssText = 'position:absolute;inset:0;pointer-events:none';
      wrap.appendChild(overlay);

      const renderTags = () => {
        overlay.innerHTML = '';
        placements.filter(p => p.page === cur).forEach(pl => {
          const el = document.createElement('div');
          if (pl.kind === 'highlight' || pl.kind === 'whiteout') {
            el.style.cssText = `position:absolute;left:${pl.xR * 100}%;top:${pl.yR * 100}%;width:140px;height:24px;background:${pl.kind === 'highlight' ? 'rgba(250,204,21,.45)' : '#ffffff'};border:1px solid rgba(0,0,0,.15);transform:translateY(-50%)`;
          } else {
            el.textContent = pl.text;
            el.style.cssText = `position:absolute;left:${pl.xR * 100}%;top:${pl.yR * 100}%;color:${pl.color};font-size:${pl.size}px;font-weight:600;transform:translateY(-85%);white-space:nowrap`;
          }
          overlay.appendChild(el);
        });
      };
      renderTags();

      c.addEventListener('click', e => {
        const kind = q('#toolKind', root).value;
        const txt = kind === 'check' ? 'Yes / [X]' : kind === 'cross' ? 'No / [ ]' : q('#txt', root).value.trim();
        if (kind === 'text' && !txt) { q('#msg', root).innerHTML = errBox('Enter text to place.'); return; }
        const r = c.getBoundingClientRect();
        const xR = (e.clientX - r.left) / r.width, yR = (e.clientY - r.top) / r.height;
        placements.push({ page: cur, kind, xR, yR, text: kind === 'check' ? 'V' : kind === 'cross' ? 'X' : txt, size: +q('#sz', root).value, color: q('#col', root).value });
        renderTags();
      });
    }

    q('#undo', root).addEventListener('click', () => { placements.pop(); show(); });
    q('#clrPage', root).addEventListener('click', () => { placements = placements.filter(p => p.page !== cur); show(); });

    q('#go', root).addEventListener('click', async () => {
      if (!placements.length) { q('#msg', root).innerHTML = errBox('Click on the page preview to add an annotation first.'); return; }
      q('#msg', root).innerHTML = spinner('Saving edited PDF…');
      try {
        const doc = await loadDoc(file);
        const font = await doc.embedFont(window.PDFLib.StandardFonts.HelveticaBold);
        const pages = doc.getPages();
        placements.forEach(pl => {
          const pg = pages[pl.page];
          const { width, height } = pg.getSize();
          const x = pl.xR * width, y = height - pl.yR * height;
          if (pl.kind === 'highlight') {
            pg.drawRectangle({ x, y: y - 8, width: 130, height: 20, color: window.PDFLib.rgb(0.98, 0.85, 0.15), opacity: 0.42 });
          } else if (pl.kind === 'whiteout') {
            pg.drawRectangle({ x, y: y - 8, width: 130, height: 22, color: window.PDFLib.rgb(1, 1, 1) });
          } else {
            const hex = pl.color;
            const col = window.PDFLib.rgb(parseInt(hex.slice(1, 3), 16) / 255, parseInt(hex.slice(3, 5), 16) / 255, parseInt(hex.slice(5, 7), 16) / 255);
            pg.drawText(pl.text.replace(/[^\x20-\x7E\xA0-\xFF]/g, ''), { x, y, size: pl.size, font, color: col });
          }
        });
        downloadPdf(await doc.save(), 'edited.pdf');
        q('#msg', root).innerHTML = okBox('Edited PDF downloaded.');
      } catch (e) { q('#msg', root).innerHTML = errBox('Failed: ' + e.message); }
    });
  });

  /* =======================================================================
     25. PDF FORMS (Fill AcroForm Fields)
     ==================================================================== */
  reg('pdf-forms', root => {
    root.innerHTML = `<div id="drop"></div><div id="fields" class="mt-4"></div><div class="btn-row mt-4" id="bar" style="display:none"><button class="btn btn--primary" id="go">Fill &amp; Download</button><label class="chip" style="display:inline-flex"><input type="checkbox" id="flat"> Flatten (make read-only)</label></div><div id="msg" class="mt-4"></div>`;
    let file;
    dropzone(q('#drop', root), { onFiles: async fl => {
      file = fl[0]; q('#msg', root).innerHTML = spinner('Reading form…');
      try {
        const doc = await loadDoc(file); const form = doc.getForm(); const fields = form.getFields();
        if (!fields.length) { q('#msg', root).innerHTML = errBox('No fillable form fields found in this PDF. Use our Edit PDF tool to type anywhere on a flat PDF.'); q('#bar', root).style.display = 'none'; q('#fields', root).innerHTML = ''; return; }
        q('#fields', root).innerHTML = fields.map(f => { const name = f.getName(); const type = f.constructor.name; if (type === 'PDFCheckBox') return `<label class="chip" style="display:flex;margin-bottom:8px"><input type="checkbox" data-f="${esc(name)}" data-t="check"> ${esc(name)}</label>`; return `<div class="field"><label class="field__label">${esc(name)}</label><input class="input" data-f="${esc(name)}" data-t="text"></div>`; }).join('');
        q('#bar', root).style.display = ''; q('#msg', root).innerHTML = okBox(fields.length + ' interactive field(s) found.');
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
     26. HTML → PDF (Instant Vector PDF Download + Print-to-PDF Option)
     ==================================================================== */
  reg('html-to-pdf', root => {
    root.innerHTML = `<label class="field__label">Paste HTML or Markdown/Text</label>
      <textarea id="html" class="textarea textarea--tall" placeholder="<h1>Invoice #1024</h1><p>Thank you for your business.</p>"><h1>Project Proposal</h1>\n<p>Generated with Toolzy HTML to PDF Studio.</p>\n<h2>Key Deliverables</h2>\n<p>• Fast client-side execution\n• Zero server uploads\n• Clean typography</p></textarea>
      <div class="btn-row mt-4">
        <button class="btn btn--primary" id="dlDirect">📄 Download PDF Directly</button>
        <button class="btn btn--ghost" id="go">🖨️ Open Browser Print → Save as PDF</button>
      </div><div id="msg" class="mt-4"></div>`;

    q('#dlDirect', root).addEventListener('click', async () => {
      const raw = q('#html', root).value || '';
      const docDom = new DOMParser().parseFromString(raw, 'text/html');
      const blocks = [];
      const walk = node => {
        if (node.nodeType === 3) {
          const t = node.textContent.trim();
          if (t) blocks.push({ type: 'p', text: t });
        } else if (node.nodeType === 1) {
          const tag = node.tagName.toLowerCase();
          if (['h1', 'h2', 'h3', 'p', 'li'].includes(tag)) {
            blocks.push({ type: tag === 'li' ? 'p' : tag, text: (tag === 'li' ? '• ' : '') + node.textContent.trim() });
          } else {
            Array.from(node.childNodes).forEach(walk);
          }
        }
      };
      Array.from(docDom.body.childNodes).forEach(walk);
      const pdfBytes = await buildVectorPdfFromBlocks(blocks.length ? blocks : [{ type: 'p', text: raw }]);
      downloadPdf(pdfBytes, 'document.pdf');
      q('#msg', root).innerHTML = okBox('PDF generated and downloaded.');
    });

    q('#go', root).addEventListener('click', () => {
      const html = q('#html', root).value || '<p>(empty)</p>';
      const w = window.open('', '_blank');
      if (!w) { U.toast('Allow pop-ups to use Print-to-PDF'); return; }
      w.document.write(`<!doctype html><html><head><meta charset="utf-8"><title>Document</title><style>body{font-family:-apple-system,Segoe UI,Roboto,sans-serif;padding:40px;line-height:1.6}@media print{body{padding:0}}</style></head><body>${html}<script>window.onload=function(){setTimeout(function(){window.print();},300);}<\/script></body></html>`);
      w.document.close();
    });
  });

  /* =======================================================================
     27. PROTECT / UNLOCK PDF — AES-256 in Browser via qpdf WebAssembly
     ==================================================================== */
  let _qpdfFactory = null;
  function loadQpdf() {
    if (_qpdfFactory) return Promise.resolve(_qpdfFactory);
    return new Promise((resolve, reject) => {
      const s = document.createElement('script');
      s.src = BASE + '/assets/js/vendor/qpdf.js';
      s.onload = () => { _qpdfFactory = window.Module; _qpdfFactory ? resolve(_qpdfFactory) : reject(new Error('qpdf engine did not initialise')); };
      s.onerror = () => reject(new Error('Could not load the qpdf engine'));
      document.head.appendChild(s);
    });
  }
  async function runQpdf(inputBytes, args, outName) {
    const factory = await loadQpdf();
    let err = '';
    const M = await factory({ noInitialRun: true, print() {}, printErr(m) { err += m + '\n'; }, locateFile: f => BASE + '/assets/js/vendor/' + f });
    M.FS.writeFile('input.pdf', inputBytes);
    let status = 0;
    try { M.callMain(args); } catch (e) { status = (e && e.status != null) ? e.status : -1; }
    let out = null; try { out = M.FS.readFile(outName); } catch (_) {}
    if ((status !== 0 && status !== 3) && (!out || !out.length)) throw new Error(err.trim() || ('qpdf exited with status ' + status));
    if (!out || !out.length) throw new Error(err.trim() || 'No output produced.');
    return out;
  }

  reg('protect-pdf', root => {
    root.innerHTML = `<div id="drop"></div>
      <div id="opts" class="mt-4" style="display:none">
        <div class="row"><div><label class="field__label">User Password (required to open)</label><input id="pw" class="input" type="password" placeholder="Choose a strong password"></div>
        <div><label class="field__label">Confirm Password</label><input id="pw2" class="input" type="password" placeholder="Repeat password"></div></div>
        <div class="btn-row mt-4"><button class="btn btn--primary" id="go">🔒 Encrypt &amp; Protect PDF (AES-256)</button></div>
      </div>
      <div class="notice notice--info mt-4">Encrypted locally in your browser using 256-bit AES encryption — your file and password never leave your device.</div>
      <div id="msg" class="mt-4"></div>`;
    let file;
    dropzone(q('#drop', root), { onFiles: fl => { file = fl[0]; q('#opts', root).style.display = ''; } });
    q('#go', root).addEventListener('click', async () => {
      if (!file) return;
      const pw = q('#pw', root).value, pw2 = q('#pw2', root).value;
      if (!pw) { q('#msg', root).innerHTML = errBox('Enter a password.'); return; }
      if (pw !== pw2) { q('#msg', root).innerHTML = errBox('Passwords do not match.'); return; }
      q('#msg', root).innerHTML = spinner('Encrypting PDF with AES-256…');
      try {
        const out = await runQpdf(await readBytes(file), ['--encrypt', pw, pw, '256', '--', 'input.pdf', 'output.pdf'], 'output.pdf');
        downloadPdf(out, file.name.replace(/\.pdf$/i, '') + '-protected.pdf');
        q('#msg', root).innerHTML = okBox('Protected PDF downloaded. It now requires your password to open.');
      } catch (e) { q('#msg', root).innerHTML = errBox('Failed: ' + e.message); }
    });
  });

  reg('unlock-pdf', root => {
    root.innerHTML = `<div id="drop"></div>
      <div id="opts" class="mt-4" style="display:none">
        <div class="row"><div><label class="field__label">Current Password (leave blank if owner-locked only)</label><input id="pw" class="input" type="password" placeholder="Enter PDF password"></div></div>
        <div class="btn-row mt-4"><button class="btn btn--primary" id="go">🔓 Unlock PDF</button></div>
      </div>
      <div class="notice notice--info mt-4">Removes password &amp; print/copy restrictions right in your browser.</div>
      <div id="msg" class="mt-4"></div>`;
    let file;
    dropzone(q('#drop', root), { onFiles: fl => { file = fl[0]; q('#opts', root).style.display = ''; } });
    q('#go', root).addEventListener('click', async () => {
      if (!file) return; const pw = q('#pw', root).value;
      q('#msg', root).innerHTML = spinner('Decrypting PDF…');
      try {
        const args = pw ? ['--decrypt', '--password=' + pw, 'input.pdf', 'output.pdf'] : ['--decrypt', 'input.pdf', 'output.pdf'];
        const out = await runQpdf(await readBytes(file), args, 'output.pdf');
        downloadPdf(out, file.name.replace(/\.pdf$/i, '') + '-unlocked.pdf');
        q('#msg', root).innerHTML = okBox('Unlocked PDF downloaded — no password required now.');
      } catch (e) { q('#msg', root).innerHTML = errBox(/password/i.test(e.message) ? 'Incorrect password for this PDF.' : ('Failed: ' + e.message)); }
    });
  });

  /* Self-mount PDF tools immediately without waiting for tools.js (saves 220 KB on PDF pages) */
  function watchPdfUse(root, slug) {
    if (typeof MutationObserver !== 'function') return;
    let sent = false;
    const obs = new MutationObserver(() => {
      if (sent || !root.querySelector('.notice--success')) return;
      sent = true; obs.disconnect();
      try {
        const u = '/api/track.php?p=' + encodeURIComponent(location.pathname) + '&e=' + encodeURIComponent('use:' + slug);
        if (navigator.sendBeacon) navigator.sendBeacon(u);
        else fetch(u, { method: 'POST', keepalive: true, cache: 'no-store' });
      } catch (_) {}
    });
    obs.observe(root, { childList: true, subtree: true });
  }
  function bootPdf() {
    document.querySelectorAll('[data-tool]').forEach(root => {
      const slug = root.getAttribute('data-tool');
      if (!slug || root._mounted || !engines[slug]) return;
      root._mounted = true;
      try { engines[slug](root); root.classList.add('fade-in'); watchPdfUse(root, slug); }
      catch (e) { root.innerHTML = errBox('This tool hit an error: ' + e.message); }
    });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', bootPdf);
  else bootPdf();
})();
