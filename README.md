# Whizz Hire — Pre-Launch Landing Page

A clean, responsive pre-launch landing page for Whizz Hire with PHP/MySQL backend for waitlist signups.

## File Structure

```
whizzhire/
├── index.html              # Landing page (HTML + Tailwind CSS + JS)
├── admin.php               # Admin dashboard (view, search, export subscribers)
├── api/
│   └── waitlist.php        # API endpoint for form submissions
├── database/
│   └── setup.sql           # MySQL database & table creation script
└── README.md
```

## Setup Instructions

### 1. Database Setup

Run the SQL script to create the database and table:

```bash
mysql -u root -p < database/setup.sql
```

Or import `database/setup.sql` via phpMyAdmin.

### 2. Configure Database Credentials

Open `api/waitlist.php` and update these constants with your credentials:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'whizzhire');
define('DB_USER', 'root');
define('DB_PASS', '');
```

### 3. Deploy

Place the project in your web server's document root:

- **XAMPP**: `htdocs/whizzhire/`
- **LAMP/LEMP**: `/var/www/html/whizzhire/`
- **Shared hosting**: `public_html/whizzhire/`

Then visit: `http://localhost/whizzhire/`

## Admin Dashboard

Access the admin panel to view, search, and export your waitlist subscribers.

**URL:** `http://localhost/whizzhire/admin.php`

**Default password:** `whizzhire2026`

> ⚠️ **Important:** Change the default password before going live. Open `admin.php` and update this line:
> ```php
> define('ADMIN_PASSWORD', 'whizzhire2026'); // Change this!
> ```

### Admin Features

- **Stats overview** — Total signups, candidates, businesses, and today's count
- **Subscriber table** — Email, type, IP address, and signup date
- **Filter tabs** — View All / Candidates only / Businesses only
- **Search** — Find subscribers by email
- **Pagination** — 25 results per page
- **CSV export** — Download all, candidates only, or businesses only
- **Session-based auth** — Password-protected with logout

---

## Landing Page Features

- **Responsive** — Looks great on mobile, tablet, and desktop
- **Two waitlist forms** — Candidate and Business, saved separately
- **Duplicate detection** — Same email + type combo won't be saved twice
- **Input validation** — Client-side (JS) and server-side (PHP)
- **Security** — PDO prepared statements, input sanitization, CORS-safe
- **Smooth UX** — Loading states, toast notifications, error highlighting
- **Animated** — Subtle fade-up entrance animations
