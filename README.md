<p align="center">
  <img src="/public/images/logo-light.png" alt="The Azari Residences" width="320">
</p>

<p align="center">
  <strong>THE AZARI RESIDENCES</strong><br>
  Africa's Finest Address
</p>

<p align="center">
A modern hotel and apartment booking platform built with Laravel, designed for premium hospitality businesses. The platform provides a seamless booking experience for guests while giving administrators complete control over rooms, apartments, reservations, payments, content, users, identities, services, and day-to-day operations through an intuitive management dashboard.
</p>

---

**Features**

- Luxury hotel and serviced apartment booking
- Apartment and room availability management
- Online payment processing
- Booking verification portal
- Guest dashboard
- Admin dashboard
- Identity verification (KYC)
- Additional guest management
- Invoice and receipt generation
- Booking history
- User notifications
- Support ticket system
- Service requests
- Concierge management
- Housekeeping requests
- Airport transfer requests
- Restaurant reservations
- Content Management System (CMS)
- Homepage section management
- Navigation management
- Review management
- Responsive mobile experience
- Email notifications
- Role-based access control
- Dynamic branding and logo management
- SEO optimized public pages
- Production-ready architecture

---

**Technology Stack**

- Laravel 13
- PHP 8.4+
- MySQL
- Blade
- Vite
- Alpine.js
- Tailwind CSS
- Laravel Breeze

---

**Requirements**

- PHP 8.4 or later
- MySQL 8+
- Composer
- Node.js 20+
- npm

---

**Installation**

Clone the repository.

Install dependencies.

```bash
composer install
npm install
```

Copy the environment file.

```bash
cp .env.example .env
```

Configure your database credentials inside `.env`.

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

Cache the application.

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Start the application.

```bash
php artisan serve
```

---

**Shared Hosting (cPanel)**

- Upload the project files.
- Point your domain's document root to the `public` directory.
- Configure the `.env` file.
- Import the database.
- Ensure the `storage` and `bootstrap/cache` directories are writable.
- Run the production asset build before deployment.

---

**Demo Administrator**

Email

admin@azariadmin.com

Password

12345678

---

**Administrator Features**

- Dashboard
- Booking Management
- Room Management
- Apartment Management
- Guest Management
- Identity Verification
- Payment Management
- Invoice & Receipt Management
- Reviews
- Homepage CMS
- Navigation Management
- Content Management
- Notifications
- Reports
- Website Settings
- Branding & Logo Management

---

**License**

This project is provided for demonstration and educational purposes unless otherwise specified by the project owner.

---

<p align="center">
Built with Laravel for premium hospitality experiences.
</p>