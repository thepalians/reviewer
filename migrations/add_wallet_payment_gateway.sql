-- ============================================
-- Add 'wallet' payment gateway option
-- Migration for Wallet Payment Feature v2.0.2
-- ============================================

USE reviewflow;

-- Add 'wallet' to payment_gateway ENUM
ALTER TABLE payment_transactions 
MODIFY COLUMN payment_gateway ENUM('razorpay', 'payumoney', 'bank_transfer', 'admin_adjustment', 'wallet', 'demo') NOT NULL;

-- Show completion message
SELECT 'Payment gateway ENUM updated successfully to include wallet payment option!' as message;
