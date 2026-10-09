# Gold Motors API — Windows Setup

Follow these steps IN ORDER. Do not skip any.

---

## STEP 1 — Install XAMPP

1. Go to: https://www.apachefriends.org/download.html
2. Download XAMPP for Windows (PHP 8.2 or higher)
3. Install it — default location is C:\xampp
4. Open the XAMPP Control Panel
5. Start **Apache** and **MySQL** — both must show green

---

## STEP 2 — Install Composer

1. Go to: https://getcomposer.org/Composer-Setup.exe
2. Download and run the installer (Composer-Setup.exe)
3. During install it will ask for the PHP path — point it to:
   C:\xampp\php\php.exe
4. Finish the install
5. Open a NEW Command Prompt (important — close any old ones)
6. Type: composer --version
   You should see something like: Composer version 2.x.x

---

## STEP 3 — Create the database in phpMyAdmin

1. Open your browser and go to: http://localhost/phpmyadmin
2. Click "New" on the left sidebar
3. Database name: gold_motors
4. Collation: utf8mb4_unicode_ci
5. Click "Create"

---

## STEP 4 — Set up this project

Open Command Prompt and navigate to this folder:

```
cd C:\path\to\gold-motors-api
```

Then run these commands ONE BY ONE:

```
composer install
```
(This takes 2-5 minutes — wait for it to finish)

```
copy .env.example .env
```

```
php artisan key:generate
```

```
php artisan migrate
```

```
php artisan db:seed
```

```
php artisan storage:link
```

```
php artisan serve
```

---

## STEP 5 — Confirm it is working

Open your browser and go to: http://localhost:8000

You should see:
{
  "name": "Gold Motors API",
  "version": "1.0.0",
  "status": "running"
}

---

## Dealer Login (after seeding)

- Email:    admin@goldmotors.com
- Password: GoldAdmin2025!

---

## If composer install fails

The most common cause on Windows is PHP extensions not enabled.

1. Open C:\xampp\php\php.ini in Notepad
2. Find these lines and remove the semicolon (;) at the start of each:
   ;extension=zip        →  extension=zip
   ;extension=openssl    →  extension=openssl
   ;extension=pdo_mysql  →  extension=pdo_mysql
   ;extension=mbstring   →  extension=mbstring
   ;extension=xml        →  extension=xml
   ;extension=fileinfo   →  extension=fileinfo
3. Save the file
4. Run composer install again

---

## Common errors

SQLSTATE[HY000] [2002] No connection
→ MySQL is not running. Open XAMPP Control Panel and start MySQL.

Class not found / Autoload error
→ Run: composer dump-autoload

php is not recognized as a command
→ Add PHP to Windows PATH:
  Control Panel → System → Advanced → Environment Variables
  Under System Variables, find Path → Edit → New
  Add: C:\xampp\php
  Click OK, close and reopen Command Prompt
