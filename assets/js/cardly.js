/* =========================================================================
   Cardly — builder (create + edit) with live preview.
   No accounts: create mints an edit token; edits use it.
   ========================================================================= */
(function () {
  'use strict';
  const root = document.getElementById('cardlyRoot');
  if (!root) return;
  const BOOT = JSON.parse(root.dataset.boot);
  const BASE = BOOT.base || window.OMNITOOLS_BASE || '';
  // Canonical Cardly base (its own domain when configured), else /cardly path.
  const CB = BOOT.cardlyBase || (BASE + '/cardly');
  const U = window.OmniUtil || { toast(m) { alert(m); }, copy(t) { navigator.clipboard && navigator.clipboard.writeText(t); } };
  const esc = s => String(s == null ? '' : s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const TPL = BOOT.templates;

  /* ---------------------------------------------------------- API helpers */
  async function post(params) {
    const body = new URLSearchParams(); body.set('csrf_token', BOOT.csrf);
    Object.entries(params).forEach(([k, v]) => body.set(k, v));
    const r = await fetch(BASE + '/api/cardly.php', { method: 'POST', body });
    return r.json();
  }
  async function uploadImage(file, kind) {
    const fd = new FormData();
    fd.set('action', 'upload'); fd.set('csrf_token', BOOT.csrf);
    fd.set('slug', BOOT.slug); fd.set('token', BOOT.token); fd.set('kind', kind);
    fd.set('file', file);
    const r = await fetch(BASE + '/api/cardly.php', { method: 'POST', body: fd });
    return r.json();
  }

  if (BOOT.mode === 'new') renderClaim(); else renderBuilder();

  /* =======================================================================
     NEW: claim a username
     ==================================================================== */
  function renderClaim() {
    const tplChips = Object.entries(TPL).filter(([k]) => k !== 'default')
      .map(([k, t]) => `<button type="button" class="cardly-chip" data-tpl="${k}" style="--c1:${t.accent[0]};--c2:${t.accent[1]}"><span class="cardly-chip__dot"></span>${esc(t.name)}</button>`).join('');
    root.innerHTML = `
      <div class="cardly-claim">
        <h1>Create your free card</h1>
        <p class="muted">Pick a link and a style, you can change everything next.</p>
        <label class="field__label mt-6">Your name</label>
        <input id="cName" class="input" placeholder="e.g. Shushant Singh" maxlength="80" autofocus>
        <label class="field__label mt-4">Your link</label>
        <div class="cardly-claim__url">
          <span>${esc(CB.replace(/^https?:\/\//, ''))}/</span>
          <input id="cUser" class="input" placeholder="yourname" autocomplete="off" spellcheck="false" maxlength="24">
          <span id="cSuffix" class="cardly-claim__suffix">-••••</span>
        </div>
        <div id="cUserMsg" class="cardly-claim__msg">A short code is added so your link stays unique.</div>
        <label class="field__label mt-4">Choose a style</label>
        <div class="cardly-chips" id="cTpls">${tplChips}</div>
        <button class="btn btn--primary btn--block mt-6" id="cCreate">Create my card →</button>
        <div id="cCreateMsg" class="mt-4"></div>
      </div>`;
    let template = BOOT.preTemplate && TPL[BOOT.preTemplate] ? BOOT.preTemplate : 'default';
    const chips = root.querySelectorAll('[data-tpl]');
    const selectChip = k => { template = k; chips.forEach(c => c.classList.toggle('is-active', c.dataset.tpl === k)); };
    chips.forEach(c => c.addEventListener('click', () => selectChip(c.dataset.tpl)));
    if (TPL[template]) selectChip(template); else selectChip('creator');

    const user = root.querySelector('#cUser');
    const name = root.querySelector('#cName');
    // Default the link to a slug of the name until the user types their own.
    let linkTouched = false;
    const slugify = v => v.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 24);
    user.addEventListener('input', () => { user.value = slugify(user.value); linkTouched = user.value !== ''; });
    name.addEventListener('input', () => { if (!linkTouched) user.value = slugify(name.value); });

    root.querySelector('#cCreate').addEventListener('click', async () => {
      const nameV = name.value.trim(), username = user.value.trim();
      const msg = root.querySelector('#cCreateMsg');
      msg.innerHTML = '<div class="row"><div class="spinner"></div><span>Creating…</span></div>';
      const r = await post({ action: 'create', username, name: nameV, template });
      if (!r.ok) { msg.innerHTML = err(r.error || 'Could not create card.'); return; }
      window.location.href = r.editUrl;
    });
  }

  /* =======================================================================
     EDIT: full builder
     ==================================================================== */
  function renderBuilder() {
    const state = normalize(BOOT.card);
    const viewUrl = CB + '/' + BOOT.slug;
    const editUrl = viewUrl + '/edit?k=' + BOOT.token;

    root.innerHTML = `
      <div class="cardly-topbar">
        <div>
          <strong>Your card is live:</strong>
          <a href="${esc(viewUrl)}" target="_blank" rel="noopener">${esc(viewUrl.replace(/^https?:\/\//, ''))}</a>
        </div>
        <div class="cardly-topbar__actions">
          <button class="btn btn--ghost btn--sm" id="cCopyEdit">Copy edit link</button>
          <a class="btn btn--ghost btn--sm" href="${esc(viewUrl)}" target="_blank">View</a>
          <button class="btn btn--primary btn--sm" id="cSave">Save</button>
        </div>
      </div>
      <div class="notice notice--info" id="cEditNote">🔑 Bookmark your private edit link, it's the only way back to edit this card. <button class="btn btn--ghost btn--sm" id="cCopyEdit2">Copy it</button></div>
      <div class="cardly-editor">
        <div class="cardly-form" id="cForm"></div>
        <div class="cardly-previewcol">
          <div class="cardly-phone"><div class="cardly-phone__screen" id="cPreview"></div></div>
        </div>
      </div>
      <div id="cSaveMsg"></div>`;

    root.querySelector('#cForm').innerHTML = formHtml(state);
    bind(state);
    renderPreview(state);

    const copyEdit = () => U.copy(editUrl);
    root.querySelector('#cCopyEdit').addEventListener('click', copyEdit);
    root.querySelector('#cCopyEdit2').addEventListener('click', copyEdit);

    root.querySelector('#cSave').addEventListener('click', async () => {
      const msg = root.querySelector('#cSaveMsg');
      msg.innerHTML = '<div class="row"><div class="spinner"></div><span>Saving…</span></div>';
      const r = await post({ action: 'save', slug: BOOT.slug, token: BOOT.token, card: JSON.stringify(state) });
      msg.innerHTML = r.ok ? ok('Saved! Your card is updated.') : err(r.error || 'Save failed.');
      if (r.ok) U.toast('Card saved');
    });
  }

  /* --------------------------------------------------------- form markup */
  function formHtml(s) {
    const tplChips = Object.entries(TPL).map(([k, t]) =>
      `<button type="button" class="cardly-chip ${s.template === k ? 'is-active' : ''}" data-tpl="${k}" style="--c1:${t.accent[0]};--c2:${t.accent[1]}"><span class="cardly-chip__dot"></span>${esc(t.name)}</button>`).join('');
    const social = (k, label) => `<div class="field"><label class="field__label">${label}</label><input class="input" data-bind="socials.${k}" value="${esc(s.socials[k] || '')}" placeholder="https://…"></div>`;
    const toggle = (k, label) => `<label class="cardly-toggle"><input type="checkbox" data-sec="${k}" ${s.sections[k] ? 'checked' : ''}> ${label}</label>`;
    return `
      <div class="cardly-grp"><h3>Style</h3><div class="cardly-chips" id="cTpls">${tplChips}</div></div>

      <div class="cardly-grp"><h3>Images</h3>
        <div class="row">
          <div><label class="field__label">Profile photo</label><div class="cardly-imgrow"><div class="cardly-upload" data-up="photo"><img class="cardly-upimg ${s.photo ? '' : 'hidden'}" data-img="photo" src="${esc(s.photo)}"><span class="cardly-upbtn">${s.photo ? 'Change' : 'Upload'}</span></div><button type="button" class="cardly-imgrm ${s.photo ? '' : 'hidden'}" data-rmimg="photo" title="Remove">✕</button></div></div>
          <div><label class="field__label">Cover image</label><div class="cardly-imgrow"><div class="cardly-upload" data-up="cover"><img class="cardly-upimg ${s.cover ? '' : 'hidden'}" data-img="cover" src="${esc(s.cover)}"><span class="cardly-upbtn">${s.cover ? 'Change' : 'Upload'}</span></div><button type="button" class="cardly-imgrm ${s.cover ? '' : 'hidden'}" data-rmimg="cover" title="Remove">✕</button></div></div>
        </div>
      </div>

      <div class="cardly-grp"><h3>Basics</h3>
        <div class="field"><label class="field__label">Name</label><input class="input" data-bind="name" value="${esc(s.name)}" maxlength="80"></div>
        <div class="field"><label class="field__label">Tagline / role</label><input class="input" data-bind="tagline" value="${esc(s.tagline)}" placeholder="e.g. Product Architect" maxlength="120"></div>
        <div class="field"><label class="field__label">About</label><textarea class="textarea" data-bind="about" maxlength="1200" style="min-height:90px">${esc(s.about)}</textarea></div>
      </div>

      <div class="cardly-grp"><h3>Contact</h3>
        <div class="row">
          <div class="field"><label class="field__label">Phone</label><input class="input" data-bind="contact.phone" value="${esc(s.contact.phone)}"></div>
          <div class="field"><label class="field__label">WhatsApp number</label><input class="input" data-bind="contact.whatsapp" value="${esc(s.contact.whatsapp)}"></div>
        </div>
        <div class="field"><label class="field__label">Email</label><input class="input" type="email" data-bind="contact.email" value="${esc(s.contact.email)}"></div>
        <div class="field"><label class="field__label">Website</label><input class="input" data-bind="contact.website" value="${esc(s.contact.website)}" placeholder="https://…"></div>
        <div class="field"><label class="field__label">Address</label><input class="input" data-bind="contact.address" value="${esc(s.contact.address)}"></div>
      </div>

      <div class="cardly-grp"><h3>Social links</h3>
        ${social('instagram', 'Instagram')}${social('linkedin', 'LinkedIn')}${social('x', 'X (Twitter)')}
        ${social('facebook', 'Facebook')}${social('github', 'GitHub')}${social('youtube', 'YouTube')}${social('spotify', 'Spotify')}
      </div>

      <div class="cardly-grp"><h3>Skills</h3>
        <div class="field"><input class="input" id="cSkillInput" placeholder="Type a skill and press Enter"></div>
        <div class="chips" id="cSkills"></div>
      </div>

      <div class="cardly-grp"><h3>Custom links / buttons</h3>
        <div id="cLinks"></div>
        <button type="button" class="btn btn--ghost btn--sm mt-2" id="cAddLink">+ Add link</button>
      </div>

      <div class="cardly-grp"><h3>Gallery</h3>
        <div class="cardly-gallery-edit" id="cGallery"></div>
        <div class="cardly-upload cardly-upload--wide mt-2" data-up="gallery"><span class="cardly-upbtn">+ Add image</span></div>
      </div>

      <div class="cardly-grp"><h3>Sections to show</h3>
        <div class="cardly-toggles">
          ${toggle('about', 'About')}${toggle('contact', 'Contact')}${toggle('socials', 'Socials')}
          ${toggle('skills', 'Skills')}${toggle('links', 'Links')}${toggle('gallery', 'Gallery')}
          ${toggle('map', 'Map')}${toggle('qr', 'QR code')}
        </div>
      </div>

      <div class="cardly-grp"><h3>Privacy</h3>
        <label class="cardly-toggle"><input type="checkbox" data-pref="discoverable" ${s.discoverable !== false ? 'checked' : ''}> List my card in search (Google &amp; AI)</label>
        <p class="muted" style="font-size:13px;margin-top:8px">On: your card can appear in search results as your public profile. Off: it's only reachable by the exact link you share (unlisted).</p>
      </div>`;
  }

  /* ------------------------------------------------------------- bindings */
  function bind(s) {
    const form = root.querySelector('#cForm');
    // text inputs
    form.querySelectorAll('[data-bind]').forEach(inp => {
      inp.addEventListener('input', () => { setPath(s, inp.dataset.bind, inp.value); renderPreview(s); });
    });
    // template chips
    form.querySelectorAll('[data-tpl]').forEach(c => c.addEventListener('click', () => {
      s.template = c.dataset.tpl;
      form.querySelectorAll('[data-tpl]').forEach(x => x.classList.toggle('is-active', x === c));
      renderPreview(s);
    }));
    // section toggles
    form.querySelectorAll('[data-sec]').forEach(t => t.addEventListener('change', () => { s.sections[t.dataset.sec] = t.checked; renderPreview(s); }));
    // preference toggles (e.g. discoverable in search)
    form.querySelectorAll('[data-pref]').forEach(t => t.addEventListener('change', () => { s[t.dataset.pref] = t.checked; }));
    // uploads
    form.querySelectorAll('[data-up]').forEach(u => u.addEventListener('click', () => pickImage(u.dataset.up, s)));
    // remove photo / cover
    form.querySelectorAll('[data-rmimg]').forEach(btn => btn.addEventListener('click', () => {
      const kind = btn.dataset.rmimg; s[kind] = '';
      const img = root.querySelector(`[data-img="${kind}"]`); if (img) { img.src = ''; img.classList.add('hidden'); }
      const up = root.querySelector(`[data-up="${kind}"] .cardly-upbtn`); if (up) up.textContent = 'Upload';
      btn.classList.add('hidden');
      renderPreview(s);
    }));
    // skills
    renderSkills(s);
    const si = form.querySelector('#cSkillInput');
    si.addEventListener('keydown', e => {
      if (e.key === 'Enter' || e.key === ',') {
        e.preventDefault(); const v = si.value.trim().replace(/,$/, '');
        if (v && !s.skills.includes(v) && s.skills.length < 30) { s.skills.push(v); si.value = ''; renderSkills(s); renderPreview(s); }
      }
    });
    // links
    renderLinks(s);
    form.querySelector('#cAddLink').addEventListener('click', () => { s.links.push({ label: '', url: '' }); renderLinks(s); });
    // gallery
    renderGallery(s);
  }

  function renderSkills(s) {
    const wrap = root.querySelector('#cSkills');
    wrap.innerHTML = s.skills.map((sk, i) => `<span class="chip">${esc(sk)} <button type="button" data-rm="${i}" style="border:none;background:none;cursor:pointer">✕</button></span>`).join('');
    wrap.querySelectorAll('[data-rm]').forEach(b => b.addEventListener('click', () => { s.skills.splice(+b.dataset.rm, 1); renderSkills(s); renderPreview(s); }));
  }
  function renderLinks(s) {
    const wrap = root.querySelector('#cLinks');
    wrap.innerHTML = s.links.map((l, i) => `<div class="row cardly-linkrow" style="margin-bottom:8px">
      <input class="input" placeholder="Label" data-lk="label" data-i="${i}" value="${esc(l.label)}">
      <input class="input" placeholder="https://…" data-lk="url" data-i="${i}" value="${esc(l.url)}">
      <button type="button" class="btn btn--ghost btn--sm" data-lkrm="${i}" style="flex:none">✕</button></div>`).join('');
    wrap.querySelectorAll('[data-lk]').forEach(inp => inp.addEventListener('input', () => { s.links[+inp.dataset.i][inp.dataset.lk] = inp.value; renderPreview(s); }));
    wrap.querySelectorAll('[data-lkrm]').forEach(b => b.addEventListener('click', () => { s.links.splice(+b.dataset.lkrm, 1); renderLinks(s); renderPreview(s); }));
  }
  function renderGallery(s) {
    const wrap = root.querySelector('#cGallery');
    wrap.innerHTML = s.gallery.map((g, i) => `<div class="cardly-gthumb"><img src="${esc(g)}"><button type="button" data-grm="${i}">✕</button></div>`).join('');
    wrap.querySelectorAll('[data-grm]').forEach(b => b.addEventListener('click', () => { s.gallery.splice(+b.dataset.grm, 1); renderGallery(s); renderPreview(s); }));
  }

  function pickImage(kind, s) {
    const inp = document.createElement('input'); inp.type = 'file'; inp.accept = 'image/*';
    inp.onchange = async () => {
      if (!inp.files[0]) return;
      U.toast('Uploading…');
      const r = await uploadImage(inp.files[0], kind);
      if (!r.ok) { U.toast(r.error || 'Upload failed'); return; }
      if (kind === 'gallery') { if (s.gallery.length < 24) s.gallery.push(r.url); renderGallery(s); }
      else {
        s[kind] = r.url;
        const img = root.querySelector(`[data-img="${kind}"]`); if (img) { img.src = r.url; img.classList.remove('hidden'); }
        const up = root.querySelector(`[data-up="${kind}"] .cardly-upbtn`); if (up) up.textContent = 'Change';
        const rm = root.querySelector(`[data-rmimg="${kind}"]`); if (rm) rm.classList.remove('hidden');
      }
      renderPreview(s); U.toast('Image added');
    };
    inp.click();
  }

  /* --------------------------------------------------------- live preview */
  function renderPreview(s) {
    const t = TPL[s.template] || TPL.default;
    const sec = s.sections;
    const socials = Object.entries(s.socials).filter(([, v]) => v);
    const contact = [];
    if (s.contact.phone) contact.push(['Phone', s.contact.phone]);
    if (s.contact.whatsapp) contact.push(['WhatsApp', s.contact.whatsapp]);
    if (s.contact.email) contact.push(['Email', s.contact.email]);
    if (s.contact.website) contact.push(['Website', s.contact.website.replace(/^https?:\/\//, '')]);
    if (s.contact.address) contact.push(['Address', s.contact.address]);
    const html = `
      <div class="cardly" style="--c1:${t.accent[0]};--c2:${t.accent[1]}">
        <div class="cardly__sheet cardly__sheet--preview">
          <div class="cardly__cover" ${s.cover ? `style="background-image:url('${esc(s.cover)}')"` : ''}></div>
          <div class="cardly__head">
            ${s.photo ? `<img class="cardly__avatar" src="${esc(s.photo)}" alt="">` : `<div class="cardly__avatar cardly__avatar--ph">${esc((s.name || '?').charAt(0).toUpperCase())}</div>`}
            <h1 class="cardly__name">${esc(s.name || 'Your name')}</h1>
            ${s.tagline ? `<p class="cardly__tagline">${esc(s.tagline)}</p>` : ''}
            <div class="cardly__actions"><span class="cardly__btn cardly__btn--primary">Save Contact</span><span class="cardly__btn">Share</span></div>
          </div>
          <div class="cardly__body">
            ${sec.about && s.about ? `<section class="cardly__sec"><h2>About</h2><p class="cardly__about">${esc(s.about)}</p></section>` : ''}
            ${sec.contact && contact.length ? `<section class="cardly__sec"><h2>Contact</h2><div class="cardly__contact">${contact.map(c => `<span class="cardly__row"><span class="cardly__row-label">${esc(c[0])}</span><span class="cardly__row-val">${esc(c[1])}</span></span>`).join('')}</div></section>` : ''}
            ${sec.socials && socials.length ? `<section class="cardly__sec"><h2>Connect</h2><div class="cardly__socials">${socials.map(([k]) => `<span class="cardly__social">${esc(k)}</span>`).join('')}</div></section>` : ''}
            ${sec.links && s.links.filter(l => l.label && l.url).length ? `<section class="cardly__sec"><h2>Links</h2><div class="cardly__links">${s.links.filter(l => l.label && l.url).map(l => `<span class="cardly__link">${esc(l.label)}</span>`).join('')}</div></section>` : ''}
            ${sec.skills && s.skills.length ? `<section class="cardly__sec"><h2>Skills</h2><div class="cardly__tags">${s.skills.map(sk => `<span class="cardly__tag">${esc(sk)}</span>`).join('')}</div></section>` : ''}
            ${sec.gallery && s.gallery.length ? `<section class="cardly__sec"><h2>Gallery</h2><div class="cardly__gallery">${s.gallery.map(g => `<img src="${esc(g)}" alt="">`).join('')}</div></section>` : ''}
          </div>
        </div>
      </div>`;
    root.querySelector('#cPreview').innerHTML = html;
  }

  /* ------------------------------------------------------------- helpers */
  function normalize(c) {
    const b = { template: 'default', name: '', tagline: '', about: '', photo: '', cover: '',
      contact: { phone: '', email: '', whatsapp: '', website: '', address: '' },
      socials: { instagram: '', linkedin: '', x: '', facebook: '', github: '', youtube: '', spotify: '' },
      skills: [], links: [], gallery: [],
      sections: { about: true, contact: true, socials: true, skills: true, links: true, gallery: true, map: true, qr: true } };
    c = c || {};
    return {
      template: c.template || 'default', name: c.name || '', tagline: c.tagline || '', about: c.about || '',
      photo: c.photo || '', cover: c.cover || '',
      contact: Object.assign(b.contact, c.contact || {}),
      socials: Object.assign(b.socials, c.socials || {}),
      skills: Array.isArray(c.skills) ? c.skills.slice() : [],
      links: Array.isArray(c.links) ? c.links.map(l => ({ label: l.label || '', url: l.url || '' })) : [],
      gallery: Array.isArray(c.gallery) ? c.gallery.slice() : [],
      sections: Object.assign(b.sections, c.sections || {}),
      discoverable: c.discoverable !== false, // default: listed in search
    };
  }
  function setPath(o, path, v) { const p = path.split('.'); let x = o; while (p.length > 1) x = x[p.shift()]; x[p[0]] = v; }
  function err(m) { return `<div class="notice notice--error">${esc(m)}</div>`; }
  function ok(m) { return `<div class="notice notice--success">${esc(m)}</div>`; }
})();
