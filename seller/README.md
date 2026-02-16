# Seller Module - ReviewFlow SaaS Platform v3.0

## Overview
The Seller Module is a comprehensive dashboard system for sellers to manage product review requests, payments, and invoices on the ReviewFlow platform.

## Directory Structure
```
seller/
├── .htaccess                   # Security and routing configuration
├── index.php                   # Seller login page
├── register.php                # Seller registration page
├── dashboard.php               # Main dashboard with analytics
├── new-request.php            # Create new review request
├── orders.php                 # Order history and management
├── invoices.php               # View and download invoices
├── wallet.php                 # Wallet management and transactions
├── profile.php                # Seller profile and settings
├── payment-callback.php       # Payment gateway callback handler
└── includes/
    ├── header.php             # Common header with sidebar navigation
    └── footer.php             # Common footer
```

## Features

### 1. Authentication System
- **Login** (`index.php`)
  - Email and password authentication
  - Session management with timeout
  - Account status verification
  - Redirect to dashboard on success

- **Registration** (`register.php`)
  - Complete seller profile form
  - Email and mobile uniqueness validation
  - Password hashing with bcrypt
  - Automatic wallet creation
  - GST number support (optional)

### 2. Dashboard (`dashboard.php`)
- Real-time statistics:
  - Wallet balance
  - Total orders
  - Pending approval count
  - Completed orders
  - Total spent
- Recent orders table
- Quick actions for new requests
- Visual status indicators

### 3. Review Request Management (`new-request.php`)
- Product information form:
  - Product link (URL validation)
  - Product name and brand
  - Product price
  - Platform selection (Amazon/Flipkart/Other)
  - Number of reviews needed (1-100)
- Real-time price calculator:
  - Commission calculation
  - GST calculation (18%)
  - Grand total display
- Automatic payment initiation

### 4. Order Management (`orders.php`)
- Comprehensive order listing
- Filter by status:
  - All orders
  - Pending approval
  - Approved
  - Completed
  - Rejected
- Order details modal:
  - Product information
  - Payment breakdown
  - Review progress
  - Rejection reason (if applicable)
- Pay now button for pending payments

### 5. Invoice System (`invoices.php`)
- GST-compliant invoice listing
- Invoice details:
  - Invoice number
  - Date and product info
  - Base amount and GST breakdown
  - Total amount
- View and download functionality
- Print support

### 6. Wallet Management (`wallet.php`)
- Current balance display
- Total spent tracking
- Add money functionality
- Transaction history:
  - Payment ID
  - Date and time
  - Description
  - Gateway used
  - Amount breakdown
  - Status tracking
- Quick amount selection (₹500, ₹1000, ₹2000, ₹5000)

### 7. Profile Management (`profile.php`)
- Update profile information:
  - Name and mobile
  - Company name
  - GST number
  - Billing address
- Change password functionality
- Account status display:
  - Active/Inactive status
  - Email verification status
  - Member since date
- Help and support links

### 8. Payment Processing (`payment-callback.php`)
- Payment gateway integration:
  - Razorpay support
  - PayU Money support
- Payment initiation
- Payment verification
- Transaction recording
- Wallet updates
- Demo mode for testing

## Database Tables Used

### sellers
- id, name, email, mobile, password
- company_name, gst_number, billing_address
- status, email_verified
- created_at, updated_at

### seller_wallet
- id, seller_id
- balance, total_spent
- created_at, updated_at

### review_requests
- id, seller_id
- product_link, product_name, product_price
- brand_name, platform
- reviews_needed, reviews_completed
- admin_commission, total_amount, gst_amount, grand_total
- payment_status, payment_id, payment_method
- admin_status, rejection_reason
- created_at, updated_at

### payment_transactions
- id, seller_id, review_request_id
- amount, gst_amount, total_amount
- payment_gateway, gateway_order_id, gateway_payment_id, gateway_signature
- status, response_data
- created_at, updated_at

