# Rucky Rentals

A commercial property management system built with **Laravel 13**, **React 19**, and **Inertia.js**.  
Handles units, tenants, leases, invoices, payments, maintenance, electricity billing, and accounting — all in one place.

---

## Requirements

| Dependency | Version |
|---|---|
| PHP | ≥ 8.3 |
| PostgreSQL | ≥ 14 |
| Node.js | ≥ 18 |
| Composer | ≥ 2 |

---

## Quick Install

```bash
# 1. Clone the repository
git clone https://github.com/shauritanga/rucky-rentals.git
cd rucky-rentals

# 2. Copy and configure environment
cp .env.example .env
```

Open `.env` and set your database credentials:
```env
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=rucky_db
DB_USERNAME=postgres
DB_PASSWORD=your_password
```

```bash
# 3. Run the one-command setup (installs deps, migrates, seeds, builds assets)
composer setup
```

That's it. Start the development server:

```bash
composer dev
```

Then open **http://localhost:8000** in your browser.

---

## Default Login

| Field | Value |
|---|---|
| Email | `admin@rukyrentals.co.tz` |
| Password | `admin123` |

> **Change the password immediately** after first login.

---

## What `composer setup` does

| Step | Command |
|---|---|
| Install PHP dependencies | `composer install` |
| Copy `.env.example` → `.env` (if missing) | — |
| Generate app encryption key | `php artisan key:generate` |
| Run database migrations | `php artisan migrate --force` |
| Seed initial superuser account | `php artisan db:seed --force` |
| Create storage symlink | `php artisan storage:link` |
| Install JS dependencies | `npm install` |
| Build frontend assets | `npm run build` |

---

## Manual Installation (step by step)

```bash
composer install
cp .env.example .env          # then edit DB credentials
php artisan key:generate
php artisan migrate
php artisan db:seed
php artisan storage:link
npm install
npm run build
php artisan serve
```

---

## Development Workflow

```bash
# Starts the web server, queue worker, log viewer, and Vite HMR together
composer dev
```

---

## Resetting to a Clean Slate (dev only)

Wipes all data and recreates the superuser:

```bash
php artisan db:seed --class=CleanSlateSeeder
```

---

## Production Deployment

The project ships with a GitHub Actions workflow (`.github/workflows/deploy.yml`) that automatically deploys to a DigitalOcean droplet on every push to `main`.

Required GitHub secrets:

| Secret | Description |
|---|---|
| `SERVER_HOST` | Droplet IP address |
| `SERVER_USER` | SSH username (e.g. `root`) |
| `SERVER_SSH_KEY` | Private SSH key for the deploy key pair |

---

## Tech Stack

- **Backend**: Laravel 13, PostgreSQL, Laravel Reverb (WebSockets)
- **Frontend**: React 19, Inertia.js v2, Vite
- **Auth**: Session-based, role-scoped (`superuser`, `manager`, `staff`)
- **Accounting**: Double-entry GL posted automatically on invoices and payments
- **Currency**: Multi-currency (TZS / USD) with exchange rate support
