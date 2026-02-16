# Seller Module - Installation & Testing Guide

## 📋 Prerequisites

Before installing the seller module, ensure you have:

1. ✅ **Web Server**: Apache 2.4+ or Nginx with PHP support
2. ✅ **PHP**: Version 7.4 or higher
3. ✅ **MySQL/MariaDB**: Version 5.7+ or MariaDB 10.2+
4. ✅ **PHP Extensions**:
   - PDO
   - PDO_MySQL
   - mbstring
   - openssl
   - json
   - session
5. ✅ **Apache Modules** (if using Apache):
   - mod_rewrite
   - mod_headers
6. ✅ **Existing ReviewFlow Installation**: Base platform v3.0

---

## 🚀 Installation Steps

### Step 1: Database Setup

1. **Run the main migration script** (if not already done):
   ```bash
   mysql -u reviewflow_user -p reviewflow < migrations/upgrade_v3.sql
   ```

2. **Add demo mode setting** (for development/testing):
   ```bash
   mysql -u reviewflow_user -p reviewflow < seller/demo_mode_setting.sql
   ```

3. **Verify tables created**:
   ```sql
   USE reviewflow;
   SHOW TABLES LIKE 'seller%';
   -- Should show: sellers, seller_wallet
   
   SHOW TABLES LIKE 'review_requests';
   SHOW TABLES LIKE 'payment_transactions';
   SHOW TABLES LIKE 'tax_invoices';
   ```

### Step 2: File Permissions

Set proper permissions for the seller directory:

```bash
cd /home/runner/work/reviewer/reviewer

# Set directory permissions
chmod 755 seller
chmod 755 seller/includes

# Set file permissions
chmod 644 seller/*.php
chmod 644 seller/includes/*.php
chmod 644 seller/.htaccess
chmod 644 seller/*.md
chmod 644 seller/*.sql

# Ensure web server can read
chown -R www-data:www-data seller  # Adjust user/group as needed
```

### Step 3: Configuration

1. **Verify config.php settings**:
   ```php
   // In includes/config.php
   const SELLER_URL = 'https://palians.com/reviewer/seller';
   const APP_NAME = 'ReviewFlow';
   const GST_RATE = 18;
   const SAC_CODE = '998371';
   ```

2. **Update .htaccess** (if needed):
   ```apache
   # In seller/.htaccess
   # Adjust RewriteBase to match your installation path
   RewriteBase /reviewer/seller/
   ```

3. **Configure system settings** (via admin panel or SQL):
   ```sql
   -- Set commission per review
   UPDATE system_settings SET setting_value = '50' WHERE setting_key = 'admin_commission_per_review';
   
   -- Enable demo mode for testing (disable in production)
   UPDATE system_settings SET setting_value = '1' WHERE setting_key = 'payment_demo_mode';
   
   -- GST settings
   UPDATE system_settings SET setting_value = '18' WHERE setting_key = 'gst_rate';
   UPDATE system_settings SET setting_value = '998371' WHERE setting_key = 'gst_sac_code';
   ```

### Step 4: Verify Installation

1. **Check file structure**:
   ```bash
   ls -la seller/
   # Should show all PHP files, .htaccess, and documentation
   ```

2. **Test PHP syntax**:
   ```bash
   find seller -name "*.php" -exec php -l {} \;
   # All files should show "No syntax errors detected"
   ```

3. **Check Apache/Web Server**:
   ```bash
   # Apache
   apachectl configtest
   service apache2 restart
   
   # Nginx
   nginx -t
   service nginx restart
   ```

---

## 🧪 Testing Guide

### Test 1: Seller Registration

1. Navigate to: `https://your-domain/reviewer/seller/register.php`

2. Fill the registration form:
   - **Name**: Test Seller
   - **Email**: seller@test.com (use unique email)
   - **Mobile**: 9876543210 (use unique mobile)
   - **Password**: test123 (min 6 characters)
   - **Confirm Password**: test123
   - **Company Name**: Test Company
   - **GST Number**: (optional) 22AAAAA0000A1Z5
   - **Billing Address**: Test Address, City, State - 123456

3. **Expected Results**:
   - ✅ Form submits successfully
   - ✅ Redirected to login page with success message
   - ✅ Database entries created in `sellers` and `seller_wallet` tables

4. **Verify Database**:
   ```sql
   SELECT * FROM sellers WHERE email = 'seller@test.com';
   SELECT * FROM seller_wallet WHERE seller_id = (last_insert_id);
   ```

### Test 2: Seller Login

1. Navigate to: `https://your-domain/reviewer/seller/index.php`

2. Login with credentials:
   - **Email**: seller@test.com
   - **Password**: test123

3. **Expected Results**:
   - ✅ Login successful
   - ✅ Session created
   - ✅ Redirected to dashboard
   - ✅ Sidebar shows seller name and email