### tax_invoices
- id, invoice_number
- seller_id, review_request_id, payment_transaction_id
- seller_gst, seller_legal_name, seller_address
- platform_gst, platform_legal_name, platform_address
- base_amount, cgst_amount, sgst_amount, igst_amount
- total_gst, grand_total
- sac_code, invoice_date
- created_at

## Configuration

### Required Constants (config.php)
```php
SELLER_URL          // Base URL for seller module
APP_NAME            // Application name
GST_RATE            // GST percentage (default: 18)
SAC_CODE            // Service Accounting Code
SESSION_TIMEOUT     // Session timeout in seconds
PASSWORD_HASH_ALGO  // Password hashing algorithm
```

### Database Settings
```php
admin_commission_per_review  // Commission per review (from system_settings)
razorpay_enabled            // Enable/disable Razorpay
razorpay_key_id             // Razorpay API key
razorpay_key_secret         // Razorpay secret key
payumoney_enabled           // Enable/disable PayU Money
```

## Security Features

1. **Session Management**
   - Secure session configuration
   - Session timeout (3600 seconds)
   - HTTPS-only cookies
   - HTTPOnly and SameSite flags

2. **Authentication**
   - Password hashing with bcrypt (cost 12)
   - Session-based authentication
   - Account status verification
   - Login time tracking

3. **Input Validation**
   - Email validation with FILTER_VALIDATE_EMAIL
   - Mobile number validation (10 digits)
   - URL validation for product links
   - SQL injection prevention with prepared statements
   - XSS prevention with htmlspecialchars()

4. **.htaccess Protection**
   - Force HTTPS
   - Security headers
   - Clickjacking prevention
   - XSS protection
   - MIME sniffing prevention
   - Sensitive file access denial

## UI/UX Features

1. **Responsive Design**
   - Bootstrap 5 framework
   - Mobile-friendly layout
   - Collapsible sidebar for mobile

2. **Visual Indicators**
   - Color-coded status badges
   - Progress bars for review completion
   - Icon-based navigation
   - Stat cards with hover effects

3. **User Experience**
   - Real-time price calculator
   - Quick amount selection
   - Inline error messages
   - Success confirmations
   - Breadcrumb navigation
   - Tooltips for additional info

## Payment Flow

1. **Review Request Creation**
   - Seller fills product details
   - System calculates total amount
   - Order created with 'pending' status

2. **Payment Initiation**
   - Redirect to payment gateway
   - Order ID generated
   - Session data stored

3. **Payment Callback**
   - Gateway sends payment response
   - Signature verification
   - Payment status update
   - Wallet update
   - Transaction recording

4. **Post-Payment**
   - Invoice generation (future)
   - Admin notification (future)
   - Email confirmation (future)

## Future Enhancements

1. **Invoice Generation**
   - Automatic GST invoice creation
   - PDF generation
   - Email delivery

2. **Notifications**
   - Email notifications
   - WhatsApp notifications
   - SMS notifications

3. **Analytics**
   - Revenue charts
   - Order trends
   - Performance metrics

4. **Advanced Features**
   - Bulk order upload
   - API integration
   - Automated review distribution
   - Rating and feedback system

## Installation

1. Ensure database migration is run (upgrade_v3.sql)
2. Configure config.php with correct database credentials
3. Set SELLER_URL constant
4. Configure payment gateway credentials
5. Set proper file permissions (755 for directories, 644 for files)
6. Test login and registration

## Testing

### Demo Mode
The payment-callback.php supports demo mode for testing without actual payment gateway:
- Automatically marks payments as successful
- Creates transaction records
- Updates wallet balances

### Test Credentials
Create a test seller account using register.php and use for testing all features.

## Support

For issues or questions:
- Check logs in /logs/error.log
- Review database for data integrity
- Verify config.php settings
- Check .htaccess configuration

## Version
ReviewFlow SaaS Platform v3.0
Seller Module - Complete Implementation
