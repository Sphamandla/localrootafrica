# Local Roots Africa - Craft CMS 5 E-commerce

Production-ready Craft CMS 5 e-commerce site based on the [Innove Couture](https://innovecouture.vamtam.com/default-shop/) template.

**Craft project path:** `/Users/test/www/localroots.africa/`  
**Static template path:** `/Users/test/www/localrootsafrica/innovecouture.vamtam.com/`  
**Repository:** https://github.com/Sphamandla/localrootafrica

---

## Requirements

- PHP 8.2+
- MySQL 8+
- Composer
- Node not required (theme assets are pre-built in `web/wp-content/`)

---

## Command runbook

Use the **Server command log** below for production — commands are numbered in the exact order to run them. The local checklist mirrors the same phases for development.

---

## Server command log

Run these on the production server from the project root (`/path/to/localroots.africa`).  
**Always run in step order.** Tick each step as you complete it.

> **Prerequisite:** `.env` must exist on the server (never commit it). Set `PRIMARY_SITE_URL`, `CRAFT_DB_*`, payment gateways, Mailchimp, Courier Guy, and `BLITZ_ENABLED` before running Craft commands.

---

### A. First-time server setup (run once)

| Step | Command | Notes |
|------|---------|-------|
| A1 | `cd /path/to/localroots.africa` | Project root on server |
| A2 | `git clone …` or upload files | Skip if repo already cloned |
| A3 | `cp .env.example.production .env` | Then edit with production values |
| A4 | `composer install --no-dev --optimize-autoloader` | Install PHP dependencies |
| A5 | `php craft install` | Create admin user + initial DB tables |
| A6 | `php craft plugin/install commerce` | |
| A7 | `php craft plugin/install seomatic` | |
| A8 | `php craft plugin/install blitz` | |
| A9 | `php craft plugin/install sprig` | |
| A10 | `php craft plugin/install wishlist` | |
| A11 | `php craft plugin/install notifier` | |
| A12 | `php craft localroots/setup` | Volumes, fields, sections, gateways |
| A13 | `php craft project-config/apply` | Apply project config from repo |
| A14 | `php craft localroots/seed/all` | Extract templates, seed pages, import products |
| A15 | `php craft resave/products` | Regenerate product URLs |
| A16 | `php craft resave/entries --section=pages` | Regenerate page URIs |
| A17 | `php craft resave/entries --section=press` | Regenerate press URIs |
| A18 | `php craft clear-caches/all` | Clear all caches |
| A19 | `php craft blitz/cache/warm` | Warm static cache (production) |

**Copy-paste block (first-time, after `.env` is configured):**

```bash
cd /path/to/localroots.africa
composer install --no-dev --optimize-autoloader
php craft install
php craft plugin/install commerce
php craft plugin/install seomatic
php craft plugin/install blitz
php craft plugin/install sprig
php craft plugin/install wishlist
php craft plugin/install notifier
php craft localroots/setup
php craft project-config/apply
php craft localroots/seed/all
php craft resave/products
php craft resave/entries --section=pages
php craft resave/entries --section=press
php craft clear-caches/all
php craft blitz/cache/warm
```

---

### B. Routine deploy (after every `git push`)

Run after pulling code that includes template, module, or config changes.

| Step | Command | Notes |
|------|---------|-------|
| B1 | `cd /path/to/localroots.africa` | |
| B2 | `git pull origin main` | Use your deploy branch if different |
| B3 | `composer install --no-dev --optimize-autoloader` | Only if `composer.lock` changed |
| B4 | `php craft migrate/all` | Run DB migrations first |
| B5 | `php craft project-config/apply` | Sync YAML config from repo |
| B6 | `php craft localroots/setup` | Safe to re-run; adds missing fields/sections |
| B7 | `php craft localroots/seed/all` | **Skip** if only code/CSS/JS changed and content already exists |
| B8 | `php craft resave/products` | Run after seed or product schema changes |
| B9 | `php craft resave/entries --section=pages` | Run after new pages seeded (contact, terms, etc.) |
| B10 | `php craft resave/entries --section=press` | Run after press seed |
| B11 | `php craft clear-caches/all` | Always run last before Blitz |
| B12 | `php craft blitz/cache/warm` | Regenerate static cache |
| B13 | `php craft localroots/seo/status` | *(Optional)* Verify SEO endpoints after template/SEO changes |

**Copy-paste block (routine deploy, full):**

```bash
cd /path/to/localroots.africa
git pull origin main
composer install --no-dev --optimize-autoloader
php craft migrate/all
php craft project-config/apply
php craft localroots/setup
php craft localroots/seed/all
php craft resave/products
php craft resave/entries --section=pages
php craft resave/entries --section=press
php craft clear-caches/all
php craft blitz/cache/warm
```

**Copy-paste block (routine deploy, code-only — no content re-seed):**

```bash
cd /path/to/localroots.africa
git pull origin main
composer install --no-dev --optimize-autoloader
php craft migrate/all
php craft project-config/apply
php craft clear-caches/all
php craft blitz/cache/warm
```

---

### C. Content / page updates only

Use when you added a new page or changed seed logic locally and need to sync content on the server (templates are already in git).

| Step | Command | Notes |
|------|---------|-------|
| C1 | `git pull origin main` | Pull latest templates + seed code |
| C2 | `php craft localroots/seed/extract-templates` | Re-extract `_static/` fragments |
| C3 | `php craft localroots/seed/terms` | Example: seed Terms page only |
| C4 | `php craft localroots/seed/contact` | Example: seed Contact only |
| C5 | `php craft localroots/seed/faq` | Example: seed FAQ only |
| C6 | `php craft localroots/seed/delivery-and-returns` | Example: seed Delivery page |
| C7 | `php craft localroots/seed/sustainability` | Example: seed Sustainability |
| C8 | `php craft localroots/seed/press` | Example: seed Press entries |
| C9 | `php craft localroots/import/from-html` | Re-import products (destructive-ish; use carefully) |
| C10 | `php craft resave/entries --section=pages` | Refresh page URIs |
| C11 | `php craft clear-caches/all` | |
| C12 | `php craft blitz/cache/warm` | |

**Or run the full seed pipeline (C2 + all seeds + import):**

```bash
php craft localroots/seed/all
php craft resave/products
php craft resave/entries --section=pages
php craft resave/entries --section=press
php craft clear-caches/all
php craft blitz/cache/warm
```

> **Note:** `localroots/seed/all` requires the reference HTML mirror on the server **only if** you rely on `--path=` or `TEMPLATE_HTML_PATH`. In normal deploys, extracted Twig and assets are committed to git — seed actions that only create/update Craft entries (contact, terms, faq) work without the mirror.

---

### D. Server command history

Log every production run here (newest first). Mirror what you actually executed.

| Date | Phase | Commands run | Notes |
|------|-------|--------------|-------|
| | | | |
| 2026-09-08 | B | `git pull`, `composer install`, `migrate/all`, `project-config/apply`, `localroots/setup`, `seed/all`, `resave/*`, `clear-caches/all`, `blitz/cache/warm` | Example row — replace with real server dates |

---

### Local development checklist

Mirror server phases locally; use `.env.example.dev` and `php craft serve`.

### One-time setup (fresh clone)

- [ ] `composer install`
- [ ] Copy and configure environment: `cp .env.example.dev .env`
- [ ] Edit `.env` — database, `PRIMARY_SITE_URL=http://localhost:8080/`
- [ ] `php craft install`

### Install plugins

- [ ] `php craft plugin/install commerce`
- [ ] `php craft plugin/install seomatic`
- [ ] `php craft plugin/install blitz`
- [ ] `php craft plugin/install sprig`
- [ ] `php craft plugin/install wishlist`
- [ ] `php craft plugin/install notifier`

### Project configuration & content seeding

- [ ] `php craft localroots/setup`
- [ ] `php craft project-config/rebuild`
- [ ] `php craft localroots/seed/all`

**Individual seed steps (optional):**

- [ ] `php craft localroots/seed/extract-templates`
- [ ] `php craft localroots/seed/pages`
- [ ] `php craft localroots/seed/contact`
- [ ] `php craft localroots/seed/terms`
- [ ] `php craft localroots/seed/faq`
- [ ] `php craft localroots/seed/delivery-and-returns`
- [ ] `php craft localroots/seed/sustainability`
- [ ] `php craft localroots/seed/press`
- [ ] `php craft localroots/import/from-html`

### Post-seed maintenance

- [ ] `php craft resave/products`
- [ ] `php craft resave/entries --section=pages`
- [ ] `php craft resave/entries --section=press`
- [ ] `php craft clear-caches/all`

### Local development server

- [ ] `php craft serve --port=8080 --docroot=web`

---

## Quick start (local)

```bash
cd /Users/test/www/localroots.africa

composer install
cp .env.example.dev .env
# Configure DB + PRIMARY_SITE_URL=http://localhost:8080/

php craft install
php craft plugin/install commerce
php craft plugin/install seomatic
php craft plugin/install blitz
php craft plugin/install sprig
php craft plugin/install wishlist
php craft plugin/install notifier

php craft localroots/setup
php craft localroots/seed/all
php craft resave/products
php craft clear-caches/all

php craft serve --port=8080 --docroot=web
```

Open: http://localhost:8080/

> **Production:** use [Server command log → B. Routine deploy](#b-routine-deploy-after-every-git-push) instead.

---

## Admin

| | |
|---|---|
| **URL (local)** | http://localhost:8080/admin |
| **Email** | admin@localroots.africa |
| **Password** | Set during `php craft install` |

---

## Custom console commands

| Command | Description |
|---------|-------------|
| `php craft localroots/setup` | Create content model, volumes, gateways, sections |
| `php craft localroots/seed/all` | Full seed: extract templates + all pages + press + products |
| `php craft localroots/seed/extract-templates` | Extract static HTML into `templates/_static/` |
| `php craft localroots/extract/templates` | Same as `seed/extract-templates` |
| `php craft localroots/seed/pages` | Seed About & Contact page entries |
| `php craft localroots/seed/contact` | Seed Contact page entry |
| `php craft localroots/seed/terms` | Seed Terms & conditions page entry |
| `php craft localroots/seed/faq` | Seed FAQ page and accordion items |
| `php craft localroots/seed/delivery-and-returns` | Seed Delivery & returns section entry |
| `php craft localroots/seed/sustainability` | Seed Sustainability section entry |
| `php craft localroots/seed/press` | Seed press entries |
| `php craft localroots/import/from-html` | Import products/promotions from template HTML |
| `php craft localroots/seo/status` | SEO module status, endpoint URLs, SEOmatic check |

**Import with custom template path:**

```bash
php craft localroots/import/from-html --path=/path/to/innovecouture.vamtam.com
php craft localroots/seed/all --path=/path/to/innovecouture.vamtam.com
```

---

## Environment variables (`.env`)

| Group | Keys |
|-------|------|
| **Site** | `PRIMARY_SITE_URL`, `CRAFT_ENVIRONMENT` |
| **Database** | `CRAFT_DB_*` |
| **PayFast** | `PAYFAST_MERCHANT_ID`, `PAYFAST_MERCHANT_KEY`, `PAYFAST_PASSPHRASE`, `PAYFAST_SANDBOX` |
| **Ozow** | `OZOW_SITE_CODE`, `OZOW_PRIVATE_KEY`, `OZOW_API_KEY`, `OZOW_SANDBOX` |
| **Yoco** | `YOCO_SECRET_KEY`, `YOCO_PUBLIC_KEY`, `YOCO_SANDBOX` |
| **Mailchimp** | `MAILCHIMP_API_KEY`, `MAILCHIMP_LIST_ID` |
| **Courier Guy** | `COURIER_GUY_API_KEY`, `COURIER_GUY_API_URL`, `COURIER_GUY_TEST_MODE`, `COURIER_GUY_FALLBACK_RATE`, `COURIER_GUY_ORIGIN_POSTCODE` |
| **Cloudflare** | `CLOUDFLARE_API_TOKEN`, `CLOUDFLARE_ZONE_ID`, `CLOUDFLARE_EMAIL` |
| **Blitz** | `BLITZ_ENABLED` |
| **Template import** | `TEMPLATE_HTML_PATH` *(local only; not needed on server if assets are in repo)* |

---

## Routes

| URL | Template |
|-----|----------|
| `/` | Homepage |
| `/shop` | Product listing |
| `/products/{slug}` | Product detail |
| `/cart` | Cart |
| `/checkout` | Checkout |
| `/account` | My account |
| `/login` | Login |
| `/register` | Register |
| `/wishlist` | Wishlist *(login required)* |
| `/about` | About |
| `/contact` | Contact |
| `/terms-and-conditions` | Terms & conditions *(Pages section entry)* |
| `/faq` | FAQ |
| `/delivery-and-returns` | Delivery & returns |
| `/sustainability` | Sustainability |
| `/press` | Press listing |

Some URLs are defined in `config/routes.php`; others come from Craft section URIs (`{slug}`). Pages in the **Pages** section (about, contact, terms-and-conditions, faq) are served by `templates/_pages/_entry.twig` when accessed via their Craft URI.

---

## Architecture (for developers)

### Layout types

| Layout | Twig base | Head / header / footer | Used for |
|--------|-----------|------------------------|----------|
| **Commerce** | `_layouts/vamtam.twig` | `head.twig`, `header.twig`, `footer.twig` | Home, shop, cart, checkout, account, product |
| **Alt (marketing)** | `_layouts/vamtam-alt.twig` | `head-alt.twig`, `header-alt.twig`, `footer-alt.twig` | About, contact, FAQ, wishlist, press, sustainability, delivery-and-returns, terms |

Commerce pages share one header/footer extracted from the shop template. Alt-layout pages share the About-style header/footer (Elementor template 150).

### Page implementation patterns

| Page | URL | Template | Content source | Page-specific CSS |
|------|-----|----------|----------------|-------------------|
| About | `/about` | `_pages/_entry.twig` | `content-about.twig` | `head-alt.twig` (shared) |
| Contact | `/contact` | `_pages/_entry.twig` | `content-contact.twig` | `head-contact.twig` (`post-728`, `post-5977`) |
| Terms & conditions | `/terms-and-conditions` | `_pages/_entry.twig` | `content-terms.twig` | `head-terms.twig` (`post-3`, `post-5977`) |
| FAQ | `/faq` | `_pages/faq/index.twig` | Craft entry + `faq.js` | `head-alt.twig` + FAQ widgets |
| Delivery & returns | `/delivery-and-returns` | `_pages/delivery-and-returns.twig` | Static shell + Craft `pageBody` via `delivery-and-returns.js` | `head-delivery-and-returns.twig` |
| Sustainability | `/sustainability` | `_pages/sustainability.twig` | Static shell + `sustainability.js` | `head-sustainability.twig` |
| Shop | `/shop` | `_pages/products/index.twig` | `shop.js` hydrates filters/grid | `head-shop.twig` |
| Checkout | `/checkout` | `_pages/checkout/index.twig` | `checkout.js` hydrates form | `head-checkout.twig` |
| Wishlist | `/wishlist` | `_pages/wishlist.twig` | Craft Wishlist plugin + `wishlist.js` | `head-wishlist.twig` |

**Contact and Terms** use the same Elementor single-page shell (`elementor-5977`): hero block (title + excerpt) plus inner content. Only the inner page CSS differs (`post-728` vs `post-3`).

### Key directories

```
modules/localroots/          # Custom Craft module (controllers, services, console)
  console/controllers/       # setup, seed, extract, import commands
  services/                  # TemplateExtractorService, SeoService, payment/shipping helpers
templates/
  _layouts/                  # vamtam.twig, vamtam-alt.twig, main.twig
  _pages/                    # Route templates and section entry templates
  _static/                   # Extracted HTML fragments (content-*.twig, header, footer)
  _includes/vamtam/          # Page-specific head partials, product/cart partials
web/
  js/                        # Client-side hydration (shop, checkout, wishlist, etc.)
  assets/wp-content/         # Theme CSS/JS/images (mirrored from reference site)
config/routes.php            # Custom URL rules
```

### Template extraction

Static HTML is extracted from the reference mirror into `templates/_static/` by `TemplateExtractorService`:

```bash
php craft localroots/extract/templates
# or (same action, called from seed/all):
php craft localroots/seed/extract-templates
```

**Reference path** (local): `/Users/test/www/localrootsafrica/innovecouture.vamtam.com/`  
Override with env `TEMPLATE_HTML_PATH` or `--path=` on seed/import commands.

**Page map** lives in `modules/localroots/services/TemplateExtractorService.php` (`$pageMap`). Each handle produces:

- `content-{handle}.twig` — main page HTML inside `#main-content`
- `body-class-{handle}.twig` — `<body>` class string for Elementor styling

**Path rewrites** in `rewritePaths()` convert WordPress URLs (e.g. `/%3Fp=3.html`) to Craft routes (e.g. `/terms-and-conditions`). Add new mappings here when linking footer/checkout to new pages.

**Sanitizers** strip static WooCommerce markup from shop, checkout, and wishlist fragments so JS can hydrate from Craft/API sources.

After extraction, copy any new Elementor CSS files from the reference into `web/assets/wp-content/uploads/elementor/css/` using the encoded filename format (e.g. `post-3.css%3Fver=1787836123.css`).

### Client-side hydration

| Script | Purpose |
|--------|---------|
| `shop.js` | Product grid, filters, load-more |
| `checkout.js` | Checkout form, shipping, payment |
| `cart.js` / `commerce-cart.js` | Cart UI updates |
| `product.js` | Variants, add-to-cart, reviews/Q&A |
| `wishlist.js` | Wishlist page list/remove |
| `wishlist-toggle.js` | Heart icon on product cards → `POST /actions/wishlist/items/add` |
| `delivery-and-returns.js` | Injects Craft `pageBody` into static Elementor shell |
| `faq.js` | FAQ accordion from Craft entry |
| `account.js` | My account tabs |

Scripts are loaded from `_layouts/vamtam.twig` or individual page templates.

### Adding a new marketing page (checklist)

Use **Contact** or **Terms** as the reference when the page shares the 5977 single-page shell.

1. Add the reference HTML path to `$pageMap` in `TemplateExtractorService.php` (e.g. `'terms' => 'index.html?p=3.html'`).
2. Add to `$altLayoutPages` if it uses the About-style header/footer.
3. Add URL rewrites in `rewritePaths()` for any legacy WordPress links.
4. Run `php craft localroots/extract/templates`.
5. Create `templates/_includes/vamtam/head-{page}.twig` with the correct `post-*.css` files from the reference `<head>`.
6. Copy missing Elementor CSS into `web/assets/wp-content/uploads/elementor/css/`.
7. Wire the page:
   - **Pages section entry:** extend `templates/_pages/_entry.twig` (`vamtamPages`, `head`, `bodyClass`, `content` blocks), **or**
   - **Dedicated section/template:** add route in `config/routes.php` and create `templates/_pages/{page}.twig` extending `vamtam-alt`.
8. Add `action{Page}()` in `SeedController.php` and call it from `actionAll()`.
9. Update footer/checkout links in `footer-alt.twig` (and `checkout-commerce.twig` if applicable).
10. Seed: `php craft localroots/seed/{page}` then `php craft clear-caches/all`.

### Known gotchas

- **WP Rocket lazy-render:** `[data-wpr-lazyrender]` can collapse footer height to 0 until scroll. `scripts.twig` strips this attribute on `DOMContentLoaded`; do not re-add it to footer partials.
- **Page-specific CSS:** Alt-layout pages that look “unstyled” usually need their own `head-*.twig` partial (Contact/Terms cannot share About’s `post-92` CSS).
- **Pages section vs routes:** `/contact` may resolve via Craft entry URI (`_entry.twig`) even when `config/routes.php` defines `_pages/contact.twig`. Keep `_entry.twig` in sync for Pages section slugs.
- **Encoded asset URLs:** Theme CSS uses `%3F` in filenames (e.g. `post-728.css%3Fver=….css`) to match the mirrored reference paths.
- **SEO / static head conflict:** Never re-add `<title>`, canonical, or Open Graph tags to `head.twig` / `head-alt.twig`. SEO is rendered by SEOmatic via `{% hook 'seomaticRender' %}` in layouts.

---

## SEO module

Built on **[SEOmatic](https://nystudio107.com/docs/seomatic/)** with a custom **`localroots` SEO layer** (patterns from Ether SEO & Sprout SEO): dynamic meta for all page types, rich social cards, JSON-LD, and AI/AEO discoverability.

### Architecture

| Layer | Responsibility |
|-------|----------------|
| **SEOmatic** | Title, meta description, canonical, OG/Twitter tags, sitemaps, robots.txt, CP previews |
| **`SeoService`** | Page-type detection, globals fallbacks, noindex rules, AI citation meta |
| **`SeoSchemaBuilder`** | JSON-LD: Organization, WebSite, Product, FAQPage, NewsArticle, CollectionPage |
| **`config/seo.php`** | Noindex routes, Twitter card defaults, AI index toggles |

### Endpoints

| URL | Purpose |
|-----|---------|
| `/sitemap.xml` | SEOmatic sitemap (auto) |
| `/llms.txt` | Machine-readable site map for AI assistants |
| `/localroots/seo/ai-index.json` | Full JSON index of pages + products for citation |

### Console

```bash
php craft localroots/seo/status   # Check SEOmatic + globals + endpoint URLs
```

### Configure in Craft CP

1. **Admin → Globals → SEO Settings** — default title & description
2. **Admin → Globals → Site Settings** — site name, logo (used for OG image fallback)
3. **Admin → Globals → Footer Settings** — social URLs (Organization `sameAs`)
4. **Admin → SEOmatic → Dashboard** — site identity, social handles, sitemap settings

### Page coverage

| Page type | Meta source | Schema |
|-----------|-------------|--------|
| Products | Title, short description, images, reviews | `Product`, `Offer`, `AggregateRating` |
| Pages (about, contact, terms…) | Entry title + `pageBody` excerpt | `WebPage`, `AboutPage`, `ContactPage` |
| FAQ | Entry + `faqItems` | `FAQPage` |
| Press | Press entries | `NewsArticle` |
| Shop / categories | Route context | `CollectionPage` |
| Cart, checkout, account, wishlist | — | `noindex` |

### AI / AEO (Answer Engine Optimization)

Follows [Google’s AI optimization guidance](https://developers.google.com/search/docs/fundamentals/ai-optimization-guide): no separate AI index required — pages must be **crawlable, indexed, and snippet-eligible**. This module adds:

- Clean HTML (no duplicate demo meta tags)
- Structured JSON-LD for rich results
- `citation_title` / `citation_public_url` meta for attribution
- Optional `llms.txt` + JSON index for non-Google AI crawlers (Perplexity, ChatGPT browsing, etc.)

### After deploy

```bash
php craft clear-caches/all
php craft blitz/cache/warm    # production
```

Verify: view source on homepage → single `<title>`, `og:image`, `application/ld+json`, no Innove Couture demo values.

---

## Template source

Static HTML/CSS/JS reference:

```
/Users/test/www/localrootsafrica/innovecouture.vamtam.com/
```

Live reference: https://innovecouture.vamtam.com/default-shop/

Theme assets are served from `/wp-content/` (symlink: `web/wp-content → assets/wp-content`).

---

## Command history log (local)

Track commands run on your **local machine** during development. For production runs, use [Server command history → D](#d-server-command-history).

| Date | Command | Notes |
|------|---------|-------|
| 2026-09-05 | `composer create-project craftcms/craft` | Initial Craft 5 install |
| 2026-09-05 | `php craft plugin/install commerce seomatic blitz` | Core plugins |
| 2026-09-05 | `php craft localroots/setup` | Content model |
| 2026-09-05 | `php craft localroots/import/from-html` | Seed products |
| 2026-09-08 | `rsync wp-content → web/wp-content` | Theme assets |
| 2026-09-08 | `php craft localroots/extract/templates` | Pixel-perfect HTML extraction |
| 2026-09-08 | `composer require sprig wishlist notifier mailchimp` | Extra plugins |
| 2026-09-08 | `php craft plugin/install sprig wishlist notifier` | Install plugins |
| 2026-09-08 | `php craft localroots/setup` | Added press section |
| 2026-09-08 | `php craft localroots/seed/all` | Full seed pipeline |
| 2026-09-08 | `php craft resave/products` | Generate product URIs |
| 2026-09-08 | `php craft clear-caches/all` | Clear caches |
| 2026-09-08 | `php craft project-config/rebuild` | Sync project config |
| 2026-09-08 | `php craft serve --port=8080 --docroot=web` | Local dev server |
| 2026-09-08 | `php craft localroots/seed/all` | Re-run after ImportController `path` fix |
| 2026-09-08 | `php craft localroots/seed/extract-templates` | Fix scripts.twig duplicate homepage bug |
| 2026-09-08 | `php craft localroots/extract/templates` | Extract terms page fragments |
| 2026-09-08 | `php craft localroots/seed/terms` | Seed Terms & conditions page |
| 2026-09-08 | Contact/Terms CSS | Added `head-contact.twig`, `head-terms.twig`; wired `_entry.twig` |
| 2026-09-08 | Wishlist | Added `wishlist-toggle.js` for shop heart icons |

*Add new local rows below:*

| Date | Command | Notes |
|------|---------|-------|
| | | |