### Test 3: Dashboard

1. After login, verify dashboard displays:
   - ✅ Wallet balance (₹0.00 for new account)
   - ✅ Total orders (0)
   - ✅ Pending approval (0)
   - ✅ Completed orders (0)
   - ✅ Statistics cards with icons
   - ✅ "No orders yet" message

### Test 4: Create Review Request

1. Click **"New Review Request"** button or navigate to `new-request.php`

2. Fill the form:
   - **Product Link**: https://www.amazon.in/product-example
   - **Product Name**: Test Product
   - **Brand Name**: Test Brand
   - **Product Price**: 1000
   - **Platform**: Amazon
   - **Reviews Needed**: 5

3. **Expected Results**:
   - ✅ Price calculator updates automatically
   - ✅ Shows: Subtotal = ₹250 (5 × ₹50)
   - ✅ Shows: GST = ₹45 (18% of ₹250)
   - ✅ Shows: Total = ₹295
   - ✅ Form submits successfully
   - ✅ Redirected to payment page (demo mode)

4. **Verify Database**:
   ```sql
   SELECT * FROM review_requests WHERE seller_id = YOUR_SELLER_ID ORDER BY id DESC LIMIT 1;
   ```

### Test 5: Payment Flow (Demo Mode)

1. After creating review request, you'll be redirected to payment callback

2. **Expected Results** (Demo Mode):
   - ✅ Payment marked as 'paid' automatically
   - ✅ Transaction recorded in `payment_transactions`
   - ✅ Wallet `total_spent` updated
   - ✅ Order status set to 'pending' (awaiting admin approval)
   - ✅ Redirected to orders page with success message

3. **Verify Database**:
   ```sql
   -- Check payment status
   SELECT payment_status, payment_id FROM review_requests WHERE id = LAST_REQUEST_ID;
   
   -- Check transaction
   SELECT * FROM payment_transactions WHERE seller_id = YOUR_SELLER_ID ORDER BY id DESC LIMIT 1;
   
   -- Check wallet
   SELECT * FROM seller_wallet WHERE seller_id = YOUR_SELLER_ID;
   ```

### Test 6: Orders Page

1. Navigate to: `orders.php`

2. **Expected Results**:
   - ✅ Shows list of all orders
   - ✅ Filter tabs work (All, Pending, Approved, Completed, Rejected)
   - ✅ Order details modal opens on "View" button
   - ✅ Shows product info, payment status, admin status
   - ✅ Progress bar shows reviews completed/needed
   - ✅ Status badges are color-coded

3. **Test Filters**:
   - Click "Pending" tab → Shows only pending orders
   - Click "All Orders" → Shows all orders

### Test 7: Wallet Management

1. Navigate to: `wallet.php`

2. **Expected Results**:
   - ✅ Shows current balance
   - ✅ Shows total spent (should match order total)
   - ✅ Shows transaction history
   - ✅ Recent payment transaction appears in table

3. **Test Add Money**:
   - Click "Add Money" button
   - Enter amount: 1000
   - Click "Proceed to Payment"
   - **Expected**: Wallet balance increases by ₹1000 (demo mode)
   - **Verify**: Transaction appears in history

### Test 8: Profile Management

1. Navigate to: `profile.php`

2. **Test Profile Update**:
   - Change name to "Updated Seller Name"
   - Change mobile to 9999988888
   - Click "Update Profile"
   - **Expected**: Success message, data updated

3. **Test Password Change**:
   - Enter current password: test123
   - Enter new password: test456
   - Confirm new password: test456
   - Click "Change Password"
   - **Expected**: Success message
   - **Test**: Logout and login with new password

### Test 9: Invoices (If Admin Creates Invoice)

1. Navigate to: `invoices.php`

2. **Note**: Invoices are created by admin after payment
   For testing, manually create an invoice:
   ```sql
   INSERT INTO tax_invoices (
       invoice_number, seller_id, review_request_id,
       seller_gst, seller_legal_name, seller_address,
       platform_gst, platform_legal_name, platform_address,
       base_amount, total_gst, grand_total,
       sac_code, invoice_date
   ) VALUES (
       'INV-2024-0001', YOUR_SELLER_ID, YOUR_REQUEST_ID,
       NULL, 'Test Seller', 'Test Address',
       NULL, 'ReviewFlow', 'Platform Address',
       250.00, 45.00, 295.00,
       '998371', CURDATE()
   );
   ```

3. **Test Invoice Features**:
   - ✅ Invoice appears in list
   - ✅ Click "View" → Opens invoice preview modal
   - ✅ Click "Download" → Downloads HTML file
   - ✅ Invoice shows GST breakdown
   - ✅ Print button works

### Test 10: Session & Security

