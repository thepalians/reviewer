-- ============================================
-- Update payment_gateway ENUM to include bank_transfer and admin_adjustment
-- Migration for Offline Wallet Recharge System & Manual Wallet Management
-- ============================================

USE reviewflow;

-- Modify payment_gateway ENUM to include 'bank_transfer' and 'admin_adjustment'
ALTER TABLE payment_transactions 
MODIFY COLUMN payment_gateway ENUM('razorpay', 'payumoney', 'bank_transfer', 'admin_adjustment') NOT NULL;

-- Show completion message
SELECT 'Payment gateway ENUM updated successfully to include bank_transfer and admin_adjustment!' as message;
