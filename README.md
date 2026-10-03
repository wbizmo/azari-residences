<p align="center">
  <img src="/public/images/logo-light.png" alt="Reserva" width="320">
</p>

<p align="center">
  <strong>THE AZARI RESIDENCES</strong><br>
  Africa's Finest Address
</p>

<p align="center">
A modern hotel and apartment booking platform built with Laravel, designed for premium hospitality businesses. The platform provides a seamless booking experience for guests while giving administrators complete control over rooms, apartments, reservations, payments, content, users, identities, services, and day-to-day operations through an intuitive management dashboard.
</p>

---

## Features

* Luxury hotel and serviced apartment booking
* Apartment and room availability management
* Online payment processing
* Booking verification portal
* Guest dashboard
* Admin dashboard
* Identity verification (KYC)
* Additional guest management
* Invoice and receipt generation
* Booking history
* User notifications
* Support ticket system
* Service requests
* Concierge management
* Housekeeping requests
* Airport transfer requests
* Restaurant reservations
* Content Management System (CMS)
* Homepage section management
* Navigation management
* Review management
* Responsive mobile experience
* Email notifications
* Role-based access control
* Dynamic branding and logo management
* SEO-optimized public pages
* Production-ready architecture

---

## Technology Stack

* Laravel 13
* PHP 8.4+
* MySQL
* Blade
* Vite
* Alpine.js
* Tailwind CSS
* Laravel Breeze

---

## Requirements

* PHP 8.4 or later
* MySQL 8+
* Composer
* Node.js 20+
* npm

---

## Installation

Clone the repository.

Install PHP and frontend dependencies.

```bash
composer install
npm install
```

Copy the environment file.

```bash
cp .env.example .env
```

Configure the application URL, database credentials, mail settings, payment providers, and other production values inside `.env`.

Generate the application key.

```bash
php artisan key:generate
```

Run migrations.

```bash
php artisan migrate
```

Build the frontend assets.

```bash
npm run build
```

Create the storage symlink.

```bash
php artisan storage:link
```

Clear stale generated files before rebuilding production caches.

```bash
find bootstrap/cache -type f ! -name '.gitignore' -delete
find storage/framework/views -type f ! -name '.gitignore' -delete
```

Cache the application.

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Start the application locally.

```bash
php artisan serve
```

---

## Production Deployment

Before deploying the application, build all frontend assets on a machine that has Node.js and npm installed.

```bash
npm install
npm run build
```

The generated production assets will be placed inside:

```text
public/build
```

Upload the complete generated `public/build` directory with the rest of the application.

Do not upload `node_modules` to production.

---

## Terminal Deployment

After uploading or pulling the project onto the production server, configure the `.env` file and install production dependencies.

```bash
composer install --no-dev --optimize-autoloader
```

Clear stale cache and compiled Blade files before caching the application.

The contents of `bootstrap/cache` should be deleted except for `.gitignore`.

The contents of `storage/framework/views` should also be deleted except for `.gitignore`.

```bash
find bootstrap/cache -type f ! -name '.gitignore' -delete
find storage/framework/views -type f ! -name '.gitignore' -delete
```

Create or refresh the storage symlink.

```bash
php artisan storage:link
```

Run database migrations when required.

```bash
php artisan migrate --force
```

Rebuild Laravel's production caches.

