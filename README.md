# 🏥 Smart Hospital Management System

A complete, production-ready Hospital Management System built with PHP, MySQL, Bootstrap 5, and modern web technologies.

---

## 🚀 Quick Setup (XAMPP)

### Step 1 — Prerequisites
- XAMPP (PHP 7.4+ or PHP 8.x, MySQL 5.7+/MariaDB 10.3+)
- Download: https://www.apachefriends.org/

### Step 2 — Install the Project
1. Extract the `hospital_management` folder into `C:\xampp\htdocs\`
2. Final path should be: `C:\xampp\htdocs\hospital_management\`

### Step 3 — Import the Database
1. Start **Apache** and **MySQL** in XAMPP Control Panel
2. Open your browser → go to `http://localhost/phpmyadmin`
3. Click **New** (left sidebar) → create database named `hospital_management`
4. Select the new database → click **Import** tab
5. Choose `database/hospital.sql` → click **Go**

### Step 4 — Configure the App
Open `config.php` and verify these settings match your XAMPP:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');          // Default XAMPP has no password
define('DB_NAME', 'hospital_management');
define('APP_URL',  'http://localhost/hospital_management');
```

### Step 5 — Run the App
Open: **http://localhost/hospital_management**

---

## 🔑 Demo Login Credentials

| Role    | Email                  | Password   |
|---------|------------------------|------------|
| Admin   | admin@hospital.com     | password   |
| Doctor  | rajesh@hospital.com    | password   |
| Doctor  | priya@hospital.com     | password   |
| Patient | amit@email.com         | password   |

> **Register new accounts** at `/register.php`

---

## 📁 Project Structure

```
hospital_management/
│
├── index.php              ← Professional landing page
├── login.php              ← Login page
├── register.php           ← Registration (patient/doctor)
├── logout.php             ← Session destroy
├── dashboard.php          ← Role-based redirect
├── forgot_password.php    ← Password reset UI
├── config.php             ← DB config + helpers
├── .htaccess              ← Security + Apache settings
├── 404.php                ← Custom error page
│
├── admin/                 ← Admin panel
│   ├── dashboard.php      ← Stats + charts overview
│   ├── manage_doctors.php ← CRUD doctors
│   ├── manage_patients.php← View/manage patients
│   ├── departments.php    ← Manage departments
│   ├── appointments.php   ← Approve/reject appointments
│   ├── prescriptions.php  ← View all prescriptions
│   ├── billing.php        ← Create and track invoices
│   ├── reports.php        ← Analytics + Chart.js
│   ├── profile.php        ← Admin profile settings
│   ├── print_bill.php     ← Printable invoice
│   ├── print_prescription.php
│   └── patient_detail.php ← Full patient history
│
├── doctor/                ← Doctor panel
│   ├── dashboard.php      ← Today's schedule
│   ├── appointments.php   ← Accept/reject appointments
│   ├── prescriptions.php  ← Write prescriptions
│   ├── patients.php       ← My patients list
│   ├── profile.php        ← Doctor profile
│   └── print_prescription.php
│
├── patient/               ← Patient panel
│   ├── dashboard.php      ← Patient overview
│   ├── book_appointment.php← Book new appointment
│   ├── appointments.php   ← View/cancel appointments
│   ├── prescriptions.php  ← View prescriptions
│   ├── medical_records.php← Upload/manage records
│   ├── billing.php        ← View invoices/bills
│   ├── profile.php        ← Edit profile
│   └── print_prescription.php
│
├── ajax/                  ← AJAX endpoints
│   ├── get_notifications.php
│   ├── mark_notification_read.php
│   ├── get_prescription.php
│   └── get_doctors.php
│
├── includes/              ← Shared layout
│   ├── layout_header.php  ← Sidebar + topbar
│   └── layout_footer.php  ← Scripts + closing tags
│
├── assets/
│   ├── css/style.css      ← Global styles
│   └── js/app.js          ← Global JavaScript
│
├── uploads/               ← User-uploaded files
│   └── records/           ← Medical records
│
└── database/
    └── hospital.sql       ← Full DB schema + seed data
```

---

## ✨ Features

### 🔐 Authentication
- Secure login with bcrypt password hashing
- Role-based access (Admin / Doctor / Patient)
- Session management with regeneration
- Forgot password UI

### 👨‍💼 Admin Panel
- Dashboard with live stats and Chart.js analytics
- Full CRUD for doctors and departments
- Patient management with detail view
- Appointment approval/rejection
- Billing and invoice generation (printable)
- Prescription viewer
- Reports: monthly appointments, revenue, doctor performance

### 👨‍⚕️ Doctor Panel
- Today's schedule at a glance
- Accept/reject/complete appointments
- Write prescriptions with multi-medicine form
- View all past patients
- Printable prescriptions

### 👤 Patient Panel
- Department + doctor selection booking flow
- View/cancel appointments with status tracking
- Read prescriptions with medicine details
- Upload and manage medical records
- View itemized bills and invoices
- Complete profile with medical history

### 🎨 UI/UX
- Glassmorphism-inspired cards
- Gradient sidebar with role-based navigation
- Chart.js analytics (bar, line, doughnut)
- Toast notifications
- Confirm modals
- AJAX live updates
- Fully responsive (mobile-first)
- Page loader animation
- Print-ready invoice and prescription templates

---

## 🔒 Security
- All queries use PDO prepared statements (SQL injection safe)
- Password hashing with bcrypt (cost 12)
- Session regeneration on login
- Role authorization on every protected page
- Input sanitization with `htmlspecialchars`
- File upload validation (type + size)
- `.htaccess` denies direct access to `.sql`, config files, and PHP in uploads

---

## 🛠 Tech Stack

| Layer     | Technology                          |
|-----------|-------------------------------------|
| Frontend  | HTML5, CSS3, Bootstrap 5, JS (ES6+) |
| Icons     | Bootstrap Icons 1.11                |
| Charts    | Chart.js 4                          |
| Fonts     | Google Fonts (Inter, Plus Jakarta Sans) |
| Backend   | Core PHP 8.x (no framework)         |
| Database  | MySQL / MariaDB                      |
| Server    | Apache via XAMPP                     |

---

## 📞 Support
For issues or customization, check the code comments throughout each file.

> Built as a minor College Project — Smart Hospital Management System
