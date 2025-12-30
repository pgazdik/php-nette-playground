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

  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
