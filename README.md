# OraChart — Guda & Soriano Dental Clinic

Laravel 12 · PHP 8.2+ · MySQL 8 · Bootstrap 5 · Mailtrap SMTP

Rebuild of the HTML/CSS/JS prototype as a database-backed Laravel app, using the team's
`orachart_schema_v1.sql` as the base schema. Laravel migrations apply approved extensions to those tables.

## Setup

```bash
composer install
cp .env.example .env && php artisan key:generate

# 1. create the database (the script creates orachart_db and seeds reference data)
mysql -u root -p < database/schema/orachart_schema_v1.sql

# 2. edit .env -> DB_USERNAME / DB_PASSWORD and Mailtrap SMTP credentials
# 3. apply additive Laravel schema changes (safe on the current SQL schema)
php artisan migrate

# 4. public storage for the admin-managed clinic GCash QR
php artisan storage:link

# 5. demo accounts + real e-mail templates (replaces the 'TODO' bodies; safe to re-run)
php artisan db:seed

# 4. copy your pictures to public/images/  (logo-removebg-preview.png, logo.png)

php artisan serve            # http://localhost:8000
php artisan schedule:work    # sends queued reminders (in production use the cron line below)
```

For an existing database created from an earlier v1 schema, apply the needed upgrade scripts once: `database/schema/upgrade_patient_management.sql`, followed by `database/schema/upgrade_patient_consents.sql`. Skip any script whose changes are already present.

Production cron: `* * * * * cd /path/to/orachart && php artisan schedule:run >> /dev/null 2>&1`

Demo logins (password `123456`): `atreyu@gmail.com` (admin) · `pericano@gmail.com` (staff) ·
`Guda@gmail.com` / `Soriano@gmail.com` (dentists) · `patient@demo.com` (patient).
**Delete these before going live.**

## Mailtrap
Email Testing → Inboxes → SMTP Settings → copy username/password into `.env`. Every message
(verification, reset, booking, reminders) is recorded in `ntf_email_log` and visible to the admin
under *Audit & E-mail Logs*. A mail failure never blocks a booking; it shows as `failed`.

## What is in
| Area | Notes |
|---|---|
| Public site | Landing page; services, prices, promos, package inclusions and dentists all read from the DB |
| Auth | Register (+ Data Privacy consent → `usr_consents`), e-mail verification, forgot/reset password (hashed tokens), lock after 5 failed logins, 15-min inactivity logout (`sys_settings`) |
| Booking | Service → dentist (or "no preference" round-robin) → date → live slots. Temporary 5-min slot hold (`apt_slot_locks`), `SELECT … FOR UPDATE` re-check on submit, min-notice, buffers, daily capacity, reserved emergency windows, emergency bookings |
| Appointments | Confirm / reject / cancel / complete / no-show / reschedule (linked rows) / linked follow-up visits for multi-visit treatments; calendar; flags + e-mails when time-off or closures collide |
| Dentists | Own weekly hours + time off |
| Admin | Default weekly hours with recurring weekday adjustments, date overrides, booking rules, specialties, emergency windows, audit & e-mail logs |
| Odontogram | FDI chart, findings per tooth/surface, append-only history; dentists and admins can record findings |
| E-mail | verify, reset, received/confirmed/declined/cancelled, reminder, thank-you, recall, schedule-changed |

## Decisions you should review
1. **One procedure per appointment** — the schema has a single `service_id` per `apt_appointments` row, so the prototype's multi-select became single-select.
2. **Date of birth is now required at registration** (`pat_patients.birth_date` is `NOT NULL`).
3. **Auto-confirm** keeps the prototype's behaviour. Turn it off in *Clinic Settings → Booking rules* for a staff approval flow (pending → confirm/reject). Reject is stored as `cancelled` + `rejection_reason`.
4. **Specialty enforcement is off by default.** Many services in the SQL seed need Prosthodontics/Orthodontics/etc. (placeholders per the SQL comments). Seeded dentists only have General Dentistry; flip *Only offer a procedure to dentists with its required specialty* once the clinic confirms.
5. Passwords are min 8 characters (prototype: 6).
6. Payments and installments support manual GCash proof review. Admins and dentists review plans and proofs. The clinic can adjust installment minimums, maximum term, flat finance rate per approval, and GCash details. No payment gateway or automatic late fees are used.
7. Extra settings keys (`auto_confirm_bookings`, `reminders_enabled`, `enforce_service_specialty`, `emergency_release_minutes`, `round_robin_last_dentist_id`, …) are *rows*, not schema changes; defaults live in `app/Support/Settings.php`.

## Code map
`app/Services/AvailabilityService.php` (slot rules) · `BookingService.php` (transactions, reschedule, follow-ups) ·
`Notifier.php` (Mailtrap + `ntf_email_log`) · `app/Support/SlotCalculator.php` (pure maths, unit-tested) ·
`public/js/booking.js`, `odontogram.js` · `routes/web.php` · `database/seeders/`.

Run the unit test: `php artisan test` (SlotCalculator).
