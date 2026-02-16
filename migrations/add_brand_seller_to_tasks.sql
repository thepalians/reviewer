-- Add brand and seller information to tasks table for Version 2.0
-- This enables brand-wise task organization and seller-specific filtering

USE reviewflow;

-- Add brand_name and seller_id to tasks table if not exists
ALTER TABLE tasks 
    ADD COLUMN IF NOT EXISTS brand_name VARCHAR(100) DEFAULT NULL AFTER product_link,
    ADD COLUMN IF NOT EXISTS seller_id INT DEFAULT NULL AFTER brand_name,
    ADD COLUMN IF NOT EXISTS review_request_id INT DEFAULT NULL AFTER seller_id,
    ADD INDEX IF NOT EXISTS idx_brand_name (brand_name),
    ADD INDEX IF NOT EXISTS idx_seller_id (seller_id);

-- Add foreign key constraint for seller_id (if sellers table exists)
ALTER TABLE tasks 
    ADD CONSTRAINT fk_tasks_seller 
    FOREIGN KEY IF NOT EXISTS (seller_id) REFERENCES sellers(id) ON DELETE SET NULL;

-- Add foreign key constraint for review_request_id (if review_requests table exists)
ALTER TABLE tasks 
    ADD CONSTRAINT fk_tasks_review_request 
    FOREIGN KEY IF NOT EXISTS (review_request_id) REFERENCES review_requests(id) ON DELETE SET NULL;

-- Update existing tasks with brand names from product links (best effort)
-- This is optional and may not work for all cases
UPDATE tasks t
LEFT JOIN review_requests rr ON t.product_link LIKE CONCAT('%', rr.product_name, '%')
SET t.brand_name = rr.brand_name,
    t.seller_id = rr.seller_id,
    t.review_request_id = rr.id
WHERE t.brand_name IS NULL AND rr.brand_name IS NOT NULL
LIMIT 1000;

SELECT 'Migration completed successfully!' as message;
