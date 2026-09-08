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

Use this checklist locally and again on the server after pushing code. Tick each step as you complete it.

### One-time setup (fresh clone)

- [ ] `composer install`
- [ ] Copy and configure environment: `cp .env.example.production .env` (or `.env.example.dev` for local)
- [ ] Edit `.env` — database, `PRIMARY_SITE_URL`, payment gateways, Mailchimp, Courier Guy, Cloudflare
- [ ] `php craft install` *(skip on server if DB already installed)*

### Install plugins

- [ ] `php craft plugin/install commerce`
- [ ] `php craft plugin/install seomatic`
- [ ] `php craft plugin/install blitz`
- [ ] `php craft plugin/install sprig`
- [ ] `php craft plugin/install wishlist`
- [ ] `php craft plugin/install notifier`

### Project configuration & content model

- [ ] `php craft localroots/setup` — volumes, fields, sections, globals, Commerce product type, gateways
- [ ] `php craft project-config/rebuild`

### Template extraction & content seeding

- [ ] `php craft localroots/seed/all` — extract static HTML → Twig, seed pages/press, import products  
  *(Or run steps individually below)*

**Individual seed steps (optional):**

- [ ] `php craft localroots/seed/extract-templates` — extract HTML fragments to `templates/_static/`
- [ ] `php craft localroots/seed/pages` — create About, Sustainability entries
- [ ] `php craft localroots/seed/press` — create press entries
- [ ] `php craft localroots/import/from-html` — import products & promotions from template HTML

### Post-seed maintenance

- [ ] `php craft resave/products` — regenerate product URLs (`/products/{slug}`)
- [ ] `php craft resave/entries --section=pages`
- [ ] `php craft resave/entries --section=press`

### Cache & deploy finalisation

- [ ] `php craft migrate/all`
- [ ] `php craft clear-caches/all`
- [ ] `php craft project-config/rebuild`
- [ ] `php craft blitz/cache/warm` *(production only, after Blitz is configured)*

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

---

## Server deployment (after `git push`)

```bash
cd /path/to/localroots.africa

git pull origin main
composer install --no-dev --optimize-autoloader

# Ensure .env is configured for production (never commit .env)
# PRIMARY_SITE_URL, DB_*, PAYFAST_*, OZOW_*, YOCO_*, MAILCHIMP_*, COURIER_GUY_*

php craft migrate/all
php craft project-config/apply   # or: php craft project-config/rebuild
php craft localroots/setup       # safe to re-run; skips existing items
php craft localroots/seed/all    # skip if content already seeded
php craft resave/products
php craft clear-caches/all
php craft blitz/cache/warm
```

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
| `php craft localroots/seed/all` | Full seed: extract templates + pages + press + products |
| `php craft localroots/seed/extract-templates` | Extract static HTML into `templates/_static/` |
| `php craft localroots/seed/pages` | Seed About & Sustainability pages |
| `php craft localroots/seed/press` | Seed press entries |
| `php craft localroots/import/from-html` | Import products/promotions from template HTML |
| `php craft localroots/extract/templates` | Alias for template extraction |

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
| `/sustainability` | Sustainability |
| `/press` | Press listing |

---

## Template source

Static HTML/CSS/JS reference:

```
/Users/test/www/localrootsafrica/innovecouture.vamtam.com/
```

Live reference: https://innovecouture.vamtam.com/default-shop/

Theme assets are served from `/wp-content/` (symlink: `web/wp-content → assets/wp-content`).

---

## Command history log

Track commands run locally; mirror on server after deploy.

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

*Add new rows below as you run commands:*

| Date | Command | Notes |
|------|---------|-------|
| | | |
