# Local Roots Africa - Craft CMS 5 E-commerce

Production-ready Craft CMS 5 e-commerce site based on the Innove Couture template.

## Requirements

- PHP 8.2+
- MySQL 8+
- Composer

## Setup

```bash
composer install
cp .env.example.dev .env
# Configure database and payment gateway keys in .env
php craft install
php craft plugin/install commerce
php craft plugin/install seomatic
php craft plugin/install blitz
php craft localroots/setup
php craft localroots/import/from-html
php craft serve --port=8080
```

## Admin

- URL: http://localhost:8080/admin
- Email: admin@localroots.africa
- Password: (set during install)

## Payment Gateways

Configured via `.env`:
- PayFast (`PAYFAST_*`)
- Ozow (`OZOW_*`)
- Yoco (`YOCO_*`)

## Commands

- `php craft localroots/setup` - Create content model, volumes, gateways
- `php craft localroots/import/from-html` - Seed products/pages from template HTML

## Template Source

HTML template files: `/Users/test/www/localrootsafrica/innovecouture.vamtam.com/`
