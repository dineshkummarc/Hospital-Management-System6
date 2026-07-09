# 📧 Email Notification System — Setup Guide

## Overview

The Smart Hospital system sends automated HTML emails for every key event:

| Event | Patient | Doctor | Admin |
|---|---|---|---|
| Registration | ✅ Welcome | ✅ Welcome | ✅ Alert |
| Appointment Booked | ✅ Confirm + Token | ✅ New Request | ✅ Log |
| Appointment Approved | ✅ Approved | ✅ Update | — |
| Appointment Rejected | ✅ Declined | ✅ Update | — |
| Appointment Completed | ✅ Complete | ✅ Done | — |
| Prescription Created | ✅ Rx Ready | ✅ Confirmed | ✅ Log |
| Bill Generated | ✅ Invoice | — | ✅ Revenue |
| Payment Confirmed | ✅ Receipt | — | ✅ Paid |

---

## Step 1 — Install PHPMailer

### Option A: Composer (recommended)
```bash
cd hospital_management
composer install
```

### Option B: Manual
1. Download from https://github.com/PHPMailer/PHPMailer/releases
2. Extract and copy these 3 files to `vendor/phpmailer/src/`:
   - `PHPMailer.php`
   - `SMTP.php`
   - `Exception.php`

---

## Step 2 — Import Email Schema
Run in phpMyAdmin:
```sql
source database/email_schema.sql;
```
Or copy-paste the contents into the phpMyAdmin SQL tab.

---

## Step 3 — Configure SMTP (Admin Panel)

1. Log in as **Admin** → `admin@hospital.com` / `password`
2. Sidebar → **Email Settings** (`admin/email_settings.php`)
3. Fill in your SMTP details:

### Gmail (most common for development)
| Field | Value |
|---|---|
| SMTP Host | `smtp.gmail.com` |
| SMTP Port | `587` |
| Encryption | `TLS` |
| Username | `your.email@gmail.com` |
| Password | Your **App Password** (NOT your Gmail password) |
| Sender Email | `your.email@gmail.com` |

### Getting a Gmail App Password
1. Go to **Google Account → Security**
2. Enable **2-Step Verification** (required)
3. Search for **App Passwords**
4. Select **Mail** + **Other (Custom name)** → type "Smart Hospital"
5. Copy the 16-character password → paste into SMTP Password field

### Other providers
| Provider | Host | Port | Encryption |
|---|---|---|---|
| Outlook/Hotmail | `smtp.office365.com` | 587 | TLS |
| Yahoo | `smtp.mail.yahoo.com` | 587 | TLS |
| Zoho | `smtp.zoho.com` | 587 | TLS |
| SendGrid | `smtp.sendgrid.net` | 587 | TLS |
| Mailgun | `smtp.mailgun.org` | 587 | TLS |

---

## Step 4 — Test the Configuration
On the Email Settings page, click **Send Test Email** and enter your email address. You should receive a confirmation email within seconds.

---

## File Structure

```
hospital_management/
├── config/
│   └── mail_config.php          ← SMTP constants + getMailConfig()
├── includes/
│   └── Mailer.php               ← sendEmail() + all HTML templates
├── vendor/
│   └── phpmailer/
│       ├── autoload.php         ← PSR-4 autoloader
│       └── src/
│           ├── PHPMailer.php    ← Main class (install real version)
│           ├── SMTP.php         ← SMTP transport
│           └── Exception.php   ← Exception class
├── logs/
│   └── email_errors.log         ← SMTP failure log
├── admin/
│   └── email_settings.php       ← Admin SMTP configuration UI
├── ajax/
│   ├── send_test_email.php      ← Test email AJAX endpoint
│   └── get_email_log.php        ← Fetch log entries AJAX endpoint
└── database/
    └── email_schema.sql         ← email_settings + email_logs tables
```

---

## How Notifications Fire

Every event calls a dedicated dispatcher in `config.php`:

```php
// In patient/book_appointment.php — after INSERT
notifyAppointmentBooked($newApptId);

// In admin/appointments.php — after status UPDATE  
notifyAppointmentStatusChange($id, $status);

// In doctor/prescriptions.php — after INSERT
notifyPrescriptionCreated($newRxId);

// In admin/billing.php — after INSERT
notifyBillingCreated($newBillId);

// In admin/billing.php — when status set to 'paid'
notifyPaymentCompleted($billId);

// In register.php — after new user INSERT
notifyUserRegistered($userId);
```

Each dispatcher:
1. Fetches all relevant data from the DB
2. Calls `createNotification()` for each recipient (patient, doctor, admin)
3. `createNotification()` inserts a DB row AND sends the email

---

## Disabling Email (keep DB notifications only)

In `config/mail_config.php`, set:
```php
define('MAIL_ENABLED', false);
```

This stops all outbound email but keeps in-app notifications working.

---

## Troubleshooting

| Problem | Solution |
|---|---|
| "SMTP credentials not configured" | Save settings in Admin → Email Settings |
| "Failed to send" with Gmail | Use App Password, not Gmail password |
| Test email works, events don't | Ensure `MAIL_ENABLED = true` in mail_config.php |
| PHP mail() fallback used | Install full PHPMailer via Composer |
| Check error details | View `logs/email_errors.log` or Admin → Email Settings → Error Log |

---

## Security Notes

- SMTP passwords are stored in the `email_settings` DB table
- Passwords are **not** shown in the admin UI (only a ✓ Saved indicator)
- CSRF tokens protect all settings form submissions
- The `logs/` directory is protected by `.htaccess` (blocks `.log` file access)
- Never commit real SMTP credentials to version control
