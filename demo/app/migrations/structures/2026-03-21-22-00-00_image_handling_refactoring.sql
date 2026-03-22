ALTER TABLE `event`
DROP COLUMN `attachment_content`,
DROP COLUMN `attachment_name`,
DROP COLUMN `attachment_type`;

ALTER TABLE `notification_msg`
ADD COLUMN `file_path` VARCHAR(1000) NULL AFTER `text`;
