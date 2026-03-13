ALTER TABLE `notification_attempt`
DROP COLUMN `scheduled_at`,
ADD COLUMN `check_at` datetime NULL AFTER `sent_at`,
ADD COLUMN `gw_check_status_history` text NULL AFTER `gw_check_status`;