1. **Test Session Timeout**:
   - Login and wait for session timeout (3600 seconds)
   - Try to access any page
   - **Expected**: Redirected to login page

2. **Test Unauthorized Access**:
   - Logout
   - Try accessing `dashboard.php` directly
   - **Expected**: Redirected to login page

3. **Test SQL Injection** (Security):
   - Try login with: `admin' OR '1'='1`
   - **Expected**: Login fails, no SQL error

4. **Test XSS** (Security):
   - Register with name: `<script>alert('XSS')</script>`
   - **Expected**: Script tags displayed as text, not executed

### Test 11: Mobile Responsiveness

1. Open seller module on mobile device or use browser DevTools

2. **Test Mobile Features**:
   - ✅ Sidebar collapses on mobile
   - ✅ Tables are responsive (scroll horizontally if needed)
   - ✅ Forms are mobile-friendly
   - ✅ Buttons are touch-friendly
   - ✅ All features work on mobile

### Test 12: Error Handling

1. **Test Duplicate Registration**:
   - Try registering with existing email
   - **Expected**: Error message "Email already registered"

2. **Test Invalid Data**:
   - Try creating review request with negative price
   - **Expected**: Form validation error

3. **Test Invalid Invoice Access**:
   - Try accessing invoice with wrong ID
   - **Expected**: "Invoice not found" error

---

## 🔧 Troubleshooting

### Issue: "Database connection error"

**Solution**:
- Check `includes/config.php` database credentials
- Verify MySQL service is running
- Test connection: `mysql -u reviewflow_user -p reviewflow`

### Issue: "500 Internal Server Error"

**Solution**:
- Check Apache/PHP error logs: `/var/log/apache2/error.log`
- Enable display_errors in development: `ini_set('display_errors', '1');`
- Check .htaccess syntax

### Issue: "Page not found" or redirect loop

**Solution**:
- Check .htaccess RewriteBase matches your path
- Verify mod_rewrite is enabled: `a2enmod rewrite`
- Check SELLER_URL in config.php

### Issue: "Session timeout immediately"

**Solution**:
- Check session directory permissions
- Verify session.save_path in php.ini
- Check cookie domain settings in config.php

### Issue: "Payment not working"

**Solution**:
- Verify payment_demo_mode is set to '1' in system_settings
- Check error logs for payment errors
- Ensure PaymentFactory is accessible

---

## 📊 Post-Installation Checklist

- [ ] All database tables created successfully
- [ ] File permissions set correctly
- [ ] Seller registration works
- [ ] Seller login works
- [ ] Dashboard displays correctly
- [ ] Review request creation works
- [ ] Payment flow completes (demo mode)
- [ ] Orders page displays orders
- [ ] Wallet shows balance and transactions
- [ ] Profile update works
- [ ] Password change works
- [ ] Invoice display works (if applicable)
- [ ] Mobile responsive design verified
- [ ] Security measures tested
- [ ] Error handling verified
- [ ] Session management working
- [ ] All links and navigation working

---

## 🔒 Production Deployment

Before deploying to production:

1. **Disable Demo Mode**:
   ```sql
   UPDATE system_settings SET setting_value = '0' WHERE setting_key = 'payment_demo_mode';
   ```

2. **Configure Real Payment Gateway**:
   ```sql
   UPDATE system_settings SET setting_value = '1' WHERE setting_key = 'razorpay_enabled';
   UPDATE system_settings SET setting_value = 'YOUR_KEY' WHERE setting_key = 'razorpay_key_id';
   UPDATE system_settings SET setting_value = 'YOUR_SECRET' WHERE setting_key = 'razorpay_key_secret';
   ```

3. **Enable HTTPS**:
   - Obtain SSL certificate
   - Update SELLER_URL to use https://
   - Test HTTPS redirect in .htaccess

4. **Security Hardening**:
   - Disable display_errors in php.ini
   - Set restrictive file permissions (644 for files, 755 for directories)
   - Enable security headers in .htaccess
   - Review and enable firewall rules

5. **Backup Strategy**:
   - Set up automated database backups
   - Back up seller module files
   - Test restore procedure

6. **Monitoring**:
   - Set up error log monitoring
   - Monitor payment transactions
   - Track seller registrations
   - Monitor system performance

---

## 📝 Notes

- Demo mode is safe for testing but must be disabled in production
- Invoice PDF generation requires additional library (TCPDF or mPDF)
- Email notifications need SMTP configuration
- Regular backups are essential
- Monitor error logs regularly
- Test all features after updates

---

## 🆘 Support

For issues or questions:
- Check error logs: `/logs/error.log`
- Review database for integrity
- Verify configuration settings
- Contact: support@reviewflow.com

---

**Version**: ReviewFlow v3.0 - Seller Module
**Last Updated**: 2024
