CREATE TABLE `event` (
  `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `patient_name` varchar(255) NOT NULL,
  `phone_number` varchar(50) NOT NULL,
  `doctor_name` varchar(255) NOT NULL,
  `doctor_address` text NOT NULL,
  `appointment_date` datetime NOT NULL,

  `attachment_content` LONGBLOB NULL,
  `attachment_name` varchar(255) NULL,
  `attachment_type` varchar(100) NULL,

  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `notification_msg` (
  `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `event_id` int(11) NOT NULL,
  `msg_index` int(11) NOT NULL,
  `media_type` varchar(20) NOT NULL,
  `notification_type` varchar(20) NOT NULL,
  `status` varchar(20) NOT NULL,
  `text` text NOT NULL,
  `send_at` datetime NOT NULL,
  `approved_at` datetime NULL,

  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  -- INDEX `idx_event_id` (`event_id`),
  FOREIGN KEY (`event_id`) REFERENCES `event` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `notification_attempt` (
  `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `notification_msg_id` int(11) NOT NULL,

  `attempt_no` int(11) NOT NULL,
  `send_at` datetime NOT NULL,
  `status` varchar(20) NOT NULL,

  `sending_error` varchar(255) NULL,

  `gw_id` int(11) NULL,
  `gw_send_status` varchar(255) NULL,
  `gw_check_status` varchar(255) NULL,
  `gw_error_code` int(11) NULL,
  `gw_send_date` datetime NULL,
  `gw_delivery_date` datetime NULL,

  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  -- INDEX `idx_notification_msg_id` (`notification_msg_id`),
  FOREIGN KEY (`notification_msg_id`) REFERENCES `notification_msg` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;