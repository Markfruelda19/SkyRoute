# ✈ SkyRoute — Travel Booking System

A full-stack PHP travel booking web app with flights, hotels, user dashboard, and admin panel.

---

## 🚀 Setup Instructions

### 1. Requirements
- PHP 7.4+
- MySQL 5.7+ or MariaDB
- Apache (XAMPP / WAMP / LAMP) with `mod_rewrite` enabled

### 2. Installation

**Step 1** — Copy the project folder into your server root:
```
C:/xampp/htdocs/travel-booking-system/   (XAMPP on Windows)
/var/www/html/travel-booking-system/      (Linux/Mac)
```

**Step 2** — Create the database. Open phpMyAdmin or MySQL CLI:
```sql
source /path/to/travel-booking-system/database/schema.sql
```
Or paste the contents of `database/schema.sql` into phpMyAdmin's SQL tab.

**Step 3** — Configure database connection in `config/db.php`:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');       // your MySQL username
define('DB_PASS', '');           // your MySQL password
define('DB_NAME', 'travel_booking');
```

Also update `SITE_URL` if needed:
```php
define('SITE_URL', 'http://localhost/travel-booking-system');
```

**Step 4** — Open in browser:
```
http://localhost/travel-booking-system/
```

---

## 🔑 Demo Credentials

| Role  | Email               | Password |
|-------|---------------------|----------|
| Admin | admin@travel.com    | password |
| User  | Register a new account |        |

> ⚠️ The admin password in the seed data uses `password_hash('password', PASSWORD_DEFAULT)`.
> If login fails, update it manually:
> ```sql
> UPDATE users SET password = '$2y$10$...' WHERE email = 'admin@travel.com';
> ```
> Generate a new hash with PHP: `echo password_hash('admin123', PASSWORD_DEFAULT);`

---

## 📁 Project Structure

```
travel-booking-system/
│
├── admin/
│   └── index.php          ← Admin panel (overview, bookings, users, flights, hotels)
│
├── assets/
│   └── css/style.css      ← Main stylesheet
│
├── config/
│   └── db.php             ← Database config & constants
│
├── database/
│   └── schema.sql         ← MySQL schema + seed data
│
├── includes/
│   └── auth.php           ← Auth helpers (login, session, sanitize)
│
├── index.php              ← Homepage + search
├── login.php              ← Login
├── register.php           ← Registration
├── logout.php             ← Session destroy
├── booking.php            ← Booking + payment simulation
├── dashboard.php          ← User dashboard (bookings, profile)
└── README.md
```

---

## ✨ Features

### User Side
- Register / Login / Logout with password hashing
- Search flights by origin & destination
- Search hotels by location
- Book flights (select passengers, payment simulation)
- Book hotels (select dates, guests, payment simulation)
- View all bookings with status
- Cancel confirmed bookings
- Update profile

### Admin Side
- Overview dashboard with revenue charts (Chart.js)
- View all bookings across all users
- Update booking status (confirmed / pending / cancelled)
- View all users
- Add / Delete flights
- Add / Delete hotels

---

## 🔮 Next Steps (Advanced Upgrades)

1. **Password change** — Add a change password form in the profile tab
2. **Email confirmation** — Use PHPMailer to send booking receipts
3. **PDF receipts** — Generate downloadable booking confirmations with TCPDF
4. **Amadeus API** — Replace mock data with real flight search
5. **Stripe** — Replace fake payment with real Stripe Checkout
6. **Dynamic pricing** — Increase prices on weekends or when seats < 20
7. **Search filters** — Sort by price, filter by airline/rating
8. **Pagination** — Paginate long booking/user lists in admin

---

## 🛠 Tech Stack
- **Frontend**: HTML5, CSS3 (custom, no Bootstrap), vanilla JavaScript
- **Backend**: PHP (core, no framework)
- **Database**: MySQL
- **Charts**: Chart.js (CDN)
- **Fonts**: Google Fonts (Playfair Display + DM Sans)
