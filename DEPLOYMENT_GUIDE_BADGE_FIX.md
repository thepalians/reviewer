# HTTP 500 Error Fix - Deployment and Testing Guide

## Overview
This fix resolves a critical HTTP 500 error that occurred when admins uploaded payment screenshots in Step 4 of task-detail.php. The error was caused by function signature mismatches and missing functions in the gamification system.

## Issues Fixed

### 1. Function Signature Mismatch
**Problem**: `checkBadgeAchievements()` was calling `awardBadge($db, $user_id, 'badge_name')` with 3 arguments, but `awardBadge()` expects 2 parameters.

**Solution**: Removed `$db` parameter from all `awardBadge()` calls. The function now uses `global $pdo` internally.

### 2. Missing addMoneyToWallet() Function
**Problem**: `awardBadge()` was calling `addMoneyToWallet()` which didn't exist, causing a fatal error.

**Solution**: Added `addMoneyToWallet()` function to gamification-functions.php with:
- Wallet existence check and auto-creation
- Balance update
- Transaction logging
- Proper error handling

### 3. Badge Code Mismatch
**Problem**: Badge names ('First Task') were being passed instead of badge codes ('first_task').

**Solution**: Updated all badge references to use proper badge_code values:
- `first_task` (instead of 'First Task')
- `ten_tasks` (instead of 'Task Master 10')
- `fifty_tasks` (instead of 'Task Master 50')
- `hundred_tasks` (instead of 'Task Master 100')
- `first_referral` (instead of 'First Referral')
- `referral_king` (instead of 'Referral Pro')
- `verified_user` (instead of 'Verified User')
- `streak_30` (instead of 'Streak Master')

### 4. Transaction Safety Issues
**Problem**: Gamification hooks were inside the main transaction, causing nested transaction errors and potential data loss if gamification failed.

**Solution**: 
- Moved gamification hooks OUTSIDE the main transaction
- Added individual try-catch blocks for each hook
- Refund now completes successfully even if gamification/referral systems fail
- All errors are logged but don't block the core functionality

### 5. Wallet Safety
**Problem**: Updating user_wallet without checking if the row exists caused SQL errors.

**Solution**: Added wallet existence check before UPDATE operations. Creates wallet entry if it doesn't exist.

## Deployment Steps

### Step 1: Backup Database
```bash
# Create a backup before applying changes
mysqldump -u your_user -p reviewflow > backup_before_badge_fix_$(date +%Y%m%d_%H%M%S).sql
```

### Step 2: Deploy Code Changes
```bash
# Pull the latest changes
git pull origin copilot/fix-http-500-error-payment-upload

# Or merge the PR into your main branch
```

### Step 3: Run Database Migration
```bash
# Apply the migration to update badges table
mysql -u your_user -p reviewflow < migrations/fix_badges_table.sql
```

**What the migration does:**
1. Adds `badge_code` column to badges table (if not exists)
2. Adds `reward_points` and `reward_amount` columns (if not exist)
3. Updates existing badges with proper badge codes
4. Seeds default badges with correct values
5. Adds UNIQUE index on badge_code

### Step 4: Verify Migration
```bash
# Check badges table structure
mysql -u your_user -p reviewflow -e "DESCRIBE badges;"

# Check seeded badges
mysql -u your_user -p reviewflow -e "SELECT name, badge_code, reward_points, reward_amount FROM badges;"
```

Expected output should show:
- badge_code column exists
- reward_points and reward_amount columns exist
- At least 8 badges with proper badge_codes

### Step 5: Clear PHP OpCache (if enabled)
```bash
# Restart PHP-FPM to clear opcache
sudo systemctl restart php-fpm
# OR
sudo systemctl restart php8.1-fpm  # adjust version as needed
```

## Testing Procedure

### Test 1: Basic Payment Upload (Critical)
1. **Login as Admin**: Access admin panel
2. **Navigate**: Go to task-detail.php for a pending refund task
3. **Upload**: Fill refund amount and upload payment screenshot
4. **Expected**: 
   - ✅ Page should NOT crash with HTTP 500
   - ✅ Success message appears
   - ✅ Refund status changes to "completed"
   - ✅ Commission is added to user wallet
   - ✅ Points are awarded (check point_transactions table)
   - ✅ Badge may be awarded if criteria met

