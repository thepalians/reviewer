# Offline Wallet Recharge System - Testing Guide

## Prerequisites
Before testing, ensure the following are completed:

1. **Database Migrations**
   ```bash
   # Run these SQL scripts
   mysql -u reviewflow_user -p reviewflow < migrations/wallet_recharge_requests.sql
   mysql -u reviewflow_user -p reviewflow < migrations/update_payment_gateway_enum.sql
   ```

2. **Directory Permissions**
   ```bash
   chmod 755 uploads/wallet_screenshots/
   ```

3. **Web Server Configuration**
   - Ensure Apache mod_rewrite is enabled (for .htaccess)
   - Verify PHP extensions: GD or Imagick (for getimagesize())

## Test Cases

### Seller Side Tests

#### Test 1: View Bank Account Details
**Steps:**
1. Login as a seller
2. Navigate to Wallet page
3. Click "Add Money" button
4. Verify bank account details are displayed:
   - Bank: State Bank Of India
   - Account: 41457761629
   - IFSC: SBIN0005362
   - Holder: THE PALIANS
   - Branch: EKTA NAGAR, BAREILLY

**Expected:** All bank details display correctly

#### Test 2: Amount Validation - Minimum
**Steps:**
1. Enter amount: ₹50
2. Fill other required fields
3. Submit form

**Expected:** Error message "Minimum amount to add is ₹100"

#### Test 3: Amount Validation - Maximum
**Steps:**
1. Enter amount: ₹150000
2. Fill other required fields
3. Submit form

**Expected:** Error message "Maximum amount to add is ₹1,00,000"

#### Test 4: Valid Recharge Request
**Steps:**
1. Enter amount: ₹1000
2. Enter valid UTR: TEST123456789
3. Select transfer date: Today
4. Upload valid screenshot (PNG/JPG < 5MB)
5. Submit form

**Expected:** 
- Success message displayed
- Redirected to wallet page
- Request appears in "Wallet Recharge Requests" table
- Status shows "Pending"

#### Test 5: Invalid File Type
**Steps:**
1. Fill all fields correctly
2. Upload .txt or .pdf file as screenshot
3. Submit form

**Expected:** Error "Only JPG, JPEG, and PNG images are allowed"

#### Test 6: File Size Limit
**Steps:**
1. Fill all fields correctly
2. Upload image file > 5MB
3. Submit form

**Expected:** Error "File size must be less than 5MB"

#### Test 7: Non-Image File
**Steps:**
1. Fill all fields correctly
2. Rename .txt file to .jpg and upload
3. Submit form

**Expected:** Error "Invalid image file. Please upload a valid image."

#### Test 8: View Request Status
**Steps:**
1. Submit a recharge request
2. Scroll to "Wallet Recharge Requests" section
3. Verify all columns display correctly

**Expected:** 
- Request ID
- Date & Time
- Amount
- UTR Number
- Transfer Date
- Screenshot link (clickable)
- Status badge
- Remarks (if any)

### Admin Side Tests

#### Test 9: Admin Dashboard Integration
**Steps:**
1. Login as admin
2. View dashboard

**Expected:**
- "💳 Wallet Recharges" link in sidebar
- Badge shows count of pending requests
- Alert card appears if pending requests exist

#### Test 10: View All Requests
**Steps:**
1. Navigate to Wallet Recharges page
2. Check "All" tab

**Expected:** All requests displayed with correct counts in badges

#### Test 11: Filter Pending Requests
**Steps:**
1. Click "Pending" tab

**Expected:** Only pending requests displayed

#### Test 12: Approve Request
**Steps:**
1. Find a pending request
2. Click "Approve" button
3. Enter optional remarks
4. Confirm approval

**Expected:**
- Success message
- Request status changed to "Approved"
- Seller wallet balance increased
- Payment transaction created with gateway='bank_transfer'
- Timestamp recorded

**Verify in Database:**
```sql
-- Check wallet balance
SELECT balance FROM seller_wallet WHERE seller_id = ?;

-- Check payment transaction
SELECT * FROM payment_transactions 
WHERE seller_id = ? AND payment_gateway = 'bank_transfer' 
ORDER BY created_at DESC LIMIT 1;

-- Check request status
SELECT status, approved_by, approved_at 
FROM wallet_recharge_requests WHERE id = ?;
```

#### Test 13: Reject Request - Without Remarks
**Steps:**
1. Find a pending request
2. Click "Reject" button
3. Leave remarks empty
4. Try to confirm

**Expected:** Error "Please provide a reason for rejection"

