# KTS Grocery Shop — Hosting Guide

## Project Structure

```
kts_grocery/
├── .htaccess              ← Security rules (auto-applied)
├── index.php              ← Entry point (routes to shop or admin)
├── login.php              ← Admin/Staff login
├── logout.php             ← Admin/Staff logout
│
├── config/
│   └── db.php             ← ⚠️ Update DB credentials here
│
├── pages/                 ← Admin management system
│   ├── dashboard.php
│   ├── pos.php            (POS billing)
│   ├── products.php
│   ├── inventory.php
│   ├── suppliers.php
│   ├── customers.php
│   ├── employees.php
│   ├── reports.php
│   ├── delivery.php
│   ├── online_orders.php
│   ├── purchase_orders.php
│   ├── settings.php
│   └── users.php
│
├── shop/                  ← Customer-facing online shop
│   ├── index.php          (homepage)
│   ├── products.php
│   ├── product.php
│   ├── cart.php
│   ├── checkout.php
│   ├── login.php          (customer login)
│   ├── register.php
│   ├── orders.php
│   └── profile.php
│
├── api/                   ← REST API (shared by both systems)
├── assets/                ← CSS, JS, Images (shared)
└── setup/
    └── install.php        ← Run ONCE to create database tables
```

## How the Two Sites Work Together

| URL | What it is |
|---|---|
| `yourdomain.com/kts_grocery/` | → Redirects to online shop |
| `yourdomain.com/kts_grocery/shop/` | Customer shopping site |
| `yourdomain.com/kts_grocery/login.php` | Admin/Staff login |
| `yourdomain.com/kts_grocery/pages/dashboard.php` | Admin dashboard |

Both sites **share** the same database, API, and assets folder.

---

## Step-by-Step: Hosting Deployment

### Step 1 — Set Up Database on your Host

1. Log into **cPanel** → **MySQL Databases**
2. Create a new database, e.g. `kts_grocery`
3. Create a database user and set a strong password
4. Grant the user **All Privileges** on the database

### Step 2 — Update Database Credentials

Open `config/db.php` and update these lines:

```php
define('DB_HOST', 'localhost');      // usually localhost
define('DB_USER', 'your_db_user');   // your cPanel DB username
define('DB_PASS', 'your_password');  // your DB password
define('DB_NAME', 'your_db_name');   // your database name
```

### Step 3 — Upload Files

Upload the entire `kts_grocery/` folder to your hosting via:
- **cPanel File Manager**, OR
- **FTP** (FileZilla)

Upload to: `public_html/` (root of website) or `public_html/kts_grocery/`

### Step 4 — Run the Database Installer

Visit in your browser:
```
https://yourdomain.com/kts_grocery/setup/install.php
```

This will create all database tables. Run it **once only**.

> ⚠️ **After installation**: The `.htaccess` blocks all access to `setup/`.
> If you ever need to re-run it, temporarily comment out the setup block in `.htaccess`.

### Step 5 — Create Admin Account

After install, the default admin credentials are set during the install wizard. Change the password immediately in **Pages → Users**.

### Step 6 — Configure Shop Settings

Log in as admin → **Pages → Settings** to set:
- Shop name
- Currency symbol
- Delivery charges

---

## Security Notes

- `config/db.php` → **blocked** from direct browser access ✅
- `setup/install.php` → **blocked** after first install ✅
- `includes/` → **blocked** from direct access ✅
- Debug and log files are **blocked** ✅

---

## User Roles

| Role | Access |
|---|---|
| `admin` | Full management system + view shop orders |
| `cashier` | POS billing only |
| `supplier` | Supplier-facing pages only |
| `customer` | Online shop only (creates account via shop register page) |
