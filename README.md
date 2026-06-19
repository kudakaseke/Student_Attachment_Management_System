# SAMS MVP (Midlands State University)

Student Attachment Management System MVP that digitizes industrial attachment workflows for:
- Students
- Attachment Lecturers
- Company Supervisors
- System Administrator

## Stack
- Frontend: HTML, CSS, Vanilla JavaScript
- Backend: PHP (REST-style endpoints)
- Database: MySQL/MariaDB via phpMyAdmin (XAMPP)

## Core MVP Features Included
- Role-select login (`student`, `lecturer`, `supervisor`, `admin`)
- Student login by registration number format like `r218270v`
- Student placement submission + document uploads
- Lecturer approval/rejection + visit reports + student tracking
- Supervisor confirmation + feedback submission
- Admin management for departments, programs, companies, users
- Basic reports + CSV export
- Mobile-friendly interface

## Project Structure
- `public/` UI files
- `api/` backend endpoints
- `sql/schema.sql` full database schema and seed data
- `uploads/` uploaded files
- `config/config.php` database and upload settings

## Setup
1. Create database/tables:
   - Open phpMyAdmin (`http://localhost/phpmyadmin`).
   - Import `sql/schema_mysql.sql`.
2. Configure DB:
   - Edit `config/config.php` or set env vars:
   - `DB_DRIVER` (default `mysql`)
   - `DB_HOST` (default `127.0.0.1`)
   - `DB_PORT` (default `3306`)
   - `DB_NAME` (default `sams_db`)
   - `DB_USER` (default `root`)
   - `DB_PASS` (default empty)
   - `DB_CHARSET` (default `utf8mb4`)
3. Start local server from project root:
   - `php -S localhost:8000`
4. Open:
   - `http://localhost:8000/public/`

## Demo Logins
- Student: role=`student`, identifier=`r218270v`, password=`Student123!`
- Lecturer: role=`lecturer`, identifier=`lecturer@msu.ac.zw`, password=`Lecturer123!`
- Supervisor: role=`supervisor`, identifier=`supervisor@midlandstech.co.zw`, password=`Supervisor123!`
- Admin: role=`admin`, identifier=`admin@msu.ac.zw`, password=`Admin123!`

## Production Notes
- Replace plain-text seed passwords with `password_hash(...)` values.
- Enforce HTTPS and secure session cookie settings.
- Add file type validation/virus scanning for uploads.
- Add audit logging.
