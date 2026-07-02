# Google Login Setup Guide

## ✅ Step-by-Step Setup

### 1. Google Cloud Console Setup

1. Go to https://console.cloud.google.com/
2. Create/Select Project: "DDCET Prep"
3. Enable APIs:
   - Go to "APIs & Services" → "Library"
   - Search and enable "Google+ API"
4. Configure OAuth Consent Screen:
   - "APIs & Services" → "OAuth consent screen"
   - User Type: **External**
   - App name: **DDCET Prep**
   - User support email: your-email@example.com
   - Authorized domains: (add your domain if deployed)
   - Scopes: `email`, `profile`, `openid`
   - Test users: Add your Gmail account
   - Status: Keep in "Testing" mode for development
5. Create OAuth Client:
   - "APIs & Services" → "Credentials"
   - Click "Create Credentials" → "OAuth client ID"
   - Application type: **Web application**
   - Name: **DDCET Web Client**
   - Authorized redirect URIs:
     ```
     http://localhost/Dddcet/auth/callback.php
     http://localhost:8000/Dddcet/auth/callback.php
     ```
   - Click "Create"
   - **Copy Client ID and Client Secret**

### 2. Update .env File

Open `C:\xampp\htdocs\Dddcet\.env` and update:

```env
# Replace these with your actual credentials from Google Cloud Console
GOOGLE_CLIENT_ID=YOUR_CLIENT_ID_HERE.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=YOUR_CLIENT_SECRET_HERE
GOOGLE_REDIRECT_URI=http://localhost/Dddcet/auth/callback.php
```

### 3. Disable TEST_MODE

Open `config.php` and change:
```php
define('TEST_MODE', false);  // Change from true to false
```

### 4. Setup Database (if not already done)

1. Go to your Supabase project: https://supabase.com/dashboard
2. Navigate to "SQL Editor"
3. Run the schema from `database/schema.sql`
4. Update `.env` with correct database password:
   ```env
   SUPABASE_DB_PASS=your-actual-database-password
   ```

### 5. Test the Login Flow

1. Start XAMPP (Apache and MySQL)
2. Visit: http://localhost/Dddcet/auth/login.php
3. Click "Sign in with Google"
4. Authorize the app
5. Should redirect to onboarding (first time) or dashboard

## 🔍 Troubleshooting

### Error: redirect_uri_mismatch
- Make sure redirect URI in Google Console exactly matches: `http://localhost/Dddcet/auth/callback.php`
- Check `.env` file has correct GOOGLE_REDIRECT_URI

### Error: token_failed or no_token
- Verify GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET are correct
- Make sure they don't have extra spaces or quotes

### Error: Connection failed
- Check database credentials in `.env`
- Verify Supabase database is accessible
- Test connection: `psql -h db.mdojemvbvyznozqsbgek.supabase.co -U postgres -d postgres`

### User not found after login
- Run database schema to create tables
- Check if `students` table exists in Supabase

## 📋 Current Status

Your code already has:
- ✅ Google OAuth flow implemented
- ✅ Login page with Google button
- ✅ Callback handler for token exchange
- ✅ User creation and session management
- ✅ Admin detection
- ✅ Onboarding redirect logic

You just need to:
- ⚠️ Add Google Cloud credentials to `.env`
- ⚠️ Disable TEST_MODE in `config.php`
- ⚠️ Ensure database is set up