```bash
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Set writable directory permissions.

```bash
chmod -R 775 storage
chmod -R 775 bootstrap/cache
```

Set all files inside `public/images` to permission `644`.

```bash
find public/images -type f -exec chmod 644 {} \;
```

Set the public `.htaccess` file to permission `644`.

```bash
chmod 644 public/.htaccess
```

When the project root itself is the document root and contains another `.htaccess`, set that file to `644` as well.

```bash
chmod 644 .htaccess
```

Recommended directory and file permissions:

```bash
find public/images -type d -exec chmod 755 {} \;
find public/images -type f -exec chmod 644 {} \;
chmod 644 public/.htaccess
```

---

## Shared Hosting Deployment With cPanel

### 1. Prepare the application locally

On your local computer or development environment, install dependencies and build the frontend assets.

```bash
composer install --no-dev --optimize-autoloader
npm install
npm run build
```

The build command generates the production CSS and JavaScript files inside:

```text
public/build
```

Upload the complete generated `public/build` directory to cPanel.

You do not need to run `npm run build` on cPanel when the assets have already been built locally.

Do not upload `node_modules`.

### 2. Upload the project

Upload and extract the project files through cPanel File Manager.

Where possible, configure the domain or subdomain document root to point directly to:

```text
project-directory/public
```

Only the contents of the Laravel `public` directory should be web-accessible.

### 3. Configure the environment

Create or update the production `.env` file.

Configure at least:

* `APP_ENV=production`
* `APP_DEBUG=false`
* `APP_URL`
* Database credentials
* Mail credentials
* Payment provider credentials
* SMS provider credentials
* Queue configuration
* Cache configuration
* Session configuration

Generate the application key if it has not already been generated.

```bash
php artisan key:generate
```

### 4. Import the database

Import the production SQL file through phpMyAdmin or run migrations from the terminal.

```bash
php artisan migrate --force
```

### 5. Clear stale generated files

Before rebuilding Laravel caches, empty the contents of:

```text
bootstrap/cache
```

Keep only:

```text
.gitignore
```

Also empty the contents of:

```text
storage/framework/views
```

Keep only:

```text
.gitignore
```

Using cPanel File Manager:

1. Open `bootstrap/cache`.
2. Select every file except `.gitignore`.
3. Delete the selected files.
4. Open `storage/framework/views`.
5. Select every file except `.gitignore`.
6. Delete the selected files.

Using cPanel Terminal:

```bash
find bootstrap/cache -type f ! -name '.gitignore' -delete
find storage/framework/views -type f ! -name '.gitignore' -delete
```

This prevents stale configuration, route, service, package, and compiled Blade files from causing deployment errors.

### 6. Set writable directory permissions

Ensure these directories are writable by PHP:

```text
storage
bootstrap/cache
```

Recommended permissions:

```text
775
```

Using cPanel Terminal:

```bash
chmod -R 775 storage
chmod -R 775 bootstrap/cache
```

When using File Manager, select each directory, choose **Change Permissions**, and enable the equivalent of `775`.

### 7. Set image permissions

Every deployed image file inside:

```text
public/images
```

must use permission:

```text
644
```

This applies whether the deployment is completed through:

* cPanel File Manager
* cPanel Terminal
* SSH
* Git deployment
* Manual ZIP upload

Using cPanel Terminal:

```bash
find public/images -type d -exec chmod 755 {} \;
find public/images -type f -exec chmod 644 {} \;
```

Using cPanel File Manager:

1. Open `public/images`.
2. Select the image files.
3. Choose **Change Permissions**.
4. Set the files to `644`.

Directories inside `public/images` should normally use permission `755`.

Incorrect image permissions such as `600` may cause uploaded images, logos, icons, and other public media to return HTTP `403 Forbidden`.

### 8. Set `.htaccess` permissions

The Laravel public `.htaccess` file must use permission:

```text
644
```

The primary file is usually:

```text
public/.htaccess
```

Using cPanel Terminal:

```bash
chmod 644 public/.htaccess
```

Using cPanel File Manager:

1. Open the `public` directory.
2. Enable **Show Hidden Files** if `.htaccess` is not visible.
3. Select `.htaccess`.
4. Choose **Change Permissions**.
5. Set it to `644`.

When another `.htaccess` exists in the configured document root, set that file to `644` as well.

Incorrect `.htaccess` permissions may cause Laravel routes to return `404 Not Found` or prevent Apache rewrite rules from loading.

### 9. Create the storage link

Run:

```bash
php artisan storage:link
```

When symlink creation is unavailable on the hosting provider, create the equivalent link using the hosting control panel or contact the hosting provider.

### 10. Rebuild production caches

After clearing old generated files and confirming the `.env` configuration, run:

```bash
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

When cPanel Terminal is unavailable, stale compiled Blade files can still be manually removed from:

```text
storage/framework/views
```

Do not delete `.gitignore`.

### 11. Confirm production configuration

Verify that:

