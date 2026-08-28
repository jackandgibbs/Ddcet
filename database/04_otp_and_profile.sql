-- SQL Migration for OTP Verification and Extended Profile

-- 1. Create otp_verifications table
CREATE TABLE IF NOT EXISTS otp_verifications (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    identifier VARCHAR NOT NULL,
    otp_code VARCHAR NOT NULL,
    expires_at TIMESTAMP WITH TIME ZONE NOT NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT timezone('utc'::text, now()) NOT NULL
);

-- Index for fast lookups
CREATE INDEX IF NOT EXISTS idx_otp_verifications_identifier ON otp_verifications(identifier);

-- 2. Add new columns to students table
ALTER TABLE students 
    ADD COLUMN IF NOT EXISTS surname VARCHAR,
    ADD COLUMN IF NOT EXISTS phone VARCHAR UNIQUE,
    ADD COLUMN IF NOT EXISTS semester VARCHAR,
    ADD COLUMN IF NOT EXISTS branch VARCHAR,
    ADD COLUMN IF NOT EXISTS target_year INTEGER;

-- 3. Set up Row Level Security (RLS) for otp_verifications
ALTER TABLE otp_verifications ENABLE ROW LEVEL SECURITY;

-- Allow anon to insert/select so the unauthenticated user can verify OTPs
CREATE POLICY "Allow anon insert OTP" ON otp_verifications FOR INSERT WITH CHECK (true);
CREATE POLICY "Allow anon select OTP" ON otp_verifications FOR SELECT USING (true);
CREATE POLICY "Allow service role full access to OTP" ON otp_verifications FOR ALL USING (true) WITH CHECK (true);
