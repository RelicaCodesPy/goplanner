# GoPlanner

Announcement, event and calendar management system for DEBESMSCAT. Built with plain PHP (PDO/MySQL), no framework.

## Features

- Role-based access: `super_admin`, `admin`, `instructor`, `student` (see Roles and accounts below)
- Announcements with audience targeting (college, program, year level, section)
- Event announcements with Google Calendar invite links and `.ics` attachments
- Automatic email notifications to targeted users when an announcement is published
- Email queue with daily rate limiting (`MAIL_DAILY_LIMIT`)
- Calendar, rooms, accounts management, analytics and activity logging

## Roles and accounts

The database defines the following values for `users.role`:

| Role | Implemented in code | Notes |
|---|---|---|
| `student` | Yes | Default role for self-registration; new accounts are saved as `student` with status `pending` until approved |
| `instructor` | Yes | Has dashboards, RBAC permissions and can create announcements and rooms |
| `admin` | Yes | College-level user, room and announcement management |
| `super_admin` | Yes | Full access to all features and settings |
| `faculty` | No | Present in the database enum but not referenced anywhere in the code; the code uses `instructor` for this role |
| `college_org` | No | Account rows and the `college_organizations` table can exist, but this build has no organization dashboard, permission set or announcement targeting |

Current limitations:

- Recognized roles are defined in `config/app.php` (`ROLES`) and `includes/rbac.php`; a role missing
  from those maps has no permissions.
- Because role checks compare against `instructor`, a user stored with the `faculty` role is not
  recognized: no permissions are granted and login routing falls back to the student dashboard
  (`api/auth/login.php`, `pages/login.php`).
- College organization accounts are routed to the student dashboard (no `college_org` case in the
  login switch) and the `college_organizations` table is not used by this build.
- Email notifications are role-agnostic: any targeted user who is `active`, not archived, has an
  email address and has not disabled email notifications receives them.

## Requirements

- XAMPP (Apache + PHP 8.1 or newer + MySQL/MariaDB)
- PHP extensions: `pdo_mysql`, `openssl`, `mbstring`
- A Gmail (or any SMTP) account with an App Password for sending mail

## Installation

1. Copy this project into the XAMPP web root, for example `C:\xampp\htdocs\goplanner`.
2. Create the database and import the schema:

   ```powershell
   & "C:\xampp\mysql\bin\mysql.exe" -u root -e "CREATE DATABASE goplanner_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
   & "C:\xampp\mysql\bin\mysql.exe" -u root goplanner_db < database\goplanner_schema.sql
   ```

   `database/goplanner_schema.sql` contains the table structure only. Add your own users
   (the first user should have the `super_admin` role) and colleges.

3. Create your environment file:

   ```powershell
   Copy-Item .env.example .env
   ```

4. Edit `.env` and set the app URL, database credentials and SMTP details:

   ```
   APP_URL="http://localhost/goplanner"
   APP_BASE_PATH="/goplanner"
   MAIL_USERNAME="you@gmail.com"
   MAIL_PASSWORD="your-16-char-app-password"
   MAIL_FROM_EMAIL="you@gmail.com"
   ```

   If the folder name contains a space (e.g. `goplanner 1`), use the URL-encoded form
   `http://localhost/goplanner%201` and `/goplanner%201` so redirects and email links stay valid.

5. Make sure these folders exist and are writable: `storage/logs`, `uploads/announcements`.

6. Open `http://localhost/goplanner` in a browser.

## Email notifications

When an announcement is published (`api/announcements/store.php`), emails are sent to all
users matching the targeting criteria (`includes/MailHelper.php::getTargetUsers`):

- only `active`, non-archived users
- users who did not opt out (`user_preferences.email_notifications`)
- matching `college_id`, `program_id`, `target_year`, `target_section`

Announcements with an event date receive a calendar invite (Google Calendar link plus `.ics`
attachment); plain announcements receive a simple email. Drafts are never emailed, and a user
who already received an announcement is skipped on re-send.

### Daily limit and queue

A maximum of `MAIL_DAILY_LIMIT` (450) emails are sent per day, counted from the `email_log`
table. Anything above the limit is stored in `email_queue` and drained later by the queue
processor.

Run the queue processor manually:

```powershell
& "C:\xampp\php\php.exe" .\api\mail\process_queue.php
```

Or schedule it (Windows Task Scheduler, daily):

```powershell
schtasks /Create /TN "GoPlanner Email Queue" `
  /TR "\"C:\xampp\htdocs\goplanner\cron\process_email_queue.bat\"" `
  /SC DAILY /ST 02:00 /F
```

The `.bat` wrapper writes output to `storage/logs/email_queue.log`. The processor can also be
triggered over the web with the secret from `.env`:
`/api/mail/process_queue.php?key=GOPLANNER_CRON_SECRET`.

### Troubleshooting: emails are not sent

1. Check `MAIL_ENABLED=true` in `.env` and that `MAIL_USERNAME` / `MAIL_PASSWORD` are set
   (Gmail requires an App Password, not the account password).
2. Confirm the PHPMailer files exist under `vendor/PHPMailer/src/`.
3. Confirm `config/env.php` and `config/mail.php` exist and that `.env` is present.
4. Check the PHP error log and `storage/logs/email_queue.log` for SMTP errors.
5. Verify recipients: announcement must be `published`, and users must be `active`, not
   archived, have an email address and not have email notifications disabled.

## Project layout

```
api/            form/AJAX endpoints (announcements, auth, accounts, mail)
components/     shared UI partials (sidebar, header, footer)
config/         app, auth, database, env loader and mail configuration
cron/           Windows Task Scheduler wrappers
database/       SQL schema
includes/       helpers (functions, auth, RBAC, MailHelper, flash)
pages/          screens (dashboard, announcements, calendar, rooms, accounts, settings)
storage/logs/   application and email queue logs
uploads/        user uploaded announcement attachments
vendor/         bundled PHPMailer
```

## Notes

- `.env` is intentionally ignored by Git. Never commit real SMTP or OAuth credentials.
- `APP_URL` is used to build links inside emails and `.ics` files, so it must match the URL
  you actually use to open the app.
