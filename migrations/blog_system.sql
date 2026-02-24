CREATE TABLE IF NOT EXISTS blog_posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(500) NOT NULL,
    slug VARCHAR(500) NOT NULL UNIQUE,
    excerpt TEXT DEFAULT NULL,
    content LONGTEXT NOT NULL,
    category VARCHAR(100) DEFAULT 'general',
    tags VARCHAR(500) DEFAULT '',
    featured_image VARCHAR(500) DEFAULT '',
    author VARCHAR(100) DEFAULT 'ReviewFlow Team',
    status ENUM('draft','published','archived') DEFAULT 'published',
    is_auto_generated TINYINT(1) DEFAULT 0,
    view_count INT DEFAULT 0,
    read_time_minutes INT DEFAULT 3,
    meta_title VARCHAR(500) DEFAULT '',
    meta_description VARCHAR(500) DEFAULT '',
    telegram_notified TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    published_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_slug (slug),
    INDEX idx_status (status),
    INDEX idx_category (category),
    INDEX idx_published (published_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO blog_posts (title, slug, excerpt, content, category, tags, author, status, is_auto_generated, read_time_minutes, published_at) VALUES

('🎯 How to Complete Your First Task & Earn ₹50+', 'how-to-complete-first-task', 
'Learn the step-by-step process to complete your first review task and earn your first commission on ReviewFlow.',
'<h2>Welcome to ReviewFlow! 🎉</h2>
<p>Congratulations on joining ReviewFlow! You''re just a few steps away from earning your first commission. This guide will walk you through everything you need to know.</p>

<h3>Step 1: Check Your Dashboard</h3>
<p>After logging in, go to your <strong>Dashboard</strong>. You''ll see your assigned tasks in the "My Tasks" section. Each task shows:</p>
<ul>
<li>📦 Product link to review</li>
<li>💰 Commission amount you''ll earn</li>
<li>📅 Deadline to complete the task</li>
<li>⚡ Priority level</li>
</ul>

<h3>Step 2: Place the Order</h3>
<p>Click on the task to view details. You''ll see the product link — click it and <strong>place the order</strong> on the e-commerce platform. After placing the order:</p>
<ol>
<li>Take a screenshot of the order confirmation</li>
<li>Upload it in Step 1 of the task</li>
<li>Click "Submit Step 1"</li>
</ol>

<h3>Step 3: Confirm Delivery</h3>
<p>Once you receive the product, mark Step 2 as complete by uploading the delivery screenshot.</p>

<h3>Step 4: Submit Your Review</h3>
<p>Write an honest review on the e-commerce platform. Take a screenshot showing your review, and upload it in Step 3.</p>

<h3>Step 5: Request Refund</h3>
<p>For Step 4, initiate a return/refund on the e-commerce platform. Upload the refund request screenshot. <strong>Note:</strong> KYC verification is required for this step.</p>

<h3>💰 Get Paid!</h3>
<p>Once admin approves all steps, your commission is credited to your wallet instantly! You can withdraw via UPI, Bank Transfer, or Paytm.</p>

<div class="tip-box">
<strong>💡 Pro Tip:</strong> Complete tasks within 48 hours for faster approval and bonus points!
</div>',
'getting-started', 'task,first-task,earn,commission,beginner', 'ReviewFlow Team', 'published', 1, 4, NOW()),

('💰 Step-by-Step: Complete a Review Task in 10 Minutes', 'complete-review-task-quickly',
'Master the art of completing review tasks quickly and efficiently. Learn shortcuts and tips from top earners.',
'<h2>Speed Up Your Task Completion ⚡</h2>
<p>Top earners on ReviewFlow complete tasks in under 10 minutes. Here''s how they do it:</p>

<h3>Preparation (Before You Start)</h3>
<ul>
<li>✅ Keep your KYC verified — you''ll need it for Step 4</li>
<li>✅ Save your payment details in Profile — saves time during withdrawal</li>
<li>✅ Connect Telegram — get instant notifications when new tasks arrive</li>
</ul>

<h3>The 10-Minute Workflow</h3>
<p><strong>Minutes 1-3: Place Order</strong></p>
<p>Open the product link, add to cart, place order. Screenshot the confirmation page immediately.</p>

<p><strong>Minutes 4-5: Upload Step 1</strong></p>
<p>Go to task detail page, upload your order screenshot, submit.</p>

<p><strong>Minutes 6-8: Review Writing</strong></p>
<p>Write a genuine 3-4 line review. Be specific about the product. Take a screenshot.</p>

<p><strong>Minutes 9-10: Submit Remaining Steps</strong></p>
<p>Upload delivery and review screenshots. For refund step, initiate return and upload proof.</p>

<h3>🏆 Top Earner Secrets</h3>
<ol>
<li>Complete tasks the same day they''re assigned</li>
<li>Write detailed reviews — admin approves faster</li>
<li>Check dashboard every morning for new tasks</li>
<li>Refer friends — earn ₹50 per referral PLUS bonus</li>
</ol>',
'tutorial', 'task,speed,efficiency,tips,earn-more', 'ReviewFlow Team', 'published', 1, 3, NOW() - INTERVAL 1 DAY),

('🤝 Refer Friends & Earn ₹50 Per Referral — Complete Guide', 'referral-system-guide',
'Learn how the referral system works and how you can earn unlimited ₹50 bonuses by inviting friends.',
'<h2>Earn While You Sleep with Referrals 💤💰</h2>
<p>The referral system is one of the easiest ways to earn on ReviewFlow. For every friend who joins using your referral code, you earn <strong>₹50 instantly</strong>!</p>

<h3>How It Works</h3>
<ol>
<li>Go to your <strong>Dashboard → Referral</strong> page</li>
<li>Copy your unique referral link or code</li>
<li>Share it with friends via WhatsApp, Telegram, or social media</li>
<li>When they register using your link, you get ₹50!</li>
</ol>

<h3>Where to Find Your Referral Code</h3>
<p>Your referral code is on your Dashboard and Profile page. You can also find a shareable link that auto-fills your code during registration.</p>

<h3>Tips to Maximize Referral Earnings</h3>
<ul>
<li>📱 Share in WhatsApp groups (family, friends, college groups)</li>
<li>📢 Post on social media with your experience</li>
<li>🎥 Make a short video showing your earnings</li>
<li>💬 Tell people about your actual earnings — proof works best!</li>
</ul>

<h3>Referral Bonus Structure</h3>
<table>
<tr><th>Referrals</th><th>Bonus Per Referral</th><th>Total Earned</th></tr>
<tr><td>1-10</td><td>₹50</td><td>Up to ₹500</td></tr>
<tr><td>11-50</td><td>₹50</td><td>Up to ₹2,500</td></tr>
<tr><td>50+</td><td>₹50</td><td>Unlimited! 🚀</td></tr>
</table>

<div class="tip-box">
<strong>💡 Pro Tip:</strong> The best time to share your referral link is right after you receive a payment — screenshot your payment proof and share it along with the link!
</div>',
'referral', 'referral,earn,bonus,invite,friends', 'ReviewFlow Team', 'published', 1, 3, NOW() - INTERVAL 2 DAY),

('💸 How to Withdraw Money — UPI, Bank & Paytm Guide', 'withdrawal-guide-upi-bank-paytm',
'Complete guide on how to withdraw your earnings via UPI, Bank Transfer, or Paytm. Minimum ₹100.',
'<h2>Cash Out Your Earnings 💰</h2>
<p>Earned some money? Here''s how to withdraw it to your bank account, UPI, or Paytm.</p>

<h3>Minimum Withdrawal</h3>
<p>You need at least <strong>₹100</strong> in your wallet to request a withdrawal.</p>

<h3>Payment Methods</h3>

<h4>1. UPI (Fastest — Recommended ⚡)</h4>
<ul>
<li>Go to Wallet → Withdraw</li>
<li>Select "UPI" as payment method</li>
<li>Enter your UPI ID (e.g., yourname@paytm, yourname@upi)</li>
<li>Enter amount and submit</li>
<li>Processing time: 1-24 hours</li>
</ul>

<h4>2. Bank Transfer</h4>
<ul>
<li>Enter Bank Name, Account Number, and IFSC Code</li>
<li>Processing time: 1-3 business days</li>
</ul>

<h4>3. Paytm</h4>
<ul>
<li>Enter your Paytm registered mobile number</li>
<li>Processing time: 1-24 hours</li>
</ul>

<h3>Withdrawal Status</h3>
<p>Track your withdrawal in Wallet page:</p>
<ul>
<li>⏳ <strong>Pending</strong> — Admin will review soon</li>
<li>✅ <strong>Approved</strong> — Payment is being processed</li>
<li>💰 <strong>Completed</strong> — Money sent to your account!</li>
</ul>

<div class="tip-box">
<strong>💡 Pro Tip:</strong> Save your payment details in Profile page so you don''t have to enter them every time!
</div>',
'payments', 'withdrawal,upi,bank,paytm,money,cashout', 'ReviewFlow Team', 'published', 1, 4, NOW() - INTERVAL 3 DAY),

('⭐ Level Up System: Bronze → Diamond Explained', 'level-up-system-explained',
'Understand the gamification system — how to earn points, level up from Bronze to Diamond, and unlock exclusive rewards.',
'<h2>Your Journey: Bronze → Diamond 💎</h2>
<p>ReviewFlow has a tier/level system that rewards active users with better opportunities and higher commissions.</p>

<h3>Tier Structure</h3>
<table>
<tr><th>Tier</th><th>Points Needed</th><th>Benefits</th></tr>
<tr><td>🥉 Bronze</td><td>0</td><td>Standard commissions</td></tr>
<tr><td>🥈 Silver</td><td>500+</td><td>Priority task assignment</td></tr>
<tr><td>🥇 Gold</td><td>2,000+</td><td>Higher commissions + Priority support</td></tr>
<tr><td>💎 Diamond</td><td>10,000+</td><td>Maximum commissions + VIP support + Exclusive tasks</td></tr>
</table>

<h3>How to Earn Points</h3>
<ul>
<li>✅ Complete a task: +100 points</li>
<li>⭐ 5-star review quality: +50 bonus points</li>
<li>🤝 Successful referral: +200 points</li>
<li>🔥 Daily login streak: +10 points/day</li>
<li>⚡ Complete task before deadline: +25 bonus points</li>
</ul>

<div class="tip-box">
<strong>💡 Pro Tip:</strong> Focus on quality reviews and consistent daily activity to level up fastest!
</div>',
'gamification', 'levels,tiers,points,rewards,bronze,silver,gold,diamond', 'ReviewFlow Team', 'published', 1, 3, NOW() - INTERVAL 4 DAY),

('📱 Connect Telegram for Instant Notifications', 'connect-telegram-notifications',
'Never miss a task! Learn how to connect your Telegram account to receive instant personal notifications.',
'<h2>Get Instant Alerts on Telegram 📲</h2>
<p>Don''t miss out on high-paying tasks! Connect your Telegram to receive instant notifications for:</p>
<ul>
<li>🎯 New task assignments</li>
<li>✅ Task approval updates</li>
<li>💰 Payment confirmations</li>
<li>🔐 KYC status updates</li>
<li>🎁 Referral bonus credits</li>
<li>⏰ Deadline reminders</li>
</ul>

<h3>How to Connect (30 seconds!)</h3>
<ol>
<li>Go to your <strong>Dashboard</strong></li>
<li>Find the blue <strong>"🔔 Connect Telegram"</strong> card</li>
<li>Click <strong>"📲 Connect Now"</strong></li>
<li>Telegram will open — click <strong>"Start"</strong></li>
<li>Done! You''ll see "✅ Telegram Connected" on your dashboard</li>
</ol>

<h3>What Notifications You''ll Get</h3>
<table>
<tr><th>Event</th><th>Notification</th></tr>
<tr><td>New Task</td><td>Task details + product link + commission</td></tr>
<tr><td>Task Approved</td><td>Approval confirmation + earnings credited</td></tr>
<tr><td>Payment Sent</td><td>Amount + payment method + transaction ID</td></tr>
<tr><td>Deadline Warning</td><td>Reminder 24 hours before deadline</td></tr>
</table>

<div class="tip-box">
<strong>💡 Pro Tip:</strong> Users who connect Telegram complete tasks 2x faster because they never miss a notification!
</div>',
'features', 'telegram,notifications,alerts,connect,instant', 'ReviewFlow Team', 'published', 1, 2, NOW() - INTERVAL 5 DAY),

('🔐 KYC Verification — Why & How to Complete', 'kyc-verification-guide',
'KYC is mandatory for Step 4 (Refund Request). Learn why it''s important and how to complete it in 5 minutes.',
'<h2>Complete Your KYC in 5 Minutes ✅</h2>
<p>KYC (Know Your Customer) verification is required before you can complete Step 4 of any task. Here''s everything you need to know.</p>

<h3>Why KYC is Required</h3>
<ul>
<li>🔒 Protects against fraud and fake accounts</li>
<li>💰 Required by Indian law for financial transactions</li>
<li>✅ Ensures your withdrawals are processed smoothly</li>
<li>🛡️ Keeps the platform safe for all users</li>
</ul>

<h3>Documents Required</h3>
<ol>
<li><strong>Aadhaar Card</strong> — 12-digit number + clear photo/scan</li>
<li><strong>PAN Card</strong> — 10-character PAN number + clear photo/scan</li>
<li><strong>Bank Passbook/Statement</strong> — First page showing your name and account number</li>
</ol>

<h3>How to Submit</h3>
<ol>
<li>Go to Dashboard → <strong>KYC Verification</strong></li>
<li>Fill in your full name (as per Aadhaar) and date of birth</li>
<li>Enter Aadhaar number and upload clear photo</li>
<li>Enter PAN number and upload clear photo</li>
<li>Upload bank passbook first page</li>
<li>Submit — Admin will verify within 24-48 hours</li>
</ol>

<h3>KYC Status</h3>
<ul>
<li>⏳ <strong>Pending</strong> — Under review by admin</li>
<li>✅ <strong>Approved</strong> — You can now complete Step 4!</li>
<li>❌ <strong>Rejected</strong> — Check reason and resubmit</li>
</ul>

<div class="tip-box">
<strong>💡 Pro Tip:</strong> Submit KYC immediately after registration so you''re ready when you reach Step 4!
</div>',
'security', 'kyc,verification,aadhaar,pan,identity,documents', 'ReviewFlow Team', 'published', 1, 3, NOW() - INTERVAL 6 DAY),

('🏆 Top Earners Tips — How They Earn ₹5000+/Month', 'top-earners-tips-5000-per-month',
'Learn the strategies top earners use to consistently make ₹5000+ per month on ReviewFlow.',
'<h2>Secrets of Top Earners 🏆</h2>
<p>Some users consistently earn ₹5,000 to ₹15,000 per month on ReviewFlow. Here are their strategies:</p>

<h3>Strategy 1: Be First, Be Fast ⚡</h3>
<p>Top earners connect Telegram and respond to task notifications within minutes. The faster you complete tasks, the more tasks you get assigned.</p>

<h3>Strategy 2: Quality Reviews Win 🌟</h3>
<p>Admin notices quality. Users who write detailed, genuine reviews get:</p>
<ul>
<li>More task assignments</li>
<li>Faster approvals</li>
<li>Higher commission tasks</li>
<li>Bonus points</li>
</ul>

<h3>Strategy 3: Referral Machine 🤝</h3>
<p>Top earners don''t just complete tasks — they build a referral network:</p>
<ul>
<li>Share payment proofs on social media</li>
<li>Create WhatsApp groups for their referrals</li>
<li>Help new users complete their first task</li>
<li>Each referral = ₹50 instant bonus</li>
</ul>

<h3>Strategy 4: Daily Consistency 📅</h3>
<p>Earning ₹5000/month = ₹167/day. That''s just 1-2 tasks per day! The key is consistency.</p>

<h3>Monthly Earning Breakdown</h3>
<table>
<tr><th>Activity</th><th>Frequency</th><th>Earning</th></tr>
<tr><td>Complete tasks</td><td>2/day × 30 days</td><td>₹3,000 - ₹6,000</td></tr>
<tr><td>Referrals</td><td>10/month</td><td>₹500</td></tr>
<tr><td>Bonuses & Points</td><td>Monthly</td><td>₹200 - ₹500</td></tr>
<tr><td><strong>Total</strong></td><td></td><td><strong>₹3,700 - ₹7,000</strong></td></tr>
</table>

<div class="tip-box">
<strong>💡 Pro Tip:</strong> Set a daily goal. Even completing just 1 task per day = ₹1,500-3,000/month!
</div>',
'motivation', 'earn-more,top-earners,tips,strategies,income', 'ReviewFlow Team', 'published', 1, 4, NOW() - INTERVAL 7 DAY),

('❓ FAQs — Common Questions Answered', 'frequently-asked-questions',
'Find answers to the most commonly asked questions about ReviewFlow — earnings, tasks, payments, and more.',
'<h2>Frequently Asked Questions 🤔</h2>

<h3>General</h3>

<h4>Q: What is ReviewFlow?</h4>
<p>ReviewFlow is a platform where you earn money by completing simple review tasks. You place orders, write reviews, and earn commission for each completed task.</p>

<h4>Q: Is ReviewFlow free to join?</h4>
<p>Yes! Registration is completely free. You start earning from day one.</p>

<h4>Q: How much can I earn?</h4>
<p>Earnings depend on the number of tasks you complete. Most users earn ₹50-200 per task. Active users earn ₹3,000-10,000+ per month.</p>

<h3>Tasks</h3>

<h4>Q: How do I get tasks?</h4>
<p>Tasks are assigned by admin. You''ll receive notifications via email, Telegram, and in-app when a new task is assigned.</p>

<h4>Q: What happens if I miss a deadline?</h4>
<p>Try to complete tasks before the deadline. Late submissions may affect your rating and future task assignments.</p>

<h4>Q: Can I reject a task?</h4>
<p>Contact admin through the Messages section if you have concerns about a specific task.</p>

<h3>Payments</h3>

<h4>Q: What is the minimum withdrawal?</h4>
<p>₹100 is the minimum withdrawal amount.</p>

<h4>Q: How long does withdrawal take?</h4>
<p>Usually within 24-48 hours. UPI payments are fastest.</p>

<h4>Q: What payment methods are supported?</h4>
<p>UPI, Bank Transfer, and Paytm.</p>

<h3>Account</h3>

<h4>Q: Is KYC mandatory?</h4>
<p>KYC is required to complete Step 4 (Refund Request) of tasks. We recommend completing it right after registration.</p>

<h4>Q: How do I contact support?</h4>
<p>Use the Messages section in your dashboard or the chatbot for instant help.</p>',
'support', 'faq,questions,help,support,answers', 'ReviewFlow Team', 'published', 1, 5, NOW() - INTERVAL 8 DAY),

('📊 Understanding Your Earnings Dashboard', 'understanding-earnings-dashboard',
'A complete guide to reading and using your earnings dashboard — track tasks, wallet, withdrawals, and analytics.',
'<h2>Your Dashboard Explained 📊</h2>
<p>Your dashboard is your command center. Here''s what every section means:</p>

<h3>Stats Cards (Top Section)</h3>
<ul>
<li><strong>💰 Wallet Balance</strong> — Money available for withdrawal</li>
<li><strong>📋 Active Tasks</strong> — Tasks currently assigned to you</li>
<li><strong>✅ Completed Tasks</strong> — Tasks you''ve finished successfully</li>
<li><strong>💸 Total Earned</strong> — All-time earnings on the platform</li>
</ul>

<h3>My Tasks Section</h3>
<p>Shows your recent and active tasks. Each task card shows:</p>
<ul>
<li>Task ID and product info</li>
<li>Commission amount</li>
<li>Current step progress (1/4, 2/4, etc.)</li>
<li>Deadline and priority</li>
</ul>

<h3>Quick Actions</h3>
<ul>
<li><strong>Wallet</strong> — View balance, withdraw money, see transaction history</li>
<li><strong>Referral</strong> — Share your code, track referrals</li>
<li><strong>Profile</strong> — Update personal info, payment details</li>
<li><strong>KYC</strong> — Submit/check verification status</li>
</ul>

<h3>Notifications Bell 🔔</h3>
<p>The bell icon shows unread notifications — task assignments, payment updates, and announcements.</p>

<div class="tip-box">
<strong>💡 Pro Tip:</strong> Check your dashboard every morning. New tasks are usually assigned in the morning!
</div>',
'tutorial', 'dashboard,earnings,analytics,wallet,tracking', 'ReviewFlow Team', 'published', 1, 3, NOW() - INTERVAL 9 DAY);
