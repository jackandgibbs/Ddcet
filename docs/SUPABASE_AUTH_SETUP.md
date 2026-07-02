# Supabase Auth Setup Guide

## ✅ Steps to Enable Google Login in Supabase

### 1. Go to Supabase Dashboard
1. Visit: https://supabase.com/dashboard
2. Select your project: **mdojemvbvyznozqsbgek**

### 2. Navigate to Authentication Settings
1. Click "Authentication" in sidebar
2. Click "Providers" tab
3. Find "Google" in the list

### 3. Configure Google Provider
1. Click on "Google" to expand
2. **Enable Google provider** (toggle ON)
3. Add your Google OAuth credentials:
   - **Client ID**: `YOUR_GOOGLE_CLIENT_ID`
   - **Client Secret**: `YOUR_GOOGLE_CLIENT_SECRET`
4. Click "Save"

### 4. Configure Site URL
1. In Authentication settings, go to "URL Configuration"
2. Set **Site URL**: `http://localhost/Dddcet`
3. Add to **Redirect URLs**:
   ```
   http://localhost/Dddcet/auth/callback.php
   http://localhost:8000/Dddcet/auth/callback.php
   ```
4. Click "Save"

### 5. Update Google Cloud Console
Add Supabase callback URL to Google Console:
1. Go to: https://console.cloud.google.com/
2. Navigate to your OAuth Client credentials
3. Add to **Authorized redirect URIs**:
   ```
   https://mdojemvbvyznozqsbgek.supabase.co/auth/v1/callback
   ```
4. Save

### 6. Test the Flow
1. Visit: **http://localhost/Dddcet/auth/login.php**
2. Click "Sign in with Google"
3. Authorize the app
4. Should redirect to onboarding page

## ✅ What Changed

### Before (Custom OAuth):
- Direct Google OAuth
- Required database connection
- Manual token exchange

### Now (Supabase Auth):
- Supabase handles OAuth
- No direct database needed
- Works in TEST_MODE
- More secure

## 🔍 How It Works

1. User clicks "Sign in with Google" → Redirects to Supabase Auth
2. Supabase handles Google OAuth → Returns tokens
3. Callback extracts tokens → Creates session
4. Redirects to onboarding/dashboard

## 📋 Current Status

- ✅ Code updated to use Supabase Auth
- ✅ Works without database connection
- ⚠️ Need to enable Google provider in Supabase dashboard
- ⚠️ Need to add Supabase callback to Google Console
