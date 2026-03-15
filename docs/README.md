# EduBoard — School Digital Notice Board

## Project Structure

```
noticeboard/                          ← Project root (place in htdocs/)
│
├── index.php                         ← Entry point — redirects to login or dashboard
├── .htaccess                         ← Routes traffic, protects internal folders
│
├── app/                              ← Back-end logic (NOT web-accessible)
│   ├── .htaccess                     ← Deny all direct HTTP access
│   ├── config/
│   │   └── config.php                ← DB credentials, constants, autoloader
│   ├── core/
│   │   ├── Database.php              ← MySQLi singleton wrapper
│   │   ├── Auth.php                  ← Login, logout, OTP, session guards
│   │   ├── header.php                ← Shared sidebar + app shell (HTML open)
│   │   └── footer.php                ← Shared closing HTML + JS
│   └── helpers/
│       ├── NoticeHelper.php          ← Notice queries, filters, display helpers
│       └── Utils.php                 ← sanitize(), flash(), redirect(), timeAgo()
│
├── public/                           ← All web-accessible pages
│   ├── dashboard.php                 ← Main notice feed + stats
│   ├── auth/
│   │   ├── login.php
│   │   ├── logout.php
│   │   ├── forgot-password.php
│   │   └── profile.php
│   ├── notices/
│   │   ├── index.php                 ← Browse all notices (paginated + filtered)
│   │   ├── create.php                ← Publish new / edit existing notice
│   │   ├── detail.php                ← Full notice view with attachment
│   │   ├── manage.php                ← Admin/Teacher notice management table
│   │   ├── delete.php                ← Soft-delete handler
│   │   └── attachment.php            ← Secure BLOB file server
│   └── admin/
│       ├── users.php                 ← User management (Admin only)
│       └── categories.php            ← Category management (Admin only)
│
├── assets/                           ← Static files (CSS, JS)
│   ├── css/style.css
│   └── js/app.js
│
├── database/                         ← SQL scripts (NOT web-accessible)
│   ├── .htaccess
│   └── schema.sql                    ← Full schema + seed data
│
└── docs/
    └── README.md
```

## Setup

1. Copy `noticeboard/` to `htdocs/`
2. Import `database/schema.sql` via phpMyAdmin
3. Edit `app/config/config.php` with your DB credentials
4. Visit `http://localhost/noticeboard/`

## Demo Credentials

| Role    | Email                | Password    |
|---------|----------------------|-------------|
| Admin   | admin@school.edu     | Admin@123   |
| Teacher | teacher@school.edu   | Teacher@123 |
| Student | student@school.edu   | Student@123 |
