# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [3.0.0] - 2026-02-03

### 🚀 Major Release - Enterprise Edition

This release includes 8 major phases of development, transforming ReviewFlow into a complete enterprise-grade review management platform.

---

### Phase 1: Core Enhancements

#### New Features
- **Advanced Notification System** - Real-time notifications with categories and preferences
- **KYC Verification System** - Document upload, admin verification, status tracking
- **Enhanced Analytics Dashboard** - Charts, graphs, revenue tracking, user insights
- **Bulk Task Upload** - CSV import for mass task creation

#### New Files
- `admin/kyc-management.php` - KYC verification dashboard
- `admin/analytics.php` - Advanced analytics
- `admin/bulk-upload.php` - Bulk task import
- `includes/notification-functions.php` - Notification helpers

---

### Phase 2: User Engagement Features

#### New Features
- **Referral System** - Multi-level referrals with commission tracking
- **Proof Verification System** - Screenshot upload, admin review workflow
- **Gamification System** - Achievements, badges, XP points, levels
- **Real-time Chat** - User-admin and user-seller messaging

#### New Files
- `user/referral.php` - Referral dashboard
- `user/achievements.php` - Achievements page
- `user/leaderboard.php` - User rankings
- `user/chat.php` - Chat interface
- `includes/gamification-functions.php` - Gamification helpers
- `includes/chat-functions.php` - Chat helpers

---

### Phase 3: Payment & Management

#### New Features
- **Enhanced Payment Gateway** - Multiple payment methods support
- **Advanced User Management** - User search, filters, bulk actions
- **Task Templates** - Reusable task templates for sellers

#### New Files
- `admin/user-management.php` - User management dashboard
- `admin/task-templates.php` - Template management
- `includes/payment-functions.php` - Payment helpers

---

### Phase 4: Communication & Security

#### New Features
- **Announcements System** - System-wide announcements with targeting
- **Broadcast Messaging** - Mass email/SMS to users
- **Data Export** - Export data to CSV, Excel, PDF
- **Security Settings** - Password policies, login restrictions

#### New Files
- `admin/announcements.php` - Announcement management
- `admin/broadcast.php` - Broadcast messaging
- `admin/export.php` - Data export tools
- `admin/security-settings.php` - Security configuration

---

### Phase 5: Advanced Features

#### New Features
- **AI Review Quality Check** - Automated review quality scoring, plagiarism detection
- **Two-Factor Authentication (2FA)** - TOTP, SMS OTP, recovery codes
- **Progressive Web App (PWA)** - Offline support, push notifications, install prompt
- **Advanced Reporting** - Custom report builder, scheduled reports
- **Multi-Language Support** - English, Hindi, Tamil, Telugu, Bengali

#### New Files
- `user/security-settings.php` - 2FA setup
- `user/verify-2fa.php` - 2FA verification
- `admin/review-quality.php` - Quality dashboard
- `admin/report-builder.php` - Report builder
- `admin/languages.php` - Language management
- `includes/2fa-functions.php` - 2FA helpers
- `includes/ai-quality-functions.php` - AI quality helpers
- `includes/language-functions.php` - Translation helpers
- `includes/cache-functions.php` - Caching system
- `languages/*.php` - Translation files
- `manifest.json` - PWA manifest
- `sw.js` - Service Worker

---

### Phase 6: Enterprise Communication

#### New Features
- **Email Marketing System** - Campaign builder, A/B testing, analytics
- **Support Ticket System** - Priority levels, SLA tracking, file attachments
- **Seller Dashboard Enhancements** - Analytics, bulk orders, order templates
- **Notification Center** - Unified notification hub
- **SEO System** - Meta tags, Open Graph, sitemap, Schema.org
- **API Rate Limiting** - Request throttling, usage dashboard
- **Mobile API** - RESTful endpoints, JWT authentication

#### New Files
- `admin/email-campaigns.php` - Email campaigns
- `admin/email-templates.php` - Email templates
- `admin/tickets.php` - Ticket management
- `admin/seo-settings.php` - SEO configuration
- `admin/api-settings.php` - API management
- `user/support-tickets.php` - User tickets
- `user/create-ticket.php` - Create ticket
- `user/notification-center.php` - Notifications
- `seller/analytics.php` - Seller analytics
- `seller/bulk-orders.php` - Bulk ordering
- `api/v1/*.php` - RESTful API endpoints
- `includes/ticket-functions.php` - Ticket helpers
- `includes/seo-functions.php` - SEO helpers
- `includes/jwt-functions.php` - JWT authentication