### Test 2: Gamification System
```sql
-- Check if points were awarded
SELECT * FROM point_transactions 
WHERE type = 'task_completion' 
ORDER BY created_at DESC 
LIMIT 5;

-- Check if badges were awarded
SELECT ub.*, b.badge_name, b.badge_code 
FROM user_badges ub 
JOIN badges b ON ub.badge_id = b.id 
ORDER BY ub.earned_at DESC 
LIMIT 5;

-- Check wallet transactions
SELECT * FROM wallet_transactions 
WHERE type = 'credit' 
ORDER BY created_at DESC 
LIMIT 5;
```

### Test 3: Error Resilience
To verify that core functionality works even if gamification fails:

1. **Temporarily break gamification**: 
   ```sql
   -- Temporarily disable gamification
   RENAME TABLE point_transactions TO point_transactions_backup;
   ```

2. **Process a refund**: Should still complete successfully (check error logs)

3. **Restore table**:
   ```sql
   RENAME TABLE point_transactions_backup TO point_transactions;
   ```

### Test 4: New User Badge Awards
1. Create a new test user
2. Complete their first task (as admin, process refund)
3. Verify they receive "First Task" badge (badge_code: first_task)
4. Check they receive 5 reward points

### Test 5: Wallet Auto-Creation
1. Create a new user without wallet entry
2. Process their first task
3. Verify:
   - Wallet entry is auto-created
   - Commission is credited
   - No SQL errors in logs

## Monitoring

### Check Error Logs
```bash
# Check for any PHP errors
tail -f /var/log/apache2/error.log
# OR
tail -f /var/log/nginx/error.log

# Check application error logs
tail -f /path/to/reviewer/logs/error.log
```

### Expected Log Entries (Normal Operation)
```
# If gamification or referral fails, you'll see:
Gamification error (task #123): ...
Referral commission error (task #123): ...
# These are non-critical and don't block refund processing
```

## Rollback Procedure (If Needed)

If issues occur, follow these steps:

### 1. Restore Code
```bash
git checkout main  # or your previous stable branch
```

### 2. Restore Database
```bash
mysql -u your_user -p reviewflow < backup_before_badge_fix_TIMESTAMP.sql
```

### 3. Restart Services
```bash
sudo systemctl restart php-fpm
sudo systemctl restart apache2  # or nginx
```

## Known Limitations

1. **Badge Criteria**: The badge awarding logic checks task completion count, referral count, and KYC status. It does NOT retroactively award badges for already-completed tasks. To retroactively award badges, you would need to run a one-time script.

2. **Nested Transactions**: The `awardPoints()` function still starts its own transaction. While this is now called OUTSIDE the main transaction in task-detail.php, be aware of this behavior if calling it from other contexts.

3. **User Wallet Table**: The fix assumes `user_wallet` table exists. If it doesn't exist in your database, you'll need to create it or run the appropriate migration first.

## Success Criteria

✅ Admin can upload payment screenshot without HTTP 500 error
✅ Refund processing completes successfully
✅ User receives commission in wallet
✅ User receives points for task completion
✅ Badges are awarded when criteria are met
✅ Wallet is auto-created if it doesn't exist
✅ Core functionality works even if gamification fails

## Support

If you encounter issues:
1. Check error logs (see Monitoring section)
2. Verify migration was applied correctly
3. Ensure all required tables exist
4. Check PHP version compatibility (PHP 7.4+ required for type hints)

## Files Changed

- `includes/gamification-functions.php`: Fixed badge function calls, added addMoneyToWallet
- `admin/task-detail.php`: Moved hooks outside transaction, added error handling
- `migrations/fix_badges_table.sql`: Database migration for badges table

## Technical Details

### Function Signatures
```php
// Before (WRONG)
awardBadge($db, $user_id, 'badge_name')

// After (CORRECT)
awardBadge($user_id, 'badge_code')

// New function added
addMoneyToWallet(int $userId, float $amount, string $description = 'Wallet Credit'): bool
```

### Transaction Structure
```php
// Before (WRONG)
$pdo->beginTransaction();
// ... core updates ...
awardTaskCompletionPoints();  // Inside transaction
$pdo->commit();

// After (CORRECT)
$pdo->beginTransaction();
// ... core updates ...
$pdo->commit();
try {
    awardTaskCompletionPoints();  // Outside transaction
} catch (Exception $e) {
    error_log($e->getMessage());
}
```

## Conclusion

This fix ensures that the payment screenshot upload feature works reliably, with proper error handling and resilience. The gamification system is now decoupled from core functionality, preventing cascading failures.
