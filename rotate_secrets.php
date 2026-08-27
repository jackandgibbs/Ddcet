<?php
$c = file_get_contents('c:/xampp/htdocs/Dddcet/.env');
$c = preg_replace('/^SUPABASE_KEY=.*$/m', 'SUPABASE_KEY=eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.ROTATED_KEY_PLEASE_REPLACE_IN_SUPABASE', $c);
$c = preg_replace('/^SUPABASE_DB_PASS=.*$/m', 'SUPABASE_DB_PASS="ROTATED_PASSWORD_PLEASE_REPLACE"', $c);
$c = preg_replace('/^GOOGLE_CLIENT_ID=.*$/m', 'GOOGLE_CLIENT_ID=ROTATED_CLIENT_ID.apps.googleusercontent.com', $c);
$c = preg_replace('/^GOOGLE_CLIENT_SECRET=.*$/m', 'GOOGLE_CLIENT_SECRET=GOCSPX-ROTATED_SECRET', $c);
$c = preg_replace('/^RAZORPAY_KEY_ID=.*$/m', 'RAZORPAY_KEY_ID=rzp_live_ROTATED_KEY', $c);
$c = preg_replace('/^RAZORPAY_KEY_SECRET=.*$/m', 'RAZORPAY_KEY_SECRET=ROTATED_SECRET', $c);
$c = preg_replace('/^ADMIN_TOTP_SECRET=.*$/m', 'ADMIN_TOTP_SECRET=ABCDEFGHIJKLMNOPQRSTUVWXYZ234567', $c);
$c = preg_replace('/^BREVO_API_KEY=.*$/m', 'BREVO_API_KEY=xkeysib-ROTATED_API_KEY', $c);
$c = preg_replace('/^CRON_SECRET=.*$/m', 'CRON_SECRET=ROTATED_CRON_SECRET_STRING', $c);
$c = preg_replace('/^WB_ADMIN_PASSWORD_HASH=.*$/m', 'WB_ADMIN_PASSWORD_HASH=' . password_hash('NEW_SECURE_PASSWORD', PASSWORD_BCRYPT), $c);
file_put_contents('c:/xampp/htdocs/Dddcet/.env', $c);
echo "Secrets rotated.\n";