* `APP_ENV` is set to `production`
* `APP_DEBUG` is set to `false`
* `APP_URL` uses the live HTTPS domain
* Database credentials are correct
* Mail credentials are correct
* `MAIL_ENCRYPTION` matches the configured SMTP port
* Payment callback URLs use the live domain
* Storage directories are writable
* `public/build` contains the latest generated Vite assets
* Files inside `public/images` use permission `644`
* Directories inside `public/images` use permission `755`
* `public/.htaccess` uses permission `644`
* `bootstrap/cache` contains no stale files
* `storage/framework/views` contains no stale compiled Blade files
* HTTPS is active
* The domain document root points to the Laravel `public` directory

---

## Manual cPanel Deployment Checklist

Use this checklist after every manual deployment.

* Upload the latest application files.
* Upload the locally generated `public/build` directory.
* Do not upload `node_modules`.
* Confirm the production `.env` file.
* Import the database or run migrations.
* Delete all files inside `bootstrap/cache` except `.gitignore`.
* Delete all files inside `storage/framework/views` except `.gitignore`.
* Set `storage` to writable permissions.
* Set `bootstrap/cache` to writable permissions.
* Set directories inside `public/images` to `755`.
* Set files inside `public/images` to `644`.
* Set `public/.htaccess` to `644`.
* Set the document-root `.htaccess` to `644` when applicable.
* Create or confirm the storage symlink.
* Clear and rebuild Laravel caches.
* Verify that public images load without `403` errors.
* Verify that application routes load without `404` errors.
* Verify that CSS and JavaScript load from `public/build`.
* Verify that email, payments, SMS, uploads, receipts, and invoices work.

---

## Recommended Post-Deployment Commands

```bash
find bootstrap/cache -type f ! -name '.gitignore' -delete
find storage/framework/views -type f ! -name '.gitignore' -delete

chmod -R 775 storage
chmod -R 775 bootstrap/cache

find public/images -type d -exec chmod 755 {} \;
find public/images -type f -exec chmod 644 {} \;

chmod 644 public/.htaccess

php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

When a root `.htaccess` file is also used:

```bash
chmod 644 .htaccess
```

---

## Deployment Troubleshooting

### Public images return `403 Forbidden`

Check the file permissions inside:

```text
public/images
```

Image files should normally use:

```text
644
```

Directories should normally use:

```text
755
```

Run:

```bash
find public/images -type d -exec chmod 755 {} \;
find public/images -type f -exec chmod 644 {} \;
```

### Laravel routes return `404 Not Found`

Confirm that:

* The document root points to the Laravel `public` directory.
* Apache rewrite support is enabled.
* `public/.htaccess` exists.
* `public/.htaccess` uses permission `644`.

Run:

```bash
chmod 644 public/.htaccess
```

### Laravel returns an HTTP `500` error after deployment

Delete stale compiled Blade files and cached application files.

```bash
find bootstrap/cache -type f ! -name '.gitignore' -delete
find storage/framework/views -type f ! -name '.gitignore' -delete
php artisan optimize:clear
```

Then rebuild the production caches.

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### CSS or JavaScript changes do not appear

Run the frontend build locally:

```bash
npm run build
```

Upload and replace the complete:

```text
public/build
```

directory on the production server.

Do not copy only part of a generated CSS or JavaScript file into an existing hashed build file.

### Blade changes do not appear

Clear compiled Blade views.

```bash
find storage/framework/views -type f ! -name '.gitignore' -delete
php artisan view:clear
```

When terminal access is unavailable, delete the generated files manually from `storage/framework/views`, preserving `.gitignore`.

---

## Demo Administrator

### Email

```text
admin@azariadmin.com
```

### Password

```text
12345678
```

Change or remove the demo administrator credentials before using the application in a real production environment.

---

## Administrator Features

* Dashboard
* Booking Management
* Room Management
* Apartment Management
* Guest Management
* Identity Verification
* Payment Management
* Invoice and Receipt Management
* Reviews
* Homepage CMS
* Navigation Management
* Content Management
* Notifications
* Reports
* Website Settings
* Branding and Logo Management

---

## License

This project is provided for demonstration and educational purposes unless otherwise specified by the project owner.

---

<p align="center">
Built with Laravel for premium hospitality experiences.
</p>
