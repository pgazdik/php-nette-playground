-- create sms table

CREATE TABLE `sms` (
	`id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
	`text` varchar(255) NOT NULL,
	`toNumber` varchar(255) NOT NULL,
	`status` varchar(255) NOT NULL,
	`created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB CHARSET=utf8;


