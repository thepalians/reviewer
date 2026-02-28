-- Add product feedback fields to task_steps table
-- These fields store the star rating and written review submitted in Step 4

ALTER TABLE `task_steps`
    ADD COLUMN IF NOT EXISTS `rating` TINYINT(1) DEFAULT NULL COMMENT 'Product rating 1-5 stars (Step 4)',
    ADD COLUMN IF NOT EXISTS `review_text` TEXT DEFAULT NULL COMMENT 'Written product feedback (Step 4)';
