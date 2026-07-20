# OmniTools

**Everything You Need. One Platform.**

A modern, fast, privacy-first online tools platform — like TinyWow / SmallPDF /
DevUtils — built with **plain PHP 8, MySQL, vanilla JS and CSS**. No Node, no
build step, no Composer, no Docker. It runs on any normal Apache shared host the
moment you upload it.

- 🧰 **100+ tools** across 13 categories (PDF, Image, Audio, Text, Developer,
  SEO, Finance, Utilities, Calculators, Documents, AI, Converters, Video)
- ⚡ **PageSpeed-friendly** — one compiled CSS file, deferred JS, caching + gzip
- 🔒 **Privacy first** — most tools run 100% in the browser; files never upload
- 🌗 **Light & dark** Apple-inspired UI, fully responsive & accessible
- 🔎 **Instant search** command palette (`/` or `Ctrl/⌘-K`)
- 📈 **SEO built-in** — clean URLs, sitemaps, JSON-LD, OpenGraph, FAQ schema
- 🛠️ **Admin panel** — dashboard, blog CMS, feedback, analytics, settings, backup

---

## Quick start (shared hosting)

1. **Upload** all files to your web root (e.g. `public_html/`).
2. **Create a MySQL database** in cPanel and note the name/user/password.
3. **Import** `database/schema.sql` via phpMyAdmin (optional but recommended).
4. **Edit `config.php`** — set `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`, and
   change `APP_SECRET` to a long random string.
5. Make sure `mod_rewrite` is enabled (it is on virtually all hosts). The bundled
   `.htaccess` handles clean URLs.
6. Visit your domain — you're live. 🎉

> **The site also works with no database at all.** The tool registry, blog and
> search all live in code, so tools work immediately. A database only adds the
> admin panel, feedback storage and CMS blog.

### Local testing

```bash
php -S localhost:8000
# then open http://localhost:8000
```

(The PHP built-in server doesn't read `.htaccess`, so clean URLs like
`/json-formatter` route through the fallback — for full routing test on Apache.)

---

## Admin panel

- URL: `/admin/login.php`
- Default login: **admin@omnitools.local** / **ChangeMe123!**
- **Change this immediately** after first login.

Features: Dashboard · Tools overview · Categories · Blog CMS · Feedback inbox ·
Search analytics · Settings (SEO/Ads/maintenance) · One-click SQL backup.

---

## Project structure

```
/
├── index.php              Homepage
├── tool.php               Tool page router (/<tool-slug>)
├── category.php           Category listing (/category/<slug>)
├── tools.php              All-tools directory (/tools)
├── page.php               Static pages (about, privacy, terms, contact…)
├── 404.php                Not-found page
├── sitemap.php            XML sitemap + index generator
├── robots.php             robots.txt generator
├── config.php             Global configuration (edit this)
├── .htaccess              Clean URLs, caching, gzip, security headers
│
├── assets/
│   ├── css/style.css      Full design system (light/dark)
│   ├── js/app.js          Theme, nav, command-palette search
│   ├── js/lib.js          QR, barcode, MD5, markdown, PDF writer
│   ├── js/tools.js        All tool engines
│   └── images/            favicon + OG image (SVG)
│
├── includes/
│   ├── functions.php      Helpers: security, SEO, rendering, rate-limit
│   ├── database.php       PDO wrapper (prepared statements)
│   ├── tools.php          THE TOOL REGISTRY (single source of truth)
│   ├── blog.php           Blog content store
│   ├── header.php / navbar.php / footer.php
│
├── api/
│   ├── search.php         Search JSON API
│   ├── feedback.php       Contact form endpoint
│   └── pdf.php            Server PDF ops (Ghostscript/qpdf/Imagick if present)
│
├── blog/                  Blog index + article pages
├── admin/                 Secure admin panel
├── uploads/               Temp file processing (auto-cleaned, code exec blocked)
└── database/schema.sql    MySQL schema + seed data
```

---

## Adding a new tool (2 steps)

1. Add one line to `includes/tools.php`:

   ```php
   $t('my-tool', 'developer', 'My Tool', 'What it does.', ['new'], 'keywords');
   ```

2. Register its engine in `assets/js/tools.js`:

   ```js
   reg('my-tool', root => {
     root.innerHTML = `<button class="btn btn--primary" id="go">Run</button>`;
     root.querySelector('#go').addEventListener('click', () => { /* ... */ });
   });
   ```

That's it — the page, SEO, breadcrumbs, sitemap entry and search indexing are
all generated automatically. This scales cleanly toward 1000+ tools.

---

## Security

- Prepared statements everywhere (PDO)
- CSRF tokens on every form; XSS-safe output escaping (`e()` / `eattr()`)
- Rate limiting on all API endpoints
- Strict upload validation (MIME + size) with immediate cleanup
- Security headers + code execution blocked in `/uploads` and `/includes`
- Sessions use HttpOnly, SameSite and Secure (on HTTPS) cookies

## Performance

- Single minified-friendly CSS file, deferred scripts
- gzip/deflate + long-lived cache headers via `.htaccess`
- Lazy, on-device processing — most tools never touch the server
- Zero third-party runtime dependencies

## Browser support

All modern browsers (Chrome, Edge, Firefox, Safari) on desktop & mobile.

---

© OmniTools · Built for speed & privacy.
