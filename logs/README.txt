This directory stores application log files.

Files created here:
  email_errors.log  — SMTP send failures logged by Mailer.php

This directory is writable by the web server (PHP).
On XAMPP the default user is usually sufficient.

For Linux/production servers, run:
  chmod 755 logs/
  chown www-data:www-data logs/