---

### Phase 7: Automation & Intelligence

#### New Features
- **Auto Task Assignment** - Smart assignment based on performance, level, workload
- **Task Scheduling & Calendar** - Calendar view, drag-drop, reminders
- **Advanced Commission System** - Tiered rates, streak bonus, quality bonus
- **Competitions & Leaderboards** - Weekly/monthly competitions with prizes
- **Fraud Detection** - Multi-account detection, VPN detection, risk scoring
- **WhatsApp Integration** - Task notifications via WhatsApp
- **Webhook System** - Event triggers, external integrations

#### New Files
- `admin/auto-assignment.php` - Auto-assignment rules
- `admin/task-scheduler.php` - Task scheduling
- `admin/commission-rules.php` - Commission management
- `admin/competition-manager.php` - Competition management
- `admin/fraud-detection.php` - Fraud dashboard
- `admin/whatsapp-settings.php` - WhatsApp config
- `admin/webhooks.php` - Webhook management
- `user/task-calendar.php` - Task calendar
- `user/competitions.php` - Competitions page
- `includes/auto-assignment-functions.php`
- `includes/commission-functions.php`
- `includes/competition-functions.php`
- `includes/fraud-detection-functions.php`
- `includes/whatsapp-functions.php`
- `includes/webhook-functions.php`

---

### Phase 8: Enterprise & Performance

#### New Features
- **BI Dashboard** - Custom widgets, KPI tracking, real-time analytics
- **Advanced Security** - IP whitelist/blacklist, session management, audit logs
- **Multi-Payment Gateway** - Razorpay, PayU, Cashfree with auto payouts
- **Mobile App Features** - Deep linking, biometric auth, offline sync, Firebase
- **Affiliate/Partner System** - Multi-tier referrals, partner dashboard
- **Inventory Management** - Product catalog, stock tracking, low stock alerts
- **Advanced Task Management** - Dependencies, milestones, bulk operations
- **Performance Optimization** - Redis caching, job queue, CDN support

#### New Files
- `admin/bi-dashboard.php` - Business intelligence
- `admin/dashboard-builder.php` - Widget builder
- `admin/kpi-tracking.php` - KPI monitoring
- `admin/ip-management.php` - IP control
- `admin/session-management.php` - Session control
- `admin/audit-logs.php` - Audit trail
- `admin/payment-gateways.php` - Gateway management
- `admin/affiliate-management.php` - Affiliate management
- `admin/performance-monitor.php` - Performance monitoring
- `affiliate/dashboard.php` - Affiliate dashboard
- `affiliate/links.php` - Tracking links
- `affiliate/payouts.php` - Affiliate payouts
- `seller/products.php` - Product management
- `seller/inventory.php` - Inventory tracking
- `api/v1/biometric.php` - Biometric auth API
- `api/v1/deep-links.php` - Deep linking API
- `api/v1/offline-sync.php` - Offline sync API
- `includes/razorpay-functions.php`
- `includes/payu-functions.php`
- `includes/cashfree-functions.php`
- `includes/affiliate-functions.php`
- `includes/inventory-functions.php`
- `includes/redis-cache-functions.php`
- `includes/queue-functions.php`
- `cron/queue-worker.php` - Background job processor

---

### Database Changes

#### New Tables Added (80+)
- Notification tables: `notifications`, `notification_settings`, `notification_categories`
- KYC tables: `kyc_documents`, `kyc_verifications`
- Gamification: `achievements`, `user_achievements`, `user_xp`, `badges`
- Chat: `chat_messages`, `chat_conversations`
- 2FA: `two_factor_auth`, `trusted_devices`
- Tickets: `support_tickets`, `ticket_replies`, `ticket_attachments`
- Email: `email_campaigns`, `email_campaign_logs`, `email_unsubscribes`
- Competitions: `competitions`, `competition_participants`, `competition_leaderboard`
- Fraud: `fraud_scores`, `fraud_alerts`, `ip_intelligence`
- Affiliate: `affiliates`, `affiliate_referrals`, `affiliate_commissions`
- Inventory: `products`, `product_reviews`, `inventory_logs`
- Performance: `job_queue`, `cache_entries`, `performance_logs`
- And many more...

---

### Technical Improvements
- Comprehensive caching system (file-based with Redis support)
- Background job queue processing
- API versioning with JWT authentication
- Webhook system with retry mechanism
- Performance monitoring and slow query logging
- Complete audit trail for all actions

