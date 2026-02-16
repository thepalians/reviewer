-- Add unique constraints to prevent duplicate Aadhaar and PAN at database level
-- Migration: add_unique_aadhaar_pan.sql
-- Version: 3.1.0
-- Date: 2026-02-16

-- Add unique index for Aadhaar number
ALTER TABLE user_kyc ADD UNIQUE INDEX idx_unique_aadhaar (aadhaar_number);

-- Add unique index for PAN number
ALTER TABLE user_kyc ADD UNIQUE INDEX idx_unique_pan (pan_number);
