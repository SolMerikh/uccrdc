# UCC – RDC ISSN Application Portal (Native PHP)

Tech: **Native PHP + MySQL + Bootstrap 5 + Font Awesome** (CDN)

## 1) Prerequisites
- XAMPP (Apache + MySQL)
- PHP 8+ recommended

## 2) Database Setup
1. Open phpMyAdmin.
2. Create the DB + tables by importing:
   - [database/schema.sql](database/schema.sql)

Default seeded admin:
- Email: `admin@ucc.local`
- Password: `Admin@12345`

## 3) App Config
Edit:
- [app/config.php](app/config.php)

Set your DB name/user/pass if different.

## 4) Run
- Start Apache + MySQL in XAMPP
- Open:
  - `http://localhost/uccrdc/`

## 5) Roles
- **Author**: register from landing page modal, then submit publications.
- **Staff**: created by Admin in Accounts.
- **Admin**: reviews and approves/rejects submissions; manages formats and accounts.

## 5.1) Email Notifications
Emails are sent when:
- Author submits a new application (Author gets confirmation; Admin(s) get a “new submission” notice)
- Admin approves/rejects a submission and/or leaves a comment (Author gets an update)

Configure the mail driver in:
- [app/config.php](app/config.php)

For local development, default is `driver = log` which writes emails to:
- `storage/mail.log`

To send real emails reliably on XAMPP, use `driver = smtp`.

Example (Gmail SMTP) in [app/config.php](app/config.php):
- `mail.driver = smtp`
- `mail.smtp.host = smtp.gmail.com`
- `mail.smtp.port = 587`
- `mail.smtp.encryption = tls`
- `mail.smtp.username = your_gmail@gmail.com`
- `mail.smtp.password = your_app_password`

Recommended:
- Set `mail.from_email` to the same Gmail address as `mail.smtp.username`.

For Gmail, you typically must use an **App Password** (not your normal password).

Steps:
1. Enable Google 2‑Step Verification on the account.
2. Create an App Password (Mail) in your Google Account security settings.
3. Use the generated 16‑character app password in `mail.smtp.password`.

Admin notification recipients:
- By default the system emails all **Active Admin** accounts in the database.
- Make sure your Admin account email is a real email address, or set `mail.admin_notify_to` (comma-separated) in config.

## 6) Uploads
Uploads are stored under:
- `uploads/ids` (valid IDs)
- `uploads/pubs` (publication PDFs)

Direct access to `uploads/` is blocked via [uploads/.htaccess](uploads/.htaccess). PDFs are served through:
- [files/submission_pdf.php](files/submission_pdf.php)
- [files/draft_pdf.php](files/draft_pdf.php)

Note: `.htaccess` requires Apache `AllowOverride` enabled.

## Optional: Logo
Place your university logo at:
- `assets/img/ucc-logo.png`

(If missing, the landing page will fallback to an icon.)
