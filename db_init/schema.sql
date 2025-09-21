-- SMS

-- create message table

CREATE TABLE IF NOT EXISTS `message` (
	`id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,

	-- from input form
	`text` varchar(255) NOT NULL,
	`toNumber` varchar(255) NOT NULL,
	`img_name` VARCHAR(255) NULL,
	`img_type` VARCHAR(255) NULL,
	`img_content` BLOB NULL,

	-- lifecycle status based on which buttons were clicked (new, sent, checked)
	`status` varchar(255) NOT NULL,

	-- read from the gateway
	`gw_id` int(11), 
	`gw_send_status` varchar(255),

	-- read from the gateway
	`gw_check_status` varchar(255),
	`gw_error_code` int,
	`gw_send_date` timestamp,
	`gw_delivery_date` timestamp,

	-- managed by the DB
	`created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
	`updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB CHARSET=utf8;


-- LEANMAPPER DEMO

CREATE TABLE book (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    available BOOLEAN DEFAULT TRUE
);

INSERT INTO book (name, available) VALUES ('The Lord of the Rings', TRUE);


-- NETTE TUTORIAL

-- create posts table

CREATE TABLE `posts` (
	`id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
	`title` varchar(255) NOT NULL,
	`content` text NOT NULL,
	`created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB CHARSET=utf8;


-- insert posts

INSERT INTO `posts` (`id`, `title`, `content`, `created_at`) VALUES
(1,	'Article One',	'Lorem ipusm dolor one',	CURRENT_TIMESTAMP),
(2,	'Article Two',	'Lorem ipsum dolor two',	CURRENT_TIMESTAMP),
(3,	'Article Three',	'Lorem ipsum dolor three',	CURRENT_TIMESTAMP);

-- create comments

CREATE TABLE `comments` (
	`id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,
	`post_id` int NOT NULL,
	`name` varchar(250) NOT NULL,
	`email` varchar(250) NOT NULL,
	`content` text NOT NULL,
	`created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
	FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`)
) ENGINE=InnoDB CHARSET=utf8;

