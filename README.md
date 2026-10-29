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

Production is deployed by hand: **pull the code from GitHub on the server**. Nothing is copied from a developer machine, and pushing to `main` does not deploy by itself. (`.github/workflows/deploy.yml` exists but is not in use.)

The compiled frontend (`public/build`) is committed to git, so a server never needs Node. **Run `npm run build` and commit `public/build` whenever you change anything under `resources/js` or `resources/css`.**

The app supports PostgreSQL and MySQL/MariaDB. Postgres keeps unit status in sync with database triggers; on every driver the `LeaseObserver` does the same in code.

### Option A: VPS (nginx + pm2)

The app lives in `/var/www/rucky-rentals` and runs under pm2 (`rucky-web`, `rucky-worker`, `rucky-scheduler`) behind nginx.

```bash
ssh root@<server>
cd /var/www/rucky-rentals
git pull --ff-only
composer install --no-dev --optimize-autoloader   # only if composer.lock changed
php artisan migrate --force                       # only if there are new migrations
php artisan config:cache && php artisan route:cache && php artisan view:cache
pm2 restart all
```

If `git pull` complains about untracked files in `public/build` (first pull after build output became tracked), run `rm -rf public/build` once and pull again.

### Option B: cPanel (MySQL, no Node)

1. **PHP**: cPanel → MultiPHP Manager → PHP 8.3 or 8.4 for the domain. In MultiPHP INI Editor / Select PHP Version enable `pdo_mysql, mbstring, openssl, xml, curl, gd, zip, fileinfo, bcmath, intl`; set `memory_limit` to at least 256M.
2. **Database**: cPanel → MySQL Databases → create a database and a user, then add the user to the database with all privileges.
3. **Code**: in the cPanel terminal, outside `public_html`:
   ```bash
   cd ~ && git clone git@github.com:shauritanga/rucky-rentals.git
   cd rucky-rentals
   ```
   (Add a deploy SSH key to the GitHub repo first: `ssh-keygen -t ed25519`, then paste `~/.ssh/id_ed25519.pub` under repo Settings → Deploy keys.)
4. **Web root**: cPanel → Domains → set the domain's (or a staging subdomain's) **Document Root to `rucky-rentals/public`**.
5. **Dependencies**: use the same PHP binary as the site, e.g.
   ```bash
   ea-php83 /opt/cpanel/composer/bin/composer install --no-dev --optimize-autoloader
   ```
6. **Environment**: `cp .env.example .env`, then set `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL`, `DB_CONNECTION=mysql`, `DB_HOST=localhost`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `SESSION_DRIVER=database`, `QUEUE_CONNECTION=database`, `CACHE_STORE=database`, `BROADCAST_CONNECTION=log`, and SMTP mail from a mailbox on your domain (cPanel → Email Accounts → Connect Devices): `MAIL_MAILER=smtp`, `MAIL_HOST=mail.<your-domain>`, `MAIL_PORT=465`, `MAIL_SCHEME=smtps` (or port 587 with `MAIL_SCHEME=smtp`), `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`. Then `ea-php83 artisan key:generate`.
7. **Install**:
   ```bash
   ea-php83 artisan migrate --force
   ea-php83 artisan storage:link
   ea-php83 artisan config:cache && ea-php83 artisan route:cache && ea-php83 artisan view:cache
   chmod -R ug+rwX storage bootstrap/cache
   ```
   Create the first superuser with `ea-php83 artisan tinker` (or seed one), and set the system settings in the app.
8. **Scheduler (replaces pm2)**: cPanel → Cron Jobs → every minute:
   ```
   cd /home/<cpanel-user>/rucky-rentals && /usr/local/bin/ea-php83 artisan schedule:run >> /dev/null 2>&1
   ```
   The schedule already runs the queue worker every minute and the daily 02:00 database backup (`mysqldump`, emailed to the superusers).
9. **SSL**: cPanel → SSL/TLS Status → run AutoSSL for the domain.
10. **Releases**: `git pull --ff-only`; `composer install --no-dev -o` if `composer.lock` changed; `php artisan migrate --force`; re-run the three cache commands.

---

## Billing rules

- **Currency**: invoices, payments, clearance and KPIs are always TZS. A unit may be priced in USD; the person creating a proforma for a USD unit types the day's USD → TZS rate, which converts the lines and is kept on the invoice (`invoices.usd_rate`). The lease shows TZS using its latest proforma rate (or "—" until one exists). The deposit rate is typed at lease approval and again at clearance.
- **Invoice numbers**: per tenant and year, `<CODE>/NN/YYYY` (the tenant's `invoice_code`). A proforma keeps its number when it becomes a tax invoice, which happens only once it is fully paid (WHT included). Receipt and WHT status are tracked per invoice.
- **Security deposit**: a non-tax "Proforma – Security Deposit" (no VAT, no WHT, never a tax invoice) is issued automatically when a lease is approved. The deposit is booked to Deposits Payable only when it is paid, and is excluded from revenue KPIs. Leases approved before this change keep their original booking; the lease drawer has an "Issue Deposit Proforma" button for them.
- **Co-tenants**: when several leases run on one unit, rent and service charge are split equally. Installments are priced month by month by the number of leases running in that month (`LeaseShareRebalancer`), so a tenant's share goes back to 100% when a co-tenant's lease ends. Invoiced and paid installments are never changed; leases with a hand-set rent are skipped.
- **Early termination**: a manager starts a Clearance and submits it for review; only a superuser can finalize it and terminate the lease.

## Backups

`php artisan backup:database` dumps the Postgres database to `storage/app/backups/` (kept 14 days) and emails the compressed dump to every superuser. It is scheduled daily at 02:00 in `routes/console.php`, so the scheduler (`schedule:work` or the cron entry) must be running. The backup contains all system data; keep the mailbox private. The Overview page shows the real time of the last backup.

---

## Tech Stack

- **Backend**: Laravel 13, PostgreSQL or MySQL/MariaDB
- **Frontend**: React 19, Inertia.js v2, Vite
- **Auth**: Session-based, role-scoped (`superuser`, `manager`, `staff`)
- **Accounting**: Double-entry GL posted automatically on invoices and payments
- **Currency**: TZS everywhere; USD exists only as a unit's rate basis (see Billing rules)
