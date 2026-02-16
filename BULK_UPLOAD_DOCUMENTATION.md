# Bulk Task Upload System - Documentation

## Overview
The Bulk Task Upload system allows administrators to upload multiple tasks at once using CSV files. This feature streamlines the process of assigning tasks to multiple users efficiently.

## Features
- CSV file upload with drag-and-drop support
- Real-time file preview (first 10 rows)
- Field validation before processing
- Progress tracking during upload
- Detailed error reporting
- Upload history tracking
- Automatic user matching by email/mobile
- CSRF protection
- Bootstrap 5 responsive design

## Files Created

### 1. `/admin/bulk-upload.php` - Main Upload Interface
- File upload form with drag-and-drop
- CSV file preview table
- Progress bar for upload status
- Results display with success/error counts
- Upload history table
- Download template button

### 2. `/admin/bulk-upload-process.php` - AJAX Processing Handler
- Processes uploaded CSV files using `fgetcsv()`
- Validates each row for required fields and formats
- Finds users by email or mobile number
- Creates tasks with proper data
- Tracks successes and errors
- Updates bulk_upload_history table
- Returns JSON response

### 3. Database Table: `bulk_upload_history`
Migration file exists at: `/migrations/bulk_upload_table.sql`

```sql
CREATE TABLE IF NOT EXISTS bulk_upload_history (
    id INT PRIMARY KEY AUTO_INCREMENT,
    admin_id INT,
    filename VARCHAR(255) NOT NULL,
    total_rows INT DEFAULT 0,
    success_count INT DEFAULT 0,
    error_count INT DEFAULT 0,
    status ENUM('processing', 'completed', 'failed') DEFAULT 'processing',
    error_log TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME,
    INDEX idx_admin_id (admin_id),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## CSV Template Format

The CSV template is located at: `/templates/bulk-task-template.csv`

### Required Columns
1. **brand_name** - Brand name for the task
2. **product_name** - Name of the product to review
3. **product_url** - URL of the product
4. **reward_amount** - Reward amount (numeric, must be > 0)
5. **reviewer_email** OR **reviewer_mobile** - At least one required to identify user

### Optional Columns
1. **amazon_link** - Amazon product link
2. **order_id** - Order ID for tracking
3. **seller_id** - Seller ID (numeric)
4. **seller_name** - Seller name (informational)
5. **task_description** - Additional task notes

### Example CSV
```csv
brand_name,product_name,product_url,amazon_link,order_id,reward_amount,seller_id,seller_name,reviewer_mobile,reviewer_email,task_description
Acme Brand,Premium Headphones,https://example.com/product1,https://amazon.in/product1,ORD-12345,150,1,Acme Seller,9876543210,reviewer1@example.com,Purchase and review Premium Headphones
Tech Corp,Wireless Mouse,https://example.com/product2,https://amazon.in/product2,ORD-12346,100,1,Tech Seller,9876543211,reviewer2@example.com,Purchase and review Wireless Mouse
```

## Validation Rules

### Field Validation
1. **brand_name** - Required, non-empty
2. **product_name** - Required, non-empty
3. **product_url** - Required, must be valid URL
4. **reward_amount** - Required, must be numeric and > 0
5. **reviewer_email** - If provided, must be valid email format
6. **reviewer_mobile** - If provided, must be 10 digits
7. **amazon_link** - If provided, must be valid URL
8. **seller_id** - If provided, must be positive integer

### User Matching
- Users are matched by email OR mobile number
- User must exist in the database with:
  - `user_type = 'user'`
  - `status = 'active'`
- If user is not found, the row is skipped with an error

### Task Creation
For each valid row:
1. Creates task record in `tasks` table
2. Sets initial status to 'pending'
3. Creates 4 task steps:
   - Order Placed
   - Delivery Received
   - Review Submitted
   - Refund Requested
4. Sends notification to user
5. Records admin name as `assigned_by`

## Security Features
1. **Authentication Check** - Only logged-in admins can access
2. **CSRF Protection** - Token validation on file upload
3. **File Type Validation** - Only .csv files accepted
4. **Input Sanitization** - All data sanitized before database insertion
5. **Prepared Statements** - SQL injection protection
6. **Transaction Support** - Rollback on errors

## Usage Instructions

### For Administrators

1. **Access the Upload Page**
   - Navigate to Admin Dashboard
   - Click "📤 Bulk Upload" in the Tasks section sidebar

2. **Download Template**
   - Click "Download Template" button
   - Opens the CSV template with example data

3. **Prepare CSV File**
   - Fill in your data following the template format
   - Ensure all required fields are present
   - Use valid email/mobile for existing users

4. **Upload File**
   - Drag and drop CSV file onto upload zone, OR
   - Click the upload zone to browse for file
   - Preview table shows first 10 rows

5. **Review Preview**
   - Check that data looks correct
   - Click "Upload & Process" to proceed
   - Or click "Cancel" to select different file

6. **Monitor Progress**
   - Progress bar shows upload status
   - Processing happens in background

7. **Review Results**
   - Shows total rows, successes, and errors
   - Error table lists specific issues with row numbers
   - Upload history updates automatically

8. **Check Upload History**
   - View past uploads
   - See success/error counts
   - Track upload status

## Error Handling

### Common Errors
1. **Missing required field** - A required column is empty
2. **Invalid email format** - Email doesn't match pattern
3. **Invalid mobile number** - Mobile is not 10 digits
4. **Invalid URL** - Product/Amazon link is malformed
5. **Invalid reward amount** - Not a number or negative
6. **User not found** - No active user with given email/mobile
7. **Database error** - Task creation failed

### Error Display
- Errors shown in results table
- Each error includes row number and description
- Upload completes even with some errors
- Successful rows are processed

## Technical Details

### File Processing
- Uses PHP's `fgetcsv()` for reliable CSV parsing
- Handles quoted fields correctly
- Processes row by row to minimize memory usage
- Transaction per task for data integrity

### Database Operations
- Prepared statements for all queries
- Transaction wrapping for task + steps creation
- Foreign key support for seller_id
- Indexes for performance

### Frontend Features
- Drag-and-drop file upload
- Client-side CSV parsing for preview
- AJAX file upload (no page reload)
- Real-time progress updates
- Responsive Bootstrap 5 design

### Notification System
- Sends notification to each user on task assignment
- Notification includes:
  - Product name and brand
  - Reward amount
  - Link to task detail page

## Customization Options

### Auto-Create Users
By default, the system only assigns tasks to existing users. To enable auto-creation of new users:

1. Open `/admin/bulk-upload-process.php`
2. Find the `findOrCreateUser()` function
3. Uncomment the user creation code block
4. New users will be created with random passwords

### Additional Fields
To add more fields to the upload:

1. Update CSV template with new columns
2. Add column to `$required_fields` or use as optional
3. Update `validateRow()` to validate new field
4. Update task insert query to include new field
5. Ensure database column exists in tasks table

### Validation Rules
Modify validation rules in `validateRow()` function:
- Change mobile number pattern for international numbers
- Add custom URL validation
- Set min/max reward amounts
- Add business logic validations

## Maintenance

### Upload History Cleanup
Old upload history can be cleaned up with:
```sql
DELETE FROM bulk_upload_history 
WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY);
```

### Monitor Performance
- Check error_log for processing issues
- Review upload history for success rates
- Monitor task creation times
- Check notification delivery

## Troubleshooting

### Upload Fails Immediately
- Check file size (max 5MB)
- Verify CSV format (UTF-8 encoding)
- Ensure CSRF token is valid
- Check server error logs

### Some Rows Fail
- Review error details in results table
- Verify user exists with email/mobile
- Check required fields are present
- Validate data formats

### Progress Bar Stuck
- Check browser console for errors
- Verify PHP max_execution_time
- Check for server timeout
- Review PHP error logs

### No Notifications Sent
- Verify notification functions exist
- Check createNotification() in functions.php
- Review notification settings
- Check error logs for notification failures

## Integration Points

### Existing Systems
- Uses existing authentication (`$_SESSION['admin_name']`)
- Leverages config.php database connection
- Uses security.php CSRF functions
- Calls functions.php notification functions
- Follows admin sidebar pattern

### Database Schema
- Tasks table (existing)
- Task_steps table (existing)
- Users table (existing)
- Bulk_upload_history table (new)

## Future Enhancements

Potential improvements:
1. Excel (.xlsx) file support
2. Batch user creation with email notifications
3. Template selection (different formats)
4. Scheduled/recurring uploads
5. API endpoint for programmatic uploads
6. Field mapping interface (custom columns)
7. Duplicate detection
8. Preview before final upload
9. Export error rows to CSV
10. Upload progress via WebSocket

## Support

For issues or questions:
1. Check error logs at `/logs/error.log`
2. Review upload history for patterns
3. Test with small CSV first (5-10 rows)
4. Verify template format matches expected structure
5. Contact system administrator with error details
