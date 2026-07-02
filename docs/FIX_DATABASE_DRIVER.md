# Fix: PostgreSQL Driver Not Found

## The Problem
PHP's PostgreSQL PDO driver (pdo_pgsql) is not enabled in your XAMPP installation.

## Solution: Enable PostgreSQL Extension

### Step 1: Open php.ini
1. Open XAMPP Control Panel
2. Click "Config" next to Apache
3. Select "php.ini"

### Step 2: Find and Uncomment These Lines
Search for these lines (Ctrl+F) and remove the semicolon (;) at the start:

```ini
;extension=pdo_pgsql
;extension=pgsql
```

Change to:
```ini
extension=pdo_pgsql
extension=pgsql
```

### Step 3: Restart Apache
1. In XAMPP Control Panel, click "Stop" for Apache
2. Click "Start" again

### Step 4: Verify
Visit: http://localhost/Dddcet/test-db.php

Should show: ✅ Connection successful!

---

## Alternative: Quick Fix Script

If you can't find the lines, add them manually:

1. Open php.ini
2. Find the section with other `extension=` lines
3. Add these two lines:
   ```ini
   extension=pdo_pgsql
   extension=pgsql
   ```
4. Save and restart Apache

## Check if Extensions are Available

Create a file `info.php` with:
```php
<?php phpinfo(); ?>
```

Visit it and search for "pgsql" - you should see it listed after enabling.
