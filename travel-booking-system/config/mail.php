<?php
// ============================================================
//  SkyRoute — Mail Configuration
//  Uses PHPMailer + SMTP (works with Gmail, Outlook, Mailtrap)
// ============================================================

// ── SMTP CREDENTIALS ────────────────────────────────────────
// Option A: Gmail (enable "App Passwords" in Google Account)
// Option B: Outlook / Office 365
// Option C: Mailtrap.io (recommended for local/dev testing)

define('MAIL_HOST',       'smtp.gmail.com');   // or smtp.mailtrap.io
define('MAIL_PORT',       587);                // 587 for TLS, 465 for SSL
define('MAIL_ENCRYPTION', 'tls');              // 'tls' or 'ssl'
define('MAIL_USERNAME',   'your@gmail.com');   // your SMTP login
define('MAIL_PASSWORD',   'your_app_password');// Gmail App Password or Mailtrap password
define('MAIL_FROM_EMAIL', 'no-reply@skyroute.com');
define('MAIL_FROM_NAME',  'SkyRoute Travel');

// ── MAILTRAP EXAMPLE (easiest for local dev) ────────────────
// Sign up free at https://mailtrap.io → Inboxes → SMTP Settings
// define('MAIL_HOST',       'sandbox.smtp.mailtrap.io');
// define('MAIL_PORT',       2525);
// define('MAIL_ENCRYPTION', 'tls');
// define('MAIL_USERNAME',   'your_mailtrap_user');
// define('MAIL_PASSWORD',   'your_mailtrap_pass');

// ── GMAIL QUICK GUIDE ───────────────────────────────────────
// 1. Go to myaccount.google.com → Security → 2-Step Verification ON
// 2. Then: myaccount.google.com/apppasswords
// 3. Create App Password for "Mail" → copy the 16-char password
// 4. Paste it as MAIL_PASSWORD above (no spaces)
?>