---

## [2.0.2] - 2026-02-01

### New Features
- Added wallet payment option for review requests
- Sellers can now pay using wallet balance instead of Razorpay
- Shows wallet balance on payment page
- Instant payment with no additional fees when using wallet
- Automatic wallet balance check before payment
- "Add Money" link for insufficient balance cases

### Improvements
- Enhanced payment page UI with two payment method options
- Better visual distinction between Wallet and Razorpay payment methods
- Added payment confirmation dialog for wallet payments
- Improved transaction logging for wallet payments
- Enhanced error handling and user feedback

### Technical Improvements
- Database transactions for wallet payments to ensure data consistency
- Row-level locking to prevent race conditions
- Automatic invoice generation for wallet payments
- Comprehensive error logging for debugging

## [2.0.1] - 2026-02-01

### Bug Fixes
- Fixed wallet balance not updating after admin approval - Enhanced error logging and validation
- Fixed seller dropdown search in Manage Seller Wallet page - Now opens on first character and focus
- Fixed missing sidebar links for brand-wise tasks and export features

### New Features
- Added brand-wise organization to pending tasks with collapsible sections
- Created dedicated brand view page for pending tasks (task-pending-brandwise.php)
- Added "Brand View" link to pending tasks page

### Improvements
- Completely reorganized admin sidebar with professional structure
- Added submenu support for brand-wise task views under main task links
- Enhanced sidebar organization with clear sections: Users, Tasks, Finance, Sellers, Reports & Export, Settings, Chatbot
- Added Export Review Data link to sidebar under Reports & Export section
- Added Manage Seller Wallet link to sidebar under Finance section
- Removed duplicate and unnecessary sidebar items
- Improved badge counts for pending items in sidebar
- Enhanced error logging in wallet approval process for better debugging
- Updated migration script to support bank_transfer and admin_adjustment payment types

### Technical Improvements
- Enhanced database migration for payment_transactions table ENUM values
- Improved error handling in wallet-requests.php with detailed logging
- Better JavaScript handling for seller dropdown with immediate search
- Added focus event handler for better UX in seller selection

## [2.0.0] - 2026-02-01

### Bug Fixes
- Fixed wallet balance not updating after admin approval - Wallet transactions now properly credit seller accounts upon approval
- Fixed review request data not showing to admin - All review request fields now visible in admin panel
- Fixed approved requests visibility - Approved requests now properly displayed in admin dashboard with filtering options

### New Features
- **AI Chatbot Integration** - Self-learning chatbot widget available on Admin, Seller, and User dashboards with FAQ integration
- **Admin Login as Seller** - Administrators can now impersonate seller accounts for support purposes with clear session indicators
- **Brand-wise Task Organization** - Tasks grouped by brand name with collapsible sections and date-wise sorting (recent first)
- **Admin Data Export** - Export review data to Excel format with brand selection and date range filtering
- **Enhanced Task Assignment** - Task assignment now includes both Seller and Brand selection with filtered dropdowns
- **Seller Brand Data Filtering** - Sellers can now view only their own brands' completed task data with export functionality
- **Light/Dark Theme Toggle** - Theme switcher available across all user types with localStorage persistence
- **Version Display** - Application version (v2.0.0) now displayed on all dashboard footers with changelog link

### UI/UX Improvements
- Modern, clean design language with consistent color schemes
- Glassmorphism effects on cards and modal dialogs
- Smooth animations and transitions throughout the interface
- Professional typography with improved readability
- Responsive layouts optimized for mobile and desktop
- Enhanced visual hierarchy with better spacing and contrast

### Technical Improvements
- Centralized theme management with CSS variables
- Improved session management for role impersonation
- Enhanced security with proper session cleanup
- Database optimization for brand-wise queries
- Improved error handling and logging
- Better code organization with reusable components

### Documentation
- Created comprehensive CHANGELOG.md for version tracking
- Added inline code documentation for new features
- Updated configuration with version constants

## [1.0.0] - 2025-01-01

### Initial Release
- User registration and authentication system
- Task assignment and completion workflow
- Wallet management for users and sellers
- Admin dashboard for system management
- Review request submission system
- Payment gateway integration (Razorpay, PayUMoney)
- GST invoice generation
- Referral and reward system
- Multi-tier user levels (Bronze, Silver, Gold, Elite)
- Fraud detection and prevention mechanisms

---

**Note**: For detailed upgrade instructions, please refer to the [Upgrade Guide](UPGRADE_GUIDE.md).
