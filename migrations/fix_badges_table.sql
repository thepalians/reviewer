-- Fix badges table to add badge_code column and reward columns
-- This migration ensures compatibility with the gamification system

-- Step 1: Add badge_code column without UNIQUE constraint initially
ALTER TABLE badges 
ADD COLUMN IF NOT EXISTS badge_code VARCHAR(50) AFTER name;

-- Add reward columns if they don't exist
ALTER TABLE badges 
ADD COLUMN IF NOT EXISTS reward_points INT DEFAULT 0 AFTER criteria,
ADD COLUMN IF NOT EXISTS reward_amount DECIMAL(10,2) DEFAULT 0 AFTER reward_points;

-- Step 2: Update existing badges with badge_code based on their names
UPDATE badges SET badge_code = 'first_task' WHERE name = 'First Task' AND badge_code IS NULL;
UPDATE badges SET badge_code = 'ten_tasks' WHERE name = 'Task Master 10' AND badge_code IS NULL;
UPDATE badges SET badge_code = 'fifty_tasks' WHERE name = 'Task Master 50' AND badge_code IS NULL;
UPDATE badges SET badge_code = 'hundred_tasks' WHERE name = 'Task Master 100' AND badge_code IS NULL;
UPDATE badges SET badge_code = 'first_referral' WHERE name = 'First Referral' AND badge_code IS NULL;
UPDATE badges SET badge_code = 'referral_king' WHERE name = 'Referral Pro' AND badge_code IS NULL;
UPDATE badges SET badge_code = 'verified_user' WHERE name = 'Verified User' AND badge_code IS NULL;
UPDATE badges SET badge_code = 'streak_30' WHERE name = 'Streak Master' AND badge_code IS NULL;

-- Step 3: Insert/update default badges with proper badge_codes (using name as unique key)
INSERT INTO badges (name, badge_code, description, icon, criteria, points_required, reward_points, is_active) VALUES
('First Task', 'first_task', 'Completed your first task', 'fa-star', 'complete_1_task', 10, 5, 1),
('Task Master 10', 'ten_tasks', 'Completed 10 tasks', 'fa-fire', 'complete_10_tasks', 100, 20, 1),
('Task Master 50', 'fifty_tasks', 'Completed 50 tasks', 'fa-trophy', 'complete_50_tasks', 500, 50, 1),
('Task Master 100', 'hundred_tasks', 'Completed 100 tasks', 'fa-crown', 'complete_100_tasks', 1000, 100, 1),
('First Referral', 'first_referral', 'Referred your first user', 'fa-user-plus', 'refer_1_user', 50, 10, 1),
('Referral Pro', 'referral_king', 'Referred 10 users', 'fa-users', 'refer_10_users', 500, 50, 1),
('Verified User', 'verified_user', 'Completed KYC verification', 'fa-check-circle', 'kyc_verified', 20, 5, 1),
('Streak Master', 'streak_30', 'Maintained 30 day login streak', 'fa-calendar-check', 'streak_30_days', 300, 40, 1)
ON DUPLICATE KEY UPDATE
    badge_code = VALUES(badge_code),
    description = VALUES(description),
    icon = VALUES(icon),
    criteria = VALUES(criteria),
    points_required = VALUES(points_required),
    reward_points = VALUES(reward_points);

-- Step 4: Add UNIQUE constraint on badge_code after all data is populated
-- First, ensure no NULL or duplicate badge_codes exist
DELETE t1 FROM badges t1
INNER JOIN badges t2 
WHERE t1.id > t2.id AND t1.badge_code = t2.badge_code AND t1.badge_code IS NOT NULL;

-- Now add the unique constraint safely
ALTER TABLE badges 
ADD UNIQUE INDEX idx_badge_code (badge_code);