#### Test 14: Reject Request - With Remarks
**Steps:**
1. Find a pending request
2. Click "Reject" button
3. Enter remarks: "Invalid UTR number"
4. Confirm rejection

**Expected:**
- Success message
- Request status changed to "Rejected"
- Remarks visible in seller's wallet page
- Wallet balance NOT increased
- No payment transaction created

#### Test 15: Already Processed Request
**Steps:**
1. Try to approve/reject an already processed request

**Expected:** Error "This request has already been processed"

#### Test 16: Screenshot Verification
**Steps:**
1. Click screenshot "View" link
2. Image opens in new tab

**Expected:** 
- Image loads correctly
- URL: /uploads/wallet_screenshots/wallet_*_*.jpg

### Security Tests

#### Test 17: File Upload Security
**Steps:**
1. Create malicious.php.jpg file with PHP code
2. Try to upload as screenshot

**Expected:** 
- File rejected as invalid image
- If somehow uploaded, .htaccess prevents execution

#### Test 18: Directory Access
**Steps:**
1. Try to access: /uploads/wallet_screenshots/
2. Try to execute: /uploads/wallet_screenshots/test.php

**Expected:**
- Directory listing disabled (403 Forbidden)
- PHP files cannot execute

#### Test 19: SQL Injection
**Steps:**
1. Try entering SQL in UTR field: `' OR '1'='1`
2. Submit form

**Expected:** Treated as regular text, no SQL injection

#### Test 20: XSS Prevention
**Steps:**
1. Enter in admin remarks: `<script>alert('XSS')</script>`
2. View in seller page

**Expected:** Script tags displayed as text, not executed

### Performance Tests

#### Test 21: Multiple Requests
**Steps:**
1. Submit 50+ recharge requests
2. Navigate to admin page
3. Test filter tabs

**Expected:** 
- Page loads in < 2 seconds
- All filters work smoothly
- No memory issues

#### Test 22: Large File Upload
**Steps:**
1. Upload 4.9MB image file
2. Submit form

**Expected:** 
- Upload completes successfully
- File stored with proper permissions

### Integration Tests

#### Test 23: Transaction History
**Steps:**
1. Approve a recharge request
2. Check seller's Transaction History

**Expected:** 
- New transaction appears
- Gateway shows "BANK_TRANSFER"
- Amount matches
- Status shows "Success"

#### Test 24: Wallet Balance Update
**Steps:**
1. Note seller's current balance
2. Approve recharge for ₹1000
3. Refresh wallet page

**Expected:** Balance increased by ₹1000

#### Test 25: Post/Redirect/Get Pattern
**Steps:**
1. Submit recharge request successfully
2. Press F5 to refresh page

**Expected:** 
- Success message shown once
- Form not resubmitted
- No duplicate request created

## Manual Verification Checklist

- [ ] All PHP files have no syntax errors
- [ ] Database tables created successfully
- [ ] File permissions are correct (755 for directories, 644 for files)
- [ ] .htaccess file is working
- [ ] Bank details match exactly as specified
- [ ] Amount limits are enforced (₹100 - ₹1,00,000)
- [ ] File upload security is working
- [ ] Admin can approve requests
- [ ] Admin can reject requests
- [ ] Wallet balance updates correctly
- [ ] Payment transactions are created
- [ ] Navigation links work
- [ ] Success/error messages display
- [ ] Screenshot links work
- [ ] Status badges show correct colors
- [ ] Filter tabs work correctly
- [ ] No SQL injection vulnerabilities
- [ ] No XSS vulnerabilities
- [ ] No PHP execution in uploads directory

## Troubleshooting

### Issue: Images not uploading
**Check:**
- Directory exists: `uploads/wallet_screenshots/`
- Permissions: `755` on directory
- PHP upload settings in php.ini:
  ```ini
  upload_max_filesize = 5M
  post_max_size = 6M
  ```

### Issue: Screenshots not displaying
**Check:**
- File exists in uploads/wallet_screenshots/
- Path is relative: `../uploads/wallet_screenshots/`
- File permissions: `644`

### Issue: Approval not updating wallet
**Check:**
- seller_wallet table has record for seller
- Transaction is committing (check for errors in logs)
- Payment_transactions insert is successful

### Issue: 500 error on admin page
**Check:**
- logs/error.log for details
- Database connection working
- Table wallet_recharge_requests exists

## Sign-off

After completing all tests, fill this checklist:

- [ ] All seller-side features working
- [ ] All admin-side features working
- [ ] Security tests passed
- [ ] Performance acceptable
- [ ] Documentation complete
- [ ] Code review completed
- [ ] Ready for production deployment

**Tested by:** _________________
**Date:** _________________
**Signature:** _________________
