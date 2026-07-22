/* =========================================================================
   OmniTools — Tool Engines
   Each engine mounts an interactive UI into the .tool-workspace element
   identified by [data-tool="<slug>"]. Vanilla JS, no dependencies.
   ========================================================================= */
(function () {
  'use strict';

  const U = window.OmniUtil;
  const Lib = window.OmniLib || {};
  // Shared, global engine registry so other files (e.g. pdf-tools.js) can
  // register engines that this bootstrap will also pick up.
  const engines = (window.OmniEngines = window.OmniEngines || {});
  const reg = (slug, fn) => { engines[slug] = fn; };
  const esc = s => U.escape(s);

  /* -------------------------------------------------------- DOM helpers */
  const q = (sel, root) => root.querySelector(sel);
  const qa = (sel, root) => Array.from(root.querySelectorAll(sel));

  function copyable(text, label) {
    return `<button type="button" class="btn btn--ghost btn--sm" data-copy="${esc(text)}">${label || 'Copy'}</button>`;
  }
  function wireCopy(root) {
    qa('[data-copy]', root).forEach(b => {
      if (b._wired) return; b._wired = true;
      b.addEventListener('click', () => U.copy(b.getAttribute('data-copy')));
    });
  }
  function outputWithCopy(root, id) {
    // Adds a copy button that reads the current text content of #id.
    const btn = q(`[data-copy-el="${id}"]`, root);
    if (btn) btn.addEventListener('click', () => U.copy(q('#' + id, root).textContent || q('#' + id, root).value || ''));
  }

  function makeDropzone(container, opts) {
    const acc = opts.accept || '';
    container.innerHTML = `
      <div class="dropzone" tabindex="0" role="button" aria-label="Upload file">
        ${svgIcon('upload', 'dropzone__icon')}
        <div><strong>Click to upload</strong> or drag &amp; drop</div>
        <div class="dropzone__hint">${opts.hint || 'Your files are processed locally in your browser.'}</div>
        <input type="file" ${opts.multiple ? 'multiple' : ''} accept="${acc}" hidden>
      </div>`;
    const dz = q('.dropzone', container);
    const input = q('input', container);
    dz.addEventListener('click', () => input.click());
    dz.addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); input.click(); } });
    input.addEventListener('change', () => { if (input.files.length) opts.onFiles(input.files); });
    ['dragover', 'dragenter'].forEach(ev => dz.addEventListener(ev, e => { e.preventDefault(); dz.classList.add('is-drag'); }));
    ['dragleave', 'drop'].forEach(ev => dz.addEventListener(ev, e => { e.preventDefault(); dz.classList.remove('is-drag'); }));
    dz.addEventListener('drop', e => { if (e.dataTransfer.files.length) opts.onFiles(e.dataTransfer.files); });
  }

  function svgIcon(name, cls) {
    const p = {
      upload: '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" x2="12" y1="3" y2="15"/>',
      download: '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="15" y2="3"/>'
    }[name] || '';
    return `<svg class="${cls || 'icon'}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">${p}</svg>`;
  }

  /* Image helpers */
  function loadImageFile(file) {
    return new Promise((resolve, reject) => {
      const img = new Image();
      const url = URL.createObjectURL(file);
      img.onload = () => { resolve(img); URL.revokeObjectURL(url); };
      img.onerror = () => reject(new Error('Could not load image'));
      img.src = url;
    });
  }
  function canvasToBlob(canvas, type, quality) {
    return new Promise(res => canvas.toBlob(res, type, quality));
  }
  function fmtBytes(b) {
    if (b < 1024) return b + ' B';
    const u = ['KB', 'MB', 'GB']; let i = -1;
    do { b /= 1024; i++; } while (b >= 1024 && i < u.length - 1);
    return b.toFixed(b < 10 ? 2 : 1) + ' ' + u[i];
  }

  /* WAV encoder from an AudioBuffer */
  function audioBufferToWav(buffer) {
    const numCh = buffer.numberOfChannels, len = buffer.length * numCh * 2 + 44;
    const ab = new ArrayBuffer(len), view = new DataView(ab);
    const chans = []; let offset = 0, pos = 0;
    function setStr(s) { for (let i = 0; i < s.length; i++) view.setUint8(pos++, s.charCodeAt(i)); }
    function set16(d) { view.setUint16(pos, d, true); pos += 2; }
    function set32(d) { view.setUint32(pos, d, true); pos += 4; }
    setStr('RIFF'); set32(len - 8); setStr('WAVE'); setStr('fmt '); set32(16); set16(1); set16(numCh);
    set32(buffer.sampleRate); set32(buffer.sampleRate * numCh * 2); set16(numCh * 2); set16(16);
    setStr('data'); set32(len - pos - 4);
    for (let i = 0; i < numCh; i++) chans.push(buffer.getChannelData(i));
    while (offset < buffer.length) {
      for (let i = 0; i < numCh; i++) {
        let s = Math.max(-1, Math.min(1, chans[i][offset]));
        view.setInt16(pos, s < 0 ? s * 0x8000 : s * 0x7FFF, true); pos += 2;
      }
      offset++;
    }
    return new Blob([ab], { type: 'audio/wav' });
  }

  /* =====================================================================
     TEXT TOOLS
     ================================================================== */
  reg('word-counter', root => {
    root.innerHTML = `
      <label class="field__label" for="wc">Paste or type your text</label>
      <textarea id="wc" class="textarea textarea--tall" placeholder="Start typing…"></textarea>
      <div class="stat-grid mt-4" id="wcStats"></div>`;
    const ta = q('#wc', root), out = q('#wcStats', root);
    const update = () => {
      const t = ta.value;
      const words = (t.trim().match(/\S+/g) || []).length;
      const chars = t.length;
      const noSpace = t.replace(/\s/g, '').length;
      const sentences = (t.match(/[.!?]+(\s|$)/g) || []).length;
      const paras = (t.trim() ? t.trim().split(/\n{2,}|\n/).filter(p => p.trim()).length : 0);
      const readMin = Math.max(1, Math.round(words / 200));
      out.innerHTML = [
        ['Words', words], ['Characters', chars], ['No Spaces', noSpace],
        ['Sentences', sentences], ['Paragraphs', paras], ['Read Time', readMin + ' min']
      ].map(([l, v]) => `<div class="stat-card"><b>${v}</b><span>${l}</span></div>`).join('');
    };
    ta.addEventListener('input', update); update();
  });

  reg('character-counter', root => {
    root.innerHTML = `
      <textarea id="cc" class="textarea textarea--tall" placeholder="Type here…"></textarea>
      <div class="stat-grid mt-4" id="ccStats"></div>`;
    const ta = q('#cc', root), out = q('#ccStats', root);
    const upd = () => {
      const t = ta.value;
      out.innerHTML = [
        ['With Spaces', t.length], ['Without Spaces', t.replace(/\s/g, '').length],
        ['Letters', (t.match(/[a-zA-Z]/g) || []).length], ['Digits', (t.match(/[0-9]/g) || []).length],
        ['Lines', t ? t.split('\n').length : 0]
      ].map(([l, v]) => `<div class="stat-card"><b>${v}</b><span>${l}</span></div>`).join('');
    };
    ta.addEventListener('input', upd); upd();
  });

  reg('case-converter', root => {
    root.innerHTML = `
      <textarea id="cv" class="textarea textarea--tall" placeholder="Type or paste text…"></textarea>
      <div class="btn-row mt-4" id="cvBtns"></div>`;
    const ta = q('#cv', root);
    const ops = {
      'UPPERCASE': s => s.toUpperCase(),
      'lowercase': s => s.toLowerCase(),
      'Title Case': s => s.replace(/\w\S*/g, w => w[0].toUpperCase() + w.slice(1).toLowerCase()),
      'Sentence case': s => s.toLowerCase().replace(/(^\s*\w|[.!?]\s*\w)/g, c => c.toUpperCase()),
      'camelCase': s => s.toLowerCase().replace(/[^a-z0-9]+(.)/g, (_, c) => c.toUpperCase()),
      'snake_case': s => s.trim().toLowerCase().replace(/\s+/g, '_').replace(/[^\w]/g, ''),
      'kebab-case': s => s.trim().toLowerCase().replace(/\s+/g, '-').replace(/[^\w-]/g, ''),
      'aLtErNaTiNg': s => s.split('').map((c, i) => i % 2 ? c.toUpperCase() : c.toLowerCase()).join('')
    };
    q('#cvBtns', root).innerHTML = Object.keys(ops).map(k => `<button class="btn btn--ghost btn--sm" data-op="${k}">${k}</button>`).join('') +
      `<button class="btn btn--primary btn--sm" id="cvCopy">Copy</button>`;
    qa('[data-op]', root).forEach(b => b.addEventListener('click', () => { ta.value = ops[b.dataset.op](ta.value); }));
    q('#cvCopy', root).addEventListener('click', () => U.copy(ta.value));
  });

  reg('remove-duplicate-lines', root => {
    root.innerHTML = `
      <div class="grid-2">
        <div><label class="field__label">Input</label><textarea id="in" class="textarea textarea--tall"></textarea></div>
        <div><label class="field__label">Result</label><textarea id="out" class="textarea textarea--tall" readonly></textarea></div>
      </div>
      <div class="chips mt-4">
        <label class="chip"><input type="checkbox" id="ci"> Case-insensitive</label>
        <label class="chip"><input type="checkbox" id="tr" checked> Trim lines</label>
        <label class="chip"><input type="checkbox" id="so"> Sort result</label>
      </div>
      <div class="btn-row mt-4"><button class="btn btn--primary" id="go">Remove Duplicates</button><button class="btn btn--ghost" id="cp">Copy</button></div>`;
    q('#go', root).addEventListener('click', () => {
      let lines = q('#in', root).value.split('\n');
      const tr = q('#tr', root).checked, ci = q('#ci', root).checked, so = q('#so', root).checked;
      if (tr) lines = lines.map(l => l.trim());
      const seen = new Set(), out = [];
      lines.forEach(l => { const k = ci ? l.toLowerCase() : l; if (!seen.has(k)) { seen.add(k); out.push(l); } });
      if (so) out.sort((a, b) => a.localeCompare(b));
      q('#out', root).value = out.join('\n');
    });
    q('#cp', root).addEventListener('click', () => U.copy(q('#out', root).value));
  });

  reg('text-sorter', root => {
    root.innerHTML = `
      <div class="grid-2">
        <div><label class="field__label">Input</label><textarea id="in" class="textarea textarea--tall"></textarea></div>
        <div><label class="field__label">Sorted</label><textarea id="out" class="textarea textarea--tall" readonly></textarea></div>
      </div>
      <div class="btn-row mt-4">
        <button class="btn btn--ghost" data-s="az">A → Z</button>
        <button class="btn btn--ghost" data-s="za">Z → A</button>
        <button class="btn btn--ghost" data-s="num">Numeric</button>
        <button class="btn btn--ghost" data-s="len">By length</button>
        <button class="btn btn--ghost" data-s="rev">Reverse</button>
        <button class="btn btn--ghost" data-s="shuf">Shuffle</button>
        <button class="btn btn--primary" id="cp">Copy</button>
      </div>`;
    qa('[data-s]', root).forEach(b => b.addEventListener('click', () => {
      let l = q('#in', root).value.split('\n').filter(x => x !== '');
      const s = b.dataset.s;
      if (s === 'az') l.sort((a, b) => a.localeCompare(b));
      else if (s === 'za') l.sort((a, b) => b.localeCompare(a));
      else if (s === 'num') l.sort((a, b) => parseFloat(a) - parseFloat(b));
      else if (s === 'len') l.sort((a, b) => a.length - b.length);
      else if (s === 'rev') l.reverse();
      else if (s === 'shuf') for (let i = l.length - 1; i > 0; i--) { const j = Math.floor(Math.random() * (i + 1));[l[i], l[j]] = [l[j], l[i]]; }
      q('#out', root).value = l.join('\n');
    }));
    q('#cp', root).addEventListener('click', () => U.copy(q('#out', root).value));
  });

  reg('reverse-text', root => {
    root.innerHTML = `
      <textarea id="in" class="textarea textarea--tall" placeholder="Enter text…"></textarea>
      <div class="btn-row mt-4">
        <button class="btn btn--ghost" data-m="chars">Reverse Characters</button>
        <button class="btn btn--ghost" data-m="words">Reverse Words</button>
        <button class="btn btn--ghost" data-m="lines">Reverse Lines</button>
      </div>
      <label class="field__label mt-4">Result</label>
      <div class="output-box" id="out"></div>`;
    qa('[data-m]', root).forEach(b => b.addEventListener('click', () => {
      const t = q('#in', root).value, m = b.dataset.m;
      let r = m === 'chars' ? t.split('').reverse().join('') :
        m === 'words' ? t.split(/\s+/).reverse().join(' ') :
          t.split('\n').reverse().join('\n');
      q('#out', root).textContent = r;
    }));
  });

  reg('whitespace-remover', root => {
    root.innerHTML = `
      <textarea id="in" class="textarea textarea--tall" placeholder="Paste messy text…"></textarea>
      <div class="chips mt-4">
        <label class="chip"><input type="checkbox" id="trim" checked> Trim lines</label>
        <label class="chip"><input type="checkbox" id="multi" checked> Collapse spaces</label>
        <label class="chip"><input type="checkbox" id="blank" checked> Remove blank lines</label>
        <label class="chip"><input type="checkbox" id="tabs"> Tabs → space</label>
      </div>
      <div class="btn-row mt-4"><button class="btn btn--primary" id="go">Clean</button><button class="btn btn--ghost" id="cp">Copy</button></div>
      <div class="output-box mt-4" id="out"></div>`;
    q('#go', root).addEventListener('click', () => {
      let t = q('#in', root).value;
      if (q('#tabs', root).checked) t = t.replace(/\t/g, ' ');
      if (q('#multi', root).checked) t = t.replace(/[ ]{2,}/g, ' ');
      if (q('#trim', root).checked) t = t.split('\n').map(l => l.trim()).join('\n');
      if (q('#blank', root).checked) t = t.split('\n').filter(l => l.trim()).join('\n');
      q('#out', root).textContent = t;
    });
    q('#cp', root).addEventListener('click', () => U.copy(q('#out', root).textContent));
  });

  reg('find-replace', root => {
    root.innerHTML = `
      <textarea id="in" class="textarea textarea--tall" placeholder="Your text…"></textarea>
      <div class="row mt-4">
        <div><label class="field__label">Find</label><input id="find" class="input"></div>
        <div><label class="field__label">Replace with</label><input id="rep" class="input"></div>
      </div>
      <div class="chips mt-4">
        <label class="chip"><input type="checkbox" id="ci"> Case-insensitive</label>
        <label class="chip"><input type="checkbox" id="rx"> Regex</label>
      </div>
      <div class="btn-row mt-4"><button class="btn btn--primary" id="go">Replace All</button><button class="btn btn--ghost" id="cp">Copy</button></div>
      <div class="output-box mt-4" id="out"></div><div id="msg"></div>`;
    q('#go', root).addEventListener('click', () => {
      const find = q('#find', root).value, rep = q('#rep', root).value;
      let flags = 'g' + (q('#ci', root).checked ? 'i' : '');
      try {
        const pat = q('#rx', root).checked ? new RegExp(find, flags) : new RegExp(find.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), flags);
        const t = q('#in', root).value;
        const count = (t.match(pat) || []).length;
        q('#out', root).textContent = t.replace(pat, rep);
        q('#msg', root).innerHTML = `<div class="notice notice--success">${count} replacement(s) made.</div>`;
      } catch (e) { q('#msg', root).innerHTML = `<div class="notice notice--error">Invalid pattern: ${esc(e.message)}</div>`; }
    });
    q('#cp', root).addEventListener('click', () => U.copy(q('#out', root).textContent));
  });

  reg('text-diff', root => {
    root.innerHTML = `
      <div class="grid-2">
        <div><label class="field__label">Original</label><textarea id="a" class="textarea"></textarea></div>
        <div><label class="field__label">Changed</label><textarea id="b" class="textarea"></textarea></div>
      </div>
      <div class="btn-row mt-4"><button class="btn btn--primary" id="go">Compare</button></div>
      <div class="output-box mt-4" id="out" style="font-family:var(--font-mono)"></div>`;
    q('#go', root).addEventListener('click', () => {
      const a = q('#a', root).value.split('\n'), b = q('#b', root).value.split('\n');
      const max = Math.max(a.length, b.length); let html = '';
      for (let i = 0; i < max; i++) {
        const l1 = a[i] ?? '', l2 = b[i] ?? '';
        if (l1 === l2) html += `<div style="color:var(--text-3)">  ${esc(l1)}</div>`;
        else {
          if (l1 !== '' || i < a.length) html += `<div style="background:color-mix(in srgb,var(--danger) 14%,transparent)">- ${esc(l1)}</div>`;
          if (l2 !== '' || i < b.length) html += `<div style="background:color-mix(in srgb,var(--success) 14%,transparent)">+ ${esc(l2)}</div>`;
        }
      }
      q('#out', root).innerHTML = html || '<span class="muted">Identical.</span>';
    });
  });

  reg('lorem-ipsum', root => {
    const W = 'lorem ipsum dolor sit amet consectetur adipiscing elit sed do eiusmod tempor incididunt ut labore et dolore magna aliqua enim ad minim veniam quis nostrud exercitation ullamco laboris nisi aliquip ex ea commodo consequat duis aute irure in reprehenderit voluptate velit esse cillum eu fugiat nulla pariatur excepteur sint occaecat cupidatat non proident sunt culpa qui officia deserunt mollit anim id est laborum'.split(' ');
    root.innerHTML = `
      <div class="row">
        <div><label class="field__label">Count</label><input id="n" class="input" type="number" value="3" min="1" max="100"></div>
        <div><label class="field__label">Type</label><select id="type" class="select"><option value="p">Paragraphs</option><option value="s">Sentences</option><option value="w">Words</option></select></div>
      </div>
      <label class="chip mt-4" style="display:inline-flex"><input type="checkbox" id="start" checked> Start with “Lorem ipsum…”</label>
      <div class="btn-row mt-4"><button class="btn btn--primary" id="go">Generate</button><button class="btn btn--ghost" id="cp">Copy</button></div>
      <div class="output-box mt-4" id="out"></div>`;
    const rand = n => W[Math.floor(Math.random() * n) % W.length];
    const sentence = () => { const len = 8 + Math.floor(Math.random() * 10); let s = []; for (let i = 0; i < len; i++) s.push(rand(W.length)); s[0] = s[0][0].toUpperCase() + s[0].slice(1); return s.join(' ') + '.'; };
    q('#go', root).addEventListener('click', () => {
      const n = Math.min(100, +q('#n', root).value || 1), type = q('#type', root).value; let out = '';
      if (type === 'w') out = Array.from({ length: n }, () => rand(W.length)).join(' ') + '.';
      else if (type === 's') out = Array.from({ length: n }, sentence).join(' ');
      else out = Array.from({ length: n }, () => { let p = []; for (let i = 0; i < 4 + Math.floor(Math.random() * 3); i++) p.push(sentence()); return p.join(' '); }).join('\n\n');
      if (q('#start', root).checked) out = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. ' + out;
      q('#out', root).textContent = out;
    });
    q('#cp', root).addEventListener('click', () => U.copy(q('#out', root).textContent));
    q('#go', root).click();
  });

  reg('random-text-generator', root => {
    root.innerHTML = `
      <div class="row">
        <div><label class="field__label">Type</label><select id="t" class="select">
          <option value="string">Random String</option><option value="alpha">Letters only</option>
          <option value="num">Numbers only</option><option value="hex">Hex</option></select></div>
        <div><label class="field__label">Length</label><input id="len" class="input" type="number" value="16" min="1" max="1024"></div>
        <div><label class="field__label">How many</label><input id="cnt" class="input" type="number" value="5" min="1" max="200"></div>
      </div>
      <div class="btn-row mt-4"><button class="btn btn--primary" id="go">Generate</button><button class="btn btn--ghost" id="cp">Copy</button></div>
      <div class="output-box mt-4" id="out"></div>`;
    const sets = { string: 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789', alpha: 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz', num: '0123456789', hex: '0123456789abcdef' };
    q('#go', root).addEventListener('click', () => {
      const set = sets[q('#t', root).value], len = +q('#len', root).value, cnt = +q('#cnt', root).value, lines = [];
      for (let c = 0; c < cnt; c++) { let s = ''; const rnd = crypto.getRandomValues(new Uint32Array(len)); for (let i = 0; i < len; i++) s += set[rnd[i] % set.length]; lines.push(s); }
      q('#out', root).textContent = lines.join('\n');
    });
    q('#cp', root).addEventListener('click', () => U.copy(q('#out', root).textContent));
    q('#go', root).click();
  });

  function markdownEngine(root, download) {
    root.innerHTML = `
      <div class="grid-2">
        <div><label class="field__label">Markdown</label><textarea id="md" class="textarea textarea--tall" placeholder="# Hello\n\nType **markdown** here."></textarea></div>
        <div><label class="field__label">Preview</label><div class="output-box prose" id="prev" style="min-height:240px"></div></div>
      </div>
      <div class="btn-row mt-4"><button class="btn btn--ghost" id="cp">Copy HTML</button>${download ? '<button class="btn btn--primary" id="dl">Download HTML</button>' : ''}</div>`;
    const md = q('#md', root), prev = q('#prev', root);
    const upd = () => { prev.innerHTML = Lib.markdown(md.value); };
    md.addEventListener('input', upd);
    q('#cp', root).addEventListener('click', () => U.copy(Lib.markdown(md.value)));
    if (download) q('#dl', root).addEventListener('click', () => U.download('document.html', '<!doctype html><meta charset="utf-8">\n' + Lib.markdown(md.value), 'text/html'));
    md.value = '# Welcome to OmniTools\n\nType **Markdown** on the left and see the *preview* update live.\n\n- Fast\n- Private\n- Free\n\n> Everything you need. One platform.'; upd();
  }
  reg('markdown-preview', root => markdownEngine(root, false));
  reg('markdown-to-html', root => markdownEngine(root, true));

  /* =====================================================================
     DEVELOPER TOOLS
     ================================================================== */
  reg('json-formatter', root => {
    root.innerHTML = `
      <textarea id="in" class="textarea textarea--tall" placeholder='{"hello":"world"}'></textarea>
      <div class="row mt-4">
        <div><label class="field__label">Indent</label><select id="ind" class="select"><option>2</option><option>4</option><option value="\t">Tab</option></select></div>
      </div>
      <div class="btn-row mt-4">
        <button class="btn btn--primary" id="fmt">Beautify</button>
        <button class="btn btn--ghost" id="min">Minify</button>
        <button class="btn btn--ghost" id="cp">Copy</button>
      </div>
      <div id="msg"></div>
      <label class="field__label mt-4">Output</label><div class="output-box" id="out"></div>`;
    const parse = () => { try { return JSON.parse(q('#in', root).value); } catch (e) { q('#msg', root).innerHTML = `<div class="notice notice--error">Invalid JSON: ${esc(e.message)}</div>`; return undefined; } };
    q('#fmt', root).addEventListener('click', () => { const d = parse(); if (d === undefined) return; q('#msg', root).innerHTML = '<div class="notice notice--success">Valid JSON ✓</div>'; const ind = q('#ind', root).value; q('#out', root).textContent = JSON.stringify(d, null, ind === '\t' ? '\t' : +ind); });
    q('#min', root).addEventListener('click', () => { const d = parse(); if (d === undefined) return; q('#out', root).textContent = JSON.stringify(d); });
    q('#cp', root).addEventListener('click', () => U.copy(q('#out', root).textContent));
  });

  reg('json-validator', root => {
    root.innerHTML = `<textarea id="in" class="textarea textarea--tall" placeholder="Paste JSON to validate…"></textarea>
      <div class="btn-row mt-4"><button class="btn btn--primary" id="go">Validate</button></div><div id="msg" class="mt-4"></div>`;
    const check = () => { const v = q('#in', root).value.trim(); if (!v) { q('#msg', root).innerHTML = ''; return; } try { const d = JSON.parse(v); const keys = typeof d === 'object' && d ? Object.keys(d).length : 0; q('#msg', root).innerHTML = `<div class="notice notice--success">✓ Valid JSON — ${Array.isArray(d) ? d.length + ' items' : keys + ' keys'}.</div>`; } catch (e) { q('#msg', root).innerHTML = `<div class="notice notice--error">✗ ${esc(e.message)}</div>`; } };
    q('#go', root).addEventListener('click', check); q('#in', root).addEventListener('input', check);
  });

  function base64Engine(root, mode) {
    root.innerHTML = `<textarea id="in" class="textarea textarea--tall" placeholder="${mode === 'enc' ? 'Text to encode…' : 'Base64 to decode…'}"></textarea>
      <div class="btn-row mt-4"><button class="btn btn--primary" id="go">${mode === 'enc' ? 'Encode' : 'Decode'}</button><button class="btn btn--ghost" id="cp">Copy</button></div>
      <div class="output-box mt-4" id="out"></div><div id="msg"></div>`;
    q('#go', root).addEventListener('click', () => {
      try {
        const t = q('#in', root).value;
        q('#out', root).textContent = mode === 'enc'
          ? btoa(unescape(encodeURIComponent(t)))
          : decodeURIComponent(escape(atob(t.trim())));
        q('#msg', root).innerHTML = '';
      } catch (e) { q('#msg', root).innerHTML = `<div class="notice notice--error">Invalid input for ${mode === 'enc' ? 'encoding' : 'decoding'}.</div>`; }
    });
    q('#cp', root).addEventListener('click', () => U.copy(q('#out', root).textContent));
  }
  reg('base64-encode', r => base64Engine(r, 'enc'));
  reg('base64-decode', r => base64Engine(r, 'dec'));

  reg('jwt-decoder', root => {
    root.innerHTML = `<label class="field__label">Paste a JWT</label>
      <textarea id="in" class="textarea" placeholder="eyJhbGciOi…"></textarea>
      <div class="btn-row mt-4"><button class="btn btn--primary" id="go">Decode</button></div>
      <div class="grid-2 mt-4">
        <div><label class="field__label">Header</label><div class="output-box" id="head"></div></div>
        <div><label class="field__label">Payload</label><div class="output-box" id="pay"></div></div>
      </div><div id="msg"></div>`;
    const b64 = s => JSON.stringify(JSON.parse(decodeURIComponent(escape(atob(s.replace(/-/g, '+').replace(/_/g, '/'))))), null, 2);
    q('#go', root).addEventListener('click', () => {
      const parts = q('#in', root).value.trim().split('.');
      if (parts.length < 2) { q('#msg', root).innerHTML = '<div class="notice notice--error">Not a valid JWT.</div>'; return; }
      try {
        q('#head', root).textContent = b64(parts[0]);
        const p = JSON.parse(decodeURIComponent(escape(atob(parts[1].replace(/-/g, '+').replace(/_/g, '/')))));
        q('#pay', root).textContent = JSON.stringify(p, null, 2);
        let m = '';
        if (p.exp) { const d = new Date(p.exp * 1000); m += `<div class="notice notice--${d < new Date() ? 'error' : 'info'}">Expires: ${d.toLocaleString()} ${d < new Date() ? '(expired)' : ''}</div>`; }
        q('#msg', root).innerHTML = m;
      } catch (e) { q('#msg', root).innerHTML = `<div class="notice notice--error">Decode failed: ${esc(e.message)}</div>`; }
    });
  });

  reg('uuid-generator', root => {
    root.innerHTML = `<div class="row"><div><label class="field__label">How many</label><input id="n" class="input" type="number" value="5" min="1" max="500"></div>
      <label class="chip" style="display:inline-flex;align-self:end"><input type="checkbox" id="up"> Uppercase</label></div>
      <div class="btn-row mt-4"><button class="btn btn--primary" id="go">Generate</button><button class="btn btn--ghost" id="cp">Copy</button></div>
      <div class="output-box mt-4" id="out"></div>`;
    const uuid = () => (crypto.randomUUID ? crypto.randomUUID() : 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, c => { const r = crypto.getRandomValues(new Uint8Array(1))[0] % 16; const v = c === 'x' ? r : (r & 0x3 | 0x8); return v.toString(16); }));
    q('#go', root).addEventListener('click', () => { const n = Math.min(500, +q('#n', root).value || 1); let out = Array.from({ length: n }, uuid); if (q('#up', root).checked) out = out.map(u => u.toUpperCase()); q('#out', root).textContent = out.join('\n'); });
    q('#cp', root).addEventListener('click', () => U.copy(q('#out', root).textContent));
    q('#go', root).click();
  });

  reg('hash-generator', root => {
    root.innerHTML = `<textarea id="in" class="textarea" placeholder="Text to hash…"></textarea>
      <div class="btn-row mt-4"><button class="btn btn--primary" id="go">Generate Hashes</button></div>
      <div id="out" class="mt-4"></div>`;
    const row = (name, val) => `<div class="field"><label class="field__label">${name}</label><div class="row"><input class="input" value="${esc(val)}" readonly style="font-family:var(--font-mono);font-size:13px">${copyable(val, 'Copy')}</div></div>`;
    q('#go', root).addEventListener('click', async () => {
      const t = q('#in', root).value; const enc = new TextEncoder().encode(t);
      const algos = ['SHA-1', 'SHA-256', 'SHA-384', 'SHA-512'];
      let html = row('MD5', Lib.md5(t));
      for (const a of algos) { const buf = await crypto.subtle.digest(a, enc); const hex = Array.from(new Uint8Array(buf)).map(b => b.toString(16).padStart(2, '0')).join(''); html += row(a, hex); }
      q('#out', root).innerHTML = html; wireCopy(root);
    });
  });

  reg('regex-tester', root => {
    root.innerHTML = `
      <div class="row"><div style="flex:3"><label class="field__label">Pattern</label><input id="pat" class="input" placeholder="\\b\\w+@\\w+\\.\\w+\\b" style="font-family:var(--font-mono)"></div>
      <div><label class="field__label">Flags</label><input id="flags" class="input" value="g" style="font-family:var(--font-mono)"></div></div>
      <label class="field__label mt-4">Test string</label><textarea id="test" class="textarea"></textarea>
      <div id="msg" class="mt-4"></div>
      <label class="field__label mt-4">Matches</label><div class="output-box" id="out"></div>`;
    const run = () => {
      try {
        const re = new RegExp(q('#pat', root).value, q('#flags', root).value);
        const text = q('#test', root).value;
        const matches = []; let m;
        if (re.global) { while ((m = re.exec(text)) !== null) { matches.push(m); if (m.index === re.lastIndex) re.lastIndex++; } }
        else { m = re.exec(text); if (m) matches.push(m); }
        q('#msg', root).innerHTML = `<div class="notice notice--success">${matches.length} match(es)</div>`;
        let html = text.replace(new RegExp(q('#pat', root).value, q('#flags', root).value.replace('g', '') + 'g'), s => `<mark>${esc(s)}</mark>`);
        q('#out', root).innerHTML = matches.length ? html : '<span class="muted">No matches.</span>';
      } catch (e) { q('#msg', root).innerHTML = `<div class="notice notice--error">${esc(e.message)}</div>`; }
    };
    ['pat', 'flags', 'test'].forEach(id => q('#' + id, root).addEventListener('input', run));
  });

  function beautifyEngine(root, kind) {
    root.innerHTML = `<textarea id="in" class="textarea textarea--tall" placeholder="Paste ${kind.toUpperCase()}…"></textarea>
      <div class="btn-row mt-4"><button class="btn btn--primary" id="fmt">Beautify</button>${kind !== 'sql' ? '<button class="btn btn--ghost" id="min">Minify</button>' : ''}<button class="btn btn--ghost" id="cp">Copy</button></div>
      <label class="field__label mt-4">Output</label><div class="output-box" id="out"></div>`;
    function beautifyHTML(s) {
      let formatted = '', indent = 0;
      s = s.replace(/>\s*</g, '>\n<').split('\n');
      s.forEach(line => {
        line = line.trim(); if (!line) return;
        if (/^<\/\w/.test(line)) indent = Math.max(0, indent - 1);
        formatted += '  '.repeat(indent) + line + '\n';
        if (/^<\w[^>]*[^\/]>$/.test(line) && !/^<(area|base|br|col|hr|img|input|link|meta)/i.test(line)) indent++;
      });
      return formatted.trim();
    }
    function beautifyCSS(s) { return s.replace(/\s*{\s*/g, ' {\n  ').replace(/;\s*/g, ';\n  ').replace(/\s*}\s*/g, '\n}\n\n').replace(/\n  }/g, '\n}').replace(/,\s*/g, ',\n').trim(); }
    function beautifySQL(s) { const kw = ['SELECT', 'FROM', 'WHERE', 'AND', 'OR', 'JOIN', 'LEFT JOIN', 'RIGHT JOIN', 'INNER JOIN', 'GROUP BY', 'ORDER BY', 'HAVING', 'LIMIT', 'INSERT INTO', 'VALUES', 'UPDATE', 'SET', 'DELETE', 'ON']; let r = s.replace(/\s+/g, ' ').trim(); kw.forEach(k => { r = r.replace(new RegExp('\\b' + k.replace(' ', '\\s+') + '\\b', 'gi'), '\n' + k); }); return r.replace(/,/g, ',\n  ').trim(); }
    const fns = { html: beautifyHTML, css: beautifyCSS, sql: beautifySQL };
    q('#fmt', root).addEventListener('click', () => { q('#out', root).textContent = fns[kind](q('#in', root).value); });
    if (q('#min', root)) q('#min', root).addEventListener('click', () => { let v = q('#in', root).value; q('#out', root).textContent = kind === 'css' ? v.replace(/\s*([{}:;,])\s*/g, '$1').replace(/;}/g, '}').replace(/\/\*[\s\S]*?\*\//g, '').trim() : v.replace(/>\s+</g, '><').replace(/\s{2,}/g, ' ').trim(); });
    q('#cp', root).addEventListener('click', () => U.copy(q('#out', root).textContent));
  }
  reg('html-formatter', r => beautifyEngine(r, 'html'));
  reg('sql-formatter', r => beautifyEngine(r, 'sql'));
  reg('html-beautifier', r => beautifyEngine(r, 'html'));

  reg('css-minifier', root => {
    root.innerHTML = `<textarea id="in" class="textarea textarea--tall" placeholder="Paste CSS…"></textarea>
      <div class="btn-row mt-4"><button class="btn btn--primary" id="go">Minify</button><button class="btn btn--ghost" id="cp">Copy</button></div>
      <div id="stat" class="mt-4"></div><div class="output-box mt-4" id="out"></div>`;
    q('#go', root).addEventListener('click', () => {
      const src = q('#in', root).value;
      const min = src.replace(/\/\*[\s\S]*?\*\//g, '').replace(/\s*([{}:;,>~+])\s*/g, '$1').replace(/;}/g, '}').replace(/\s+/g, ' ').trim();
      q('#out', root).textContent = min;
      q('#stat', root).innerHTML = `<div class="notice notice--success">${src.length} → ${min.length} bytes (${Math.round((1 - min.length / (src.length || 1)) * 100)}% smaller)</div>`;
    });
    q('#cp', root).addEventListener('click', () => U.copy(q('#out', root).textContent));
  });

  reg('js-minifier', root => {
    root.innerHTML = `<textarea id="in" class="textarea textarea--tall" placeholder="Paste JavaScript…"></textarea>
      <div class="btn-row mt-4"><button class="btn btn--primary" id="go">Minify</button><button class="btn btn--ghost" id="cp">Copy</button></div>
      <div id="stat" class="mt-4"></div><div class="output-box mt-4" id="out"></div>
      <div class="notice notice--info mt-4">A safe minifier: removes comments and collapses whitespace without renaming identifiers.</div>`;
    q('#go', root).addEventListener('click', () => {
      let s = q('#in', root).value;
      // Remove block & line comments carefully (not inside strings — simple heuristic).
      s = s.replace(/\/\*[\s\S]*?\*\//g, '').replace(/([^:])\/\/[^\n\r]*/g, '$1');
      const min = s.replace(/\s*([=+\-*/%<>!&|,;:?{}()\[\]])\s*/g, '$1').replace(/\s+/g, ' ').trim();
      q('#out', root).textContent = min;
      q('#stat', root).innerHTML = `<div class="notice notice--success">${q('#in', root).value.length} → ${min.length} bytes</div>`;
    });
    q('#cp', root).addEventListener('click', () => U.copy(q('#out', root).textContent));
  });

  function urlEngine(root, mode) {
    root.innerHTML = `<textarea id="in" class="textarea" placeholder="${mode === 'enc' ? 'Text to encode…' : 'URL-encoded string…'}"></textarea>
      <div class="chips mt-4"><label class="chip"><input type="checkbox" id="comp" ${mode === 'enc' ? 'checked' : ''}> Component encoding</label></div>
      <div class="btn-row mt-4"><button class="btn btn--primary" id="go">${mode === 'enc' ? 'Encode' : 'Decode'}</button><button class="btn btn--ghost" id="cp">Copy</button></div>
      <div class="output-box mt-4" id="out"></div>`;
    q('#go', root).addEventListener('click', () => {
      const t = q('#in', root).value, c = q('#comp', root).checked;
      try { q('#out', root).textContent = mode === 'enc' ? (c ? encodeURIComponent(t) : encodeURI(t)) : (c ? decodeURIComponent(t) : decodeURI(t)); }
      catch (e) { q('#out', root).textContent = 'Error: ' + e.message; }
    });
    q('#cp', root).addEventListener('click', () => U.copy(q('#out', root).textContent));
  }
  reg('url-encode', r => urlEngine(r, 'enc'));
  reg('url-decode', r => urlEngine(r, 'dec'));

  reg('html-entities', root => {
    root.innerHTML = `<textarea id="in" class="textarea"></textarea>
      <div class="btn-row mt-4"><button class="btn btn--primary" id="enc">Encode</button><button class="btn btn--ghost" id="dec">Decode</button><button class="btn btn--ghost" id="cp">Copy</button></div>
      <div class="output-box mt-4" id="out"></div>`;
    q('#enc', root).addEventListener('click', () => { q('#out', root).textContent = q('#in', root).value.replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c])); });
    q('#dec', root).addEventListener('click', () => { const d = document.createElement('textarea'); d.innerHTML = q('#in', root).value; q('#out', root).textContent = d.value; });
    q('#cp', root).addEventListener('click', () => U.copy(q('#out', root).textContent));
  });

  reg('color-contrast', root => {
    root.innerHTML = `<div class="row">
      <div><label class="field__label">Foreground</label><input id="fg" type="color" class="input" value="#1d1d1f" style="height:48px"></div>
      <div><label class="field__label">Background</label><input id="bg" type="color" class="input" value="#ffffff" style="height:48px"></div></div>
      <div id="out" class="mt-4"></div>`;
    const lum = hex => { const c = hex.replace('#', '').match(/.{2}/g).map(x => { let v = parseInt(x, 16) / 255; return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4); }); return 0.2126 * c[0] + 0.7152 * c[1] + 0.0722 * c[2]; };
    const upd = () => {
      const fg = q('#fg', root).value, bg = q('#bg', root).value;
      const l1 = lum(fg), l2 = lum(bg); const ratio = (Math.max(l1, l2) + 0.05) / (Math.min(l1, l2) + 0.05);
      const pass = (r, t) => r >= t ? '<span style="color:var(--success)">Pass ✓</span>' : '<span style="color:var(--danger)">Fail ✗</span>';
      q('#out', root).innerHTML = `<div style="background:${bg};color:${fg};padding:24px;border-radius:12px;text-align:center;font-size:20px;font-weight:600;border:1px solid var(--border)">Sample text preview</div>
        <div class="stat-grid mt-4"><div class="stat-card"><b>${ratio.toFixed(2)}:1</b><span>Contrast ratio</span></div>
        <div class="stat-card"><b>${pass(ratio, 4.5)}</b><span>AA normal</span></div>
        <div class="stat-card"><b>${pass(ratio, 3)}</b><span>AA large</span></div>
        <div class="stat-card"><b>${pass(ratio, 7)}</b><span>AAA normal</span></div></div>`;
    };
    q('#fg', root).addEventListener('input', upd); q('#bg', root).addEventListener('input', upd); upd();
  });

  reg('cron-parser', root => {
    root.innerHTML = `<label class="field__label">Cron expression</label><input id="in" class="input" value="*/5 * * * *" style="font-family:var(--font-mono)">
      <div class="btn-row mt-4"><button class="btn btn--primary" id="go">Explain</button></div><div id="out" class="mt-4"></div>`;
    const names = ['minute', 'hour', 'day of month', 'month', 'day of week'];
    const explainField = (v, i) => {
      if (v === '*') return `every ${names[i]}`;
      if (v.startsWith('*/')) return `every ${v.slice(2)} ${names[i]}s`;
      if (v.includes(',')) return `at ${names[i]} ${v}`;
      if (v.includes('-')) return `${names[i]} from ${v}`;
      return `at ${names[i]} ${v}`;
    };
    q('#go', root).addEventListener('click', () => {
      const parts = q('#in', root).value.trim().split(/\s+/);
      if (parts.length !== 5) { q('#out', root).innerHTML = '<div class="notice notice--error">A cron expression must have exactly 5 fields.</div>'; return; }
      q('#out', root).innerHTML = `<div class="notice notice--success">Runs: ${parts.map(explainField).join(', ')}.</div>
        <table class="table mt-4"><tr><th>Field</th><th>Value</th><th>Meaning</th></tr>${parts.map((p, i) => `<tr><td>${names[i]}</td><td style="font-family:var(--font-mono)">${esc(p)}</td><td>${explainField(p, i)}</td></tr>`).join('')}</table>`;
    });
    q('#go', root).click();
  });

  /* =====================================================================
     SEO TOOLS
     ================================================================== */
  reg('meta-generator', root => {
    root.innerHTML = `
      <div class="field"><label class="field__label">Page title</label><input id="title" class="input" placeholder="Best PDF Tools Online"></div>
      <div class="field"><label class="field__label">Meta description</label><textarea id="desc" class="textarea" style="min-height:80px" placeholder="Short compelling description…"></textarea></div>
      <div class="row"><div><label class="field__label">Keywords</label><input id="kw" class="input" placeholder="pdf, tools, free"></div>
      <div><label class="field__label">Author</label><input id="auth" class="input"></div></div>
      <div class="field mt-4"><label class="field__label">Canonical URL</label><input id="url" class="input" placeholder="https://example.com/page"></div>
      <div class="btn-row mt-4"><button class="btn btn--primary" id="go">Generate</button><button class="btn btn--ghost" id="cp">Copy</button></div>
      <label class="field__label mt-4">Generated tags</label><div class="output-box" id="out"></div>`;
    q('#go', root).addEventListener('click', () => {
      const g = id => esc(q('#' + id, root).value);
      const t = `<title>${g('title')}</title>
<meta name="description" content="${g('desc')}">
<meta name="keywords" content="${g('kw')}">
<meta name="author" content="${g('auth')}">
<link rel="canonical" href="${g('url')}">
<meta property="og:title" content="${g('title')}">
<meta property="og:description" content="${g('desc')}">
<meta property="og:url" content="${g('url')}">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="${g('title')}">
<meta name="twitter:description" content="${g('desc')}">`;
      q('#out', root).textContent = t;
    });
    q('#cp', root).addEventListener('click', () => U.copy(q('#out', root).textContent));
  });

  function counterField(root, id, min, max) {
    const el = q('#' + id, root), c = q('#' + id + 'c', root);
    const upd = () => { const n = el.value.length; c.textContent = n + ' chars'; c.style.color = (n >= min && n <= max) ? 'var(--success)' : 'var(--warning)'; };
    el.addEventListener('input', upd); upd();
  }
  reg('meta-description-generator', root => {
    root.innerHTML = `<label class="field__label">Meta description <span id="dc" class="muted"></span></label>
      <textarea id="d" class="textarea" placeholder="Write your meta description (aim 120–158 characters)…"></textarea>
      <div class="notice notice--info mt-4">Ideal length: 120–158 characters. Google typically truncates around 155–160.</div>
      <div class="btn-row mt-4"><button class="btn btn--primary" id="cp">Copy</button></div>`;
    counterField(root, 'd', 120, 158);
    q('#cp', root).addEventListener('click', () => U.copy(q('#d', root).value));
  });
  reg('title-generator', root => {
    root.innerHTML = `<label class="field__label">Title tag <span id="tc" class="muted"></span></label>
      <input id="t" class="input" placeholder="Your SEO title (aim 50–60 characters)">
      <div class="notice notice--info mt-4">Ideal length: 50–60 characters. Titles over ~60 chars get truncated in search results.</div>
      <div id="preview" class="mt-4" style="border:1px solid var(--border);border-radius:12px;padding:16px">
        <div style="color:#1a0dab;font-size:19px">Preview title</div>
        <div style="color:#006621;font-size:13px">example.com › page</div></div>
      <div class="btn-row mt-4"><button class="btn btn--primary" id="cp">Copy</button></div>`;
    counterField(root, 't', 50, 60);
    q('#t', root).addEventListener('input', () => { q('#preview div', root).textContent = q('#t', root).value || 'Preview title'; });
    q('#cp', root).addEventListener('click', () => U.copy(q('#t', root).value));
  });

  reg('robots-txt-generator', root => {
    root.innerHTML = `
      <div class="chips"><label class="chip"><input type="radio" name="mode" value="all" checked> Allow all</label>
      <label class="chip"><input type="radio" name="mode" value="none"> Block all</label>
      <label class="chip"><input type="radio" name="mode" value="custom"> Custom</label></div>
      <div class="field mt-4"><label class="field__label">Disallow paths (one per line)</label><textarea id="dis" class="textarea" placeholder="/admin/&#10;/private/"></textarea></div>
      <div class="field"><label class="field__label">Sitemap URL</label><input id="sm" class="input" placeholder="https://example.com/sitemap.xml"></div>
      <div class="btn-row mt-4"><button class="btn btn--primary" id="go">Generate</button><button class="btn btn--ghost" id="cp">Copy</button><button class="btn btn--ghost" id="dl">Download</button></div>
      <div class="output-box mt-4" id="out"></div>`;
    const gen = () => {
      const mode = q('input[name=mode]:checked', root).value;
      let txt = 'User-agent: *\n';
      if (mode === 'none') txt += 'Disallow: /\n';
      else if (mode === 'all') txt += 'Disallow:\n';
      else { const paths = q('#dis', root).value.split('\n').filter(x => x.trim()); txt += (paths.length ? paths.map(p => 'Disallow: ' + p.trim()).join('\n') : 'Disallow:') + '\n'; }
      const sm = q('#sm', root).value.trim(); if (sm) txt += '\nSitemap: ' + sm + '\n';
      q('#out', root).textContent = txt;
    };
    q('#go', root).addEventListener('click', gen);
    q('#cp', root).addEventListener('click', () => U.copy(q('#out', root).textContent));
    q('#dl', root).addEventListener('click', () => U.download('robots.txt', q('#out', root).textContent));
    gen();
  });

  reg('sitemap-generator', root => {
    root.innerHTML = `<label class="field__label">URLs (one per line)</label>
      <textarea id="in" class="textarea textarea--tall" placeholder="https://example.com/&#10;https://example.com/about"></textarea>
      <div class="row mt-4"><div><label class="field__label">Change frequency</label><select id="freq" class="select"><option>weekly</option><option>daily</option><option>monthly</option><option>yearly</option></select></div>
      <div><label class="field__label">Priority</label><select id="pri" class="select"><option>0.8</option><option>1.0</option><option>0.5</option></select></div></div>
      <div class="btn-row mt-4"><button class="btn btn--primary" id="go">Generate XML</button><button class="btn btn--ghost" id="cp">Copy</button><button class="btn btn--ghost" id="dl">Download</button></div>
      <div class="output-box mt-4" id="out"></div>`;
    const gen = () => {
      const urls = q('#in', root).value.split('\n').map(u => u.trim()).filter(Boolean);
      const today = new Date().toISOString().slice(0, 10);
      let xml = '<?xml version="1.0" encoding="UTF-8"?>\n<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">\n';
      urls.forEach(u => { xml += `  <url>\n    <loc>${esc(u)}</loc>\n    <lastmod>${today}</lastmod>\n    <changefreq>${q('#freq', root).value}</changefreq>\n    <priority>${q('#pri', root).value}</priority>\n  </url>\n`; });
      xml += '</urlset>';
      q('#out', root).textContent = xml;
    };
    q('#go', root).addEventListener('click', gen);
    q('#cp', root).addEventListener('click', () => U.copy(q('#out', root).textContent));
    q('#dl', root).addEventListener('click', () => U.download('sitemap.xml', q('#out', root).textContent, 'application/xml'));
  });

  reg('schema-generator', root => {
    root.innerHTML = `<div class="row"><div><label class="field__label">Type</label><select id="type" class="select"><option>Article</option><option>Product</option><option>Organization</option><option>FAQPage</option><option>LocalBusiness</option></select></div></div>
      <div id="fields" class="mt-4"></div>
      <div class="btn-row mt-4"><button class="btn btn--primary" id="go">Generate JSON-LD</button><button class="btn btn--ghost" id="cp">Copy</button></div>
      <div class="output-box mt-4" id="out"></div>`;
    const fieldSets = {
      Article: ['headline', 'author', 'datePublished', 'image', 'description'],
      Product: ['name', 'image', 'description', 'brand', 'price', 'priceCurrency'],
      Organization: ['name', 'url', 'logo', 'sameAs'],
      FAQPage: ['question', 'answer'],
      LocalBusiness: ['name', 'address', 'telephone', 'priceRange', 'url']
    };
    const render = () => {
      const t = q('#type', root).value;
      q('#fields', root).innerHTML = fieldSets[t].map(f => `<div class="field"><label class="field__label">${f}</label><input class="input" data-f="${f}"></div>`).join('');
    };
    q('#type', root).addEventListener('change', render); render();
    q('#go', root).addEventListener('click', () => {
      const t = q('#type', root).value; const data = { '@context': 'https://schema.org', '@type': t };
      qa('[data-f]', root).forEach(i => { if (i.value) data[i.dataset.f] = i.value; });
      if (t === 'FAQPage' && data.question) { data.mainEntity = [{ '@type': 'Question', name: data.question, acceptedAnswer: { '@type': 'Answer', text: data.answer || '' } }]; delete data.question; delete data.answer; }
      q('#out', root).textContent = '<script type="application/ld+json">\n' + JSON.stringify(data, null, 2) + '\n<\/script>';
    });
    q('#cp', root).addEventListener('click', () => U.copy(q('#out', root).textContent));
  });

  reg('canonical-generator', root => {
    root.innerHTML = `<label class="field__label">Canonical URL</label><input id="u" class="input" placeholder="https://example.com/page">
      <div class="btn-row mt-4"><button class="btn btn--primary" id="go">Generate</button><button class="btn btn--ghost" id="cp">Copy</button></div>
      <div class="output-box mt-4" id="out"></div>`;
    q('#go', root).addEventListener('click', () => { q('#out', root).textContent = `<link rel="canonical" href="${esc(q('#u', root).value)}">`; });
    q('#cp', root).addEventListener('click', () => U.copy(q('#out', root).textContent));
  });

  function socialEngine(root, kind) {
    const fields = kind === 'og'
      ? [['og:title', 'Title'], ['og:description', 'Description'], ['og:url', 'URL'], ['og:image', 'Image URL'], ['og:type', 'Type (website/article)']]
      : [['twitter:title', 'Title'], ['twitter:description', 'Description'], ['twitter:image', 'Image URL'], ['twitter:site', '@username']];
    root.innerHTML = fields.map(([k, l]) => `<div class="field"><label class="field__label">${l}</label><input class="input" data-k="${k}"></div>`).join('') +
      `<div class="btn-row mt-4"><button class="btn btn--primary" id="go">Generate</button><button class="btn btn--ghost" id="cp">Copy</button></div><div class="output-box mt-4" id="out"></div>`;
    q('#go', root).addEventListener('click', () => {
      let out = kind === 'tw' ? '<meta name="twitter:card" content="summary_large_image">\n' : '';
      qa('[data-k]', root).forEach(i => { if (i.value) out += `<meta ${kind === 'og' ? 'property' : 'name'}="${i.dataset.k}" content="${esc(i.value)}">\n`; });
      q('#out', root).textContent = out.trim();
    });
    q('#cp', root).addEventListener('click', () => U.copy(q('#out', root).textContent));
  }
  reg('opengraph-generator', r => socialEngine(r, 'og'));
  reg('twitter-card-generator', r => socialEngine(r, 'tw'));

  reg('slug-generator', root => {
    root.innerHTML = `<label class="field__label">Title / text</label><input id="in" class="input" placeholder="My Awesome Blog Post!">
      <div class="row mt-4"><div><label class="field__label">Separator</label><select id="sep" class="select"><option value="-">Hyphen (-)</option><option value="_">Underscore (_)</option></select></div></div>
      <label class="field__label mt-4">Slug</label><div class="row"><input id="out" class="input" readonly>${'<button class="btn btn--primary" id="cp">Copy</button>'}</div>`;
    const upd = () => { const sep = q('#sep', root).value; q('#out', root).value = q('#in', root).value.toLowerCase().trim().replace(/[^\w\s-]/g, '').replace(/[\s_-]+/g, sep).replace(new RegExp('^' + sep + '+|' + sep + '+$', 'g'), ''); };
    q('#in', root).addEventListener('input', upd); q('#sep', root).addEventListener('change', upd);
    q('#cp', root).addEventListener('click', () => U.copy(q('#out', root).value));
  });

  /* =====================================================================
     FINANCE + CALCULATORS
     ================================================================== */
  function calc(root, html, compute) {
    root.innerHTML = html + `<div class="btn-row mt-4"><button class="btn btn--primary" id="go">Calculate</button></div><div id="out" class="mt-4"></div>`;
    const run = () => q('#out', root).innerHTML = compute(id => q('#' + id, root));
    q('#go', root).addEventListener('click', run);
    qa('input,select', root).forEach(i => i.addEventListener('input', run));
    run();
  }
  const stat = (label, val) => `<div class="stat-card"><b>${val}</b><span>${label}</span></div>`;
  const grid = html => `<div class="stat-grid">${html}</div>`;
  const money = n => (isFinite(n) ? n.toLocaleString(undefined, { maximumFractionDigits: 2 }) : '—');

  reg('emi-calculator', root => calc(root,
    `<div class="row"><div><label class="field__label">Loan amount</label><input id="p" class="input" type="number" value="500000"></div>
     <div><label class="field__label">Interest rate (% p.a.)</label><input id="r" class="input" type="number" value="9" step="0.1"></div>
     <div><label class="field__label">Tenure (months)</label><input id="n" class="input" type="number" value="60"></div></div>`,
    g => { const p = +g('p').value, r = +g('r').value / 1200, n = +g('n').value; const emi = r ? p * r * Math.pow(1 + r, n) / (Math.pow(1 + r, n) - 1) : p / n; const total = emi * n; return grid(stat('Monthly EMI', money(emi)) + stat('Total interest', money(total - p)) + stat('Total payment', money(total))); }));

  reg('loan-calculator', root => calc(root,
    `<div class="row"><div><label class="field__label">Principal</label><input id="p" class="input" type="number" value="200000"></div>
     <div><label class="field__label">Rate (% p.a.)</label><input id="r" class="input" type="number" value="7.5" step="0.1"></div>
     <div><label class="field__label">Years</label><input id="y" class="input" type="number" value="15"></div></div>`,
    g => { const p = +g('p').value, r = +g('r').value / 1200, n = +g('y').value * 12; const m = r ? p * r * Math.pow(1 + r, n) / (Math.pow(1 + r, n) - 1) : p / n; return grid(stat('Monthly payment', money(m)) + stat('Total interest', money(m * n - p)) + stat('Total cost', money(m * n))); }));

  reg('gst-calculator', root => calc(root,
    `<div class="row"><div><label class="field__label">Amount</label><input id="a" class="input" type="number" value="1000"></div>
     <div><label class="field__label">GST %</label><input id="g" class="input" type="number" value="18" step="0.1"></div>
     <div><label class="field__label">Mode</label><select id="m" class="select"><option value="add">Add GST</option><option value="rem">Remove GST</option></select></div></div>`,
    g => { const a = +g('a').value, r = +g('g').value; let base, tax, total; if (g('m').value === 'add') { base = a; tax = a * r / 100; total = a + tax; } else { total = a; base = a * 100 / (100 + r); tax = a - base; } return grid(stat('Base amount', money(base)) + stat('GST', money(tax)) + stat('Total', money(total))); }));

  reg('discount-calculator', root => calc(root,
    `<div class="row"><div><label class="field__label">Original price</label><input id="p" class="input" type="number" value="1200"></div>
     <div><label class="field__label">Discount %</label><input id="d" class="input" type="number" value="25"></div></div>`,
    g => { const p = +g('p').value, d = +g('d').value; const save = p * d / 100; return grid(stat('You save', money(save)) + stat('Final price', money(p - save))); }));

  reg('percentage-calculator', root => calc(root,
    `<div class="row"><div><label class="field__label">X</label><input id="x" class="input" type="number" value="25"></div>
     <div><label class="field__label">% of Y</label><input id="y" class="input" type="number" value="200"></div></div>`,
    g => { const x = +g('x').value, y = +g('y').value; return grid(stat(`${x}% of ${y}`, money(x * y / 100)) + stat(`${x} is what % of ${y}`, (x / y * 100).toFixed(2) + '%') + stat('% change X→Y', ((y - x) / x * 100).toFixed(2) + '%')); }));

  reg('sip-calculator', root => calc(root,
    `<div class="row"><div><label class="field__label">Monthly investment</label><input id="m" class="input" type="number" value="5000"></div>
     <div><label class="field__label">Expected return (% p.a.)</label><input id="r" class="input" type="number" value="12" step="0.1"></div>
     <div><label class="field__label">Years</label><input id="y" class="input" type="number" value="10"></div></div>`,
    g => { const m = +g('m').value, r = +g('r').value / 1200, n = +g('y').value * 12; const fv = r ? m * ((Math.pow(1 + r, n) - 1) / r) * (1 + r) : m * n; const invested = m * n; return grid(stat('Invested', money(invested)) + stat('Est. returns', money(fv - invested)) + stat('Future value', money(fv))); }));

  reg('age-calculator', root => calc(root,
    `<div class="row"><div><label class="field__label">Date of birth</label><input id="dob" class="input" type="date"></div>
     <div><label class="field__label">Age at date</label><input id="at" class="input" type="date"></div></div>`,
    g => { const dob = new Date(g('dob').value); const at = g('at').value ? new Date(g('at').value) : new Date(); if (isNaN(dob)) return '<div class="muted">Enter your date of birth.</div>'; let y = at.getFullYear() - dob.getFullYear(), m = at.getMonth() - dob.getMonth(), d = at.getDate() - dob.getDate(); if (d < 0) { m--; d += new Date(at.getFullYear(), at.getMonth(), 0).getDate(); } if (m < 0) { y--; m += 12; } const days = Math.floor((at - dob) / 864e5); return grid(stat('Age', `${y}y ${m}m ${d}d`) + stat('Total days', days.toLocaleString()) + stat('Total weeks', Math.floor(days / 7).toLocaleString())); }));

  reg('currency-converter', root => {
    // Editable static rates (relative to USD) — works offline, honest & fast.
    const rates = { USD: 1, EUR: 0.92, GBP: 0.79, INR: 83.2, NPR: 133.1, JPY: 149.5, AUD: 1.52, CAD: 1.36, CNY: 7.24, AED: 3.67 };
    const opts = Object.keys(rates).map(c => `<option>${c}</option>`).join('');
    calc(root, `<div class="row"><div><label class="field__label">Amount</label><input id="a" class="input" type="number" value="100"></div>
      <div><label class="field__label">From</label><select id="f" class="select">${opts}</select></div>
      <div><label class="field__label">To</label><select id="t" class="select">${opts.replace('<option>EUR', '<option selected>EUR')}</select></div></div>
      <div class="notice notice--info mt-4">Rates are indicative and editable in the tool. For live rates, connect a currency API.</div>`,
      g => { const a = +g('a').value, f = g('f').value, t = g('t').value; const usd = a / rates[f]; const res = usd * rates[t]; return grid(stat(`${a} ${f}`, money(res) + ' ' + t) + stat('Rate', (rates[t] / rates[f]).toFixed(4))); });
  });

  reg('bmi-calculator', root => calc(root,
    `<div class="row"><div><label class="field__label">Weight (kg)</label><input id="w" class="input" type="number" value="70"></div>
     <div><label class="field__label">Height (cm)</label><input id="h" class="input" type="number" value="175"></div></div>`,
    g => { const w = +g('w').value, h = +g('h').value / 100; const bmi = w / (h * h); const cat = bmi < 18.5 ? 'Underweight' : bmi < 25 ? 'Normal' : bmi < 30 ? 'Overweight' : 'Obese'; return grid(stat('BMI', bmi.toFixed(1)) + stat('Category', cat)); }));

  reg('tip-calculator', root => calc(root,
    `<div class="row"><div><label class="field__label">Bill</label><input id="b" class="input" type="number" value="50"></div>
     <div><label class="field__label">Tip %</label><input id="t" class="input" type="number" value="15"></div>
     <div><label class="field__label">People</label><input id="p" class="input" type="number" value="2" min="1"></div></div>`,
    g => { const b = +g('b').value, t = +g('t').value, p = Math.max(1, +g('p').value); const tip = b * t / 100; const total = b + tip; return grid(stat('Tip', money(tip)) + stat('Total', money(total)) + stat('Per person', money(total / p))); }));

  reg('average-calculator', root => {
    root.innerHTML = `<label class="field__label">Numbers (comma or space separated)</label>
      <textarea id="in" class="textarea" placeholder="10, 20, 30, 40"></textarea>
      <div class="btn-row mt-4"><button class="btn btn--primary" id="go">Calculate</button></div><div id="out" class="mt-4"></div>`;
    q('#go', root).addEventListener('click', () => {
      const nums = q('#in', root).value.split(/[\s,]+/).map(Number).filter(n => !isNaN(n));
      if (!nums.length) { q('#out', root).innerHTML = '<div class="muted">Enter some numbers.</div>'; return; }
      const sum = nums.reduce((a, b) => a + b, 0); const mean = sum / nums.length;
      const sorted = [...nums].sort((a, b) => a - b); const mid = Math.floor(sorted.length / 2);
      const median = sorted.length % 2 ? sorted[mid] : (sorted[mid - 1] + sorted[mid]) / 2;
      const freq = {}; nums.forEach(n => freq[n] = (freq[n] || 0) + 1); const maxF = Math.max(...Object.values(freq));
      const mode = Object.keys(freq).filter(k => freq[k] === maxF).join(', ');
      q('#out', root).innerHTML = grid(stat('Count', nums.length) + stat('Sum', money(sum)) + stat('Mean', money(mean)) + stat('Median', money(median)) + stat('Mode', mode) + stat('Min / Max', sorted[0] + ' / ' + sorted[sorted.length - 1]));
    });
    q('#go', root).click();
  });

  reg('date-difference', root => calc(root,
    `<div class="row"><div><label class="field__label">Start date</label><input id="a" class="input" type="date"></div>
     <div><label class="field__label">End date</label><input id="b" class="input" type="date"></div></div>`,
    g => { const a = new Date(g('a').value), b = new Date(g('b').value); if (isNaN(a) || isNaN(b)) return '<div class="muted">Pick two dates.</div>'; const days = Math.abs(Math.round((b - a) / 864e5)); return grid(stat('Days', days.toLocaleString()) + stat('Weeks', (days / 7).toFixed(1)) + stat('Months', (days / 30.44).toFixed(1)) + stat('Years', (days / 365.25).toFixed(2))); }));

  reg('scientific-calculator', root => {
    root.innerHTML = `<input id="disp" class="input" style="font-size:22px;text-align:right;font-family:var(--font-mono);height:56px" value="0" readonly>
      <div class="mt-4" style="display:grid;grid-template-columns:repeat(5,1fr);gap:8px" id="pad"></div>`;
    const keys = ['sin', 'cos', 'tan', '√', '^', '(', ')', 'π', 'e', 'C', '7', '8', '9', '/', '⌫', '4', '5', '6', '*', 'log', '1', '2', '3', '-', 'ln', '0', '.', '%', '+', '='];
    q('#pad', root).innerHTML = keys.map(k => `<button class="btn ${k === '=' ? 'btn--primary' : 'btn--ghost'}" data-k="${k}">${k}</button>`).join('');
    const disp = q('#disp', root); let expr = '';
    const setD = v => disp.value = v || '0';
    qa('[data-k]', root).forEach(b => b.addEventListener('click', () => {
      const k = b.dataset.k;
      if (k === 'C') { expr = ''; setD(''); return; }
      if (k === '⌫') { expr = expr.slice(0, -1); setD(expr); return; }
      if (k === '=') { try { let e = expr.replace(/π/g, 'Math.PI').replace(/(?<![a-z])e/g, 'Math.E').replace(/√/g, 'Math.sqrt').replace(/\^/g, '**').replace(/sin/g, 'Math.sin').replace(/cos/g, 'Math.cos').replace(/tan/g, 'Math.tan').replace(/log/g, 'Math.log10').replace(/ln/g, 'Math.log').replace(/%/g, '/100'); const r = Function('"use strict";return (' + e + ')')(); setD(String(r)); expr = String(r); } catch (_) { setD('Error'); expr = ''; } return; }
      expr += (['sin', 'cos', 'tan', 'log', 'ln', '√'].includes(k)) ? k + '(' : k; setD(expr);
    }));
  });

  /* =====================================================================
     UTILITIES
     ================================================================== */
  reg('password-generator', root => {
    root.innerHTML = `<div class="row"><div><label class="field__label">Length: <b id="lv">16</b></label><input id="len" type="range" min="4" max="64" value="16" style="width:100%"></div></div>
      <div class="chips mt-4">
        <label class="chip"><input type="checkbox" id="up" checked> A-Z</label>
        <label class="chip"><input type="checkbox" id="lo" checked> a-z</label>
        <label class="chip"><input type="checkbox" id="nu" checked> 0-9</label>
        <label class="chip"><input type="checkbox" id="sy" checked> !@#$</label>
        <label class="chip"><input type="checkbox" id="ex"> Exclude similar (0O1l)</label></div>
      <div class="row mt-4"><input id="out" class="input" readonly style="font-family:var(--font-mono);font-size:16px">
      <button class="btn btn--primary" id="cp" style="flex:none">Copy</button></div>
      <div id="meter" class="mt-4"></div>
      <div class="btn-row mt-4"><button class="btn btn--ghost btn--block" id="go">Regenerate</button></div>`;
    q('#len', root).addEventListener('input', () => { q('#lv', root).textContent = q('#len', root).value; gen(); });
    const gen = () => {
      let chars = '';
      if (q('#up', root).checked) chars += 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
      if (q('#lo', root).checked) chars += 'abcdefghijklmnopqrstuvwxyz';
      if (q('#nu', root).checked) chars += '0123456789';
      if (q('#sy', root).checked) chars += '!@#$%^&*()-_=+[]{};:,.<>?';
      if (q('#ex', root).checked) chars = chars.replace(/[0O1lI]/g, '');
      if (!chars) { q('#out', root).value = ''; return; }
      const len = +q('#len', root).value; let pw = '';
      const rnd = crypto.getRandomValues(new Uint32Array(len));
      for (let i = 0; i < len; i++) pw += chars[rnd[i] % chars.length];
      q('#out', root).value = pw;
      const entropy = len * Math.log2(chars.length);
      const level = entropy < 40 ? ['Weak', 'var(--danger)', 30] : entropy < 70 ? ['Good', 'var(--warning)', 65] : entropy < 100 ? ['Strong', 'var(--success)', 90] : ['Very strong', 'var(--success)', 100];
      q('#meter', root).innerHTML = `<div style="height:8px;border-radius:4px;background:var(--surface-2)"><div style="height:100%;width:${level[2]}%;background:${level[1]};border-radius:4px;transition:.3s"></div></div><div class="mt-2 muted">${level[0]} · ${Math.round(entropy)} bits of entropy</div>`;
    };
    qa('input[type=checkbox]', root).forEach(c => c.addEventListener('change', gen));
    q('#go', root).addEventListener('click', gen);
    q('#cp', root).addEventListener('click', () => U.copy(q('#out', root).value));
    gen();
  });

  reg('password-strength', root => {
    root.innerHTML = `<label class="field__label">Enter a password to test</label>
      <input id="pw" class="input" type="text" style="font-family:var(--font-mono)" placeholder="Type a password…">
      <div id="out" class="mt-4"></div>`;
    q('#pw', root).addEventListener('input', () => {
      const pw = q('#pw', root).value;
      let pool = 0;
      if (/[a-z]/.test(pw)) pool += 26; if (/[A-Z]/.test(pw)) pool += 26; if (/[0-9]/.test(pw)) pool += 10; if (/[^a-zA-Z0-9]/.test(pw)) pool += 32;
      const entropy = pw.length ? pw.length * Math.log2(pool || 1) : 0;
      const guesses = Math.pow(2, entropy);
      const perSec = 1e10; let secs = guesses / perSec;
      const human = secs < 1 ? 'instantly' : secs < 60 ? Math.round(secs) + ' seconds' : secs < 3600 ? Math.round(secs / 60) + ' minutes' : secs < 86400 ? Math.round(secs / 3600) + ' hours' : secs < 3.15e7 ? Math.round(secs / 86400) + ' days' : secs < 3.15e9 ? Math.round(secs / 3.15e7) + ' years' : 'centuries';
      const lvl = entropy < 40 ? ['Weak', 'var(--danger)', 30] : entropy < 70 ? ['Fair', 'var(--warning)', 60] : entropy < 100 ? ['Strong', 'var(--success)', 85] : ['Excellent', 'var(--success)', 100];
      q('#out', root).innerHTML = `<div style="height:10px;border-radius:5px;background:var(--surface-2)"><div style="height:100%;width:${lvl[2]}%;background:${lvl[1]};border-radius:5px"></div></div>
        ${grid(stat('Strength', lvl[0]) + stat('Entropy', Math.round(entropy) + ' bits') + stat('Crack time', human))}`;
    });
  });

  reg('qr-code-generator', root => {
    root.innerHTML = `<label class="field__label">Text or URL</label>
      <textarea id="in" class="textarea" placeholder="https://apps.briefnepal.com">https://apps.briefnepal.com</textarea>
      <div class="row mt-4"><div><label class="field__label">Size</label><select id="size" class="select"><option value="6">Small</option><option value="8" selected>Medium</option><option value="12">Large</option></select></div>
      <div><label class="field__label">Error correction</label><select id="ecl" class="select"><option value="LOW">Low</option><option value="MEDIUM" selected>Medium</option><option value="QUARTILE">Quartile</option><option value="HIGH">High</option></select></div>
      <div><label class="field__label">Color</label><input id="col" type="color" value="#000000" class="input" style="height:44px"></div></div>
      <div class="btn-row mt-4"><button class="btn btn--primary" id="go">Generate</button><button class="btn btn--ghost" id="dl">Download PNG</button></div>
      <div class="mt-4" id="canvasWrap" style="display:grid;place-items:center;padding:20px;background:var(--surface-2);border-radius:16px"></div>`;
    let canvas;
    const gen = () => {
      try {
        const qr = Lib.QR.encode(q('#in', root).value || ' ', q('#ecl', root).value);
        canvas = Lib.qrToCanvas(qr, +q('#size', root).value, 4, q('#col', root).value, '#ffffff');
        canvas.style.maxWidth = '260px'; canvas.style.borderRadius = '8px';
        q('#canvasWrap', root).innerHTML = ''; q('#canvasWrap', root).appendChild(canvas);
      } catch (e) { q('#canvasWrap', root).innerHTML = `<div class="notice notice--error">${esc(e.message)}</div>`; }
    };
    q('#go', root).addEventListener('click', gen);
    qa('select,#col', root).forEach(el => el.addEventListener('change', gen));
    q('#dl', root).addEventListener('click', () => { if (canvas) canvas.toBlob(b => U.download('qrcode.png', b)); });
    gen();
  });

  reg('barcode-generator', root => {
    root.innerHTML = `<label class="field__label">Text (CODE128)</label><input id="in" class="input" value="OMNITOOLS-123">
      <div class="btn-row mt-4"><button class="btn btn--primary" id="go">Generate</button><button class="btn btn--ghost" id="dl">Download PNG</button></div>
      <div class="mt-4" id="wrap" style="display:grid;place-items:center;padding:20px;background:#fff;border-radius:16px;overflow:auto"></div>`;
    let canvas;
    const gen = () => { try { canvas = Lib.CODE128.toCanvas(q('#in', root).value || ' '); q('#wrap', root).innerHTML = ''; q('#wrap', root).appendChild(canvas); } catch (e) { q('#wrap', root).innerHTML = `<div class="notice notice--error">${esc(e.message)}</div>`; } };
    q('#go', root).addEventListener('click', gen);
    q('#in', root).addEventListener('input', gen);
    q('#dl', root).addEventListener('click', () => { if (canvas) canvas.toBlob(b => U.download('barcode.png', b)); });
    gen();
  });

  reg('timestamp-converter', root => {
    root.innerHTML = `<div class="field"><label class="field__label">Unix timestamp (seconds or ms)</label>
      <div class="row"><input id="ts" class="input" placeholder="1700000000"><button class="btn btn--primary" id="now" style="flex:none">Now</button></div></div>
      <div id="out1" class="mt-4"></div>
      <div class="field mt-6"><label class="field__label">Human date → timestamp</label><input id="dt" class="input" type="datetime-local"></div>
      <div id="out2" class="mt-4"></div>`;
    const showTs = () => { let v = q('#ts', root).value.trim(); if (!v) { q('#out1', root).innerHTML = ''; return; } let n = +v; if (v.length > 11) n = n / 1000; const d = new Date(n * 1000); if (isNaN(d)) { q('#out1', root).innerHTML = '<div class="notice notice--error">Invalid timestamp.</div>'; return; } q('#out1', root).innerHTML = grid(stat('Local', d.toLocaleString()) + stat('UTC', d.toUTCString()) + stat('ISO', d.toISOString())); };
    const showDt = () => { const v = q('#dt', root).value; if (!v) return; const d = new Date(v); q('#out2', root).innerHTML = grid(stat('Seconds', Math.floor(d.getTime() / 1000)) + stat('Milliseconds', d.getTime())); };
    q('#ts', root).addEventListener('input', showTs);
    q('#now', root).addEventListener('click', () => { q('#ts', root).value = Math.floor(Date.now() / 1000); showTs(); });
    q('#dt', root).addEventListener('input', showDt);
    q('#ts', root).value = Math.floor(Date.now() / 1000); showTs();
  });

  function colorTool(root) {
    root.innerHTML = `<div class="row"><div><label class="field__label">Pick a color</label><input id="c" type="color" value="#0071e3" class="input" style="height:64px"></div></div>
      <div id="out" class="mt-4"></div>`;
    const upd = () => {
      const hex = q('#c', root).value; const r = parseInt(hex.slice(1, 3), 16), g = parseInt(hex.slice(3, 5), 16), b = parseInt(hex.slice(5, 7), 16);
      const max = Math.max(r, g, b) / 255, min = Math.min(r, g, b) / 255; let h = 0, s = 0, l = (max + min) / 2;
      if (max !== min) { const d = max - min; s = l > 0.5 ? d / (2 - max - min) : d / (max + min); const rr = r / 255, gg = g / 255, bb = b / 255; if (max === rr) h = (gg - bb) / d + (gg < bb ? 6 : 0); else if (max === gg) h = (bb - rr) / d + 2; else h = (rr - gg) / d + 4; h *= 60; }
      const rows = [['HEX', hex], ['RGB', `rgb(${r}, ${g}, ${b})`], ['HSL', `hsl(${Math.round(h)}, ${Math.round(s * 100)}%, ${Math.round(l * 100)}%)`]];
      q('#out', root).innerHTML = `<div style="height:80px;border-radius:14px;background:${hex};border:1px solid var(--border)"></div>` +
        rows.map(([k, v]) => `<div class="field mt-4"><label class="field__label">${k}</label><div class="row"><input class="input" value="${esc(v)}" readonly>${copyable(v, 'Copy')}</div></div>`).join('');
      wireCopy(root);
    };
    q('#c', root).addEventListener('input', upd); upd();
  }
  reg('color-picker', colorTool);

  reg('gradient-generator', root => {
    root.innerHTML = `<div class="row"><div><label class="field__label">Color 1</label><input id="c1" type="color" value="#0071e3" class="input" style="height:48px"></div>
      <div><label class="field__label">Color 2</label><input id="c2" type="color" value="#7c3aed" class="input" style="height:48px"></div>
      <div><label class="field__label">Angle: <b id="av">135</b>°</label><input id="ang" type="range" min="0" max="360" value="135" style="width:100%"></div></div>
      <div id="prev" class="mt-4" style="height:160px;border-radius:16px"></div>
      <div class="field mt-4"><label class="field__label">CSS</label><div class="row"><input id="css" class="input" readonly style="font-family:var(--font-mono)"><button class="btn btn--primary" id="cp" style="flex:none">Copy</button></div></div>`;
    const upd = () => { const g = `linear-gradient(${q('#ang', root).value}deg, ${q('#c1', root).value}, ${q('#c2', root).value})`; q('#prev', root).style.background = g; q('#css', root).value = 'background: ' + g + ';'; q('#av', root).textContent = q('#ang', root).value; };
    qa('input', root).forEach(i => i.addEventListener('input', upd));
    q('#cp', root).addEventListener('click', () => U.copy(q('#css', root).value)); upd();
  });

  function hexRgb(root, mode) {
    if (mode === 'h2r') {
      root.innerHTML = `<label class="field__label">HEX color</label><input id="in" class="input" value="#0071e3" style="font-family:var(--font-mono)">
        <div class="row mt-4"><input id="out" class="input" readonly><button class="btn btn--primary" id="cp" style="flex:none">Copy</button></div>
        <div id="sw" class="mt-4" style="height:70px;border-radius:12px;border:1px solid var(--border)"></div>`;
      const upd = () => { let h = q('#in', root).value.replace('#', ''); if (h.length === 3) h = h.split('').map(c => c + c).join(''); if (!/^[0-9a-f]{6}$/i.test(h)) { q('#out', root).value = 'Invalid HEX'; return; } const r = parseInt(h.slice(0, 2), 16), g = parseInt(h.slice(2, 4), 16), b = parseInt(h.slice(4, 6), 16); q('#out', root).value = `rgb(${r}, ${g}, ${b})`; q('#sw', root).style.background = '#' + h; };
      q('#in', root).addEventListener('input', upd); q('#cp', root).addEventListener('click', () => U.copy(q('#out', root).value)); upd();
    } else {
      root.innerHTML = `<div class="row"><div><label class="field__label">R</label><input id="r" class="input" type="number" min="0" max="255" value="0"></div>
        <div><label class="field__label">G</label><input id="g" class="input" type="number" min="0" max="255" value="113"></div>
        <div><label class="field__label">B</label><input id="b" class="input" type="number" min="0" max="255" value="227"></div></div>
        <div class="row mt-4"><input id="out" class="input" readonly style="font-family:var(--font-mono)"><button class="btn btn--primary" id="cp" style="flex:none">Copy</button></div>
        <div id="sw" class="mt-4" style="height:70px;border-radius:12px;border:1px solid var(--border)"></div>`;
      const upd = () => { const to = v => Math.max(0, Math.min(255, +v || 0)).toString(16).padStart(2, '0'); const hex = '#' + to(q('#r', root).value) + to(q('#g', root).value) + to(q('#b', root).value); q('#out', root).value = hex.toUpperCase(); q('#sw', root).style.background = hex; };
      qa('input[type=number]', root).forEach(i => i.addEventListener('input', upd)); q('#cp', root).addEventListener('click', () => U.copy(q('#out', root).value)); upd();
    }
  }
  reg('hex-to-rgb', r => hexRgb(r, 'h2r'));
  reg('rgb-to-hex', r => hexRgb(r, 'r2h'));

  reg('random-number', root => {
    root.innerHTML = `<div class="row"><div><label class="field__label">Min</label><input id="min" class="input" type="number" value="1"></div>
      <div><label class="field__label">Max</label><input id="max" class="input" type="number" value="100"></div>
      <div><label class="field__label">Count</label><input id="cnt" class="input" type="number" value="1" min="1" max="1000"></div></div>
      <label class="chip mt-4" style="display:inline-flex"><input type="checkbox" id="uniq"> Unique</label>
      <div class="btn-row mt-4"><button class="btn btn--primary" id="go">Generate</button><button class="btn btn--ghost" id="cp">Copy</button></div>
      <div class="output-box mt-4" id="out" style="font-size:18px"></div>`;
    q('#go', root).addEventListener('click', () => {
      let min = +q('#min', root).value, max = +q('#max', root).value; if (min > max)[min, max] = [max, min];
      const cnt = Math.min(1000, +q('#cnt', root).value || 1); const uniq = q('#uniq', root).checked; const res = [];
      const range = max - min + 1;
      if (uniq && cnt <= range) { const pool = Array.from({ length: range }, (_, i) => min + i); for (let i = 0; i < cnt; i++) { const j = Math.floor(Math.random() * pool.length); res.push(pool.splice(j, 1)[0]); } }
      else for (let i = 0; i < cnt; i++) res.push(min + Math.floor(Math.random() * range));
      q('#out', root).textContent = res.join(', ');
    });
    q('#cp', root).addEventListener('click', () => U.copy(q('#out', root).textContent));
    q('#go', root).click();
  });

  reg('coin-flip', root => {
    root.innerHTML = `<div style="text-align:center;padding:20px">
      <div id="coin" style="font-size:80px;transition:transform .5s">🪙</div>
      <div id="res" style="font-size:28px;font-weight:700;margin:16px 0">Ready</div>
      <button class="btn btn--primary" id="go">Flip Coin</button>
      <div class="mt-6 muted" id="tally">Heads: 0 · Tails: 0</div></div>`;
    let h = 0, tl = 0;
    q('#go', root).addEventListener('click', () => {
      const coin = q('#coin', root); coin.style.transform = 'rotateY(1080deg)';
      setTimeout(() => coin.style.transform = 'none', 500);
      setTimeout(() => { const heads = Math.random() < 0.5; if (heads) h++; else tl++; q('#res', root).textContent = heads ? 'Heads' : 'Tails'; q('#coin', root).textContent = heads ? '🪙' : '🌕'; q('#tally', root).textContent = `Heads: ${h} · Tails: ${tl}`; }, 250);
    });
  });

  reg('stopwatch', root => {
    root.innerHTML = `<div style="text-align:center;padding:20px">
      <div id="time" style="font-size:56px;font-weight:700;font-family:var(--font-mono);letter-spacing:-.02em">00:00.00</div>
      <div class="btn-row mt-6" style="justify-content:center"><button class="btn btn--primary" id="se">Start</button><button class="btn btn--ghost" id="lap">Lap</button><button class="btn btn--ghost" id="reset">Reset</button></div>
      <div id="laps" class="mt-6" style="text-align:left;max-width:320px;margin:0 auto"></div></div>`;
    let start = 0, elapsed = 0, timer = null, laps = [];
    const fmt = ms => { const m = Math.floor(ms / 60000), s = Math.floor(ms / 1000) % 60, cs = Math.floor(ms / 10) % 100; return `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}.${String(cs).padStart(2, '0')}`; };
    const tick = () => q('#time', root).textContent = fmt(elapsed + (Date.now() - start));
    q('#se', root).addEventListener('click', () => { if (timer) { elapsed += Date.now() - start; clearInterval(timer); timer = null; q('#se', root).textContent = 'Start'; } else { start = Date.now(); timer = setInterval(tick, 33); q('#se', root).textContent = 'Pause'; } });
    q('#lap', root).addEventListener('click', () => { const t = elapsed + (timer ? Date.now() - start : 0); laps.unshift(fmt(t)); q('#laps', root).innerHTML = laps.map((l, i) => `<div class="row" style="border-bottom:1px solid var(--border);padding:6px 0"><span class="muted">Lap ${laps.length - i}</span><span style="text-align:right;font-family:var(--font-mono)">${l}</span></div>`).join(''); });
    q('#reset', root).addEventListener('click', () => { clearInterval(timer); timer = null; elapsed = 0; laps = []; q('#time', root).textContent = '00:00.00'; q('#laps', root).innerHTML = ''; q('#se', root).textContent = 'Start'; });
  });

  /* =====================================================================
     CONVERTERS (unit)
     ================================================================== */
  function unitTool(root, units, defaultFrom, defaultTo) {
    const opts = sel => Object.keys(units).map(u => `<option ${u === sel ? 'selected' : ''}>${u}</option>`).join('');
    root.innerHTML = `<div class="row"><div><label class="field__label">Value</label><input id="v" class="input" type="number" value="1"></div>
      <div><label class="field__label">From</label><select id="f" class="select">${opts(defaultFrom)}</select></div>
      <div><label class="field__label">To</label><select id="t" class="select">${opts(defaultTo)}</select></div></div>
      <div id="out" class="mt-4"></div>`;
    const upd = () => { const v = +q('#v', root).value, f = q('#f', root).value, t = q('#t', root).value; const base = v * units[f]; const res = base / units[t]; q('#out', root).innerHTML = grid(stat(`${v} ${f}`, (isFinite(res) ? +res.toPrecision(8) : '—') + ' ' + t)); };
    qa('input,select', root).forEach(i => i.addEventListener('input', upd)); upd();
  }
  reg('bytes-converter', r => unitTool(r, { Bytes: 1, KB: 1024, MB: 1048576, GB: 1073741824, TB: 1099511627776, PB: 1125899906842624 }, 'MB', 'GB'));
  reg('length-converter', r => unitTool(r, { mm: 0.001, cm: 0.01, m: 1, km: 1000, inch: 0.0254, foot: 0.3048, yard: 0.9144, mile: 1609.344 }, 'm', 'foot'));
  reg('weight-converter', r => unitTool(r, { mg: 0.001, g: 1, kg: 1000, tonne: 1e6, ounce: 28.3495, pound: 453.592, stone: 6350.29 }, 'kg', 'pound'));
  reg('speed-converter', r => unitTool(r, { 'm/s': 1, 'km/h': 0.277778, 'mph': 0.44704, 'knot': 0.514444, 'ft/s': 0.3048 }, 'km/h', 'mph'));
  reg('area-converter', r => unitTool(r, { 'm²': 1, 'km²': 1e6, 'cm²': 0.0001, 'hectare': 10000, 'acre': 4046.86, 'ft²': 0.092903, 'mile²': 2589988 }, 'm²', 'ft²'));
  reg('volume-converter', r => unitTool(r, { ml: 0.001, l: 1, 'm³': 1000, 'gallon (US)': 3.78541, 'quart': 0.946353, 'cup': 0.236588, 'fl oz': 0.0295735 }, 'l', 'gallon (US)'));
  reg('time-converter', r => unitTool(r, { ms: 0.001, second: 1, minute: 60, hour: 3600, day: 86400, week: 604800, month: 2629800, year: 31557600 }, 'hour', 'minute'));

  reg('temperature-converter', root => {
    root.innerHTML = `<div class="row"><div><label class="field__label">Value</label><input id="v" class="input" type="number" value="25"></div>
      <div><label class="field__label">From</label><select id="f" class="select"><option>Celsius</option><option>Fahrenheit</option><option>Kelvin</option></select></div>
      <div><label class="field__label">To</label><select id="t" class="select"><option>Fahrenheit</option><option>Celsius</option><option>Kelvin</option></select></div></div>
      <div id="out" class="mt-4"></div>`;
    const toC = { Celsius: v => v, Fahrenheit: v => (v - 32) * 5 / 9, Kelvin: v => v - 273.15 };
    const fromC = { Celsius: v => v, Fahrenheit: v => v * 9 / 5 + 32, Kelvin: v => v + 273.15 };
    const upd = () => { const v = +q('#v', root).value, f = q('#f', root).value, t = q('#t', root).value; const res = fromC[t](toC[f](v)); q('#out', root).innerHTML = grid(stat(`${v}° ${f}`, (+res.toFixed(2)) + '° ' + t)); };
    qa('input,select', root).forEach(i => i.addEventListener('input', upd)); upd();
  });

  reg('number-to-words', root => {
    root.innerHTML = `<label class="field__label">Number</label><input id="in" class="input" type="number" value="12345">
      <label class="field__label mt-4">In words</label><div class="output-box" id="out"></div>`;
    const ones = ['', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten', 'eleven', 'twelve', 'thirteen', 'fourteen', 'fifteen', 'sixteen', 'seventeen', 'eighteen', 'nineteen'];
    const tens = ['', '', 'twenty', 'thirty', 'forty', 'fifty', 'sixty', 'seventy', 'eighty', 'ninety'];
    const scales = ['', 'thousand', 'million', 'billion', 'trillion'];
    function three(n) { let s = ''; if (n >= 100) { s += ones[Math.floor(n / 100)] + ' hundred '; n %= 100; } if (n >= 20) { s += tens[Math.floor(n / 10)] + ' '; n %= 10; } if (n > 0) s += ones[n] + ' '; return s.trim(); }
    function toWords(num) { if (num === 0) return 'zero'; let neg = num < 0; num = Math.abs(Math.floor(num)); const groups = []; while (num > 0) { groups.push(num % 1000); num = Math.floor(num / 1000); } let words = []; for (let i = groups.length - 1; i >= 0; i--) { if (groups[i]) words.push(three(groups[i]) + (scales[i] ? ' ' + scales[i] : '')); } return (neg ? 'negative ' : '') + words.join(' '); }
    const upd = () => { const v = q('#in', root).value; const w = toWords(+v); q('#out', root).textContent = w.charAt(0).toUpperCase() + w.slice(1); };
    q('#in', root).addEventListener('input', upd); upd();
  });

  reg('roman-numerals', root => {
    root.innerHTML = `<div class="grid-2">
      <div><label class="field__label">Number → Roman</label><input id="n" class="input" type="number" value="2024"><div class="output-box mt-2" id="r1"></div></div>
      <div><label class="field__label">Roman → Number</label><input id="r" class="input" value="MMXXIV" style="text-transform:uppercase"><div class="output-box mt-2" id="r2"></div></div></div>`;
    const map = [[1000, 'M'], [900, 'CM'], [500, 'D'], [400, 'CD'], [100, 'C'], [90, 'XC'], [50, 'L'], [40, 'XL'], [10, 'X'], [9, 'IX'], [5, 'V'], [4, 'IV'], [1, 'I']];
    const toR = n => { if (n < 1 || n > 3999) return 'Range 1–3999'; let s = ''; for (const [v, sym] of map) while (n >= v) { s += sym; n -= v; } return s; };
    const fromR = s => { s = s.toUpperCase(); const vals = { I: 1, V: 5, X: 10, L: 50, C: 100, D: 500, M: 1000 }; let n = 0; for (let i = 0; i < s.length; i++) { if (!(s[i] in vals)) return 'Invalid'; if (vals[s[i]] < (vals[s[i + 1]] || 0)) n -= vals[s[i]]; else n += vals[s[i]]; } return n; };
    q('#n', root).addEventListener('input', () => q('#r1', root).textContent = toR(+q('#n', root).value));
    q('#r', root).addEventListener('input', () => q('#r2', root).textContent = fromR(q('#r', root).value));
    q('#r1', root).textContent = toR(2024); q('#r2', root).textContent = fromR('MMXXIV');
  });

  /* =====================================================================
     DOCUMENTS
     ================================================================== */
  reg('csv-to-json', root => {
    root.innerHTML = `<label class="field__label">CSV (first row = headers)</label>
      <textarea id="in" class="textarea" placeholder="name,age&#10;Alice,30&#10;Bob,25"></textarea>
      <div class="btn-row mt-4"><button class="btn btn--primary" id="go">Convert</button><button class="btn btn--ghost" id="cp">Copy</button></div>
      <div class="output-box mt-4" id="out"></div>`;
    q('#go', root).addEventListener('click', () => {
      const lines = q('#in', root).value.trim().split('\n');
      if (lines.length < 1) return;
      const parseLine = l => { const out = []; let cur = '', inQ = false; for (let i = 0; i < l.length; i++) { const c = l[i]; if (c === '"') { if (inQ && l[i + 1] === '"') { cur += '"'; i++; } else inQ = !inQ; } else if (c === ',' && !inQ) { out.push(cur); cur = ''; } else cur += c; } out.push(cur); return out; };
      const headers = parseLine(lines[0]);
      const rows = lines.slice(1).map(l => { const vals = parseLine(l); const o = {}; headers.forEach((h, i) => o[h.trim()] = (vals[i] || '').trim()); return o; });
      q('#out', root).textContent = JSON.stringify(rows, null, 2);
    });
    q('#cp', root).addEventListener('click', () => U.copy(q('#out', root).textContent));
  });

  reg('json-to-csv', root => {
    root.innerHTML = `<label class="field__label">JSON array of objects</label>
      <textarea id="in" class="textarea" placeholder='[{"name":"Alice","age":30}]'></textarea>
      <div class="btn-row mt-4"><button class="btn btn--primary" id="go">Convert</button><button class="btn btn--ghost" id="cp">Copy</button><button class="btn btn--ghost" id="dl">Download CSV</button></div>
      <div class="output-box mt-4" id="out"></div><div id="msg"></div>`;
    q('#go', root).addEventListener('click', () => {
      try {
        const data = JSON.parse(q('#in', root).value);
        if (!Array.isArray(data)) throw new Error('Expected a JSON array.');
        const headers = [...new Set(data.flatMap(o => Object.keys(o)))];
        const esc2 = v => { v = v == null ? '' : String(v); return /[",\n]/.test(v) ? '"' + v.replace(/"/g, '""') + '"' : v; };
        const csv = [headers.join(','), ...data.map(o => headers.map(h => esc2(o[h])).join(','))].join('\n');
        q('#out', root).textContent = csv; q('#msg', root).innerHTML = '';
      } catch (e) { q('#msg', root).innerHTML = `<div class="notice notice--error">${esc(e.message)}</div>`; }
    });
    q('#cp', root).addEventListener('click', () => U.copy(q('#out', root).textContent));
    q('#dl', root).addEventListener('click', () => U.download('data.csv', q('#out', root).textContent, 'text/csv'));
  });

  reg('text-to-pdf', root => {
    root.innerHTML = `<label class="field__label">Text content</label>
      <textarea id="in" class="textarea textarea--tall" placeholder="Type or paste text to turn into a PDF…"></textarea>
      <div class="btn-row mt-4"><button class="btn btn--primary" id="go">Create PDF</button></div><div id="msg" class="mt-4"></div>`;
    q('#go', root).addEventListener('click', () => {
      const text = q('#in', root).value; if (!text.trim()) { q('#msg', root).innerHTML = '<div class="notice notice--error">Enter some text.</div>'; return; }
      const blob = Lib.PDF.fromText(text); U.download('document.pdf', blob); q('#msg', root).innerHTML = '<div class="notice notice--success">PDF created & downloaded.</div>';
    });
  });

  /* =====================================================================
     AI (on-device text intelligence)
     ================================================================== */
  const STOP = new Set('the a an and or but of to in on at for with by is are was were be been being this that these those it its as from have has had do does did will would can could should i you he she we they them his her their our your my me not no so if then than too very just also into over under about after before'.split(' '));
  reg('text-summarizer', root => {
    root.innerHTML = `<label class="field__label">Paste text to summarise</label>
      <textarea id="in" class="textarea textarea--tall"></textarea>
      <div class="row mt-4"><div><label class="field__label">Summary length</label><select id="n" class="select"><option value="3">3 sentences</option><option value="5">5 sentences</option><option value="7">7 sentences</option></select></div></div>
      <div class="btn-row mt-4"><button class="btn btn--primary" id="go">Summarise</button><button class="btn btn--ghost" id="cp">Copy</button></div>
      <label class="field__label mt-4">Summary</label><div class="output-box" id="out"></div>`;
    q('#go', root).addEventListener('click', () => {
      const text = q('#in', root).value.trim(); if (!text) return;
      const sentences = text.match(/[^.!?]+[.!?]+/g) || [text];
      const freq = {};
      text.toLowerCase().match(/\b[a-z]{3,}\b/g)?.forEach(w => { if (!STOP.has(w)) freq[w] = (freq[w] || 0) + 1; });
      const scored = sentences.map((s, i) => { let score = 0; (s.toLowerCase().match(/\b[a-z]{3,}\b/g) || []).forEach(w => score += freq[w] || 0); return { s: s.trim(), score: score / Math.sqrt(s.split(' ').length + 1), i }; });
      const n = Math.min(+q('#n', root).value, sentences.length);
      const top = scored.slice().sort((a, b) => b.score - a.score).slice(0, n).sort((a, b) => a.i - b.i);
      q('#out', root).textContent = top.map(t => t.s).join(' ');
    });
    q('#cp', root).addEventListener('click', () => U.copy(q('#out', root).textContent));
  });

  reg('keyword-extractor', root => {
    root.innerHTML = `<textarea id="in" class="textarea textarea--tall" placeholder="Paste text…"></textarea>
      <div class="btn-row mt-4"><button class="btn btn--primary" id="go">Extract Keywords</button></div>
      <div class="chips mt-4" id="out"></div>`;
    q('#go', root).addEventListener('click', () => {
      const words = (q('#in', root).value.toLowerCase().match(/\b[a-z]{3,}\b/g) || []).filter(w => !STOP.has(w));
      const freq = {}; words.forEach(w => freq[w] = (freq[w] || 0) + 1);
      const top = Object.entries(freq).sort((a, b) => b[1] - a[1]).slice(0, 20);
      q('#out', root).innerHTML = top.length ? top.map(([w, c]) => `<span class="chip" style="cursor:default">${esc(w)} <b style="color:var(--accent)">${c}</b></span>`).join('') : '<span class="muted">No keywords found.</span>';
    });
  });

  reg('readability-checker', root => {
    root.innerHTML = `<textarea id="in" class="textarea textarea--tall" placeholder="Paste your writing…"></textarea>
      <div class="btn-row mt-4"><button class="btn btn--primary" id="go">Analyse</button></div><div id="out" class="mt-4"></div>`;
    const syllables = w => { w = w.toLowerCase().replace(/[^a-z]/g, ''); if (w.length <= 3) return 1; w = w.replace(/(?:[^laeiouy]es|ed|[^laeiouy]e)$/, '').replace(/^y/, ''); return (w.match(/[aeiouy]{1,2}/g) || []).length || 1; };
    q('#go', root).addEventListener('click', () => {
      const text = q('#in', root).value.trim(); if (!text) return;
      const words = text.match(/\b[a-z']+\b/gi) || []; const sentences = (text.match(/[.!?]+/g) || []).length || 1;
      const syl = words.reduce((s, w) => s + syllables(w), 0);
      const W = words.length || 1;
      const flesch = 206.835 - 1.015 * (W / sentences) - 84.6 * (syl / W);
      const grade = 0.39 * (W / sentences) + 11.8 * (syl / W) - 15.59;
      const level = flesch >= 90 ? 'Very easy' : flesch >= 70 ? 'Easy' : flesch >= 60 ? 'Standard' : flesch >= 50 ? 'Fairly hard' : flesch >= 30 ? 'Hard' : 'Very hard';
      q('#out', root).innerHTML = grid(stat('Flesch score', Math.round(flesch)) + stat('Reading ease', level) + stat('Grade level', Math.max(1, Math.round(grade))) + stat('Words', W) + stat('Sentences', sentences) + stat('Avg words/sentence', (W / sentences).toFixed(1)));
    });
  });

  /* =====================================================================
     IMAGE TOOLS (canvas, on-device)
     ================================================================== */
  function imgResultUI(root, extra) {
    return `<div id="drop"></div><div id="opts" class="hidden mt-4">${extra || ''}</div>
      <div id="result" class="mt-4"></div>`;
  }
  function showImageResult(root, canvas, filename, type, quality, origSize) {
    canvas.toBlob(blob => {
      const url = URL.createObjectURL(blob);
      q('#result', root).innerHTML = `
        <div class="row" style="align-items:center">
          <img src="${url}" style="max-height:220px;border-radius:12px;border:1px solid var(--border)" alt="result preview">
          <div>
            ${grid(stat('New size', fmtBytes(blob.size)) + (origSize ? stat('Original', fmtBytes(origSize)) : '') + stat('Dimensions', canvas.width + '×' + canvas.height))}
            <button class="btn btn--primary mt-4" id="dlbtn">Download</button>
          </div>
        </div>`;
      q('#dlbtn', root).addEventListener('click', () => U.download(filename, blob));
    }, type, quality);
  }

  reg('compress-image', root => {
    root.innerHTML = imgResultUI(root, `<label class="field__label">Quality: <b id="qv">70</b>%</label><input id="q" type="range" min="10" max="100" value="70" style="width:100%">`);
    let img, origSize;
    makeDropzone(q('#drop', root), { accept: 'image/*', onFiles: async f => { origSize = f[0].size; img = await loadImageFile(f[0]); q('#opts', root).classList.remove('hidden'); render(); } });
    const render = () => { if (!img) return; const c = document.createElement('canvas'); c.width = img.naturalWidth; c.height = img.naturalHeight; c.getContext('2d').drawImage(img, 0, 0); q('#qv', root).textContent = q('#q', root).value; showImageResult(root, c, 'compressed.jpg', 'image/jpeg', +q('#q', root).value / 100, origSize); };
    q('#q', root).addEventListener('input', render);
  });

  reg('resize-image', root => {
    root.innerHTML = imgResultUI(root, `<div class="row"><div><label class="field__label">Width</label><input id="w" class="input" type="number"></div>
      <div><label class="field__label">Height</label><input id="h" class="input" type="number"></div></div>
      <label class="chip mt-4" style="display:inline-flex"><input type="checkbox" id="lock" checked> Lock aspect ratio</label>
      <div class="btn-row mt-4"><button class="btn btn--primary" id="go">Resize</button></div>`);
    let img, ratio = 1;
    makeDropzone(q('#drop', root), { accept: 'image/*', onFiles: async f => { img = await loadImageFile(f[0]); ratio = img.naturalWidth / img.naturalHeight; q('#w', root).value = img.naturalWidth; q('#h', root).value = img.naturalHeight; q('#opts', root).classList.remove('hidden'); } });
    q('#w', root).addEventListener('input', () => { if (q('#lock', root).checked) q('#h', root).value = Math.round(q('#w', root).value / ratio); });
    q('#h', root).addEventListener('input', () => { if (q('#lock', root).checked) q('#w', root).value = Math.round(q('#h', root).value * ratio); });
    q('#go', root).addEventListener('click', () => { if (!img) return; const c = document.createElement('canvas'); c.width = +q('#w', root).value; c.height = +q('#h', root).value; c.getContext('2d').drawImage(img, 0, 0, c.width, c.height); showImageResult(root, c, 'resized.png', 'image/png'); });
  });

  reg('crop-image', root => {
    root.innerHTML = `<div id="drop"></div>
      <div id="opts" class="hidden mt-4">
        <div class="row"><div><label class="field__label">X</label><input id="x" class="input" type="number" value="0"></div>
        <div><label class="field__label">Y</label><input id="y" class="input" type="number" value="0"></div>
        <div><label class="field__label">Width</label><input id="w" class="input" type="number"></div>
        <div><label class="field__label">Height</label><input id="h" class="input" type="number"></div></div>
        <div class="btn-row mt-4"><button class="btn btn--primary" id="go">Crop</button></div>
      </div><div id="result" class="mt-4"></div>`;
    let img;
    makeDropzone(q('#drop', root), { accept: 'image/*', onFiles: async f => { img = await loadImageFile(f[0]); q('#w', root).value = Math.round(img.naturalWidth / 2); q('#h', root).value = Math.round(img.naturalHeight / 2); q('#opts', root).classList.remove('hidden'); } });
    q('#go', root).addEventListener('click', () => { if (!img) return; const x = +q('#x', root).value, y = +q('#y', root).value, w = +q('#w', root).value, h = +q('#h', root).value; const c = document.createElement('canvas'); c.width = w; c.height = h; c.getContext('2d').drawImage(img, x, y, w, h, 0, 0, w, h); showImageResult(root, c, 'cropped.png', 'image/png'); });
  });

  function transformImage(root, kind) {
    root.innerHTML = `<div id="drop"></div><div id="opts" class="hidden mt-4"></div><div id="result" class="mt-4"></div>`;
    let img;
    const optsHtml = kind === 'rotate'
      ? `<div class="btn-row"><button class="btn btn--ghost" data-a="90">Rotate 90°</button><button class="btn btn--ghost" data-a="180">180°</button><button class="btn btn--ghost" data-a="270">270°</button></div>`
      : kind === 'flip'
        ? `<div class="btn-row"><button class="btn btn--ghost" data-f="h">Flip Horizontal</button><button class="btn btn--ghost" data-f="v">Flip Vertical</button></div>`
        : `<div class="btn-row"><button class="btn btn--primary" data-g="1">Convert to Grayscale</button></div>`;
    makeDropzone(q('#drop', root), { accept: 'image/*', onFiles: async f => { img = await loadImageFile(f[0]); q('#opts', root).innerHTML = optsHtml; q('#opts', root).classList.remove('hidden'); wire(); } });
    function wire() {
      qa('[data-a]', root).forEach(b => b.addEventListener('click', () => { const a = +b.dataset.a * Math.PI / 180; const c = document.createElement('canvas'); const swap = b.dataset.a === '90' || b.dataset.a === '270'; c.width = swap ? img.naturalHeight : img.naturalWidth; c.height = swap ? img.naturalWidth : img.naturalHeight; const ctx = c.getContext('2d'); ctx.translate(c.width / 2, c.height / 2); ctx.rotate(a); ctx.drawImage(img, -img.naturalWidth / 2, -img.naturalHeight / 2); showImageResult(root, c, 'rotated.png', 'image/png'); }));
      qa('[data-f]', root).forEach(b => b.addEventListener('click', () => { const c = document.createElement('canvas'); c.width = img.naturalWidth; c.height = img.naturalHeight; const ctx = c.getContext('2d'); if (b.dataset.f === 'h') { ctx.translate(c.width, 0); ctx.scale(-1, 1); } else { ctx.translate(0, c.height); ctx.scale(1, -1); } ctx.drawImage(img, 0, 0); showImageResult(root, c, 'flipped.png', 'image/png'); }));
      qa('[data-g]', root).forEach(b => b.addEventListener('click', () => { const c = document.createElement('canvas'); c.width = img.naturalWidth; c.height = img.naturalHeight; const ctx = c.getContext('2d'); ctx.drawImage(img, 0, 0); const d = ctx.getImageData(0, 0, c.width, c.height); for (let i = 0; i < d.data.length; i += 4) { const g = d.data[i] * 0.299 + d.data[i + 1] * 0.587 + d.data[i + 2] * 0.114; d.data[i] = d.data[i + 1] = d.data[i + 2] = g; } ctx.putImageData(d, 0, 0); showImageResult(root, c, 'grayscale.png', 'image/png'); }));
    }
  }
  reg('rotate-image', r => transformImage(r, 'rotate'));
  reg('flip-image', r => transformImage(r, 'flip'));
  reg('grayscale-image', r => transformImage(r, 'gray'));

  function convertImage(root, type, ext) {
    root.innerHTML = `<div id="drop"></div><div id="result" class="mt-4"></div>
      ${type === 'image/jpeg' ? '<div id="opts" class="hidden mt-4"><label class="field__label">Quality: <b id="qv">90</b>%</label><input id="q" type="range" min="10" max="100" value="90" style="width:100%"></div>' : ''}`;
    let img;
    makeDropzone(q('#drop', root), { accept: 'image/*', onFiles: async f => { img = await loadImageFile(f[0]); if (q('#opts', root)) q('#opts', root).classList.remove('hidden'); render(); } });
    const render = () => { const c = document.createElement('canvas'); c.width = img.naturalWidth; c.height = img.naturalHeight; const ctx = c.getContext('2d'); if (type === 'image/jpeg') { ctx.fillStyle = '#fff'; ctx.fillRect(0, 0, c.width, c.height); } ctx.drawImage(img, 0, 0); if (q('#qv', root)) q('#qv', root).textContent = q('#q', root).value; showImageResult(root, c, 'converted.' + ext, type, q('#q', root) ? +q('#q', root).value / 100 : undefined); };
    if (q('#opts', root)) q('#q', root)?.addEventListener('input', render);
  }
  reg('convert-png', r => convertImage(r, 'image/png', 'png'));
  reg('convert-jpg', r => convertImage(r, 'image/jpeg', 'jpg'));
  reg('convert-webp', r => convertImage(r, 'image/webp', 'webp'));

  reg('image-converter', root => {
    root.innerHTML = `<div id="drop"></div>
      <div id="opts" class="hidden mt-4"><div class="row"><div><label class="field__label">Output format</label><select id="fmt" class="select"><option value="image/png">PNG</option><option value="image/jpeg">JPG</option><option value="image/webp">WebP</option></select></div>
      <div><label class="field__label">Quality: <b id="qv">90</b>%</label><input id="q" type="range" min="10" max="100" value="90" style="width:100%"></div></div>
      <div class="btn-row mt-4"><button class="btn btn--primary" id="go">Convert</button></div></div>
      <div id="result" class="mt-4"></div>`;
    let img;
    makeDropzone(q('#drop', root), { accept: 'image/*', onFiles: async f => { img = await loadImageFile(f[0]); q('#opts', root).classList.remove('hidden'); } });
    q('#q', root).addEventListener('input', () => q('#qv', root).textContent = q('#q', root).value);
    q('#go', root).addEventListener('click', () => { if (!img) return; const type = q('#fmt', root).value; const ext = type.split('/')[1].replace('jpeg', 'jpg'); const c = document.createElement('canvas'); c.width = img.naturalWidth; c.height = img.naturalHeight; const ctx = c.getContext('2d'); if (type === 'image/jpeg') { ctx.fillStyle = '#fff'; ctx.fillRect(0, 0, c.width, c.height); } ctx.drawImage(img, 0, 0); showImageResult(root, c, 'converted.' + ext, type, +q('#q', root).value / 100); });
  });

  reg('image-to-base64', root => {
    root.innerHTML = `<div id="drop"></div><div id="out" class="mt-4"></div>`;
    makeDropzone(q('#drop', root), { accept: 'image/*', onFiles: f => { const r = new FileReader(); r.onload = () => { const uri = r.result; q('#out', root).innerHTML = `<img src="${uri}" style="max-height:160px;border-radius:12px;border:1px solid var(--border)" class="mb-4"><label class="field__label mt-4">Data URI (${fmtBytes(uri.length)})</label><div class="output-box">${esc(uri)}</div><div class="btn-row mt-4"><button class="btn btn--primary" id="cp">Copy Data URI</button><button class="btn btn--ghost" id="cc">Copy as CSS</button></div>`; q('#cp', root).addEventListener('click', () => U.copy(uri)); q('#cc', root).addEventListener('click', () => U.copy('background-image: url("' + uri + '");')); }; r.readAsDataURL(f[0]); } });
  });

  /* ---- Image → GIF (single w/ motion effects, or multi-image animation) ---- */
  function gifDrawContain(ctx, img, W, H) {
    const s = Math.min(W / img.naturalWidth, H / img.naturalHeight);
    const w = img.naturalWidth * s, h = img.naturalHeight * s;
    ctx.drawImage(img, (W - w) / 2, (H - h) / 2, w, h);
  }
  // Persistent 64px thumbnail (loadImageFile revokes the object URL, so we
  // can't reuse img.src in a new <img>).
  function gifThumb(img) {
    const c = document.createElement('canvas'); c.width = c.height = 64;
    const x = c.getContext('2d');
    const s = Math.max(64 / img.naturalWidth, 64 / img.naturalHeight);
    const w = img.naturalWidth * s, h = img.naturalHeight * s;
    x.drawImage(img, (64 - w) / 2, (64 - h) / 2, w, h);
    return c.toDataURL('image/jpeg', 0.75);
  }
  function gifDrawCover(ctx, img, W, H, zoom, panX) {
    const s = Math.max(W / img.naturalWidth, H / img.naturalHeight) * (zoom || 1);
    const w = img.naturalWidth * s, h = img.naturalHeight * s;
    ctx.drawImage(img, (W - w) / 2 + (panX || 0), (H - h) / 2, w, h);
  }
  // Draw one effect frame at progress t (0..1).
  function gifDrawEffect(ctx, img, W, H, effect, t) {
    let zoom = 1, panX = 0, alpha = 1;
    if (effect === 'zoomin') zoom = 1 + 0.28 * t;
    else if (effect === 'zoomout') zoom = 1.28 - 0.28 * t;
    else if (effect === 'pulse') zoom = 1 + 0.09 * (0.5 - 0.5 * Math.cos(t * Math.PI * 2));
    else if (effect === 'fade') { alpha = Math.sin(t * Math.PI); zoom = 1.04; }
    else if (effect === 'pan') zoom = 1.18;
    ctx.globalAlpha = alpha;
    if (effect === 'pan') {
      const s = Math.max(W / img.naturalWidth, H / img.naturalHeight) * zoom;
      const maxPan = Math.max(0, (img.naturalWidth * s - W) / 2);
      panX = maxPan - 2 * maxPan * t;
    }
    gifDrawCover(ctx, img, W, H, zoom, panX);
    ctx.globalAlpha = 1;
  }
  function loadGifenc() {
    return new Promise((res, rej) => {
      if (window.gifenc) return res();
      const s = document.createElement('script');
      s.src = (window.OMNITOOLS_BASE || '') + '/assets/js/vendor/gifenc.js';
      s.onload = () => window.gifenc ? res() : rej(new Error('gifenc missing'));
      s.onerror = () => rej(new Error('load failed'));
      document.head.appendChild(s);
    });
  }
  reg('image-to-gif', root => {
    root.innerHTML = `<div id="drop"></div>
      <div id="opts" class="hidden mt-4">
        <div id="frames" style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin:0 0 4px"></div>
        <div id="fx" class="hidden mt-4">
          <label class="field__label">Animation (from your single image)</label>
          <select id="effect" class="select">
            <option value="zoomin">Zoom in (Ken Burns)</option>
            <option value="zoomout">Zoom out</option>
            <option value="pan">Pan across</option>
            <option value="pulse">Pulse</option>
            <option value="fade">Fade in &amp; out</option>
            <option value="none">None (static image)</option>
          </select>
          <label class="field__label mt-4">Smoothness: <b id="fv">24</b> frames</label>
          <input id="frn" type="range" min="8" max="48" step="2" value="24" style="width:100%">
        </div>
        <div class="row mt-4">
          <div><label class="field__label">Max width: <b id="mwv">480</b>px</label><input id="mw" type="range" min="120" max="1000" step="20" value="480" style="width:100%"></div>
          <div id="delaywrap"><label class="field__label">Frame delay: <b id="dv">500</b>ms</label><input id="delay" type="range" min="40" max="3000" step="20" value="500" style="width:100%"></div>
        </div>
        <label class="chip mt-4" style="display:inline-flex;align-items:center;gap:6px"><input type="checkbox" id="loop" checked> Loop forever</label>
        <div class="btn-row mt-4"><button class="btn btn--primary" id="go">Create GIF</button><button class="btn btn--ghost" id="add">+ Add more images</button></div>
        <div id="prog" class="muted mt-2" aria-live="polite"></div>
      </div>
      <div id="result" class="mt-4"></div>`;
    const imgs = [], thumbs = [];
    makeDropzone(q('#drop', root), { accept: 'image/*', multiple: true, hint: 'One image → animate it with an effect · Several images → a slideshow. Processed locally.', onFiles: addFiles });
    async function addFiles(fl) {
      for (const f of fl) {
        if (!f.type || f.type.indexOf('image/') !== 0) continue;
        try { const im = await loadImageFile(f); imgs.push(im); thumbs.push(gifThumb(im)); } catch (e) { /* skip */ }
      }
      if (!imgs.length) return;
      q('#opts', root).classList.remove('hidden');
      renderFrames();
    }
    function renderFrames() {
      const single = imgs.length === 1;
      q('#fx', root).classList.toggle('hidden', !single);       // effects only for 1 image
      q('#delaywrap', root).style.display = single ? 'none' : ''; // per-frame delay only for slideshow
      q('#frames', root).innerHTML = imgs.map((im, i) =>
        `<div style="position:relative"><img src="${thumbs[i]}" style="width:64px;height:64px;object-fit:cover;border-radius:8px;border:1px solid var(--border)"><button data-rm="${i}" title="Remove" style="position:absolute;top:-6px;right:-6px;width:20px;height:20px;border-radius:50%;background:var(--danger);color:#fff;border:none;cursor:pointer;font-size:11px;line-height:1">✕</button></div>`
      ).join('') + `<span class="muted" style="align-self:center;font-size:13px">${imgs.length} image${single ? '' : 's'}</span>`;
      qa('[data-rm]', root).forEach(b => b.addEventListener('click', () => {
        const idx = +b.dataset.rm; imgs.splice(idx, 1); thumbs.splice(idx, 1);
        if (!imgs.length) q('#opts', root).classList.add('hidden'); else renderFrames();
      }));
    }
    q('#mw', root).addEventListener('input', () => q('#mwv', root).textContent = q('#mw', root).value);
    q('#delay', root).addEventListener('input', () => q('#dv', root).textContent = q('#delay', root).value);
    q('#frn', root).addEventListener('input', () => q('#fv', root).textContent = q('#frn', root).value);
    q('#add', root).addEventListener('click', () => { const i = q('#drop input', root); if (i) i.click(); });
    q('#go', root).addEventListener('click', async () => {
      if (!imgs.length) return;
      const prog = q('#prog', root), btn = q('#go', root);
      btn.disabled = true; prog.textContent = 'Loading encoder…';
      try {
        await loadGifenc();
        const { GIFEncoder, quantize, applyPalette } = window.gifenc;
        const maxW = +q('#mw', root).value, first = imgs[0];
        const W = Math.min(maxW, first.naturalWidth);
        const H = Math.max(1, Math.round(first.naturalHeight * (W / first.naturalWidth)));
        const c = document.createElement('canvas'); c.width = W; c.height = H;
        const ctx = c.getContext('2d');
        const repeat = q('#loop', root).checked ? 0 : -1;

        // Build the frame plan.
        const single = imgs.length === 1;
        const effect = single ? q('#effect', root).value : 'none';
        let plan, frameDelay;
        if (single && effect !== 'none') {
          const N = +q('#frn', root).value;
          plan = []; for (let i = 0; i < N; i++) plan.push({ t: N === 1 ? 0 : i / (N - 1) });
          frameDelay = 66; // ~15fps smooth motion
        } else {
          plan = imgs.map(im => ({ img: im }));
          frameDelay = single ? 1000 : +q('#delay', root).value;
        }

        const gif = GIFEncoder();
        for (let i = 0; i < plan.length; i++) {
          prog.textContent = `Encoding frame ${i + 1} of ${plan.length}…`;
          await new Promise(r => setTimeout(r)); // yield to keep UI responsive
          ctx.fillStyle = '#fff'; ctx.fillRect(0, 0, W, H);
          if (plan[i].img) gifDrawContain(ctx, plan[i].img, W, H);
          else gifDrawEffect(ctx, first, W, H, effect, plan[i].t);
          const data = ctx.getImageData(0, 0, W, H).data;
          const palette = quantize(data, 256);
          const index = applyPalette(data, palette);
          gif.writeFrame(index, W, H, i === 0 ? { palette, delay: frameDelay, repeat } : { palette, delay: frameDelay });
        }
        gif.finish();
        const blob = new Blob([gif.bytes()], { type: 'image/gif' });
        const url = URL.createObjectURL(blob);
        prog.textContent = '';
        const label = (single && effect !== 'none') ? `animated · ${plan.length} frames` : (single ? 'static' : `${plan.length} frames`);
        q('#result', root).innerHTML =
          `<img src="${url}" alt="GIF preview" style="max-height:320px;max-width:100%;border-radius:12px;border:1px solid var(--border)" class="mb-4">
           <div class="muted mb-2">GIF · ${W}×${H} · ${label} · ${fmtBytes(blob.size)}</div>
           <div class="btn-row"><button class="btn btn--primary" id="dl">Download GIF</button></div>`;
        q('#dl', root).addEventListener('click', () => U.download('animation.gif', blob));
      } catch (e) {
        prog.textContent = '';
        U.toast('Could not create the GIF — try a smaller image or fewer frames.');
      } finally {
        btn.disabled = false;
      }
    });
  });

  /* ---- GIF Animation Studio: layered, per-element animation → GIF -------- */
  function easeOutCubic(p) { return 1 - Math.pow(1 - p, 3); }
  function easeOutBack(p) { const c1 = 1.70158, c3 = c1 + 1; return 1 + c3 * Math.pow(p - 1, 3) + c1 * Math.pow(p - 1, 2); }
  // Fonts for the studio: system fonts (instant) + 100+ Google Fonts (lazy-loaded on pick).
  var GIF_SYSTEM = ['Arial', 'Georgia', 'Times New Roman', 'Courier New', 'Verdana', 'Impact', 'Trebuchet MS', 'Comic Sans MS'];
  var GIF_GFONTS = ['Poppins', 'Montserrat', 'Roboto', 'Open Sans', 'Lato', 'Oswald', 'Raleway', 'Inter', 'Nunito', 'Nunito Sans', 'Playfair Display', 'Merriweather', 'Bebas Neue', 'Anton', 'Pacifico', 'Lobster', 'Dancing Script', 'Caveat', 'Righteous', 'Archivo', 'Archivo Black', 'Rubik', 'Work Sans', 'Quicksand', 'Josefin Sans', 'Comfortaa', 'Fredoka', 'Kanit', 'Teko', 'Barlow', 'Manrope', 'DM Sans', 'DM Serif Display', 'Sora', 'Space Grotesk', 'Outfit', 'Plus Jakarta Sans', 'Abril Fatface', 'Alfa Slab One', 'Bungee', 'Titan One', 'Luckiest Guy', 'Permanent Marker', 'Shadows Into Light', 'Satisfy', 'Great Vibes', 'Sacramento', 'Cookie', 'Courgette', 'Amatic SC', 'Indie Flower', 'Patrick Hand', 'Kalam', 'Bangers', 'Passion One', 'Concert One', 'Chewy', 'Cabin', 'PT Sans', 'PT Serif', 'Source Sans 3', 'Source Serif 4', 'Noto Sans', 'Noto Serif', 'Libre Baskerville', 'Crimson Text', 'Lora', 'Cormorant Garamond', 'EB Garamond', 'Bitter', 'Arvo', 'Zilla Slab', 'Domine', 'Vollkorn', 'Karla', 'Mukta', 'Heebo', 'Assistant', 'Varela Round', 'Exo 2', 'Orbitron', 'Audiowide', 'Press Start 2P', 'Monoton', 'Fjalla One', 'Staatliches', 'Russo One', 'Black Ops One', 'Bree Serif', 'Yellowtail', 'Kaushan Script', 'Gloria Hallelujah', 'Rock Salt', 'Special Elite', 'Chakra Petch', 'Rajdhani', 'Saira', 'Signika', 'Cairo', 'Prompt', 'Sarabun', 'Hind', 'Bricolage Grotesque', 'Unbounded', 'Onest', 'Instrument Serif', 'Baloo 2', 'Secular One', 'Jost', 'Lexend', 'Red Hat Display', 'Sen', 'Epilogue', 'Syne', 'Grandstander'];
  var GIF_FONTS = GIF_SYSTEM.concat(GIF_GFONTS.slice().sort());
  var _gfLoaded = {};
  function loadWebFont(family) {
    return new Promise(res => {
      if (!family || GIF_SYSTEM.indexOf(family) >= 0) return res();
      if (!_gfLoaded[family]) {
        _gfLoaded[family] = true;
        const lk = document.createElement('link'); lk.rel = 'stylesheet';
        lk.href = 'https://fonts.googleapis.com/css2?family=' + family.replace(/ /g, '+') + '&display=swap';
        document.head.appendChild(lk);
      }
      let done = false; const fin = () => { if (done) return; done = true; res(); };
      try { Promise.allSettled([document.fonts.load("700 40px '" + family + "'"), document.fonts.load("400 40px '" + family + "'")]).then(fin); } catch (e) { fin(); }
      setTimeout(fin, 2500);
    });
  }

  reg('gif-animation-studio', root => {
    const S = { W: 600, H: 600, bg: '#0d1117', duration: 2600, fps: 15, loop: true, layers: [], sel: null };
    let uid = 1, playing = false, rafId = 0, startT = 0, drag = null, dragId = null, snapV = false, snapH = false;
    const IN = [['none', 'None'], ['fade', 'Fade in'], ['slideL', 'Slide ← left'], ['slideR', 'Slide → right'], ['slideT', 'Slide ↑ top'], ['slideB', 'Slide ↓ bottom'], ['pop', 'Pop'], ['spin', 'Spin in'], ['blur', 'Blur in'], ['typewriter', 'Typewriter (text)']];
    const LP = [['none', 'None'], ['float', 'Float'], ['pulse', 'Pulse'], ['spin', 'Spin'], ['bounce', 'Bounce']];

    root.innerHTML =
      '<div class="row" style="gap:18px;align-items:flex-start;flex-wrap:wrap">' +
        '<div style="flex:1 1 320px;min-width:290px">' +
          '<div class="btn-row mb-2" style="flex-wrap:wrap;gap:6px">' +
            '<span class="muted" style="align-self:center;font-size:13px">Template:</span>' +
            '<button class="btn btn--ghost btn--sm" data-tpl="social">Social intro</button>' +
            '<button class="btn btn--ghost btn--sm" data-tpl="logo">Logo reveal</button>' +
            '<button class="btn btn--ghost btn--sm" data-tpl="title">Title card</button>' +
            '<button class="btn btn--ghost btn--sm" data-tpl="blank">Blank</button>' +
          '</div>' +
          '<div class="btn-row mb-2" style="flex-wrap:wrap;gap:6px">' +
            '<button class="btn btn--ghost btn--sm" data-size="600,600">Square</button>' +
            '<button class="btn btn--ghost btn--sm" data-size="540,960">Story 9:16</button>' +
            '<button class="btn btn--ghost btn--sm" data-size="800,450">Wide 16:9</button>' +
            '<label class="chip" style="display:inline-flex;gap:6px;align-items:center">BG&nbsp;<input type="color" id="bg" value="#0d1117" style="width:32px;height:24px;border:none;background:none;padding:0"></label>' +
          '</div>' +
          '<canvas id="cv" style="width:100%;max-width:520px;display:block;margin:0 auto;background:#000;border-radius:12px;border:1px solid var(--border);touch-action:none;cursor:grab"></canvas>' +
          '<div class="btn-row mt-2" style="justify-content:center;gap:10px;flex-wrap:wrap">' +
            '<button class="btn btn--ghost btn--sm" id="play">▶ Play</button>' +
            '<span class="muted" style="font-size:13px;display:inline-flex;align-items:center;gap:5px">Length <input id="dur" type="number" min="500" max="12000" step="100" value="2600" class="input" style="width:80px;padding:6px"> ms</span>' +
            '<label class="chip" style="display:inline-flex;gap:6px;align-items:center"><input type="checkbox" id="loop" checked> Loop</label>' +
          '</div>' +
        '</div>' +
        '<div style="flex:1 1 300px;min-width:270px">' +
          '<div class="btn-row mb-3"><button class="btn btn--primary btn--sm" id="addImg">+ Image / Icon</button><button class="btn btn--ghost btn--sm" id="addTxt">+ Text</button><input type="file" id="imgFile" accept="image/*" hidden multiple></div>' +
          '<div id="layers" class="mb-2"></div>' +
          '<div id="panel"></div>' +
          '<div class="btn-row mt-4"><button class="btn btn--primary" id="export">🎞 Export GIF</button></div>' +
          '<div id="prog" class="muted mt-2" aria-live="polite"></div>' +
          '<div id="result" class="mt-3"></div>' +
        '</div>' +
      '</div>';

    const cv = q('#cv', root), ctx = cv.getContext('2d');
    function setSize(w, h) { S.W = w; S.H = h; cv.width = w; cv.height = h; drawStatic(); }

    function layerBox(l) {
      if (l.type === 'image') return { w: l.w * l.scale, h: l.h * l.scale };
      ctx.font = '700 ' + l.size + 'px Arial, sans-serif';
      return { w: Math.max(20, ctx.measureText(l.text || '').width) * l.scale, h: l.size * 1.25 * l.scale };
    }
    function state(l, t) {
      const a = l.anim; if (t < a.delay) return null;
      let alpha = 1, dx = 0, dy = 0, sc = l.scale, rot = 0;
      const p = Math.min(1, (t - a.delay) / (a.dur || 1)), e = easeOutCubic(p);
      if (a.in === 'fade') alpha = p;
      else if (a.in === 'slideL') { dx = -(1 - e) * S.W * 0.6; alpha = Math.min(1, p * 1.5); }
      else if (a.in === 'slideR') { dx = (1 - e) * S.W * 0.6; alpha = Math.min(1, p * 1.5); }
      else if (a.in === 'slideT') { dy = -(1 - e) * S.H * 0.6; alpha = Math.min(1, p * 1.5); }
      else if (a.in === 'slideB') { dy = (1 - e) * S.H * 0.6; alpha = Math.min(1, p * 1.5); }
      else if (a.in === 'pop') { sc = l.scale * easeOutBack(p); alpha = Math.min(1, p * 2); }
      else if (a.in === 'spin') { rot = (1 - e) * Math.PI * 1.5; sc = l.scale * e; alpha = Math.min(1, p * 2); }
      else if (a.in === 'blur') { alpha = Math.min(1, p * 1.3); }
      else if (a.in === 'typewriter') { alpha = 1; } // reveal handled at draw time
      if (p >= 1 && a.loop && a.loop !== 'none') {
        const tt = t / S.duration;
        const cyc = Math.max(1, Math.round((a.speed || 1) * (a.loop === 'spin' ? 1 : 2)));
        const ph = tt * Math.PI * 2 * cyc;
        if (a.loop === 'float') dy += Math.sin(ph) * S.H * 0.02;
        else if (a.loop === 'pulse') sc *= 1 + 0.06 * Math.sin(ph);
        else if (a.loop === 'spin') rot += tt * Math.PI * 2 * cyc;
        else if (a.loop === 'bounce') dy -= Math.abs(Math.sin(ph)) * S.H * 0.03;
      }
      return { alpha, dx, dy, sc, rot, p };
    }
    function drawScene(c, t, showSel) {
      c.clearRect(0, 0, S.W, S.H); c.fillStyle = S.bg; c.fillRect(0, 0, S.W, S.H);
      S.layers.forEach(l => {
        const st = state(l, t); if (!st) return;
        c.save(); c.globalAlpha = Math.max(0, Math.min(1, st.alpha * (l.opacity == null ? 1 : l.opacity)));
        if (l.anim.in === 'blur' && st.p < 1) c.filter = 'blur(' + ((1 - st.p) * 18).toFixed(1) + 'px)';
        c.translate(l.x + st.dx, l.y + st.dy); c.rotate(st.rot + (l.rotation || 0)); c.scale(st.sc, st.sc);
        if (l.type === 'image' && l.img) c.drawImage(l.img, -l.w / 2, -l.h / 2, l.w, l.h);
        else if (l.type === 'text') {
          let txt = l.text || '';
          if (l.anim.in === 'typewriter' && st.p < 1) { const n = Math.max(0, Math.ceil(st.p * txt.length)); txt = txt.slice(0, n) + (n < txt.length ? '|' : ''); }
          c.font = "700 " + l.size + "px '" + (l.font || 'Arial') + "', Arial, sans-serif"; c.fillStyle = l.color; c.textAlign = 'center'; c.textBaseline = 'middle'; c.fillText(txt, 0, 0);
        }
        c.restore();
      });
      if (showSel && S.sel) {
        const l = S.layers.find(x => x.id === S.sel);
        if (l) {
          const b = layerBox(l); c.save(); c.globalAlpha = 1;
          c.strokeStyle = '#3b82f6'; c.lineWidth = 2; c.setLineDash([6, 4]);
          c.strokeRect(l.x - b.w / 2, l.y - b.h / 2, b.w, b.h);
          c.setLineDash([]); c.fillStyle = '#3b82f6';
          const hs = Math.max(12, S.W * 0.022);
          c.fillRect(l.x + b.w / 2 - hs / 2, l.y + b.h / 2 - hs / 2, hs, hs); // resize handle (bottom-right)
          c.restore();
        }
      }
      if (showSel && (snapV || snapH)) {
        c.save(); c.strokeStyle = '#ec4899'; c.lineWidth = 1; c.setLineDash([5, 5]);
        if (snapV) { c.beginPath(); c.moveTo(S.W / 2, 0); c.lineTo(S.W / 2, S.H); c.stroke(); }
        if (snapH) { c.beginPath(); c.moveTo(0, S.H / 2); c.lineTo(S.W, S.H / 2); c.stroke(); }
        c.restore();
      }
    }
    function drawStatic() { if (!playing) drawScene(ctx, S.duration, true); }

    /* ---- play / stop ---- */
    function stop() { playing = false; cancelAnimationFrame(rafId); q('#play', root).textContent = '▶ Play'; drawStatic(); }
    function play() {
      if (playing) return stop();
      playing = true; q('#play', root).textContent = '⏸ Pause'; startT = performance.now();
      (function loop() {
        if (!playing) return;
        const el = performance.now() - startT;
        if (!S.loop && el >= S.duration) { drawScene(ctx, S.duration, false); return stop(); }
        drawScene(ctx, el % S.duration, false); rafId = requestAnimationFrame(loop);
      })();
    }

    /* ---- layer + panel UI ---- */
    function moveLayer(id, dir) { // dir +1 = toward front, -1 = toward back
      const i = S.layers.findIndex(x => x.id === id); if (i < 0) return;
      const j = i + dir; if (j < 0 || j >= S.layers.length) return;
      const t = S.layers[i]; S.layers[i] = S.layers[j]; S.layers[j] = t;
      renderLayers(); drawStatic();
    }
    function renderLayers() {
      const ls = q('#layers', root);
      if (!S.layers.length) { ls.innerHTML = '<p class="muted" style="font-size:13px">No layers yet. Add an image/icon or text.</p>'; return; }
      // Front layer (last in array) shown at the top of the list, like a design tool.
      const order = S.layers.slice().reverse();
      ls.innerHTML = '<div class="muted" style="font-size:12px;margin-bottom:6px">Drag to reorder · top = front</div>' +
        order.map(l => {
          const idx = S.layers.indexOf(l);
          const name = l.type === 'text' ? (l.text || 'Text') : ('Image ' + (idx + 1));
          return '<div class="cst-lyr" draggable="true" data-lyr="' + l.id + '" style="display:flex;align-items:center;gap:6px;padding:8px;border:1px solid ' + (l.id === S.sel ? 'var(--accent)' : 'var(--border)') + ';border-radius:10px;margin-bottom:6px;cursor:pointer">' +
            '<span data-grip style="cursor:grab;color:var(--text-3);font-size:15px" title="Drag to reorder">⠿</span>' +
            '<span style="flex:1;font-size:13px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">' + (l.type === 'image' ? '🖼 ' : '🔤 ') + esc(name) + '</span>' +
            '<button class="btn btn--ghost btn--sm" data-fwd="' + l.id + '" title="Bring forward" style="padding:2px 6px">↑</button>' +
            '<button class="btn btn--ghost btn--sm" data-bwd="' + l.id + '" title="Send backward" style="padding:2px 6px">↓</button>' +
            '<button class="btn btn--ghost btn--sm" data-del="' + l.id + '" title="Delete" style="padding:2px 6px;color:var(--danger)">✕</button>' +
          '</div>';
        }).join('');
      qa('[data-lyr]', ls).forEach(el => {
        el.addEventListener('click', e => { if (e.target.closest('[data-del],[data-fwd],[data-bwd]')) return; S.sel = +el.dataset.lyr; renderLayers(); renderPanel(); drawStatic(); });
        el.addEventListener('dragstart', e => { dragId = +el.dataset.lyr; el.style.opacity = '.5'; e.dataTransfer.effectAllowed = 'move'; });
        el.addEventListener('dragend', () => { el.style.opacity = ''; });
        el.addEventListener('dragover', e => e.preventDefault());
        el.addEventListener('drop', e => {
          e.preventDefault();
          const targetId = +el.dataset.lyr;
          if (!dragId || dragId === targetId) return;
          const di = S.layers.findIndex(x => x.id === dragId); if (di < 0) return;
          const [drg] = S.layers.splice(di, 1);
          const ti = S.layers.findIndex(x => x.id === targetId);
          S.layers.splice(ti + 1, 0, drg); // dropped row goes in front of target
          dragId = null; renderLayers(); drawStatic();
        });
      });
      qa('[data-fwd]', ls).forEach(b => b.addEventListener('click', () => moveLayer(+b.dataset.fwd, +1)));
      qa('[data-bwd]', ls).forEach(b => b.addEventListener('click', () => moveLayer(+b.dataset.bwd, -1)));
      qa('[data-del]', ls).forEach(b => b.addEventListener('click', () => { const id = +b.dataset.del; S.layers = S.layers.filter(x => x.id !== id); if (S.sel === id) S.sel = S.layers.length ? S.layers[S.layers.length - 1].id : null; renderLayers(); renderPanel(); drawStatic(); }));
    }
    function dupLayer(id) {
      const l = S.layers.find(x => x.id === id); if (!l) return;
      const c = Object.assign({}, l); c.anim = Object.assign({}, l.anim); c.id = uid++; c.x += 24; c.y += 24;
      S.layers.push(c); S.sel = c.id; refresh();
    }
    function renderPanel() {
      const p = q('#panel', root), l = S.layers.find(x => x.id === S.sel);
      if (!l) { p.innerHTML = ''; return; }
      const opt = (arr, v) => arr.map(o => '<option value="' + o[0] + '"' + (o[0] === v ? ' selected' : '') + '>' + o[1] + '</option>').join('');
      const fontOpts = GIF_FONTS.map(f => '<option value="' + f + '"' + (f === (l.font || 'Arial') ? ' selected' : '') + ' style="font-family:\'' + f + '\'">' + f + '</option>').join('');
      const op = Math.round((l.opacity == null ? 1 : l.opacity) * 100);
      p.innerHTML =
        '<div style="border:1px solid var(--border);border-radius:12px;padding:14px">' +
        '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px"><span class="field__label" style="margin:0">Selected element</span>' +
          '<span><button class="btn btn--ghost btn--sm" data-dup title="Duplicate" style="padding:3px 9px">⧉ Duplicate</button> <button class="btn btn--ghost btn--sm" data-del2 title="Delete" style="padding:3px 9px;color:var(--danger)">Delete</button></span></div>' +
        (l.type === 'text'
          ? '<div class="field"><input class="input" data-p="text" value="' + esc(l.text) + '" placeholder="Text"></div>' +
            '<label class="field__label">Font</label><select class="select" data-p="font" style="font-family:\'' + (l.font || 'Arial') + '\'">' + fontOpts + '</select>' +
            '<div class="row mt-2"><div class="field"><label class="field__label">Size</label><input class="input" type="number" data-p="size" value="' + l.size + '" min="10" max="400"></div>' +
            '<div class="field"><label class="field__label">Color</label><input type="color" data-p="color" value="' + l.color + '" style="width:100%;height:38px;border:1px solid var(--border);border-radius:8px;background:none"></div></div>'
          : '<label class="field__label">Size: <b data-sv>' + Math.round(l.scale * 100) + '</b>%</label><input type="range" data-p="scale" min="10" max="300" value="' + Math.round(l.scale * 100) + '" style="width:100%">') +
        '<label class="field__label mt-4">Opacity: <b data-ov>' + op + '</b>%</label><input type="range" data-p="opacity" min="0" max="100" value="' + op + '" style="width:100%">' +
        '<label class="field__label mt-4">Entrance</label><select class="select" data-p="in">' + opt(IN, l.anim.in) + '</select>' +
        '<label class="field__label mt-4">Continuous motion</label><select class="select" data-p="loop">' + opt(LP, l.anim.loop) + '</select>' +
        '<label class="field__label mt-4">Start delay: <b data-dv>' + l.anim.delay + '</b>ms</label><input type="range" data-p="delay" min="0" max="4000" step="100" value="' + l.anim.delay + '" style="width:100%">' +
        '<label class="field__label mt-4">Motion speed: <b data-spv>' + (l.anim.speed || 1) + '</b>×</label><input type="range" data-p="speed" min="0.5" max="3" step="0.5" value="' + (l.anim.speed || 1) + '" style="width:100%">' +
        '<label class="field__label mt-4">Rotation: <b data-rv>' + Math.round((l.rotation || 0) * 180 / Math.PI) + '</b>°</label><input type="range" data-p="rot" min="-180" max="180" step="1" value="' + Math.round((l.rotation || 0) * 180 / Math.PI) + '" style="width:100%">' +
        '</div>';
      qa('[data-p]', p).forEach(inp => inp.addEventListener('input', () => {
        const k = inp.dataset.p, v = inp.value;
        if (k === 'text') l.text = v;
        else if (k === 'font') { l.font = v; inp.style.fontFamily = "'" + v + "'"; loadWebFont(v).then(drawStatic); }
        else if (k === 'size') l.size = +v || 40;
        else if (k === 'color') l.color = v;
        else if (k === 'scale') { l.scale = (+v) / 100; const sv = q('[data-sv]', p); if (sv) sv.textContent = v; }
        else if (k === 'opacity') { l.opacity = (+v) / 100; const ov = q('[data-ov]', p); if (ov) ov.textContent = v; }
        else if (k === 'in') l.anim.in = v;
        else if (k === 'loop') l.anim.loop = v;
        else if (k === 'delay') { l.anim.delay = +v; const dv = q('[data-dv]', p); if (dv) dv.textContent = v; }
        else if (k === 'speed') { l.anim.speed = +v; const spv = q('[data-spv]', p); if (spv) spv.textContent = v; }
        else if (k === 'rot') { l.rotation = (+v) * Math.PI / 180; const rv = q('[data-rv]', p); if (rv) rv.textContent = v; }
        drawStatic();
      }));
      const dup = q('[data-dup]', p); if (dup) dup.addEventListener('click', () => dupLayer(l.id));
      const del = q('[data-del2]', p); if (del) del.addEventListener('click', () => { S.layers = S.layers.filter(x => x.id !== l.id); S.sel = S.layers.length ? S.layers[S.layers.length - 1].id : null; refresh(); });
    }
    function refresh() { renderLayers(); renderPanel(); drawStatic(); }

    /* ---- add layers ---- */
    function addText() { S.layers.push({ id: uid++, type: 'text', text: 'Your Text', color: '#ffffff', size: 56, font: 'Poppins', opacity: 1, x: S.W / 2, y: S.H / 2, scale: 1, rotation: 0, anim: { in: 'fade', delay: 0, dur: 600, loop: 'none', speed: 1 } }); S.sel = S.layers[S.layers.length - 1].id; refresh(); loadWebFont('Poppins').then(drawStatic); }
    q('#addTxt', root).addEventListener('click', addText);
    q('#addImg', root).addEventListener('click', () => q('#imgFile', root).click());
    q('#imgFile', root).addEventListener('change', async e => {
      let n = 0;
      for (const f of e.target.files) {
        if (!f.type || f.type.indexOf('image/') !== 0) continue;
        try {
          const im = await loadImageFile(f);
          const base = Math.min(S.W, S.H) * 0.35, s = base / Math.max(im.naturalWidth, im.naturalHeight);
          S.layers.push({ id: uid++, type: 'image', img: im, w: im.naturalWidth * s, h: im.naturalHeight * s, opacity: 1, x: S.W / 2 + (n * 30) % 120 - 60, y: S.H / 2 + (n * 24) % 100 - 50, scale: 1, rotation: 0, anim: { in: ['pop', 'fade', 'slideB', 'spin'][n % 4], delay: n * 250, dur: 600, loop: 'float', speed: 1 } });
          S.sel = S.layers[S.layers.length - 1].id; n++;
        } catch (err) { /* skip */ }
      }
      e.target.value = ''; refresh();
    });

    /* ---- drag to position ---- */
    function pt(e) { const r = cv.getBoundingClientRect(); return { x: (e.clientX - r.left) * (S.W / r.width), y: (e.clientY - r.top) * (S.H / r.height) }; }
    cv.addEventListener('pointerdown', e => {
      const m = pt(e);
      // Resize handle of the currently-selected layer takes priority.
      if (S.sel) {
        const l = S.layers.find(x => x.id === S.sel);
        if (l) {
          const b = layerBox(l), hx = l.x + b.w / 2, hy = l.y + b.h / 2, tol = Math.max(16, S.W * 0.035);
          if (Math.hypot(m.x - hx, m.y - hy) <= tol) {
            drag = { l, mode: 'resize', startDist: Math.max(1, Math.hypot(m.x - l.x, m.y - l.y)), startScale: l.scale };
            cv.setPointerCapture(e.pointerId); cv.style.cursor = 'nwse-resize'; return;
          }
        }
      }
      for (let i = S.layers.length - 1; i >= 0; i--) { const l = S.layers[i], b = layerBox(l); if (m.x >= l.x - b.w / 2 && m.x <= l.x + b.w / 2 && m.y >= l.y - b.h / 2 && m.y <= l.y + b.h / 2) { S.sel = l.id; drag = { l, mode: 'move', ox: m.x - l.x, oy: m.y - l.y }; cv.setPointerCapture(e.pointerId); cv.style.cursor = 'grabbing'; renderLayers(); renderPanel(); drawStatic(); return; } }
      S.sel = null; renderLayers(); renderPanel(); drawStatic();
    });
    cv.addEventListener('pointermove', e => {
      if (!drag) return; const m = pt(e);
      if (drag.mode === 'resize') {
        const d = Math.max(1, Math.hypot(m.x - drag.l.x, m.y - drag.l.y));
        drag.l.scale = Math.max(0.1, Math.min(6, drag.startScale * (d / drag.startDist)));
      } else {
        let nx = Math.round(m.x - drag.ox), ny = Math.round(m.y - drag.oy);
        const th = Math.max(6, S.W * 0.012);
        snapV = Math.abs(nx - S.W / 2) < th; if (snapV) nx = Math.round(S.W / 2);
        snapH = Math.abs(ny - S.H / 2) < th; if (snapH) ny = Math.round(S.H / 2);
        drag.l.x = nx; drag.l.y = ny;
      }
      drawStatic();
    });
    cv.addEventListener('pointerup', () => { if (drag && drag.mode === 'resize') renderPanel(); drag = null; snapV = false; snapH = false; cv.style.cursor = 'grab'; drawStatic(); });

    /* ---- controls ---- */
    qa('[data-size]', root).forEach(b => b.addEventListener('click', () => { const [w, h] = b.dataset.size.split(',').map(Number); setSize(w, h); S.layers.forEach(l => { l.x = Math.min(l.x, w); l.y = Math.min(l.y, h); }); drawStatic(); }));
    q('#bg', root).addEventListener('input', () => { S.bg = q('#bg', root).value; drawStatic(); });
    q('#dur', root).addEventListener('input', () => { S.duration = Math.max(500, +q('#dur', root).value || 2600); });
    q('#loop', root).addEventListener('change', () => { S.loop = q('#loop', root).checked; });
    q('#play', root).addEventListener('click', play);

    /* ---- export ---- */
    q('#export', root).addEventListener('click', async () => {
      if (!S.layers.length) { U.toast('Add an image or text first.'); return; }
      stop();
      const prog = q('#prog', root), btn = q('#export', root);
      btn.disabled = true; prog.textContent = 'Loading encoder…';
      try {
        await loadGifenc();
        // Ensure every text font is loaded before rasterising frames.
        await Promise.all(S.layers.filter(l => l.type === 'text' && l.font).map(l => loadWebFont(l.font)));
        try { if (document.fonts && document.fonts.ready) await document.fonts.ready; } catch (e) {}
        const { GIFEncoder, quantize, applyPalette } = window.gifenc;
        const frames = Math.max(2, Math.min(120, Math.round(S.duration / 1000 * S.fps)));
        const delay = Math.round(1000 / S.fps), repeat = S.loop ? 0 : -1;
        const oc = document.createElement('canvas'); oc.width = S.W; oc.height = S.H; const octx = oc.getContext('2d');
        const gif = GIFEncoder();
        for (let f = 0; f < frames; f++) {
          prog.textContent = 'Rendering frame ' + (f + 1) + ' of ' + frames + '…';
          await new Promise(r => setTimeout(r));
          drawScene(octx, f / frames * S.duration, false);
          const data = octx.getImageData(0, 0, S.W, S.H).data;
          const palette = quantize(data, 256);
          const index = applyPalette(data, palette);
          gif.writeFrame(index, S.W, S.H, f === 0 ? { palette, delay, repeat } : { palette, delay });
        }
        gif.finish();
        const blob = new Blob([gif.bytes()], { type: 'image/gif' });
        const url = URL.createObjectURL(blob);
        prog.textContent = '';
        q('#result', root).innerHTML =
          '<img src="' + url + '" alt="GIF" style="max-width:100%;max-height:320px;border-radius:12px;border:1px solid var(--border)" class="mb-2"><div class="muted mb-2">GIF · ' + S.W + '×' + S.H + ' · ' + frames + ' frames · ' + fmtBytes(blob.size) + '</div><div class="btn-row"><button class="btn btn--primary" id="dl">Download GIF</button></div>';
        q('#dl', root).addEventListener('click', () => U.download('animation.gif', blob));
      } catch (err) { prog.textContent = ''; U.toast('Could not export — try a shorter animation or smaller canvas.'); }
      finally { btn.disabled = false; }
    });

    /* ---- preset templates ---- */
    function loadPreset(name) {
      stop();
      const T = (text, color, size, x, y, anim) => ({ id: uid++, type: 'text', text, color, size, font: 'Poppins', opacity: 1, x, y, scale: 1, rotation: 0, anim });
      if (name === 'blank') { S.bg = '#0d1117'; S.duration = 2600; S.layers = []; S.sel = null; setSize(600, 600); }
      else if (name === 'social') {
        S.bg = '#111827'; S.duration = 3000; setSize(540, 960);
        S.layers = [
          T('Follow Me', '#ffffff', 72, 270, 360, { in: 'pop', delay: 200, dur: 700, loop: 'float', speed: 1 }),
          T('@yourhandle', '#38bdf8', 42, 270, 470, { in: 'slideB', delay: 800, dur: 600, loop: 'none', speed: 1 }),
        ];
      } else if (name === 'logo') {
        S.bg = '#0d1117'; S.duration = 2600; setSize(600, 600);
        S.layers = [
          T('LOGO', '#ffffff', 100, 300, 275, { in: 'spin', delay: 200, dur: 800, loop: 'pulse', speed: 1 }),
          T('Your tagline here', '#9ca3af', 34, 300, 380, { in: 'fade', delay: 1000, dur: 600, loop: 'none', speed: 1 }),
        ];
      } else if (name === 'title') {
        S.bg = '#0b0b12'; S.duration = 3400; setSize(800, 450);
        S.layers = [
          T('Your Title', '#ffffff', 74, 400, 195, { in: 'typewriter', delay: 200, dur: 1500, loop: 'none', speed: 1 }),
          T('a short subtitle', '#38bdf8', 34, 400, 285, { in: 'fade', delay: 1800, dur: 700, loop: 'none', speed: 1 }),
        ];
      }
      S.sel = S.layers.length ? S.layers[0].id : null;
      q('#dur', root).value = S.duration; q('#bg', root).value = S.bg;
      refresh(); loadWebFont('Poppins').then(drawStatic);
    }
    qa('[data-tpl]', root).forEach(b => b.addEventListener('click', () => loadPreset(b.dataset.tpl)));

    /* ---- seed a starter scene ---- */
    setSize(600, 600);
    S.layers.push({ id: uid++, type: 'text', text: 'Your Title', color: '#ffffff', size: 66, font: 'Poppins', opacity: 1, x: 300, y: 300, scale: 1, rotation: 0, anim: { in: 'pop', delay: 200, dur: 700, loop: 'float', speed: 1 } });
    S.sel = S.layers[0].id;
    refresh(); loadWebFont('Poppins').then(drawStatic);
  });

  // PDF-category engines (merge, split, jpg-to-pdf, watermark, …) live in
  // assets/js/pdf-tools.js, which uses pdf-lib + pdf.js and is loaded only on
  // PDF tool pages.

  /* =====================================================================
     VIDEO TOOLS
     ================================================================== */
  reg('video-metadata', root => {
    root.innerHTML = `<div id="drop"></div><div id="out" class="mt-4"></div>`;
    makeDropzone(q('#drop', root), { accept: 'video/*', onFiles: f => { const v = document.createElement('video'); v.preload = 'metadata'; v.onloadedmetadata = () => { q('#out', root).innerHTML = grid(stat('Duration', new Date(v.duration * 1000).toISOString().substr(11, 8)) + stat('Resolution', v.videoWidth + '×' + v.videoHeight) + stat('Aspect ratio', (v.videoWidth / v.videoHeight).toFixed(2)) + stat('File size', fmtBytes(f[0].size)) + stat('Type', f[0].type || '—')); }; v.src = URL.createObjectURL(f[0]); } });
  });

  reg('video-thumbnail', root => {
    root.innerHTML = `<div id="drop"></div>
      <div id="opts" class="hidden mt-4"><video id="v" controls style="width:100%;max-height:280px;border-radius:12px"></video>
      <div class="btn-row mt-4"><button class="btn btn--primary" id="grab">Capture Current Frame</button></div></div>
      <div id="out" class="mt-4"></div>`;
    makeDropzone(q('#drop', root), { accept: 'video/*', onFiles: f => { const v = q('#v', root); v.src = URL.createObjectURL(f[0]); q('#opts', root).classList.remove('hidden'); } });
    q('#grab', root).addEventListener('click', () => { const v = q('#v', root); const c = document.createElement('canvas'); c.width = v.videoWidth; c.height = v.videoHeight; c.getContext('2d').drawImage(v, 0, 0); c.toBlob(b => { const url = URL.createObjectURL(b); q('#out', root).innerHTML = `<img src="${url}" style="max-height:220px;border-radius:12px;border:1px solid var(--border)"><div class="btn-row mt-4"><button class="btn btn--primary" id="dl">Download Frame</button></div>`; q('#dl', root).addEventListener('click', () => U.download('frame.png', b)); }); });
  });

  /* =====================================================================
     AUDIO TOOLS (Web Audio API → WAV)
     ================================================================== */
  async function decodeAudio(file) {
    const AC = window.AudioContext || window.webkitAudioContext;
    const ctx = new AC();
    const buf = await ctx.decodeAudioData(await file.arrayBuffer());
    return { ctx, buf };
  }
  reg('audio-metadata', root => {
    root.innerHTML = `<div id="drop"></div><div id="out" class="mt-4"></div>`;
    makeDropzone(q('#drop', root), { accept: 'audio/*', onFiles: async f => { try { const { buf } = await decodeAudio(f[0]); q('#out', root).innerHTML = grid(stat('Duration', buf.duration.toFixed(2) + 's') + stat('Channels', buf.numberOfChannels) + stat('Sample rate', buf.sampleRate + ' Hz') + stat('File size', fmtBytes(f[0].size))); } catch (e) { q('#out', root).innerHTML = `<div class="notice notice--error">Could not decode: ${esc(e.message)}</div>`; } } });
  });

  reg('audio-converter', root => {
    root.innerHTML = `<div id="drop"></div><div id="out" class="mt-4"></div>`;
    makeDropzone(q('#drop', root), { accept: 'audio/*', onFiles: async f => { q('#out', root).innerHTML = '<div class="row"><div class="spinner"></div><span>Decoding…</span></div>'; try { const { buf } = await decodeAudio(f[0]); const wav = audioBufferToWav(buf); q('#out', root).innerHTML = `<audio controls src="${URL.createObjectURL(wav)}" style="width:100%"></audio><div class="btn-row mt-4"><button class="btn btn--primary" id="dl">Download WAV</button></div>`; q('#dl', root).addEventListener('click', () => U.download('audio.wav', wav)); } catch (e) { q('#out', root).innerHTML = `<div class="notice notice--error">${esc(e.message)}</div>`; } } });
  });

  function audioGain(root, mode) {
    root.innerHTML = `<div id="drop"></div>
      <div id="opts" class="hidden mt-4">${mode === 'boost' ? '<label class="field__label">Gain: <b id="gv">1.5</b>×</label><input id="g" type="range" min="1" max="5" step="0.1" value="1.5" style="width:100%">' : '<div class="notice notice--info">Audio will be normalised so its peak reaches 0 dB (maximum without clipping).</div>'}
      <div class="btn-row mt-4"><button class="btn btn--primary" id="go">Process</button></div></div>
      <div id="out" class="mt-4"></div>`;
    let file;
    makeDropzone(q('#drop', root), { accept: 'audio/*', onFiles: f => { file = f[0]; q('#opts', root).classList.remove('hidden'); } });
    if (q('#g', root)) q('#g', root).addEventListener('input', () => q('#gv', root).textContent = q('#g', root).value);
    q('#go', root).addEventListener('click', async () => {
      if (!file) return;
      q('#out', root).innerHTML = '<div class="row"><div class="spinner"></div><span>Processing…</span></div>';
      try {
        const { buf } = await decodeAudio(file);
        let gain = mode === 'boost' ? +q('#g', root).value : 1;
        if (mode === 'norm') { let peak = 0; for (let ch = 0; ch < buf.numberOfChannels; ch++) { const d = buf.getChannelData(ch); for (let i = 0; i < d.length; i++) peak = Math.max(peak, Math.abs(d[i])); } gain = peak > 0 ? 1 / peak : 1; }
        const AC = window.OfflineAudioContext || window.webkitOfflineAudioContext;
        const off = new AC(buf.numberOfChannels, buf.length, buf.sampleRate);
        const src = off.createBufferSource(); src.buffer = buf;
        const g = off.createGain(); g.gain.value = gain;
        src.connect(g); g.connect(off.destination); src.start();
        const rendered = await off.startRendering();
        const wav = audioBufferToWav(rendered);
        q('#out', root).innerHTML = `<div class="notice notice--success">Applied gain ×${gain.toFixed(2)}.</div><audio controls src="${URL.createObjectURL(wav)}" style="width:100%" class="mt-4"></audio><div class="btn-row mt-4"><button class="btn btn--primary" id="dl">Download WAV</button></div>`;
        q('#dl', root).addEventListener('click', () => U.download(mode === 'boost' ? 'boosted.wav' : 'normalized.wav', wav));
      } catch (e) { q('#out', root).innerHTML = `<div class="notice notice--error">${esc(e.message)}</div>`; }
    });
  }
  reg('volume-booster', r => audioGain(r, 'boost'));
  reg('normalize-audio', r => audioGain(r, 'norm'));

  reg('mp3-cutter', root => {
    root.innerHTML = `<div id="drop"></div>
      <div id="opts" class="hidden mt-4"><audio id="au" controls style="width:100%"></audio>
      <div class="row mt-4"><div><label class="field__label">Start (seconds)</label><input id="s" class="input" type="number" value="0" step="0.1" min="0"></div>
      <div><label class="field__label">End (seconds)</label><input id="e" class="input" type="number" step="0.1"></div></div>
      <div class="btn-row mt-4"><button class="btn btn--primary" id="go">Cut &amp; Export WAV</button></div></div>
      <div id="out" class="mt-4"></div>`;
    let file, dur = 0;
    makeDropzone(q('#drop', root), { accept: 'audio/*', onFiles: f => { file = f[0]; const au = q('#au', root); au.src = URL.createObjectURL(f[0]); au.onloadedmetadata = () => { dur = au.duration; q('#e', root).value = dur.toFixed(1); }; q('#opts', root).classList.remove('hidden'); } });
    q('#go', root).addEventListener('click', async () => {
      if (!file) return;
      const start = Math.max(0, +q('#s', root).value), end = Math.min(dur || 1e9, +q('#e', root).value);
      if (end <= start) { q('#out', root).innerHTML = '<div class="notice notice--error">End must be after start.</div>'; return; }
      q('#out', root).innerHTML = '<div class="row"><div class="spinner"></div><span>Cutting…</span></div>';
      try {
        const { buf } = await decodeAudio(file);
        const sr = buf.sampleRate, s0 = Math.floor(start * sr), s1 = Math.floor(end * sr), len = s1 - s0;
        const AC = window.OfflineAudioContext || window.webkitOfflineAudioContext;
        const out = new AC(buf.numberOfChannels, len, sr);
        const newBuf = out.createBuffer(buf.numberOfChannels, len, sr);
        for (let ch = 0; ch < buf.numberOfChannels; ch++) newBuf.copyToChannel(buf.getChannelData(ch).slice(s0, s1), ch);
        const wav = audioBufferToWav(newBuf);
        q('#out', root).innerHTML = `<div class="notice notice--success">Cut ${(end - start).toFixed(1)}s clip.</div><audio controls src="${URL.createObjectURL(wav)}" style="width:100%" class="mt-4"></audio><div class="btn-row mt-4"><button class="btn btn--primary" id="dl">Download WAV</button></div>`;
        q('#dl', root).addEventListener('click', () => U.download('clip.wav', wav));
      } catch (e) { q('#out', root).innerHTML = `<div class="notice notice--error">${esc(e.message)}</div>`; }
    });
  });

  // (All PDF-category engines now live in assets/js/pdf-tools.js.)

  /* =====================================================================
     BOOTSTRAP
     ================================================================== */
  function boot() {
    const root = document.querySelector('[data-tool]');
    if (!root) return;
    const slug = root.getAttribute('data-tool');
    const fn = engines[slug];
    if (fn) {
      try { fn(root); root.classList.add('fade-in'); }
      catch (e) { root.innerHTML = `<div class="notice notice--error">This tool hit an error: ${esc(e.message)}</div>`; }
    } else {
      root.innerHTML = `<div class="notice notice--info">This tool is being finalised and will be available shortly.</div>`;
    }
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
  else boot();
})();
