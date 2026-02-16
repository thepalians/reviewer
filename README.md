# ReviewFlow - Review Management Platform

[![Version](https://img.shields.io/badge/version-3.0.0-blue.svg)](https://github.com/aqidul/reviewer)
[![PHP](https://img.shields.io/badge/php-%3E%3D7.4-8892BF.svg)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-Proprietary-red.svg)](LICENSE)

A comprehensive review management and task automation platform that connects sellers/brands with users for authentic product reviews through a gamified task-based workflow system.

## 🚀 Features

### Core Functionality
- **Multi-User System**: Admin, User (Reviewer), Seller, and Affiliate portals
- **Task Management**: Assign, track, and verify review tasks with multi-step workflows
- **Wallet System**: Digital wallet with payments, withdrawals, and transaction history
- **KYC Verification**: User verification system for secure payouts
- **Gamification**: Rewards, badges, leaderboard, competitions, and achievements
- **Referral Program**: Earn commissions by referring new users
- **AI Chatbot**: Intelligent assistant across all dashboards
- **PWA Support**: Progressive Web App with offline capabilities

### Admin Features
- Comprehensive dashboard with analytics and reporting
- User, seller, and affiliate management
- Task assignment and approval workflow
- Payment gateway integrations (Razorpay, PayU, Cashfree)
- Fraud detection and quality control
- Business intelligence reports
- System settings and configuration

### User Features
- Intuitive task dashboard
- Multi-step task completion workflow
- Wallet and earnings tracking
- KYC document submission
- Support ticket system
- Referral tracking and bonuses
- Achievement and leaderboard system

### Seller Features
- Product catalog management
- Order request creation
- Invoice generation
- Bulk order upload
- Analytics and reporting
- Wallet and payment tracking

### Affiliate Features
- Referral link generation
- Commission tracking
- Performance analytics
- Payout management

## 📋 Requirements

- **PHP**: 7.4 or higher
- **MySQL/MariaDB**: 5.7 or higher
- **Web Server**: Apache or Nginx
- **Extensions**: PDO, PDO_MySQL, JSON, OpenSSL, mbstring
- **Optional**: Redis for caching

## 🔧 Installation

### 1. Clone the Repository

```bash
git clone https://github.com/aqidul/reviewer.git
cd reviewer
```

### 2. Configure Environment

Copy the example environment file and update with your settings:

```bash
cp .env.example .env
```

Edit `.env` and configure:
- Database credentials
- Application URLs
- Email (SMTP) settings
- Payment gateway credentials
- Admin credentials

**IMPORTANT**: Never commit the `.env` file to version control.

### 3. Set File Permissions

```bash
chmod 755 uploads/ cache/ logs/
chmod 644 .env
```

### 4. Install Database

Run the installation script (only once):

```bash
php install.php
```

Or access via browser: `https://yourdomain.com/reviewer/install.php`

### 5. Run Migrations (if applicable)

```bash
php migrations/run_all.php
```

### 6. Configure Web Server

#### Apache (.htaccess already included)
Ensure `mod_rewrite` is enabled.

#### Nginx
Add this to your server block:

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}

location ~ \.php$ {
    include snippets/fastcgi-php.conf;
    fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
}
```

### 7. Access the Application

- **User Portal**: `https://yourdomain.com/reviewer/`
- **Admin Panel**: `https://yourdomain.com/reviewer/admin/`
- **Seller Portal**: `https://yourdomain.com/reviewer/seller/`
- **Affiliate Portal**: `https://yourdomain.com/reviewer/affiliate/`

## 🔐 Security

### Environment Variables
All sensitive credentials are stored in `.env` file (never commit to git).

### Security Features
- BCrypt password hashing (cost: 12)
- CSRF protection on all forms
- Rate limiting on login attempts
- SQL injection prevention (prepared statements)
- XSS protection (htmlspecialchars)
- Secure session cookies (HTTPOnly, Secure, SameSite)
- Input sanitization and validation

### Security Best Practices
1. Change default admin credentials immediately after installation
2. Use strong passwords (min 12 characters)
3. Enable HTTPS in production
4. Keep PHP and database updated
5. Regularly backup database and files
6. Review access logs periodically

## 🎨 UI/UX Features

- Modern, responsive design
- Dark mode support
- Mobile-first approach
- Glass morphism effects
- Smooth animations and transitions
- Consistent design language across all portals
- Accessibility considerations

## 📱 Progressive Web App (PWA)

The application supports installation as a PWA with:
- Offline functionality
- Push notifications
- App-like experience on mobile devices
- Service worker caching strategy

## 🔄 Cron Jobs

Set up these cron jobs for automated tasks:

```bash
# Process payment queue (every 5 minutes)
*/5 * * * * php /path/to/reviewer/cron/process_queue.php

# Daily reports (every day at 2 AM)
0 2 * * * php /path/to/reviewer/cron/daily_reports.php

# Weekly cleanup (every Sunday at 3 AM)
0 3 * * 0 php /path/to/reviewer/cron/cleanup.php
```

## 📊 Database Schema

The application uses a normalized MySQL database with tables for:
- Users and authentication
- Tasks and orders
- Wallet transactions
- KYC documents
- Referrals and affiliates
- Notifications
- Activity logs
- And more...

See `/docs/database-schema.md` for detailed schema documentation.

## 🌐 API Documentation

REST API is available at `/api/v1/`

Key endpoints:
- `/api/v1/auth/login` - User authentication
- `/api/v1/tasks` - Task management
- `/api/v1/wallet` - Wallet operations
- `/api/v1/notifications` - Push notifications
- `/api/v1/webhooks` - Payment callbacks

See `/docs/api-documentation.md` for complete API reference.

## 🛠️ Development

### Technology Stack
- **Backend**: PHP 7.4+ with PDO
- **Frontend**: Vanilla JavaScript, Bootstrap CSS
- **Database**: MySQL/MariaDB
- **Caching**: Redis (optional)
- **Email**: PHPMailer
- **Payments**: Razorpay, PayU, Cashfree

### Code Structure
```
reviewer/
├── admin/          # Admin portal files
├── user/           # User portal files
├── seller/         # Seller portal files
├── affiliate/      # Affiliate portal files
├── api/            # REST API endpoints
├── includes/       # Core PHP libraries
├── assets/         # CSS, JS, images
├── uploads/        # User uploads
├── cache/          # Cache files
├── logs/           # Error logs
├── migrations/     # Database migrations
├── cron/           # Cron job scripts
└── docs/           # Documentation
```

### Coding Standards
- PSR-12 coding style
- Strict type declarations
- Prepared statements for all queries
- Input validation and sanitization
- Error logging (not displaying in production)

## 📖 Documentation

Additional documentation available in `/docs/`:
- User Guide
- Testing Guide
- Troubleshooting Guide
- Upgrade Guide
- Security Guidelines
- Archive of historical documentation

## 🐛 Troubleshooting

### Common Issues

**Database Connection Error**
- Verify database credentials in `.env`
- Ensure MySQL service is running
- Check database user permissions

**Session Issues**
- Verify session directory is writable
- Check PHP session configuration
- Clear browser cookies

**Payment Gateway Issues**
- Verify API credentials in `.env`
- Check webhook URLs are accessible
- Review payment gateway logs

See `/docs/TROUBLESHOOTING.md` for more solutions.

## 🤝 Contributing

This is a proprietary project. Contact the repository owner for contribution guidelines.

## 📄 License

Proprietary - All rights reserved.

## 👥 Support

For support inquiries:
- Email: support@reviewflow.com
- WhatsApp: +91-9876543210

## 🔖 Version History

### v3.0.0 (Current)
- Security improvements: Environment variable configuration
- Removed hardcoded credentials
- Cleaned up test/debug files
- Consolidated documentation
- UI/UX enhancements
- Code quality improvements

For detailed changelog, see `/docs/archive/CHANGELOG.md`

## ⚠️ Important Notes

1. **Never commit `.env` file** - Contains sensitive credentials
2. **Change default passwords** - Immediately after installation
3. **Enable HTTPS** - Required for production use
4. **Backup regularly** - Database and uploaded files
5. **Test in staging** - Before deploying to production
6. **Monitor logs** - Check `/logs/error.log` regularly

---

**Built with ❤️ for ReviewFlow**
# reviewer
