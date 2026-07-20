# Project scope & safety rules — READ FIRST

This repository (`intmain10/apps-briefnepal`) and this local folder are **ONLY**
for the **OmniTools** app served at **https://apps.briefnepal.com**.

## 🚫 Hard boundary — never touch the main site

- **`briefnepal.com`** (the main BriefNepal news site) is a **completely
  separate project**. It is NOT in this repo. Its source lives in the user's
  `briefnepal` project / `briefnepal.zip`, and it deploys via its own
  SSH/rsync workflow (`deploy-frontend.yml` in that repo).
- Nothing in this repo may deploy to, overwrite, or delete files in the main
  site's document root.

## How deployment works here

- `.github/workflows/deploy.yml` auto-deploys to Hostinger on push to `main`.
- The FTP account (`u682902872.apps`, in repo secrets) is **jailed to
  `public_html`**, which is the **shared root of the main domain**. The
  OmniTools subdomain's document root is **`public_html/apps`**.
- Therefore the workflow deploys with **`server-dir: ./apps/`** — and a
  **guard step fails the build** if that value is ever changed. Do not change it.
- `dangerous-clean-slate: false` — the deploy only adds/updates files, never
  wipes.

## What went wrong once (do not repeat)

Early deploys used `server-dir: ./` (and `/public_html/`), which landed OmniTools
in the **main site's root** and overwrote its `.htaccess`. Cleaning that up then
briefly broke the main site's clean URLs. Root cause: deploying to the shared
`public_html` root instead of `public_html/apps`.

**Rule:** any change to `server-dir`, the FTP account, or the deploy target must
keep everything strictly inside `apps/`. When in doubt, stop and ask.

## Strongest safeguard (recommended)

Create a **dedicated FTP account in Hostinger jailed to
`public_html/apps`**, point `FTP_USERNAME` at it, and set `server-dir: ./`. Then
this repo physically cannot reach the main site even by mistake.

## App overview

OmniTools — a PHP 8 + vanilla JS online-tools platform (no framework, no build
step). Tool registry in `includes/tools.php`; engines in `assets/js/tools.js`.
See `README.md`.
