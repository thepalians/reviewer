-- Migration: Create missing tables for ReviewFlow
-- Run this SQL against the MySQL 8 database to create any missing tables

CREATE TABLE IF NOT EXISTS `sellers` (
  `id`         INT NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(100) NOT NULL,
  `email`      VARCHAR(100) NOT NULL,
  `password`   VARCHAR(255) NOT NULL,
  `status`     VARCHAR(10) NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sellers_email_key` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `tasks` (
  `id`              INT NOT NULL AUTO_INCREMENT,
  `user_id`         INT NOT NULL,
  `order_id`        VARCHAR(50) DEFAULT NULL,
  `product_name`    VARCHAR(255) DEFAULT NULL,
  `product_link`    TEXT DEFAULT NULL,
  `platform`        VARCHAR(50) DEFAULT NULL,
  `instructions`    TEXT DEFAULT NULL,
  `status`          VARCHAR(30) NOT NULL DEFAULT 'assigned',
  `commission`      DECIMAL(10,2) DEFAULT NULL,
  `deadline`        DATETIME DEFAULT NULL,
  `refund_requested` TINYINT(1) NOT NULL DEFAULT 0,
  `refund_date`     DATETIME DEFAULT NULL,
  `review_text`     TEXT DEFAULT NULL,
  `review_rating`   INT DEFAULT NULL,
  `created_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tasks_order_id_key` (`order_id`),
  KEY `tasks_user_id_status_idx` (`user_id`, `status`),
  CONSTRAINT `tasks_user_id_fkey` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `task_steps` (
  `id`                          INT NOT NULL AUTO_INCREMENT,
  `task_id`                     INT NOT NULL,
  `step_number`                 INT NOT NULL,
  `step_name`                   VARCHAR(100) DEFAULT NULL,
  `step_status`                 VARCHAR(20) NOT NULL DEFAULT 'pending',
  `submitted_by_user`           TINYINT(1) NOT NULL DEFAULT 0,
  `order_screenshot`            TEXT DEFAULT NULL,
  `delivery_screenshot`         TEXT DEFAULT NULL,
  `review_screenshot`           TEXT DEFAULT NULL,
  `review_submitted_screenshot` TEXT DEFAULT NULL,
  `review_live_screenshot`      TEXT DEFAULT NULL,
  `payment_qr_code`             TEXT DEFAULT NULL,
  `refund_amount`               DECIMAL(10,2) DEFAULT NULL,
  `admin_payment_screenshot`    TEXT DEFAULT NULL,
  `refund_processed_at`         DATETIME DEFAULT NULL,
  `refund_processed_by`         VARCHAR(100) DEFAULT NULL,
  `completed_at`                DATETIME DEFAULT NULL,
  `created_at`                  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`                  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `task_steps_task_id_step_number_idx` (`task_id`, `step_number`),
  CONSTRAINT `task_steps_task_id_fkey` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `wallet_transactions` (
  `id`             INT NOT NULL AUTO_INCREMENT,
  `user_id`        INT NOT NULL,
  `type`           VARCHAR(30) NOT NULL,
  `amount`         DECIMAL(10,2) NOT NULL,
  `description`    VARCHAR(255) DEFAULT NULL,
  `reference_id`   INT DEFAULT NULL,
  `reference_type` VARCHAR(50) DEFAULT NULL,
  `balance_before` DECIMAL(10,2) DEFAULT NULL,
  `balance_after`  DECIMAL(10,2) DEFAULT NULL,
  `created_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `wallet_transactions_user_id_idx` (`user_id`),
  CONSTRAINT `wallet_transactions_user_id_fkey` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `user_wallet` (
  `id`         INT NOT NULL AUTO_INCREMENT,
  `user_id`    INT NOT NULL,
  `balance`    DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_wallet_user_id_key` (`user_id`),
  CONSTRAINT `user_wallet_user_id_fkey` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `seller_wallet` (
  `id`         INT NOT NULL AUTO_INCREMENT,
  `seller_id`  INT NOT NULL,
  `balance`    DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `seller_wallet_seller_id_key` (`seller_id`),
  CONSTRAINT `seller_wallet_seller_id_fkey` FOREIGN KEY (`seller_id`) REFERENCES `sellers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `seller_wallet_transactions` (
  `id`          INT NOT NULL AUTO_INCREMENT,
  `wallet_id`   INT NOT NULL,
  `type`        VARCHAR(30) NOT NULL,
  `amount`      DECIMAL(10,2) NOT NULL,
  `description` VARCHAR(255) DEFAULT NULL,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `seller_wallet_transactions_wallet_id_fkey` FOREIGN KEY (`wallet_id`) REFERENCES `seller_wallet` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `social_platforms` (
  `id`         INT NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(100) NOT NULL,
  `slug`       VARCHAR(50) NOT NULL,
  `icon`       VARCHAR(255) DEFAULT NULL,
  `is_active`  TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `social_platforms_slug_key` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `social_campaigns` (
  `id`             INT NOT NULL AUTO_INCREMENT,
  `seller_id`      INT NOT NULL,
  `platform_id`    INT NOT NULL,
  `title`          VARCHAR(255) NOT NULL,
  `description`    TEXT DEFAULT NULL,
  `url`            TEXT DEFAULT NULL,
  `reward_amount`  DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `required_time`  INT DEFAULT NULL,
  `status`         VARCHAR(20) NOT NULL DEFAULT 'pending',
  `admin_approved` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `social_campaigns_seller_id_fkey` FOREIGN KEY (`seller_id`) REFERENCES `sellers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `social_campaigns_platform_id_fkey` FOREIGN KEY (`platform_id`) REFERENCES `social_platforms` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `social_task_completions` (
  `id`           INT NOT NULL AUTO_INCREMENT,
  `user_id`      INT NOT NULL,
  `campaign_id`  INT NOT NULL,
  `completed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `reward`       DECIMAL(10,2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `social_task_completions_user_id_campaign_id_key` (`user_id`, `campaign_id`),
  CONSTRAINT `social_task_completions_user_id_fkey` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `social_task_completions_campaign_id_fkey` FOREIGN KEY (`campaign_id`) REFERENCES `social_campaigns` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `social_watch_sessions` (
  `id`          INT NOT NULL AUTO_INCREMENT,
  `user_id`     INT NOT NULL,
  `campaign_id` INT NOT NULL,
  `started_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ended_at`    DATETIME DEFAULT NULL,
  `duration`    INT DEFAULT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `social_watch_sessions_user_id_fkey` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `social_watch_sessions_campaign_id_fkey` FOREIGN KEY (`campaign_id`) REFERENCES `social_campaigns` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `blog_posts` (
  `id`             INT NOT NULL AUTO_INCREMENT,
  `title`          VARCHAR(255) NOT NULL,
  `slug`           VARCHAR(255) NOT NULL,
  `content`        LONGTEXT NOT NULL,
  `excerpt`        TEXT DEFAULT NULL,
  `featured_image` VARCHAR(255) DEFAULT NULL,
  `status`         VARCHAR(20) NOT NULL DEFAULT 'draft',
  `author_id`      INT DEFAULT NULL,
  `published_at`   DATETIME DEFAULT NULL,
  `created_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `blog_posts_slug_key` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `announcements` (
  `id`              INT NOT NULL AUTO_INCREMENT,
  `title`           VARCHAR(255) NOT NULL,
  `content`         TEXT NOT NULL,
  `target_audience` VARCHAR(20) NOT NULL DEFAULT 'all',
  `is_active`       TINYINT(1) NOT NULL DEFAULT 1,
  `start_date`      DATETIME DEFAULT NULL,
  `end_date`        DATETIME DEFAULT NULL,
  `created_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `announcement_views` (
  `id`              INT NOT NULL AUTO_INCREMENT,
  `announcement_id` INT NOT NULL,
  `user_id`         INT NOT NULL,
  `viewed_at`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `announcement_views_announcement_id_user_id_key` (`announcement_id`, `user_id`),
  CONSTRAINT `announcement_views_announcement_id_fkey` FOREIGN KEY (`announcement_id`) REFERENCES `announcements` (`id`) ON DELETE CASCADE,
  CONSTRAINT `announcement_views_user_id_fkey` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `chat_messages` (
  `id`         INT NOT NULL AUTO_INCREMENT,
  `user_id`    INT NOT NULL,
  `sender`     VARCHAR(10) NOT NULL,
  `message`    TEXT NOT NULL,
  `is_read`    TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `chat_messages_user_id_idx` (`user_id`),
  CONSTRAINT `chat_messages_user_id_fkey` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `user_points` (
  `id`          INT NOT NULL AUTO_INCREMENT,
  `user_id`     INT NOT NULL,
  `points`      INT NOT NULL DEFAULT 0,
  `type`        VARCHAR(50) NOT NULL,
  `description` VARCHAR(255) DEFAULT NULL,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_points_user_id_idx` (`user_id`),
  CONSTRAINT `user_points_user_id_fkey` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `user_badges` (
  `id`         INT NOT NULL AUTO_INCREMENT,
  `user_id`    INT NOT NULL,
  `badge_id`   INT NOT NULL,
  `awarded_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_badges_user_id_idx` (`user_id`),
  CONSTRAINT `user_badges_user_id_fkey` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `competitions` (
  `id`          INT NOT NULL AUTO_INCREMENT,
  `title`       VARCHAR(255) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `start_date`  DATETIME NOT NULL,
  `end_date`    DATETIME NOT NULL,
  `prize_pool`  DECIMAL(10,2) DEFAULT NULL,
  `status`      VARCHAR(20) NOT NULL DEFAULT 'active',
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `kyc_documents` (
  `id`            INT NOT NULL AUTO_INCREMENT,
  `user_id`       INT NOT NULL,
  `document_type` VARCHAR(50) NOT NULL,
  `document_path` VARCHAR(255) NOT NULL,
  `status`        VARCHAR(20) NOT NULL DEFAULT 'pending',
  `reviewed_by`   INT DEFAULT NULL,
  `reviewed_at`   DATETIME DEFAULT NULL,
  `notes`         TEXT DEFAULT NULL,
  `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `kyc_documents_user_id_idx` (`user_id`),
  CONSTRAINT `kyc_documents_user_id_fkey` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `referrals` (
  `id`            INT NOT NULL AUTO_INCREMENT,
  `referrer_id`   INT NOT NULL,
  `referred_id`   INT NOT NULL,
  `reward_paid`   TINYINT(1) NOT NULL DEFAULT 0,
  `reward_amount` DECIMAL(10,2) DEFAULT NULL,
  `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `referrals_referrer_id_referred_id_key` (`referrer_id`, `referred_id`),
  CONSTRAINT `referrals_referrer_id_fkey` FOREIGN KEY (`referrer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `referrals_referred_id_fkey` FOREIGN KEY (`referred_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
